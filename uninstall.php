<?php
/**
 * Plugin uninstall script.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$shadow_cart_posts = get_posts(
    array(
        'post_type'      => 'yukcomflow_shadow_cart',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'suppress_filters' => true,
    )
);

if ( ! empty( $shadow_cart_posts ) ) {
    foreach ( $shadow_cart_posts as $post_id ) {
        wp_delete_post( absint( $post_id ), true );
    }
}
