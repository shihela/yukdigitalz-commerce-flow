<?php
/**
 * License contract data builder.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Contract {

	/**
	 * Build license payload data from order details.
	 *
	 * @param WC_Order              $order The WooCommerce order object.
	 * @param WC_Order_Item_Product $item  The WooCommerce order item.
	 * @param string                $plan  The plan name.
	 * @return array
	 */
	public static function build_license_data( $order, $item, $plan ) {
		return array(
			'order_id'     => (int) $order->get_id(),
			'product_id'   => method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0,
			'variation_id' => method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0,
			'quantity'     => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1,
			'plan'         => (string) $plan,
			'email'        => (string) $order->get_billing_email(),
		);
	}
}
