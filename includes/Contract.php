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
	 * @param WC_Order      $order The WooCommerce order object.
	 * @param WC_Order_Item $item  The WooCommerce order item.
	 * @param string        $plan  The plan name.
	 * @return array
	 */
	public static function build_license_data( $order, $item, $plan ) {
		return array(
			'order_id'   => (int) $order->get_id(),
			'product_id' => (int) $item->get_product_id(),
			'plan'       => (string) $plan,
			'email'      => (string) $order->get_billing_email(),
		);
	}
}