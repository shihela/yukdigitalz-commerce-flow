<?php
/**
 * Privacy policy content, personal data exporter, and eraser.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Privacy {

	/**
	 * Initialize privacy hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register suggested privacy policy text with WordPress.
	 *
	 * @return void
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$retention_days = class_exists( 'YukComFlow_Settings' )
			? YukComFlow_Settings::get_retention_days()
			: absint( apply_filters( 'yukcomflow_shadow_cart_retention_days', 30 ) );

		$content = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__(
				'When you enter your email address on the checkout page while items are in your cart, Yukdigitalz Commerce Flow may temporarily store your email address, selected plan, and cart contents to help you recover an incomplete checkout.',
				'yukdigitalz-commerce-flow'
			),
			sprintf(
				/* translators: %d is the number of days abandoned cart records are retained. */
				esc_html__(
					'Abandoned cart records are automatically marked as recovered once an order is placed and are permanently deleted after %d days.',
					'yukdigitalz-commerce-flow'
				),
				$retention_days
			)
		);

		wp_add_privacy_policy_content(
			__( 'Yukdigitalz Commerce Flow', 'yukdigitalz-commerce-flow' ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['yukdigitalz-commerce-flow'] = array(
			'exporter_friendly_name' => __( 'Yukdigitalz Commerce Flow Abandoned Carts', 'yukdigitalz-commerce-flow' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['yukdigitalz-commerce-flow'] = array(
			'eraser_friendly_name' => __( 'Yukdigitalz Commerce Flow Abandoned Carts', 'yukdigitalz-commerce-flow' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Export abandoned cart personal data for a given email address.
	 *
	 * @param string $email_address Email address to export.
	 * @param int    $page          Pagination page number.
	 * @return array
	 */
	public static function export_personal_data( $email_address, $page = 1 ) {
		$email_address = sanitize_email( $email_address );
		$page          = max( 1, absint( $page ) );
		$per_page      = 50;
		$export_items  = array();

		if ( ! is_email( $email_address ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$post_ids = self::find_records_by_email( $email_address, $per_page, $page );

		foreach ( $post_ids as $post_id ) {
			$post     = get_post( $post_id );
			$products = get_post_meta( $post_id, '_yukcomflow_abandoned_products', true );
			$plan     = get_post_meta( $post_id, '_yukcomflow_abandoned_plan', true );
			$status   = get_post_meta( $post_id, '_yukcomflow_abandoned_status', true );

			$export_items[] = array(
				'group_id'    => 'yukcomflow-abandoned-carts',
				'group_label' => __( 'Abandoned Cart Records', 'yukdigitalz-commerce-flow' ),
				'item_id'     => 'yukcomflow-shadow-cart-' . absint( $post_id ),
				'data'        => array(
					array(
						'name'  => __( 'Email Address', 'yukdigitalz-commerce-flow' ),
						'value' => $email_address,
					),
					array(
						'name'  => __( 'Products', 'yukdigitalz-commerce-flow' ),
						'value' => (string) $products,
					),
					array(
						'name'  => __( 'Selected Plan', 'yukdigitalz-commerce-flow' ),
						'value' => (string) $plan,
					),
					array(
						'name'  => __( 'Status', 'yukdigitalz-commerce-flow' ),
						'value' => (string) $status,
					),
					array(
						'name'  => __( 'Captured Date', 'yukdigitalz-commerce-flow' ),
						'value' => $post ? (string) $post->post_date_gmt : '',
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $post_ids ) < $per_page,
		);
	}

	/**
	 * Erase abandoned cart personal data for a given email address.
	 *
	 * @param string $email_address Email address to erase.
	 * @param int    $page          Pagination page number.
	 * @return array
	 */
	public static function erase_personal_data( $email_address, $page = 1 ) {
		$email_address = sanitize_email( $email_address );
		$per_page      = 50;
		$items_removed = false;

		if ( ! is_email( $email_address ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// Always query page 1 while deleting so remaining records are not skipped.
		$post_ids = self::find_records_by_email( $email_address, $per_page, 1 );

		foreach ( $post_ids as $post_id ) {
			if ( wp_delete_post( absint( $post_id ), true ) ) {
				$items_removed = true;
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $post_ids ) < $per_page,
		);
	}

	/**
	 * Find abandoned cart record IDs by email address.
	 *
	 * @param string $email    Sanitized email address.
	 * @param int    $per_page Number of records per page.
	 * @param int    $page     Page number.
	 * @return int[]
	 */
	private static function find_records_by_email( $email, $per_page, $page ) {
		return get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'any',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded query for GDPR personal data lookup.
				'meta_query'     => array(
					array(
						'key'   => '_yukcomflow_abandoned_email',
						'value' => $email,
					),
				),
			)
		);
	}
}
