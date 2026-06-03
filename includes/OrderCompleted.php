<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $plan = $order->get_meta('_ydz_plan');
    if (!$plan) return;

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        
        // Memanggil Class Contract Anda yang sangat rapi itu
        $license_data = YDZ_Contract::build_license_data($order, $item, $plan);
        $license_data['item_id'] = $item_id; // Tambahkan item_id agar lisensi menempel

        do_action('ydz_generate_license_from_flow', $license_data);
    }
});