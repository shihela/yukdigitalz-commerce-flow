<?php
defined( 'ABSPATH' ) || exit;

class YukComFlow_OrderMeta {
	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_plan' ) );
	}

	public static function attach_plan( $order ) {
		$plan = '';
		if ( function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				$plan = $wc->session->get( 'yukcomflow_active_plan' );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified by WooCommerce checkout processing.
		if ( empty( $plan ) && isset( $_GET['plan'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the subsequent line.
			$raw_plan = wp_unslash( $_GET['plan'] );
			$plan     = is_array( $raw_plan ) ? '' : sanitize_text_field( $raw_plan );
		}

		$plan = sanitize_text_field( (string) $plan );
		if ( '' !== $plan && is_object( $order ) && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_yukcomflow_plan', $plan );

			if ( function_exists( 'WC' ) ) {
				$wc = WC();
				if ( is_object( $wc ) && isset( $wc->session ) ) {
					$wc->session->__unset( 'yukcomflow_active_plan' );
				}
			}
		}
	}
}