<?php
/**
 * Plugin Name: Yukdigitalz Commerce Flow
 * Plugin URI: https://yukdigitalz.com/yukdigitalz-commerce-flow
 * Description: Direct checkout flow with plan-based routing for WooCommerce.
 * Version: 1.1.0
 * Author: Shihela
 * Author URI: https://yukdigitalz.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yukdigitalz-commerce-flow
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.8
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

define( 'YUKCOMFLOW_VERSION', '1.1.0' );
define( 'YUKCOMFLOW_PLUGIN_FILE', __FILE__ );
define( 'YUKCOMFLOW_PLUGIN_DIR', plugin_dir_path( YUKCOMFLOW_PLUGIN_FILE ) );
define( 'YUKCOMFLOW_PLUGIN_URL', plugin_dir_url( YUKCOMFLOW_PLUGIN_FILE ) );
define( 'YUKCOMFLOW_TEXT_DOMAIN', 'yukdigitalz-commerce-flow' );
define( 'YUKCOMFLOW_PLUGIN_SLUG', 'yukdigitalz_commerce_flow' );

require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Helpers.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Settings.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Logger.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/PlanMapper.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/CheckoutHandler.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Router.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/OrderMeta.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/ShadowCart.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Contract.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/OrderCompleted.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/Privacy.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/ProductLinks.php';
require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/CheckoutUX.php';

/**
 * Declare WooCommerce feature compatibility (HPOS and Cart/Checkout Blocks).
 *
 * @return void
 */
function yukcomflow_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', YUKCOMFLOW_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', YUKCOMFLOW_PLUGIN_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'yukcomflow_declare_woocommerce_compatibility' );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function yukcomflow_activate_plugin() {
	YukComFlow_ShadowCart::schedule_cleanup();
}
register_activation_hook( YUKCOMFLOW_PLUGIN_FILE, 'yukcomflow_activate_plugin' );

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function yukcomflow_deactivate_plugin() {
	YukComFlow_ShadowCart::unschedule_cleanup();
}
register_deactivation_hook( YUKCOMFLOW_PLUGIN_FILE, 'yukcomflow_deactivate_plugin' );

/**
 * Bootstrap plugin components once plugins are loaded.
 *
 * @return void
 */
function yukcomflow_bootstrap_plugin() {
	YukComFlow_Privacy::init();

	if ( ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', 'yukcomflow_render_woocommerce_notice' );
		return;
	}

	YukComFlow_Settings::init();
	YukComFlow_Router::init();
	YukComFlow_OrderMeta::init();
	YukComFlow_ShadowCart::init();
	YukComFlow_OrderCompleted::init();
	YukComFlow_ProductLinks::init();
	YukComFlow_CheckoutUX::init();
}
add_action( 'plugins_loaded', 'yukcomflow_bootstrap_plugin' );

/**
 * Display admin notice when WooCommerce is inactive.
 *
 * @return void
 */
function yukcomflow_render_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Yukdigitalz Commerce Flow requires WooCommerce to be installed and activated.', 'yukdigitalz-commerce-flow' )
	);
}
