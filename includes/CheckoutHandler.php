<?php
if (!defined('ABSPATH')) exit;

class YDZ_CheckoutHandler {

    public static function redirect_to_checkout($product_id, $plan) {
        if (!$product_id) return;

        do_action('ydz_before_redirect_checkout', $product_id, $plan);

        if ( function_exists('WC') ) {
            WC()->cart->empty_cart();
            WC()->session->set( 'ydz_active_plan', sanitize_text_field($plan) );
        }

        $checkout_url = wc_get_checkout_url();

        // 1. Parameter Dasar Commerce Flow
        $query_args = [
            'add-to-cart' => $product_id,
            'plan'        => $plan
        ];

        // 2. [PERBAIKAN AFILIASI] Tangkap semua parameter tambahan (seperti 'ref', 'aff', 'utm_source')
        // dan bawa mereka ikut pindah ke halaman Checkout!
        if ( ! empty( $_GET ) ) {
            foreach ( $_GET as $key => $value ) {
                // Abaikan parameter 'plan' agar tidak dobel
                if ( strtolower($key) !== 'plan' ) {
                    $query_args[$key] = sanitize_text_field($value);
                }
            }
        }

        $url = add_query_arg($query_args, $checkout_url);

        YDZ_Helpers::redirect($url);
    }
}