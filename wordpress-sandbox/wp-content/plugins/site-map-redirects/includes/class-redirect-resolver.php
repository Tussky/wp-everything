<?php
/**
 * Module B — Redirect Resolver (scaffold).
 *
 * Owns discovering all active redirect rules and matching them to indexed
 * nodes. This scaffold provides the storage contract and an empty rule list;
 * the real discovery is implemented in T3 (IA-11).
 *
 * RedirectRule shape (IA-6 plan §2.1):
 * {
 *   "source_url":      "string",         // URL redirected FROM, e.g. "/old-page/"
 *   "destination_url": "string",         // URL redirected TO, e.g. "/new-page/"
 *   "status_code":     "number",         // 301 | 302 | 307 | 308 | 0 (unknown)
 *   "priority":        "number",         // order/resolution priority (lower = checked first)
 *   "source_label":    "string",         // "Redirection plugin" | "WordPress canonical" | ".htaccess"
 *   "matched_node_id": "string | null"  // pre-resolved to an IndexedNode.id if source matches a node path
 * }
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Redirect_Resolver {

	public static function init() {
		// No hooks at scaffold stage. T3 wires canonical/Redirection/.htaccess discovery.
	}

	/**
	 * Get all discovered redirect rules from the transient cache. Returns an
	 * empty array when nothing is cached or no rules exist. T3 (IA-11)
	 * implements the actual discovery and caching.
	 *
	 * @param bool $force Bypass the cache and re-discover (no-op at scaffold stage).
	 * @return array[] RedirectRule[] (empty at scaffold stage).
	 */
	public static function get_rules( $force = false ) {
		if ( $force ) {
			return self::discover();
		}
		$rules = get_transient( SMR_TRANSIENT_RULES );
		if ( false === $rules ) {
			$rules = self::discover();
			set_transient( SMR_TRANSIENT_RULES, $rules, SMR_TRANSIENT_RULES_TTL );
		}
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Discover redirect rules. Scaffold no-op: returns an empty list. T3
	 * reads WP core canonical redirects, the Redirection plugin DB table, and
	 * (stretch) parses .htaccess read-only.
	 *
	 * @return array[] RedirectRule[].
	 */
	public static function discover() {
		return array();
	}
}