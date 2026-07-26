<?php
/**
 * Abandoned cart tracker and custom post type.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_ShadowCart {
	public static function init() {
		add_action( 'init', array( self::class, 'register_shadow_cart_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_tracker_assets' ) );
		add_action( 'wp_ajax_yukcomflow_capture_email', array( self::class, 'ajax_capture_email' ) );
		add_action( 'wp_ajax_nopriv_yukcomflow_capture_email', array( self::class, 'ajax_capture_email' ) );
		add_action( 'woocommerce_thankyou', array( self::class, 'mark_cart_as_recovered' ) );
		add_filter( 'manage_yukcomflow_shadow_cart_posts_columns', array( self::class, 'set_custom_columns' ) );
		add_action( 'manage_yukcomflow_shadow_cart_posts_custom_column', array( self::class, 'custom_column_data' ), 10, 2 );
	}

	public static function register_shadow_cart_cpt() {
		register_post_type(
			'yukcomflow_shadow_cart',
			array(
				'labels' => array(
					'name'          => __( 'Abandoned Carts', 'yukdigitalz-commerce-flow' ),
					'singular_name' => __( 'Abandoned Cart', 'yukdigitalz-commerce-flow' ),
					'menu_name'     => __( 'Abandoned Carts', 'yukdigitalz-commerce-flow' ),
					'all_items'     => __( 'All Abandoned Records', 'yukdigitalz-commerce-flow' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'woocommerce',
				'supports'     => array( 'title', 'custom-fields' ),
				'menu_icon'    => 'dashicons-cart',
				'capabilities' => array(
					'create_posts' => false,
				),
				'map_meta_cap' => true,
			)
		);
	}

	public static function enqueue_tracker_assets() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_script(
			'yukcomflow-shadow-tracker',
			YUKCOMFLOW_PLUGIN_URL . 'assets/js/yukcomflow-shadow-tracker.js',
			array( 'jquery' ),
			'1.1.0',
			true
		);

		wp_localize_script(
			'yukcomflow-shadow-tracker',
			'yukComFlowShadowCart',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'yukcomflow-shadow-nonce' ),
			)
		);
	}

	public static function ajax_capture_email() {
		check_ajax_referer( 'yukcomflow-shadow-nonce', 'security' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error();
		}

		$existing = get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'title'          => $email,
				'date_query'     => array(
					array( 'after' => '12 hours ago' ),
				),
			)
		);

		if ( ! empty( $existing ) ) {
			wp_send_json_success( array( 'message' => __( 'Already captured', 'yukdigitalz-commerce-flow' ) ) );
		}

		$cart_items   = array();
		$cart_details = array();
		$affiliate_id = 0;
		$plan         = '';

		if ( function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->cart ) ) {
				foreach ( $wc->cart->get_cart() as $cart_item ) {
					$product      = $cart_item['data'];
					$cart_items[] = $product->get_name() . ' (x' . $cart_item['quantity'] . ')';

					$cart_details[] = array(
						'product_id'   => absint( $cart_item['product_id'] ),
						'variation_id' => absint( $cart_item['variation_id'] ),
						'quantity'     => absint( $cart_item['quantity'] ),
						'variation'    => isset( $cart_item['variation'] ) ? (array) $cart_item['variation'] : array(),
					);

					/**
					 * Allow paid add-ons to inject affiliate info into cart items.
					 * Free plugin will not persist affiliate IDs by default; premium
					 * extensions should listen to `yukcomflow_shadow_cart_affiliate_save`.
					 */
					if ( isset( $cart_item['vs_affiliate_id'] ) && 0 === $affiliate_id ) {
						$affiliate_id = absint( $cart_item['vs_affiliate_id'] );
					}
				}
			}

			if ( is_object( $wc ) && isset( $wc->session ) ) {
				$plan = $wc->session->get( 'yukcomflow_active_plan' );
			}
		}

		$products_string = implode( ', ', $cart_items );
		$post_id = wp_insert_post(
			array(
				'post_title'   => $email,
				'post_type'    => 'yukcomflow_shadow_cart',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		if ( $post_id ) {
			// store basic captured data
			update_post_meta( $post_id, '_yukcomflow_abandoned_products', $products_string );
			update_post_meta( $post_id, '_yukcomflow_abandoned_status', 'pending' );
			update_post_meta( $post_id, '_yukcomflow_abandoned_email', $email );
			update_post_meta( $post_id, '_yukcomflow_cart_details', $cart_details );
			update_post_meta( $post_id, '_yukcomflow_abandoned_plan', sanitize_text_field( $plan ) );

			/**
			 * Filter affiliate id detected in the cart. Premium add-ons can
			 * modify or provide affiliate identification by hooking this filter.
			 *
			 * @param int $affiliate_id
			 * @param array $cart_items
			 */
			$affiliate_id = apply_filters( 'yukcomflow_detect_affiliate_id', $affiliate_id, $cart_items );

			/**
			 * Action for premium add-ons to persist affiliate data as needed.
			 * Free plugin deliberately does NOT persist affiliate-specific meta.
			 *
			 * @param int $post_id
			 * @param int $affiliate_id
			 */
			do_action( 'yukcomflow_shadow_cart_affiliate_save', $post_id, $affiliate_id );

			/**
			 * Action after a shadow cart record is captured.
			 *
			 * @param int $post_id
			 * @param array $data
			 */
			do_action( 'yukcomflow_shadow_cart_captured', $post_id, array(
				'email'        => $email,
				'products'     => $products_string,
				'affiliate_id' => $affiliate_id,
			) );

			wp_send_json_success( array( 'message' => __( 'Captured', 'yukdigitalz-commerce-flow' ) ) );
		}

		wp_send_json_error();
	}

	public static function mark_cart_as_recovered( $order_id ) {
		$order_id = absint( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		$abandoned_carts = get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'title'          => $email,
			)
		);

		foreach ( $abandoned_carts as $cart ) {
			update_post_meta( $cart->ID, '_yukcomflow_abandoned_status', 'recovered' );
			update_post_meta( $cart->ID, '_yukcomflow_recovered_order_id', $order_id );
			wp_update_post(
				array(
					'ID'          => $cart->ID,
					'post_status' => 'draft',
				)
			);
		}
	}

	public static function set_custom_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = __( 'Prospect Email', 'yukdigitalz-commerce-flow' );
		$new_columns['abandoned_products'] = __( 'Abandoned Products', 'yukdigitalz-commerce-flow' );
		$new_columns['affiliate_id'] = __( 'Affiliate ID', 'yukdigitalz-commerce-flow' );
		$new_columns['status'] = __( 'Status', 'yukdigitalz-commerce-flow' );
		$new_columns['actions'] = __( 'Actions', 'yukdigitalz-commerce-flow' );
		$new_columns['date'] = $columns['date'];

		return $new_columns;
	}

	public static function custom_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'abandoned_products':
				$products = get_post_meta( $post_id, '_yukcomflow_abandoned_products', true );
				echo ! empty( $products ) ? esc_html( $products ) : '<em>' . esc_html__( 'Empty Cart', 'yukdigitalz-commerce-flow' ) . '</em>';
				break;
			case 'affiliate_id':
				$aff_id = get_post_meta( $post_id, '_' . YUKCOMFLOW_PLUGIN_SLUG . '_affiliate_id', true );
				if ( $aff_id ) {
					$user_info = get_userdata( absint( $aff_id ) );
					// translators: %d is the affiliate user ID.
					$name = $user_info ? $user_info->display_name : sprintf( __( 'ID: %d', 'yukdigitalz-commerce-flow' ), absint( $aff_id ) );
					echo '<span style="background:#e0e7ff; color:#3730a3; padding:3px 8px; border-radius:4px; font-size:12px;">' . esc_html( $name ) . '</span>';
				} else {
					echo '<span style="color:#94a3b8;">' . esc_html__( '- Organic -', 'yukdigitalz-commerce-flow' ) . '</span>';
				}
				break;
			case 'status':
				$status = get_post_meta( $post_id, '_yukcomflow_abandoned_status', true );
				if ( 'recovered' === $status ) {
					echo '<span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">' . esc_html__( 'Recovered', 'yukdigitalz-commerce-flow' ) . '</span>';
				} else {
					echo '<span style="background:#fef08a; color:#854d0e; padding:3px 8px; border-radius:4px; font-size:12px;">' . esc_html__( 'Pending', 'yukdigitalz-commerce-flow' ) . '</span>';
				}
				break;
			case 'actions':
				$email = get_post_meta( $post_id, '_yukcomflow_abandoned_email', true );
				if ( empty( $email ) ) {
					$post  = get_post( $post_id );
					$email = $post ? $post->post_title : '';
				}
				$email = sanitize_email( $email );

				if ( is_email( $email ) ) {
					$recovery_url = add_query_arg(
						array(
							'yukcomflow_recover' => $post_id,
							'key'                => wp_hash( $post_id, 'yukcomflow-recovery' ),
						),
						home_url()
					);

					$subject = rawurlencode( __( 'Complete your order', 'yukdigitalz-commerce-flow' ) );
					$body    = rawurlencode(
						sprintf(
							// translators: %s is the recovery checkout URL
							__( "Hi,\n\nWe noticed you left items in your cart. You can complete your purchase here:\n%s\n\nBest regards,", 'yukdigitalz-commerce-flow' ),
							$recovery_url
						)
					);

					$mailto_url = 'mailto:' . esc_attr( $email ) . '?subject=' . $subject . '&body=' . $body;

					echo '<div class="yukcomflow-action-buttons" style="display:flex; gap:10px; align-items:center;">';
					printf(
						'<a href="%1$s" class="button button-primary" style="background:#4f46e5; border-color:#4338ca; box-shadow:none;">%2$s</a>',
						esc_url( $mailto_url ),
						esc_html__( 'Send Email', 'yukdigitalz-commerce-flow' )
					);
					printf(
						'<button type="button" class="button yukcomflow-copy-btn" data-url="%1$s" style="background:#f3f4f6; border-color:#d1d5db; color:#374151;" onclick="navigator.clipboard.writeText(this.getAttribute(\'data-url\')).then(() => { const originalText = this.innerText; this.innerText = \'%2$s\'; this.style.background=\'#dcfce7\'; this.style.color=\'#15803d\'; this.style.borderColor=\'#bbf7d0\'; setTimeout(() => { this.innerText = originalText; this.style.background=\'#f3f4f6\'; this.style.color=\'#374151\'; this.style.borderColor=\'#d1d5db\'; }, 2000); });">%3$s</button>',
						esc_url( $recovery_url ),
						esc_js( __( 'Copied!', 'yukdigitalz-commerce-flow' ) ),
						esc_html__( 'Copy Link', 'yukdigitalz-commerce-flow' )
					);
					echo '</div>';
				}
				break;
		}
	}
}
