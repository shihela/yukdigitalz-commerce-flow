<?php
/**
 * Plugin Name: Yukdigitalz Commerce Flow
 * Description: Direct checkout flow with plan-based routing.
 * Version: 1.0.3
 */

if (!defined('ABSPATH')) exit;

// Load all includes
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/PlanMapper.php';
require_once __DIR__ . '/includes/CheckoutHandler.php';
require_once __DIR__ . '/includes/Router.php';
require_once __DIR__ . '/includes/OrderMeta.php';
require_once __DIR__ . '/includes/ShadowChart.php';

// Init all components
add_action('plugins_loaded', function() {
    YDZ_Router::init();
    YDZ_OrderMeta::init();
    YDZ_ShadowCart::init();
});