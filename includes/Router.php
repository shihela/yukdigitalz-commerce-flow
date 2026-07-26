<?php
defined( 'ABSPATH' ) || exit;

class YukComFlow_Router {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_request' ) );
	}

	public static function handle_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce cannot be verified on initial route landing; signature is validated cryptographically instead.
		if ( isset( $_GET['yukcomflow_recover'] ) && isset( $_GET['key'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['yukcomflow_recover'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key     = sanitize_text_field( wp_unslash( $_GET['key'] ) );

			if ( hash_equals( wp_hash( $post_id, 'yukcomflow-recovery' ), $key ) ) {
				self::restore_cart( $post_id );
			}
		}

		$path = YukComFlow_Helpers::get_uri_path();
		if ( 0 !== strpos( $path, 'buy/' ) ) {
			return;
		}

		$segments = explode( '/', $path );
		$product_slug = isset( $segments[1] ) ? sanitize_text_field( wp_unslash( $segments[1] ) ) : '';
		$plan = YukComFlow_Helpers::get_query_string( 'plan' );

		if ( '' === $product_slug || empty( $plan ) ) {
			return;
		}

		$product_id = YukComFlow_PlanMapper::get_product_id( $product_slug, $plan );
		if ( ! $product_id ) {
			return;
		}

		YukComFlow_CheckoutHandler::redirect_to_checkout( $product_id, $plan );
	}

	public static function restore_cart( $post_id ) {
		$post_id      = absint( $post_id );
		$cart_details = get_post_meta( $post_id, '_yukcomflow_cart_details', true );
		if ( empty( $cart_details ) || ! is_array( $cart_details ) ) {
			return;
		}

		$plan = get_post_meta( $post_id, '_yukcomflow_abandoned_plan', true );

		if ( function_exists( 'WC' ) ) {
			$wc = WC();

			// Ensure WooCommerce Session is active (especially for new guest visits via recovery link)
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( ! $wc->session->has_session() && method_exists( $wc->session, 'set_customer_session_cookie' ) ) {
					$wc->session->set_customer_session_cookie( true );
				}
			}

			if ( is_object( $wc ) && isset( $wc->cart ) ) {
				$wc->cart->empty_cart();
				foreach ( $cart_details as $item ) {
					$product_id   = absint( $item['product_id'] );
					$variation_id = absint( $item['variation_id'] );
					$quantity     = absint( $item['quantity'] );
					$variation    = isset( $item['variation'] ) ? (array) $item['variation'] : array();

					$wc->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
				}
			}

			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( ! empty( $plan ) ) {
					$wc->session->set( 'yukcomflow_active_plan', sanitize_text_field( $plan ) );
				}

				// Force save data session before redirect and exit
				if ( method_exists( $wc->session, 'save_data' ) ) {
					$wc->session->save_data();
				}
			}
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
}