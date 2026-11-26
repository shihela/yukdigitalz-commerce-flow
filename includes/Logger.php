<?php
/**
 * Structured WooCommerce logger wrapper for Yukdigitalz Commerce Flow.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_Logger {

	/**
	 * WooCommerce log source identifier.
	 *
	 * @var string
	 */
	const SOURCE = 'yukdigitalz-commerce-flow';

	/**
	 * Log a debug message (only recorded when Debug Logging is enabled).
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		if ( ! YukComFlow_Settings::is_debug_logging_enabled() ) {
			return;
		}
		self::log( 'debug', $message, $context );
	}

	/**
	 * Log an informational message (only recorded when Debug Logging is enabled).
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		if ( ! YukComFlow_Settings::is_debug_logging_enabled() ) {
			return;
		}
		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning message (always recorded for operational visibility).
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error message (always recorded for operational visibility).
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
	}

	/**
	 * Dispatch a log entry to WooCommerce's WC_Logger.
	 *
	 * @param string $level   PSR-3 log level.
	 * @param string $message Log message.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	private static function log( $level, $message, $context = array() ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		if ( ! is_object( $logger ) || ! method_exists( $logger, 'log' ) ) {
			return;
		}

		$log_context = array( 'source' => self::SOURCE );
		if ( ! empty( $context ) && is_array( $context ) ) {
			$message .= ' | ' . wp_json_encode( $context );
		}

		$logger->log( $level, $message, $log_context );
	}
}
