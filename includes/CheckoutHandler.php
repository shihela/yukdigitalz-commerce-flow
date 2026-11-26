<?php
/**
 * Checkout cart population, coupon application, and redirect handler.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_CheckoutHandler {

	/**
	 * Populate the WooCommerce cart and redirect the visitor to checkout.
	 *
	 * @param int    $product_id   Product ID (or variation ID for backwards compatibility).
	 * @param string $plan         Selected plan identifier.
	 * @param int    $variation_id Optional variation ID.
	 * @param array  $variation    Optional variation attributes.
	 * @param array  $args         Optional extra parameters: cycle, coupon, quantity.
	 * @return void
	 */
	public static function redirect_to_checkout( $product_id, $plan, $variation_id = 0, $variation = array(), $args = array() ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$plan         = sanitize_text_field( (string) $plan );
		$variation    = is_array( $variation ) ? $variation : array();
		$args         = is_array( $args ) ? $args : array();

		$cycle    = isset( $args['cycle'] ) ? sanitize_text_field( (string) $args['cycle'] ) : '';
		$coupon   = isset( $args['coupon'] ) ? sanitize_text_field( (string) $args['coupon'] ) : '';
		$max_qty  = max( 1, absint( apply_filters( 'yukcomflow_max_direct_quantity', 100 ) ) );
		$quantity = isset( $args['quantity'] ) ? min( $max_qty, max( 1, absint( $args['quantity'] ) ) ) : 1;

		if ( ! $product_id ) {
			return;
		}

		// Support legacy callers passing a variation ID as $product_id.
		if ( 0 === $variation_id ) {
			$product_obj = wc_get_product( $product_id );
			if ( $product_obj instanceof WC_Product_Variation ) {
				$variation_id = $product_obj->get_id();
				$product_id   = $product_obj->get_parent_id();
				if ( empty( $variation ) ) {
					$variation = $product_obj->get_variation_attributes();
				}
			}
		}

		/**
		 * Fires before populating the cart and redirecting to checkout.
		 *
		 * @param int    $target_id Target product or variation ID.
		 * @param string $plan      Selected plan identifier.
		 * @param array  $args      Additional flow parameters (cycle, coupon, quantity).
		 */
		do_action( 'yukcomflow_before_redirect_checkout', $variation_id ? $variation_id : $product_id, $plan, $args );

		$added = false;

		if ( function_exists( 'WC' ) ) {
			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) {
				wc_load_cart();
			}

			$wc = WC();

			// 1. Ensure WooCommerce session is active (especially for new guest visitors).
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( ! $wc->session->has_session() && method_exists( $wc->session, 'set_customer_session_cookie' ) ) {
					$wc->session->set_customer_session_cookie( true );
				}
			}

			if ( is_object( $wc ) && isset( $wc->cart ) ) {
				// 2. Respect configured cart behavior (replace vs append).
				if ( 'replace' === YukComFlow_Settings::get_cart_behavior() ) {
					$wc->cart->empty_cart();
				}

				$cart_item_key = $wc->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
				$added         = false !== $cart_item_key;

				// 3. Auto-apply coupon if provided and valid.
				if ( $added && '' !== $coupon ) {
					self::maybe_apply_coupon( $wc->cart, $coupon );
				}
			}

			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( $added && '' !== $plan ) {
					$wc->session->set( 'yukcomflow_active_plan', $plan );
				} else {
					$wc->session->__unset( 'yukcomflow_active_plan' );
				}

				if ( $added && '' !== $cycle ) {
					$wc->session->set( 'yukcomflow_active_cycle', $cycle );
				} else {
					$wc->session->__unset( 'yukcomflow_active_cycle' );
				}

				$utm = YukComFlow_Helpers::extract_utm_params();
				if ( $added && ! empty( $utm ) ) {
					$wc->session->set( 'yukcomflow_active_utm', $utm );
				}

				// 4. Persist session data before exiting via redirect.
				if ( method_exists( $wc->session, 'save_data' ) ) {
					$wc->session->save_data();
				}
			}
		}

		// Redirect safely to configured fallback or product/shop page if adding to cart failed.
		if ( ! $added ) {
			YukComFlow_Logger::warning(
				'Failed to add product to cart during direct checkout.',
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'plan'         => $plan,
					'cycle'        => $cycle,
					'quantity'     => $quantity,
				)
			);
			if ( function_exists( 'wc_add_notice' ) && function_exists( 'wc_notice_count' ) && 0 === wc_notice_count( 'error' ) ) {
				wc_add_notice(
					__( 'This product or plan is currently unavailable for direct checkout.', 'yukdigitalz-commerce-flow' ),
					'error'
				);
			}

			$fallback_url = YukComFlow_Settings::get_fallback_url();
			if ( '' === $fallback_url ) {
				$fallback_url = (string) get_permalink( $product_id );
			}
			if ( '' === $fallback_url ) {
				$fallback_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			}

			YukComFlow_Helpers::redirect( $fallback_url );
		}

		// Build checkout URL without parameters that could interfere with checkout.
		$checkout_url = wc_get_checkout_url();
		$query_args   = array();

		if ( '' !== $plan ) {
			$query_args['plan'] = $plan;
		}
		if ( '' !== $cycle ) {
			$query_args['cycle'] = $cycle;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public landing route query passthrough (e.g. UTM parameters).
		if ( ! empty( $_GET ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below per key/value.
			$raw_get = wp_unslash( $_GET );
			foreach ( $raw_get as $key => $value ) {
				$key_string = (string) $key;
				if ( in_array( strtolower( $key_string ), array( 'plan', 'cycle', 'billing', 'coupon', 'qty', 'add-to-cart', 'yukcomflow_recover', 'key' ), true ) ) {
					continue;
				}
				$sanitized_key = sanitize_key( $key_string );
				if ( '' === $sanitized_key ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$query_args[ $sanitized_key ] = map_deep( $value, 'sanitize_text_field' );
				} else {
					$query_args[ $sanitized_key ] = sanitize_text_field( (string) $value );
				}
			}
		}

		$url = ! empty( $query_args ) ? add_query_arg( $query_args, $checkout_url ) : $checkout_url;
		YukComFlow_Helpers::redirect( $url );
	}

	/**
	 * Safely validate and apply a coupon code to the WooCommerce cart.
	 *
	 * @param WC_Cart $cart        WooCommerce cart instance.
	 * @param string  $coupon_code Raw coupon code from URL.
	 * @return void
	 */
	private static function maybe_apply_coupon( $cart, $coupon_code ) {
		if ( ! function_exists( 'wc_coupons_enabled' ) || ! wc_coupons_enabled() ) {
			return;
		}

		$formatted_code = function_exists( 'wc_format_coupon_code' )
			? wc_format_coupon_code( $coupon_code )
			: sanitize_text_field( $coupon_code );

		if ( '' === $formatted_code || $cart->has_discount( $formatted_code ) ) {
			return;
		}

		if ( class_exists( 'WC_Coupon' ) ) {
			$coupon_obj = new WC_Coupon( $formatted_code );
			if ( ! $coupon_obj->get_id() ) {
				YukComFlow_Logger::warning(
					'Ignored non-existent coupon code from direct checkout URL.',
					array( 'coupon' => $formatted_code )
				);
				return;
			}
		}

		$applied = $cart->apply_coupon( $formatted_code );
		YukComFlow_Logger::debug(
			'Direct checkout coupon application attempt.',
			array(
				'coupon'  => $formatted_code,
				'applied' => (bool) $applied,
			)
		);
	}
}
