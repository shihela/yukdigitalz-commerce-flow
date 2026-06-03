<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class YDZ_ShadowCart {

    public static function init() {
        // 1. Daftarkan Tempat Penyimpanan (Custom Post Type)
        add_action( 'init', [ self::class, 'register_shadow_cart_cpt' ] );

        // 2. Tanamkan Skrip Mata-mata di Halaman Checkout
        add_action( 'wp_footer', [ self::class, 'inject_shadow_tracker' ] );

        // 3. Buka Jalur AJAX untuk Menangkap Data
        add_action( 'wp_ajax_ydz_capture_email', [ self::class, 'ajax_capture_email' ] );
        add_action( 'wp_ajax_nopriv_ydz_capture_email', [ self::class, 'ajax_capture_email' ] );

        // 4. Hapus/Selesaikan Data Jika Pembayaran Berhasil
        add_action( 'woocommerce_thankyou', [ self::class, 'mark_cart_as_recovered' ] );

        // 5. Modifikasi Kolom Tabel Admin
        add_filter( 'manage_ydz_shadow_cart_posts_columns', [ self::class, 'set_custom_columns' ] );
        add_action( 'manage_ydz_shadow_cart_posts_custom_column', [ self::class, 'custom_column_data' ], 10, 2 );
    }

    /**
     * Membuat Menu "Keranjang Tertunda" di Dasbor Admin
     */
    public static function register_shadow_cart_cpt() {
        register_post_type( 'ydz_shadow_cart', [
            'labels' => [
                'name'               => 'Keranjang Tertunda',
                'singular_name'      => 'Keranjang Tertunda',
                'menu_name'          => 'Keranjang Tertunda',
                'all_items'          => 'Semua Data Tertunda',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'woocommerce', // Taruh di bawah menu WooCommerce
            'supports'            => ['title', 'custom-fields'],
            'menu_icon'           => 'dashicons-cart',
            'capabilities'        => [
                'create_posts' => false, // Mencegah admin buat manual
            ],
            'map_meta_cap'        => true,
        ]);
    }

    /**
     * Skrip JS yang berjalan diam-diam di halaman Checkout
     */
    public static function inject_shadow_tracker() {
        if ( ! is_checkout() || is_order_received_page() ) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            let emailCaptured = false;

            // Dengarkan saat pengunjung mengetik di kolom email
            $('#billing_email').on('blur', function() {
                if (emailCaptured) return; // Jangan spam server jika sudah terekam

                let email = $(this).val();
                let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (email && emailRegex.test(email)) {
                    $.ajax({
                        url: '<?php echo admin_url("admin-ajax.php"); ?>',
                        type: 'POST',
                        data: {
                            action: 'ydz_capture_email',
                            email: email,
                            security: '<?php echo wp_create_nonce("ydz-shadow-nonce"); ?>'
                        },
                        success: function(response) {
                            if(response.success) {
                                emailCaptured = true; // Berhasil ditangkap!
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Menangkap Data dari JS dan Menyimpannya ke Database
     */
    public static function ajax_capture_email() {
        check_ajax_referer( 'ydz-shadow-nonce', 'security' );

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if ( ! is_email( $email ) ) wp_send_json_error();

        // Cek apakah email ini sudah ada di Shadow Cart hari ini agar tidak dobel
        $existing = get_posts([
            'post_type'   => 'ydz_shadow_cart',
            'title'       => $email,
            'post_status' => 'publish',
            'date_query'  => [
                [ 'after' => '12 hours ago' ]
            ],
            'numberposts' => 1
        ]);

        if ( ! empty($existing) ) wp_send_json_success('Already captured');

        // Ambil isi keranjang saat ini dan ID afiliasi dari dalam keranjang
        $cart_items = [];
        $affiliate_id = 0; // Siapkan wadah untuk ID Afiliator

        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = $cart_item['data'];
                $cart_items[] = $product->get_name() . ' (x' . $cart_item['quantity'] . ')';
                
                // [PERBAIKAN SUPER AMAN] Tarik ID Afiliasi langsung dari data keranjang
                // karena VentureShare sudah menitipkannya di sana (bind_affiliate_to_cart_item)
                if ( isset( $cart_item['vs_affiliate_id'] ) && $affiliate_id === 0 ) {
                    $affiliate_id = absint( $cart_item['vs_affiliate_id'] );
                }
            }
        }
        $products_string = implode(', ', $cart_items);

        // Simpan sebagai Post Baru
        $post_id = wp_insert_post([
            'post_title'   => $email,
            'post_type'    => 'ydz_shadow_cart',
            'post_status'  => 'publish',
            'post_content' => ''
        ]);

        if ( $post_id ) {
            update_post_meta( $post_id, '_ydz_abandoned_products', $products_string );
            update_post_meta( $post_id, '_ydz_abandoned_status', 'pending' );
            
            // Simpan ID afiliator jika berhasil ditemukan di keranjang
            if ( $affiliate_id > 0 ) {
                update_post_meta( $post_id, '_vs_affiliate_id_saved', $affiliate_id );
            }
            
            wp_send_json_success('Captured');
        }

        wp_send_json_error();
    }

    /**
     * Mengubah Status Jika Pembayaran Ternyata Berhasil
     */
    public static function mark_cart_as_recovered( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email = $order->get_billing_email();

        // Cari shadow cart dengan email ini
        $abandoned_carts = get_posts([
            'post_type'   => 'ydz_shadow_cart',
            'title'       => $email,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        foreach ( $abandoned_carts as $cart ) {
            update_post_meta( $cart->ID, '_ydz_abandoned_status', 'recovered' );
            update_post_meta( $cart->ID, '_ydz_recovered_order_id', $order_id );
            
            // Opsional: Ubah status ke Draft agar hilang dari antrean utama
            wp_update_post([
                'ID' => $cart->ID,
                'post_status' => 'draft'
            ]);
        }
    }

    /**
     * Menambahkan judul kolom kustom di tabel admin
     */
    public static function set_custom_columns( $columns ) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb']; // Checkbox bawaan
        $new_columns['title'] = 'Email Prospek';
        $new_columns['abandoned_products'] = 'Produk Tertunda';
        $new_columns['affiliate_id'] = 'ID Afiliator';
        $new_columns['status'] = 'Status';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Mengisi data ke dalam kolom kustom
     */
    public static function custom_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'abandoned_products':
                $products = get_post_meta( $post_id, '_ydz_abandoned_products', true );
                echo !empty($products) ? esc_html( $products ) : '<em>Keranjang Kosong</em>';
                break;
            case 'affiliate_id':
                $aff_id = get_post_meta( $post_id, '_vs_affiliate_id_saved', true );
                if ( $aff_id ) {
                    $user_info = get_userdata($aff_id);
                    $name = $user_info ? $user_info->display_name : 'ID: ' . $aff_id;
                    echo '<span style="background:#e0e7ff; color:#3730a3; padding:3px 8px; border-radius:4px; font-size:12px;">' . esc_html($name) . '</span>';
                } else {
                    echo '<span style="color:#94a3b8;">- Organik -</span>';
                }
                break;
            case 'status':
                $status = get_post_meta( $post_id, '_ydz_abandoned_status', true );
                if ( $status === 'recovered' ) {
                    echo '<span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">Terselamatkan 🎉</span>';
                } else {
                    echo '<span style="background:#fef08a; color:#854d0e; padding:3px 8px; border-radius:4px; font-size:12px;">Tertunda</span>';
                }
                break;
        }
    }
}