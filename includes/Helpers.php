<?php
/**
 * Request and redirect helper utilities.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Helpers {

	/**
	 * Get the current request URI path relative to the WordPress home URL.
	 *
	 * Properly handles subdirectory WordPress installations.
	 *
	 * @return string
	 */
	public static function get_uri_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home_path ) {
			if ( $path === $home_path ) {
				return '';
			}
			$prefix = $home_path . '/';
			if ( 0 === strpos( $path, $prefix ) ) {
				$path = substr( $path, strlen( $prefix ) );
			}
		}

		return trim( $path, '/' );
	}

	/**
	 * Retrieve a sanitized query parameter value.
	 *
	 * @param string $key     Query parameter key.
	 * @param mixed  $default Default value if key is missing.
	 * @return mixed
	 */
	public static function get_query( $key, $default = null ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Generic read-only query parameter helper.
		if ( isset( $_GET[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized dynamically below based on type.
			$value = wp_unslash( $_GET[ $key ] );
			if ( is_array( $value ) ) {
				return map_deep( $value, 'sanitize_text_field' );
			}
			return sanitize_text_field( (string) $value );
		}

		return $default;
	}

	/**
	 * Retrieve a sanitized string query parameter.
	 *
	 * @param string $key     Query parameter key.
	 * @param string $default Default string if key is missing or an array.
	 * @return string
	 */
	public static function get_query_string( $key, $default = '' ) {
		$value = self::get_query( $key, $default );
		if ( is_array( $value ) ) {
			return $default;
		}
		return (string) $value;
	}

	/**
	 * Extract sanitized UTM attribution parameters from the current request query string.
	 *
	 * @return array Associative array of non-empty UTM key/value pairs.
	 */
	public static function extract_utm_params() {
		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		$utm      = array();

		foreach ( $utm_keys as $key ) {
			$val = trim( self::get_query_string( $key ) );
			if ( '' !== $val ) {
				$utm[ $key ] = $val;
			}
		}

		return $utm;
	}

	/**
	 * Perform a safe redirect and terminate execution.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 */
	public static function redirect( $url ) {
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}
}
