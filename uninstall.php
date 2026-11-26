<?php
/**
 * Plugin uninstall script.
 *
 * Removes abandoned cart records in bounded batches and clears scheduled events.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin settings options.
$yukcomflow_option_keys = array(
	'yukcomflow_base_slug',
	'yukcomflow_cart_behavior',
	'yukcomflow_fallback_url',
	'yukcomflow_shadow_cart_enabled',
	'yukcomflow_retention_days',
	'yukcomflow_recovery_ttl_days',
	'yukcomflow_debug_logging',
	'yukcomflow_lean_checkout_enabled',
	'yukcomflow_lean_hide_address',
	'yukcomflow_lean_hide_company',
	'yukcomflow_lean_hide_phone',
	'yukcomflow_lean_hide_notes',
	'yukcomflow_plan_switcher_enabled',
);

foreach ( $yukcomflow_option_keys as $yukcomflow_option_key ) {
	delete_option( $yukcomflow_option_key );
}

delete_transient( 'yukcomflow_shadow_cart_metrics' );

// Clear any scheduled retention cleanup cron events.
$yukcomflow_timestamp = wp_next_scheduled( 'yukcomflow_cleanup_abandoned_carts' );
while ( $yukcomflow_timestamp ) {
	wp_unschedule_event( $yukcomflow_timestamp, 'yukcomflow_cleanup_abandoned_carts' );
	$yukcomflow_timestamp = wp_next_scheduled( 'yukcomflow_cleanup_abandoned_carts' );
}

// Delete shadow cart posts in batches to avoid memory/timeout issues on large stores.
$yukcomflow_batch_size     = 100;
$yukcomflow_max_iterations = 200;
$yukcomflow_iteration      = 0;

do {
	$yukcomflow_post_ids = get_posts(
		array(
			'post_type'      => 'yukcomflow_shadow_cart',
			'post_status'    => 'any',
			'posts_per_page' => $yukcomflow_batch_size,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	if ( empty( $yukcomflow_post_ids ) ) {
		break;
	}

	foreach ( $yukcomflow_post_ids as $yukcomflow_post_id ) {
		wp_delete_post( absint( $yukcomflow_post_id ), true );
	}

	++$yukcomflow_iteration;
} while ( count( $yukcomflow_post_ids ) === $yukcomflow_batch_size && $yukcomflow_iteration < $yukcomflow_max_iterations );
