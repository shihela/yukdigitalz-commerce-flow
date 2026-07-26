<?php
defined( 'ABSPATH' ) || exit;

class YukComFlow_PlanMapper {
	public static function get_product_id( $product_slug, $plan ) {
		$product_slug = sanitize_title( (string) $product_slug );
		$plan = sanitize_text_field( (string) $plan );

		if ( '' === $product_slug ) {
			return null;
		}

		$parent_product = get_page_by_path( $product_slug, OBJECT, 'product' );
		if ( ! $parent_product instanceof WP_Post ) {
			return null;
		}

		$wc_product = wc_get_product( $parent_product->ID );
		if ( ! $wc_product instanceof WC_Product ) {
			return null;
		}

		if ( $wc_product->is_type( 'simple' ) ) {
			return $wc_product->get_id();
		}

		if ( $wc_product->is_type( 'variable' ) ) {
			$plan_lower = strtolower( trim( $plan ) );
			$variations = $wc_product->get_available_variations();

			foreach ( $variations as $variation ) {
				if ( empty( $variation['variation_id'] ) ) {
					continue;
				}

				$attributes = isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array();
				foreach ( $attributes as $attr_value ) {
					if ( strtolower( trim( (string) $attr_value ) ) === $plan_lower ) {
						return absint( $variation['variation_id'] );
					}
				}
			}
		}

		return null;
	}
}