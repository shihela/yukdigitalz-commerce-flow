<?php
if (!defined('ABSPATH')) exit;

class YDZ_OrderMeta {

    public static function init() {
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'attach_plan']);
    }

    public static function attach_plan($order) {
        // [PERBAIKAN 2] Ambil plan dari Sesi WC, bukan dari $_GET
        $plan = WC()->session->get( 'ydz_active_plan' );

        // Jika di sesi tidak ada, sebagai cadangan darurat (fallback), baru cari di $_GET
        if (!$plan) {
            $plan = $_GET['plan'] ?? null;
        }

        if ($plan) {
            $order->update_meta_data('_ydz_plan', sanitize_text_field($plan));
            
            // Bersihkan sesi agar tidak menempel di transaksi berikutnya
            WC()->session->__unset( 'ydz_active_plan' );
        }
    }
}