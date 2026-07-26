<?php
/**
 * Order completed hooks for license integration.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_OrderCompleted {

	/**
	 * Hook actions.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ) );
	}

	/**
	 * Handle completed orders and trigger license generation actions.
	 *
	 * @param int $order_id The completed order ID.
	 */
	public static function handle_order_completed( $order_id ) {
		$order_id = absint( $order_id );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$plan = $order->get_meta( '_yukcomflow_plan' );
		if ( empty( $plan ) ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			// Build license payload using the contract helper.
			$license_data            = YukComFlow_Contract::build_license_data( $order, $item, $plan );
			$license_data['item_id'] = $item_id; // Add item_id to associate the license with the order item.

			/**
			 * Trigger license generation. Premium plugins hook into this action.
			 *
			 * @param array $license_data Payload data for license generation.
			 */
			do_action( 'yukcomflow_generate_license_from_flow', $license_data );
		}
	}
}