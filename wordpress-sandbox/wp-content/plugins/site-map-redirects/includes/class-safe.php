<?php
/**
 * Safe-call wrappers for WordPress APIs used by the plugin.
 *
 * The SiteMap Redirects plugin must degrade gracefully when any underlying
 * WordPress API throws or returns a WP_Error. This module provides tiny
 * "safe" wrappers so the rest of the plugin can assume sane defaults
 * instead of peppering every call site with is_wp_error() / try/catch.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safe-call helpers.
 *
 * @package SiteMapRedirects
 */
class SMR_Safe {

	/**
	 * Run a callable with a default fallback when it throws or returns
	 * WP_Error / null / false / unexpected types.
	 *
	 * @param callable $callable The thing to run.
	 * @param mixed    $default  Value to return on failure.
	 * @param string   $label    Optional label used in error logs.
	 * @param string   $code     Optional error code for last-error tracking.
	 * @return mixed Default value on failure, callable's return value otherwise.
	 */
	public static function call( $callable, $default, $label = '', $code = '' ) {
		if ( ! is_callable( $callable ) ) {
			SMR_Logger::warning(
				$label ? $label : 'Non-callable passed to SMR_Safe::call',
				array( 'code' => $code )
			);
			return $default;
		}
		try {
			$result = $callable();
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, $label ? $label : 'SMR_Safe::call exception' );
			if ( $code ) {
				SMR_Logger::record_last_error(
					$code,
					$label,
					array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
				);
			}
			return $default;
		}
		if ( is_wp_error( $result ) ) {
			SMR_Logger::warning(
				$label ? $label : 'WP_Error from SMR_Safe::call',
				array(
					'code'    => $code,
					'wp_error' => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
			return $default;
		}
		return $result;
	}

	/**
	 * Run a callable that is expected to return an array; return an empty
	 * array on failure. Logs the failure and records last-error.
	 *
	 * @param callable $callable The thing to run.
	 * @param string   $label    Optional label used in error logs.
	 * @param string   $code     Optional error code for last-error tracking.
	 * @return array
	 */
	public static function array( $callable, $label = '', $code = '' ) {
		$result = self::call( $callable, array(), $label, $code );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Coerce a value into a non-empty string, or return the fallback.
	 *
	 * @param mixed  $value    Value to coerce.
	 * @param string $fallback Fallback when value is empty/invalid.
	 * @return string
	 */
	public static function str( $value, $fallback = '' ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (string) $value;
		}
		return $fallback;
	}

	/**
	 * Coerce a value into an integer, or return the fallback.
	 *
	 * @param mixed $value    Value to coerce.
	 * @param int   $fallback Fallback when value is not numeric.
	 * @return int
	 */
	public static function int( $value, $fallback = 0 ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		return (int) $fallback;
	}

	/**
	 * Try to delete a transient, but never throw if WordPress is unavailable.
	 *
	 * @param string $key Transient key.
	 * @return bool Whether the transient existed (best-effort).
	 */
	public static function delete_transient( $key ) {
		try {
			return delete_transient( $key );
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'Failed to delete transient ' . $key );
			return false;
		}
	}

	/**
	 * Try to read a transient, but never throw. Returns false on any failure
	 * (matching the native transient contract).
	 *
	 * @param string $key Transient key.
	 * @return mixed Transient value or false.
	 */
	public static function get_transient( $key ) {
		try {
			return get_transient( $key );
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'Failed to read transient ' . $key );
			return false;
		}
	}

	/**
	 * Try to write a transient, but never throw. Returns false on failure.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   TTL in seconds.
	 * @return bool
	 */
	public static function set_transient( $key, $value, $ttl = 0 ) {
		try {
			return set_transient( $key, $value, max( 0, (int) $ttl ) );
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'Failed to set transient ' . $key );
			return false;
		}
	}
}