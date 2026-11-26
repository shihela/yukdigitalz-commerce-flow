<?php
/**
 * WooCommerce Settings Page for Yukdigitalz Commerce Flow.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Settings_Page' ) ) {
	return;
}

class YukComFlow_SettingsPage extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'yukcomflow';
		$this->label = __( 'Commerce Flow', 'yukdigitalz-commerce-flow' );

		parent::__construct();
	}

	/**
	 * Define settings fields for the Commerce Flow tab.
	 *
	 * @return array
	 */
	public function get_settings() {
		$base_slug   = YukComFlow_Settings::get_base_slug();
		$example_url = home_url( '/' . $base_slug . '/your-product/?plan=agency&cycle=yearly&coupon=SAVE20' );

		$settings = array(
			array(
				'title' => __( 'Direct Checkout Routing', 'yukdigitalz-commerce-flow' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s is an example direct checkout URL. */
					__( 'Configure how plan-based direct checkout links behave. Current URL format: %s', 'yukdigitalz-commerce-flow' ),
					'<code>' . esc_html( $example_url ) . '</code>'
				),
				'id'    => 'yukcomflow_routing_section',
			),
			array(
				'title'    => __( 'Base URL Slug', 'yukdigitalz-commerce-flow' ),
				'desc'     => __( 'The leading URL segment used for direct checkout links (default: buy). Letters, numbers, and hyphens only.', 'yukdigitalz-commerce-flow' ),
				'id'       => 'yukcomflow_base_slug',
				'type'     => 'text',
				'default'  => 'buy',
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Cart Behavior', 'yukdigitalz-commerce-flow' ),
				'desc'     => __( 'Choose whether direct checkout links replace the existing cart or append to it.', 'yukdigitalz-commerce-flow' ),
				'id'       => 'yukcomflow_cart_behavior',
				'type'     => 'select',
				'default'  => 'replace',
				'options'  => array(
					'replace' => __( 'Replace existing cart (Recommended for SaaS / single-plan checkout)', 'yukdigitalz-commerce-flow' ),
					'append'  => __( 'Append to existing cart', 'yukdigitalz-commerce-flow' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'       => __( 'Fallback Redirect URL', 'yukdigitalz-commerce-flow' ),
				'desc'        => __( 'Optional URL to redirect visitors if a product or plan cannot be added to the cart. Leave blank to fall back to the product or shop page.', 'yukdigitalz-commerce-flow' ),
				'id'          => 'yukcomflow_fallback_url',
				'type'        => 'url',
				'default'     => '',
				'placeholder' => home_url( '/pricing/' ),
				'desc_tip'    => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'yukcomflow_routing_section',
			),
			array(
				'title' => __( 'SaaS Checkout Experience (Optional)', 'yukdigitalz-commerce-flow' ),
				'type'  => 'title',
				'desc'  => __( 'Optional conversion enhancements for digital and virtual products. Compatible with Classic and Block Checkout.', 'yukdigitalz-commerce-flow' ),
				'id'    => 'yukcomflow_checkout_ux_section',
			),
			array(
				'title'   => __( 'Lean Digital Checkout Mode', 'yukdigitalz-commerce-flow' ),
				'desc'    => __( 'Simplify checkout fields automatically when the cart contains only virtual (non-shippable) products. Billing country is preserved for tax and payment gateway compatibility.', 'yukdigitalz-commerce-flow' ),
				'id'      => 'yukcomflow_lean_checkout_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'         => __( 'Fields to Hide (Virtual Carts)', 'yukdigitalz-commerce-flow' ),
				'desc'          => __( 'Hide street address, city, state, and postcode fields', 'yukdigitalz-commerce-flow' ),
				'id'            => 'yukcomflow_lean_hide_address',
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'start',
			),
			array(
				'desc'          => __( 'Hide company name field', 'yukdigitalz-commerce-flow' ),
				'id'            => 'yukcomflow_lean_hide_company',
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			),
			array(
				'desc'          => __( 'Hide phone number field (uncheck if required by your payment gateway)', 'yukdigitalz-commerce-flow' ),
				'id'            => 'yukcomflow_lean_hide_phone',
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			),
			array(
				'desc'          => __( 'Hide order notes field', 'yukdigitalz-commerce-flow' ),
				'id'            => 'yukcomflow_lean_hide_notes',
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'end',
			),
			array(
				'title'   => __( 'In-Checkout Plan Switcher', 'yukdigitalz-commerce-flow' ),
				'desc'    => __( 'Display a compact plan/variation switcher on the checkout page for variable products so buyers can upgrade or change billing cycles without leaving checkout. Also available via [yukcomflow_plan_switcher].', 'yukdigitalz-commerce-flow' ),
				'id'      => 'yukcomflow_plan_switcher_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'yukcomflow_checkout_ux_section',
			),
			array(
				'title' => __( 'Abandoned Cart (Shadow Cart)', 'yukdigitalz-commerce-flow' ),
				'type'  => 'title',
				'desc'  => __( 'Control checkout email capture, recovery link expiration, and GDPR data retention.', 'yukdigitalz-commerce-flow' ),
				'id'    => 'yukcomflow_shadow_cart_section',
			),
			array(
				'title'   => __( 'Enable Shadow Cart Tracking', 'yukdigitalz-commerce-flow' ),
				'desc'    => __( 'Capture prospect email addresses on checkout when the cart is not empty.', 'yukdigitalz-commerce-flow' ),
				'id'      => 'yukcomflow_shadow_cart_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'             => __( 'Recovery Link Expiry (Days)', 'yukdigitalz-commerce-flow' ),
				'desc'              => __( 'Number of days a cart recovery link remains valid (minimum 1 day).', 'yukdigitalz-commerce-flow' ),
				'id'                => 'yukcomflow_recovery_ttl_days',
				'type'              => 'number',
				'default'           => 7,
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 90,
					'step' => 1,
				),
				'desc_tip'          => true,
			),
			array(
				'title'             => __( 'Auto-Delete Retention (Days)', 'yukdigitalz-commerce-flow' ),
				'desc'              => __( 'Automatically delete abandoned cart records older than this many days. Set to 0 to disable automatic deletion.', 'yukdigitalz-commerce-flow' ),
				'id'                => 'yukcomflow_retention_days',
				'type'              => 'number',
				'default'           => 30,
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 365,
					'step' => 1,
				),
				'desc_tip'          => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'yukcomflow_shadow_cart_section',
			),
			array(
				'title' => __( 'Diagnostics & Logging', 'yukdigitalz-commerce-flow' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s is the link to WooCommerce Status Logs. */
					__( 'View operational warnings and debug traces under %s (source: yukdigitalz-commerce-flow).', 'yukdigitalz-commerce-flow' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'WooCommerce &rarr; Status &rarr; Logs', 'yukdigitalz-commerce-flow' ) . '</a>'
				),
				'id'    => 'yukcomflow_diagnostics_section',
			),
			array(
				'title'   => __( 'Enable Debug Logging', 'yukdigitalz-commerce-flow' ),
				'desc'    => __( 'Record detailed routing, coupon, and cart recovery events in WooCommerce Logs. Warnings and errors are always logged.', 'yukdigitalz-commerce-flow' ),
				'id'      => 'yukcomflow_debug_logging',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'yukcomflow_diagnostics_section',
			),
		);

		/**
		 * Filter the Commerce Flow settings array.
		 *
		 * @param array $settings WooCommerce settings fields.
		 */
		return apply_filters( 'yukcomflow_settings', $settings );
	}

	/**
	 * Sanitize and save settings.
	 *
	 * @return void
	 */
	public function save() {
		$settings = $this->get_settings();
		WC_Admin_Settings::save_fields( $settings );

		// Ensure base slug is always a clean URL slug.
		$saved_slug = sanitize_title( (string) get_option( 'yukcomflow_base_slug', 'buy' ) );
		update_option( 'yukcomflow_base_slug', '' !== $saved_slug ? $saved_slug : 'buy' );
	}
}
