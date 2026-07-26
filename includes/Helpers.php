<?php
defined( 'ABSPATH' ) || exit;

class YukComFlow_Helpers {
	public static function get_uri_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	}

	public static function get_query( $key, $default = null ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Generic query parameter helper.
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

	public static function get_query_string( $key, $default = '' ) {
		$value = self::get_query( $key, $default );
		if ( is_array( $value ) ) {
			return $default;
		}
		return (string) $value;
	}

	public static function redirect( $url ) {
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}
}