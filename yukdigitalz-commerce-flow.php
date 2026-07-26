<?php
/**
 * Plugin Name: Yukdigitalz Commerce Flow
 * Plugin URI: https://yukdigitalz.com/yukdigitalz-commerce-flow
 * Description: Direct checkout flow with plan-based routing for WooCommerce.
 * Version: 1.0.0
 * Author: Shihela
 * Author URI: https://yukdigitalz.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yukdigitalz-commerce-flow
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Requires Plugin: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'YUKCOMFLOW_PLUGIN_FILE', __FILE__ );
define( 'YUKCOMFLOW_PLUGIN_DIR', plugin_dir_path( YUKCOMFLOW_PLUGIN_FILE ) );
define( 'YUKCOMFLOW_PLUGIN_URL', plugin_dir_url( YUKCOMFLOW_PLUGIN_FILE ) );
define( 'YUKCOMFLOW_TEXT_DOMAIN', 'yukdigitalz-commerce-flow' );
define( 'YUKCOMFLOW_PLUGIN_SLUG', 'yukdigitalz_commerce_flow' );

require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Helpers.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/PlanMapper.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/CheckoutHandler.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Router.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/OrderMeta.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/ShadowCart.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Contract.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/OrderCompleted.php';

/**
 * Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
 */
add_action( 'before_woocommerce_init', 'yukcomflow_declare_hpos_compatibility' );
function yukcomflow_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', YUKCOMFLOW_PLUGIN_FILE, true );
	}
}

function yukcomflow_bootstrap_plugin() {
	if ( ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', 'yukcomflow_render_woocommerce_notice' );
		return;
	}

	YukComFlow_Router::init();
	YukComFlow_OrderMeta::init();
	YukComFlow_ShadowCart::init();
	YukComFlow_OrderCompleted::init();
}
add_action( 'plugins_loaded', 'yukcomflow_bootstrap_plugin' );

function yukcomflow_render_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Yukdigitalz Commerce Flow requires WooCommerce to be installed and activated.', 'yukdigitalz-commerce-flow' )
	);
}