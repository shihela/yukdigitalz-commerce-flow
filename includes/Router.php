<?php
/**
 * Frontend URL router for direct checkout and cart recovery links.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Router {

	/**
	 * Register routing hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 1 );
	}

	/**
	 * Handle incoming cart recovery links and /{base_slug}/{product-slug}/ requests.
	 *
	 * Supports multi-dimensional query parameters: plan, cycle (or billing), coupon, and qty.
	 *
	 * @return void
	 */
	public static function handle_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public recovery link validated with a stored cryptographic token.
		if ( isset( $_GET['yukcomflow_recover'] ) && isset( $_GET['key'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['yukcomflow_recover'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

			if ( YukComFlow_ShadowCart::verify_recovery_token( $post_id, $key ) ) {
				YukComFlow_Logger::info( 'Valid cart recovery link accessed.', array( 'post_id' => $post_id ) );
				self::restore_cart( $post_id );
			} else {
				YukComFlow_Logger::warning( 'Invalid or expired cart recovery link accessed.', array( 'post_id' => $post_id ) );
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice(
						__( 'This cart recovery link is invalid or has expired.', 'yukdigitalz-commerce-flow' ),
						'notice'
					);
				}
				YukComFlow_Helpers::redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
			}
		}

		$base_slug = YukComFlow_Settings::get_base_slug();
		$prefix    = $base_slug . '/';
		$path      = YukComFlow_Helpers::get_uri_path();

		if ( 0 !== strpos( $path, $prefix ) ) {
			return;
		}

		$segments     = explode( '/', $path );
		$product_slug = isset( $segments[1] ) ? sanitize_title( $segments[1] ) : '';
		$plan         = YukComFlow_Helpers::get_query_string( 'plan' );
		$cycle        = YukComFlow_Helpers::get_query_string( 'cycle' );
		if ( '' === $cycle ) {
			$cycle = YukComFlow_Helpers::get_query_string( 'billing' );
		}
		$coupon = YukComFlow_Helpers::get_query_string( 'coupon' );
		$qty    = max( 1, absint( YukComFlow_Helpers::get_query_string( 'qty', '1' ) ) );

		if ( '' === $product_slug ) {
			return;
		}

		$resolved = YukComFlow_PlanMapper::resolve_product( $product_slug, $plan, $cycle );
		if ( ! $resolved ) {
			YukComFlow_Logger::warning(
				'Direct checkout route could not resolve product or plan.',
				array(
					'slug'  => $product_slug,
					'plan'  => $plan,
					'cycle' => $cycle,
				)
			);

			$fallback_url = YukComFlow_Settings::get_fallback_url();
			if ( '' !== $fallback_url ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice(
						__( 'The requested product or plan could not be found.', 'yukdigitalz-commerce-flow' ),
						'error'
					);
				}
				YukComFlow_Helpers::redirect( $fallback_url );
			}
			return;
		}

		YukComFlow_Logger::debug(
			'Resolved direct checkout route.',
			array(
				'slug'         => $product_slug,
				'product_id'   => $resolved['product_id'],
				'variation_id' => $resolved['variation_id'],
				'plan'         => $plan,
				'cycle'        => $cycle,
				'qty'          => $qty,
			)
		);

		YukComFlow_CheckoutHandler::redirect_to_checkout(
			$resolved['product_id'],
			$plan,
			$resolved['variation_id'],
			$resolved['variation'],
			array(
				'cycle'    => $cycle,
				'coupon'   => $coupon,
				'quantity' => $qty,
			)
		);
	}

	/**
	 * Restore an abandoned cart record into the active WooCommerce session and redirect to checkout.
	 *
	 * @param int $post_id Shadow cart post ID.
	 * @return void
	 */
	public static function restore_cart( $post_id ) {
		$post_id = absint( $post_id );
		if ( 'yukcomflow_shadow_cart' !== get_post_type( $post_id ) ) {
			return;
		}

		$cart_details = get_post_meta( $post_id, '_yukcomflow_cart_details', true );
		if ( empty( $cart_details ) || ! is_array( $cart_details ) ) {
			return;
		}

		$plan = (string) get_post_meta( $post_id, '_yukcomflow_abandoned_plan', true );
		$utm  = get_post_meta( $post_id, '_yukcomflow_utm', true );

		if ( function_exists( 'WC' ) ) {
			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) {
				wc_load_cart();
			}

			$wc = WC();

			// Ensure WooCommerce session is active for new guest visits via recovery link.
			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( ! $wc->session->has_session() && method_exists( $wc->session, 'set_customer_session_cookie' ) ) {
					$wc->session->set_customer_session_cookie( true );
				}
			}

			if ( is_object( $wc ) && isset( $wc->cart ) ) {
				$wc->cart->empty_cart();
				foreach ( $cart_details as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
					$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
					$quantity     = isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1;
					$variation    = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();

					if ( $product_id > 0 ) {
						$wc->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
					}
				}
			}

			if ( is_object( $wc ) && isset( $wc->session ) ) {
				if ( '' !== $plan ) {
					$wc->session->set( 'yukcomflow_active_plan', sanitize_text_field( $plan ) );
				}

				if ( is_array( $utm ) && ! empty( $utm ) ) {
					$wc->session->set( 'yukcomflow_active_utm', array_map( 'sanitize_text_field', $utm ) );
				}

				// Persist session data before redirect and exit.
				if ( method_exists( $wc->session, 'save_data' ) ) {
					$wc->session->save_data();
				}
			}
		}

		YukComFlow_Helpers::redirect( wc_get_checkout_url() );
	}
}
