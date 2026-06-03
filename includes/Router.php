<?php
if (!defined('ABSPATH')) exit;

class YDZ_Router {

    public static function init() {
        add_action('init', [__CLASS__, 'handle_request']);
    }

    public static function handle_request() {

        $path = YDZ_Helpers::get_uri_path();

        // Cek apakah URL dimulai dengan /buy/
        if (strpos($path, 'buy/') !== 0) return;

        $segments = explode('/', $path);

        $product_slug = $segments[1] ?? null;
        $plan         = YDZ_Helpers::get_query('plan');

        if (!$product_slug || !$plan) return;

        $product_id = YDZ_PlanMapper::get_product_id($product_slug, $plan);

        if (!$product_id) return;

        YDZ_CheckoutHandler::redirect_to_checkout($product_id, $plan);
    }

}