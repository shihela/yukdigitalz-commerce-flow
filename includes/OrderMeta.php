<?php
/**
 * Persist and display the selected commerce flow plan, cycle, and UTM attribution on WooCommerce orders.
 *
 * Supports both Classic Checkout and WooCommerce Block Checkout (Store API).
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_OrderMeta {

	/**
	 * Register order meta hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_plan' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'attach_plan' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_order_plan' ) );
	}

	/**
	 * Attach the active plan, billing cycle, and UTM attribution from session (or query fallback) to the order.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public static function attach_plan( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		// Do not overwrite if already attached during this checkout lifecycle.
		if ( method_exists( $order, 'get_meta' ) && '' !== (string) $order->get_meta( '_yukcomflow_plan' ) ) {
			return;
		}

		$plan  = '';
		$cycle = '';
		$utm   = array();

		if ( function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				$plan        = (string) $wc->session->get( 'yukcomflow_active_plan' );
				$cycle       = (string) $wc->session->get( 'yukcomflow_active_cycle' );
				$session_utm = $wc->session->get( 'yukcomflow_active_utm' );
				if ( is_array( $session_utm ) ) {
					$utm = $session_utm;
				}
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checkout request is already validated by WooCommerce.
		if ( '' === $plan && isset( $_GET['plan'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the subsequent line.
			$raw_plan = wp_unslash( $_GET['plan'] );
			$plan     = is_array( $raw_plan ) ? '' : sanitize_text_field( $raw_plan );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checkout request is already validated by WooCommerce.
		if ( '' === $cycle && isset( $_GET['cycle'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the subsequent line.
			$raw_cycle = wp_unslash( $_GET['cycle'] );
			$cycle     = is_array( $raw_cycle ) ? '' : sanitize_text_field( $raw_cycle );
		}

		if ( empty( $utm ) ) {
			$utm = YukComFlow_Helpers::extract_utm_params();
		}

		$plan  = sanitize_text_field( $plan );
		$cycle = sanitize_text_field( $cycle );

		if ( '' !== $plan ) {
			$order->update_meta_data( '_yukcomflow_plan', $plan );
		}

		if ( '' !== $cycle ) {
			$order->update_meta_data( '_yukcomflow_cycle', $cycle );
		}

		if ( ! empty( $utm ) ) {
			$sanitized_utm = array_map( 'sanitize_text_field', $utm );
			$order->update_meta_data( '_yukcomflow_utm', $sanitized_utm );
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $utm_key ) {
				if ( ! empty( $sanitized_utm[ $utm_key ] ) ) {
					$order->update_meta_data( '_yukcomflow_' . $utm_key, $sanitized_utm[ $utm_key ] );
				}
			}
		}

		if ( ( '' !== $plan || '' !== $cycle || ! empty( $utm ) ) && function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				$wc->session->__unset( 'yukcomflow_active_plan' );
				$wc->session->__unset( 'yukcomflow_active_cycle' );
				$wc->session->__unset( 'yukcomflow_active_utm' );
			}
		}
	}

	/**
	 * Display the purchased plan, billing cycle, and UTM attribution on the Admin Order screen (HPOS-compatible).
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public static function render_admin_order_plan( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		$plan  = (string) $order->get_meta( '_yukcomflow_plan' );
		$cycle = (string) $order->get_meta( '_yukcomflow_cycle' );
		$utm   = $order->get_meta( '_yukcomflow_utm' );

		if ( '' !== $plan ) {
			printf(
				'<p><strong>%1$s:</strong> %2$s</p>',
				esc_html__( 'Commerce Flow Plan', 'yukdigitalz-commerce-flow' ),
				esc_html( $plan )
			);
		}

		if ( '' !== $cycle ) {
			printf(
				'<p><strong>%1$s:</strong> %2$s</p>',
				esc_html__( 'Billing Cycle', 'yukdigitalz-commerce-flow' ),
				esc_html( $cycle )
			);
		}

		if ( is_array( $utm ) && ! empty( $utm ) ) {
			$parts = array();
			foreach ( $utm as $key => $val ) {
				if ( '' !== (string) $val ) {
					$parts[] = str_replace( 'utm_', '', (string) $key ) . ': ' . (string) $val;
				}
			}
			if ( ! empty( $parts ) ) {
				printf(
					'<p><strong>%1$s:</strong> %2$s</p>',
					esc_html__( 'Attribution (UTM)', 'yukdigitalz-commerce-flow' ),
					esc_html( implode( ' | ', $parts ) )
				);
			}
		}
	}
}
