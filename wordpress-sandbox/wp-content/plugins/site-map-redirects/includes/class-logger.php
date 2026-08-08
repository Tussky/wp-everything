<?php
/**
 * Error logging helper for SiteMap Redirects.
 *
 * Centralises plugin logging so every module uses the same format and the
 * same destination. Failure to log never throws — logging failures are
 * swallowed to keep the plugin's "fail-safe" guarantee.
 *
 * Log lines are written via WP's `error_log()` and use a consistent prefix
 * so they can be filtered in the WordPress debug log and in production
 * monitoring tools:
 *
 *     [smr] <level> <message> | context=<json>
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin error logger.
 *
 * @package SiteMapRedirects
 */
class SMR_Logger {

	/**
	 * Log channel prefix used in every line.
	 *
	 * @var string
	 */
	const PREFIX = '[smr]';

	/**
	 * Write an "info" level message.
	 *
	 * @param string $message Human-readable description.
	 * @param array  $context Optional structured context (will be JSON-encoded).
	 */
	public static function info( $message, $context = array() ) {
		self::write( 'info', $message, $context );
	}

	/**
	 * Write a "warning" level message.
	 *
	 * @param string $message Human-readable description.
	 * @param array  $context Optional structured context (will be JSON-encoded).
	 */
	public static function warning( $message, $context = array() ) {
		self::write( 'warning', $message, $context );
	}

	/**
	 * Write an "error" level message.
	 *
	 * @param string $message Human-readable description.
	 * @param array  $context Optional structured context (will be JSON-encoded).
	 */
	public static function error( $message, $context = array() ) {
		self::write( 'error', $message, $context );
	}

	/**
	 * Log an exception with full backtrace context.
	 *
	 * @param Throwable $e      Exception or Error.
	 * @param string    $message Optional human-readable prefix.
	 */
	public static function exception( Throwable $e, $message = '' ) {
		$context = array(
			'exception' => get_class( $e ),
			'message'   => $e->getMessage(),
			'file'      => $e->getFile(),
			'line'      => (int) $e->getLine(),
		);
		$label    = $message ? $message . ': ' . $e->getMessage() : $e->getMessage();
		self::write( 'error', $label, $context );
	}

	/**
	 * Record a transient "last error" entry so the admin UI can show the
	 * most recent problem without scanning the PHP error log.
	 *
	 * Stored under the `smr_last_error` option. Only the first 50 entries
	 * are kept (oldest dropped) so the option never grows unbounded.
	 *
	 * @param string $code    Machine-readable error code (e.g. "index_failed").
	 * @param string $message User-facing message.
	 * @param array  $context Optional context for support/diagnostics.
	 */
	public static function record_last_error( $code, $message, $context = array() ) {
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'code'    => $code,
			'message' => $message,
			'context' => $context,
		);

		$history = get_option( 'smr_last_error', array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		array_unshift( $history, $entry );
		$history = array_slice( $history, 0, 50 );
		update_option( 'smr_last_error', $history, false );

		// Keep a single "current" pointer for the admin UI / REST payload.
		update_option( 'smr_current_error', $entry, false );
	}

	/**
	 * Get the most recent error entry, or null if none.
	 *
	 * @return array|null
	 */
	public static function get_last_error() {
		$entry = get_option( 'smr_current_error', null );
		if ( ! is_array( $entry ) ) {
			return null;
		}
		return $entry;
	}

	/**
	 * Clear the stored last-error entry. Called after a successful reindex.
	 */
	public static function clear_last_error() {
		delete_option( 'smr_current_error' );
	}

	/**
	 * Internal writer. Never throws.
	 *
	 * @param string $level   One of info/warning/error.
	 * @param string $message Message text.
	 * @param array  $context Structured context.
	 */
	protected static function write( $level, $message, $context = array() ) {
		try {
			$ctx = $context ? ' | context=' . wp_json_encode( $context ) : '';
			$line = sprintf( '%s %s %s%s', self::PREFIX, strtoupper( $level ), $message, $ctx );

			// Strip control characters that could corrupt log files.
			$line = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $line );

			error_log( $line );
		} catch ( Throwable $e ) {
			// Last-resort: if even the logger is broken, swallow it.
			// We intentionally do not call error_log again to avoid recursion.
		}
	}
}