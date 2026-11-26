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
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ) );
	}

	/**
	 * Handle completed orders and trigger license generation actions idempotently.
	 *
	 * @param int $order_id The completed order ID.
	 * @return void
	 */
	public static function handle_order_completed( $order_id ) {
		$order_id = absint( $order_id );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Prevent duplicate license dispatch if order status transitions to completed more than once.
		if ( ! empty( $order->get_meta( '_yukcomflow_license_dispatched' ) ) ) {
			return;
		}

		$plan = (string) $order->get_meta( '_yukcomflow_plan' );
		if ( '' === $plan ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$license_data            = YukComFlow_Contract::build_license_data( $order, $item, $plan );
			$license_data['item_id'] = absint( $item_id );

			/**
			 * Trigger license generation. Premium plugins hook into this action.
			 *
			 * @param array $license_data Payload data for license generation.
			 */
			do_action( 'yukcomflow_generate_license_from_flow', $license_data );
		}

		$order->update_meta_data( '_yukcomflow_license_dispatched', time() );
		$order->save_meta_data();

		YukComFlow_Logger::info(
			'Dispatched license generation hooks for completed order.',
			array(
				'order_id' => $order_id,
				'plan'     => $plan,
			)
		);
	}
}
