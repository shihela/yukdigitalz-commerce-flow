<?php
/**
 * Native WooCommerce settings integration and typed setting getters.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Settings {

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_wc_settings_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YUKCOMFLOW_PLUGIN_FILE ), array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Register the Commerce Flow tab inside WooCommerce -> Settings.
	 *
	 * @param array $settings Existing WooCommerce settings pages.
	 * @return array
	 */
	public static function register_wc_settings_page( $settings ) {
		if ( ! class_exists( 'WC_Settings_Page' ) ) {
			return $settings;
		}

		require_once YUKCOMFLOW_PLUGIN_DIR . 'includes/SettingsPage.php';
		$settings[] = new YukComFlow_SettingsPage();

		return $settings;
	}

	/**
	 * Add a direct Settings link on the WordPress Plugins list table.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function add_plugin_action_links( $links ) {
		$settings_url  = admin_url( 'admin.php?page=wc-settings&tab=yukcomflow' );
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'yukdigitalz-commerce-flow' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get the configured direct checkout base URL slug (default: 'buy').
	 *
	 * @return string
	 */
	public static function get_base_slug() {
		$raw  = get_option( 'yukcomflow_base_slug', 'buy' );
		$slug = sanitize_title( (string) $raw );
		if ( '' === $slug ) {
			$slug = 'buy';
		}

		/**
		 * Filter the direct checkout base URL slug.
		 *
		 * @param string $slug Sanitized base slug.
		 */
		$slug = sanitize_title( (string) apply_filters( 'yukcomflow_base_slug', $slug ) );
		return '' !== $slug ? $slug : 'buy';
	}

	/**
	 * Get the configured cart behavior ('replace' or 'append').
	 *
	 * @return string
	 */
	public static function get_cart_behavior() {
		$behavior = (string) get_option( 'yukcomflow_cart_behavior', 'replace' );
		return in_array( $behavior, array( 'replace', 'append' ), true ) ? $behavior : 'replace';
	}

	/**
	 * Get the optional custom fallback redirect URL.
	 *
	 * @return string
	 */
	public static function get_fallback_url() {
		$url = (string) get_option( 'yukcomflow_fallback_url', '' );
		return '' !== trim( $url ) ? esc_url_raw( trim( $url ) ) : '';
	}

	/**
	 * Check whether Shadow Cart (abandoned cart tracking) is enabled.
	 *
	 * @return bool
	 */
	public static function is_shadow_cart_enabled() {
		return 'yes' === (string) get_option( 'yukcomflow_shadow_cart_enabled', 'yes' );
	}

	/**
	 * Get the abandoned cart retention period in days (0 disables automatic deletion).
	 *
	 * @return int
	 */
	public static function get_retention_days() {
		$days = absint( get_option( 'yukcomflow_retention_days', 30 ) );
		return absint( apply_filters( 'yukcomflow_shadow_cart_retention_days', $days ) );
	}

	/**
	 * Get the recovery token TTL in seconds.
	 *
	 * @return int
	 */
	public static function get_recovery_ttl_seconds() {
		$days    = max( 1, absint( get_option( 'yukcomflow_recovery_ttl_days', 7 ) ) );
		$seconds = $days * DAY_IN_SECONDS;
		return max( HOUR_IN_SECONDS, absint( apply_filters( 'yukcomflow_recovery_token_ttl', $seconds ) ) );
	}

	/**
	 * Check whether verbose debug logging via WC_Logger is enabled.
	 *
	 * @return bool
	 */
	public static function is_debug_logging_enabled() {
		return 'yes' === (string) get_option( 'yukcomflow_debug_logging', 'no' );
	}

	/**
	 * Check whether Lean Digital Checkout Mode is enabled (opt-in).
	 *
	 * @return bool
	 */
	public static function is_lean_checkout_enabled() {
		return 'yes' === (string) get_option( 'yukcomflow_lean_checkout_enabled', 'no' );
	}

	/**
	 * Get granular field hiding options for Lean Digital Checkout Mode.
	 *
	 * @return array{address:bool,company:bool,phone:bool,notes:bool}
	 */
	public static function get_lean_checkout_fields() {
		return array(
			'address' => 'yes' === (string) get_option( 'yukcomflow_lean_hide_address', 'yes' ),
			'company' => 'yes' === (string) get_option( 'yukcomflow_lean_hide_company', 'yes' ),
			'phone'   => 'yes' === (string) get_option( 'yukcomflow_lean_hide_phone', 'yes' ),
			'notes'   => 'yes' === (string) get_option( 'yukcomflow_lean_hide_notes', 'yes' ),
		);
	}

	/**
	 * Check whether the In-Checkout Plan Switcher is enabled (opt-in).
	 *
	 * @return bool
	 */
	public static function is_plan_switcher_enabled() {
		return 'yes' === (string) get_option( 'yukcomflow_plan_switcher_enabled', 'no' );
	}

	/**
	 * Return all option keys managed by this plugin (used during uninstall).
	 *
	 * @return string[]
	 */
	public static function get_option_keys() {
		return array(
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
	}
}
