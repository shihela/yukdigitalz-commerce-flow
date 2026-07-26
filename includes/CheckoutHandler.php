<?php
defined( 'ABSPATH' ) || exit;

class YukComFlow_CheckoutHandler {
	public static function redirect_to_checkout( $product_id, $plan ) {
		$product_id = absint( $product_id );
		$plan = sanitize_text_field( (string) $plan );

		if ( ! $product_id ) {
			return;
		}

		do_action( 'yukcomflow_before_redirect_checkout', $product_id, $plan );

		if ( function_exists( 'WC' ) ) {
			$wc = WC();

			// 1. Pastikan WooCommerce Session sudah aktif (terutama untuk guest user baru)
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( ! $wc->session->has_session() && method_exists( $wc->session, 'set_customer_session_cookie' ) ) {
					$wc->session->set_customer_session_cookie( true );
				}
			}

			if ( is_object( $wc ) && isset( $wc->cart ) ) {
				// 2. Kosongkan keranjang lama
				$wc->cart->empty_cart();

				// 3. Masukkan produk baru secara backend/programatik
				$wc->cart->add_to_cart( $product_id );
			}

			if ( is_object( $wc ) && isset( $wc->session ) ) {
				$wc->session->set( 'yukcomflow_active_plan', $plan );

				// 4. Force save data session sebelum script di-exit oleh fungsi redirect
				if ( method_exists( $wc->session, 'save_data' ) ) {
					$wc->session->save_data();
				}
			}
		}

		// 3. Susun URL checkout tanpa menyertakan 'add-to-cart'
		$checkout_url = wc_get_checkout_url();
		$query_args = array(
			'plan' => $plan, // Tetap bawa parameter plan jika dibutuhkan untuk UI/logika halaman checkout
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce cannot be verified on initial route query passthrough.
		if ( ! empty( $_GET ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw_get = wp_unslash( $_GET );
			foreach ( $raw_get as $key => $value ) {
				$key_string = (string) $key;
				// Lewati parameter bawaan yang bisa merusak alur checkout
				if ( in_array( strtolower( $key_string ), array( 'plan', 'add-to-cart' ), true ) ) {
					continue;
				}
				$sanitized_key = sanitize_key( $key_string );
				if ( is_array( $value ) ) {
					$query_args[ $sanitized_key ] = map_deep( $value, 'sanitize_text_field' );
				} else {
					$query_args[ $sanitized_key ] = sanitize_text_field( (string) $value );
				}
			}
		}

		$url = add_query_arg( $query_args, $checkout_url );
		YukComFlow_Helpers::redirect( $url );
	}
}