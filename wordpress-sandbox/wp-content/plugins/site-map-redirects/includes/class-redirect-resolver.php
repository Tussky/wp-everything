<?php
/**
 * Module B — Redirect Resolver (scaffold).
 *
 * Owns discovering all active redirect rules and matching them to indexed
 * nodes. This scaffold provides the storage contract and an empty rule list;
 * the real discovery is implemented in T3 (IA-11).
 *
 * Error handling contract:
 *   - get_rules() never throws. On cache or discovery failure it returns an
 *     empty array (so the REST tree still renders), logs the failure, and
 *     records a last-error entry the admin UI can surface.
 *   - discover() is the only place the real work happens; any exception
 *     thrown by future implementations must be caught at this boundary so
 *     the rest of the plugin keeps working.
 *
 * RedirectRule shape (IA-6 plan section 2.1):
 *   - source_url      : URL redirected FROM, e.g. "/old-page/".
 *   - destination_url : URL redirected TO, e.g. "/new-page/".
 *   - status_code     : 301 | 302 | 307 | 308 | 0 (unknown).
 *   - priority        : order/resolution priority (lower = checked first).
 *   - source_label    : "Redirection plugin" | "WordPress canonical" | ".htaccess".
 *   - matched_node_id : pre-resolved to an IndexedNode.id if source matches a node path.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect rule discovery and lookup.
 *
 * @package SiteMapRedirects
 */
class SMR_Redirect_Resolver {

	/**
	 * Error code used when the discovery step throws.
	 *
	 * @var string
	 */
	const ERR_DISCOVERY = 'redirect_discovery_failed';

	/**
	 * Error code used when reading or writing the cached rule list fails.
	 *
	 * @var string
	 */
	const ERR_CACHE = 'redirect_cache_failed';

	/**
	 * No hooks at scaffold stage. T3 wires canonical/Redirection/.htaccess discovery.
	 */
	public static function init() {
		// No-op for now; future discovery is wired here.
	}

	/**
	 * Get all discovered redirect rules from the transient cache. Returns an
	 * empty array when nothing is cached or no rules exist. T3 (IA-11)
	 * implements the actual discovery and caching.
	 *
	 * Never throws. On any failure (cache read, discovery throw, malformed
	 * cached value) returns an empty array and logs/records the failure so
	 * the REST tree still renders without redirects.
	 *
	 * @param bool $force Bypass the cache and re-discover.
	 * @return array[] RedirectRule[]. Always an array.
	 */
	public static function get_rules( $force = false ) {
		if ( $force ) {
			return self::discover();
		}
		$rules = SMR_Safe::get_transient( SMR_TRANSIENT_RULES );
		if ( false === $rules ) {
			$rules = self::discover();
		}
		if ( ! is_array( $rules ) ) {
			return array();
		}
		return $rules;
	}

	/**
	 * Discover redirect rules. Scaffold no-op: returns an empty list. T3
	 * reads WP core canonical redirects, the Redirection plugin DB table, and
	 * (stretch) parses .htaccess read-only.
	 *
	 * Wrapped in a try/catch so a future implementation that throws cannot
	 * break the REST API. Errors are recorded as the plugin's last-error.
	 *
	 * @return array[] RedirectRule[].
	 */
	public static function discover() {
		try {
			$rules = self::do_discover();
			if ( ! is_array( $rules ) ) {
				SMR_Logger::warning(
					'Redirect discovery returned non-array; normalising to empty list',
					array( 'type' => is_object( $rules ) ? get_class( $rules ) : gettype( $rules ) )
				);
				$rules = array();
			}

			$stored = SMR_Safe::set_transient( SMR_TRANSIENT_RULES, $rules, SMR_TRANSIENT_RULES_TTL );
			if ( false === $stored ) {
				SMR_Logger::warning(
					'Redirect rules transient write failed',
					array( 'key' => SMR_TRANSIENT_RULES )
				);
				SMR_Logger::record_last_error(
					self::ERR_CACHE,
					__( 'Could not cache redirect rules. The plugin will keep working without a redirect cache.', 'site-map-redirects' ),
					array( 'key' => SMR_TRANSIENT_RULES )
				);
			}
			return $rules;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_Redirect_Resolver::discover failed' );
			SMR_Logger::record_last_error(
				self::ERR_DISCOVERY,
				__( 'Redirect rules could not be loaded. The site map will still render.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			return array();
		}
	}

	/**
	 * Actual discovery implementation. Kept separate so `discover()` can wrap
	 * a stable try/catch around it without obscuring the work.
	 *
	 * @return array[]
	 */
	protected static function do_discover() {
		return array();
	}
}