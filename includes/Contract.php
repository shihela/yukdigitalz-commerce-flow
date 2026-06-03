<?php
if (!defined('ABSPATH')) exit;

class YDZ_Contract {

    public static function build_license_data($order, $item, $plan) {

        return [
            'order_id'   => (int) $order->get_id(),
            'product_id' => (int) $item->get_product_id(),
            'plan'       => (string) $plan,
            'email'      => (string) $order->get_billing_email(),
        ];
    }

}