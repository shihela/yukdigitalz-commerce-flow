<?php
/**
 * Plan and billing-cycle mapper for WooCommerce simple and variable products.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_PlanMapper {

	/**
	 * Resolve a product slug, plan, and optional billing cycle into add-to-cart parameters.
	 *
	 * Supports multi-dimensional matching (e.g. plan=agency & cycle=yearly) across any
	 * variation attribute names without requiring fixed attribute slugs.
	 *
	 * @param string $product_slug Product post_name slug.
	 * @param string $plan         Requested plan identifier.
	 * @param string $cycle        Optional billing cycle identifier (e.g. monthly, yearly, lifetime).
	 * @return array|null Array with product_id, variation_id, and variation attributes, or null if not found.
	 */
	public static function resolve_product( $product_slug, $plan = '', $cycle = '' ) {
		$product_slug = sanitize_title( (string) $product_slug );
		$plan         = sanitize_text_field( (string) $plan );
		$cycle        = sanitize_text_field( (string) $cycle );

		if ( '' === $product_slug ) {
			return null;
		}

		$parent_product = get_page_by_path( $product_slug, OBJECT, 'product' );
		if ( ! $parent_product instanceof WP_Post || 'publish' !== $parent_product->post_status ) {
			return null;
		}

		$wc_product = wc_get_product( $parent_product->ID );
		if ( ! $wc_product instanceof WC_Product ) {
			return null;
		}

		if ( $wc_product->is_type( 'simple' ) ) {
			return array(
				'product_id'   => $wc_product->get_id(),
				'variation_id' => 0,
				'variation'    => array(),
			);
		}

		if ( $wc_product->is_type( 'variable' ) ) {
			$requested_terms = array_values(
				array_filter(
					array(
						strtolower( trim( $plan ) ),
						strtolower( trim( $cycle ) ),
					),
					static function ( $term ) {
						return '' !== $term;
					}
				)
			);

			if ( empty( $requested_terms ) ) {
				return null;
			}

			$children = $wc_product->get_children();
			foreach ( $children as $variation_id ) {
				$variation_obj = wc_get_product( $variation_id );
				if ( ! $variation_obj instanceof WC_Product_Variation || 'publish' !== $variation_obj->get_status() ) {
					continue;
				}

				$attributes = $variation_obj->get_variation_attributes();
				if ( self::variation_matches_terms( $attributes, $requested_terms ) ) {
					return array(
						'product_id'   => $wc_product->get_id(),
						'variation_id' => $variation_obj->get_id(),
						'variation'    => $attributes,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Check whether a variation's attribute values satisfy all requested terms.
	 *
	 * Each requested term must match a distinct non-empty variation attribute value
	 * (by exact lowercase match or sanitized slug match).
	 *
	 * @param array    $attributes      Variation attributes map.
	 * @param string[] $requested_terms Normalized lowercase terms to match.
	 * @return bool
	 */
	private static function variation_matches_terms( $attributes, $requested_terms ) {
		$used_keys = array();

		foreach ( $requested_terms as $term ) {
			$term_slug = sanitize_title( $term );
			$matched   = false;

			foreach ( $attributes as $attr_key => $attr_value ) {
				if ( isset( $used_keys[ $attr_key ] ) ) {
					continue;
				}

				$val_lower = strtolower( trim( (string) $attr_value ) );
				if ( '' === $val_lower ) {
					continue;
				}

				if ( $val_lower === $term || sanitize_title( $val_lower ) === $term_slug ) {
					$used_keys[ $attr_key ] = true;
					$matched                = true;
					break;
				}
			}

			if ( ! $matched ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve a product slug and plan into a product or variation ID.
	 *
	 * Maintained for backwards compatibility.
	 *
	 * @param string $product_slug Product post_name slug.
	 * @param string $plan         Requested plan identifier.
	 * @param string $cycle        Optional billing cycle identifier.
	 * @return int|null
	 */
	public static function get_product_id( $product_slug, $plan, $cycle = '' ) {
		$resolved = self::resolve_product( $product_slug, $plan, $cycle );
		if ( ! $resolved ) {
			return null;
		}

		return ! empty( $resolved['variation_id'] )
			? absint( $resolved['variation_id'] )
			: absint( $resolved['product_id'] );
	}
}
