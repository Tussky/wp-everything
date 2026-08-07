<?php
/**
 * Redirect source detection.
 *
 * Gathers redirects from:
 *  - The "Redirection" plugin's DB table (most popular WP redirect plugin).
 *  - WP core redirect hooks (wp_redirect / template_redirect / canonical) — captured
 *    as a static known set + live matches against indexed URLs via a HEAD probe.
 *  - .htaccess static rules (read-only parse) — best-effort, read-only.
 *
 * Each redirect is normalized to: {source_path, source_url, destination, status, type, priority, explainer}.
 * Priority order mirrors WP's redirect evaluation order:
 *   1 = highest (executed first), larger numbers run later.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Redirect_Sources {

	public static function init() {}

	/**
	 * Collect all known redirects, sorted by priority (1 = first).
	 *
	 * @return array[] Redirect records.
	 */
	public static function get_all() {
		$redirects = array();

		// 1) .htaccess — runs at Apache level, before WP. Highest priority (lowest number).
		$redirects = array_merge( $redirects, self::from_htaccess() );

		// 2) Redirection plugin — registered via wp_loaded, runs on template_redirect.
		$redirects = array_merge( $redirects, self::from_redirection_plugin() );

		// 3) WP core canonical / wp_redirect hooks — run during template_redirect.
		$redirects = array_merge( $redirects, self::from_core_hooks() );

		// Sort by priority then source path.
		usort(
			$redirects,
			function ( $a, $b ) {
				if ( $a['priority'] === $b['priority'] ) {
					return strcmp( $a['source_path'], $b['source_path'] );
				}
				return $a['priority'] <=> $b['priority'];
			}
		);

		return $redirects;
	}

	/**
	 * Group redirects by source path for quick node lookup.
	 *
	 * @return array<string,array[]> Map of source_path => redirect[].
	 */
	public static function get_by_path() {
		$map = array();
		foreach ( self::get_all() as $r ) {
			$map[ $r['source_path'] ][] = $r;
		}
		return $map;
	}

	/**
	 * Parse .htaccess for Redirect / RedirectMatch / RewriteRule [R=...] directives.
	 * Read-only. Returns list of redirects with priority 1.
	 *
	 * @return array[]
	 */
	public static function from_htaccess() {
		$out = array();
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$home_path = get_home_path();
		$htaccess  = $home_path . '.htaccess';
		if ( ! is_readable( $htaccess ) ) {
			return $out;
		}
		$contents = file_get_contents( $htaccess );
		if ( false === $contents ) {
			return $out;
		}

		// Redirect 301 /old /new
		// RedirectPermanent /old /new
		// RedirectMatch 301 ^/old/(.*)$ /new/$1
		$lines = preg_split( '/\r\n|\r|\n/', $contents );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( preg_match( '#^Redirect(?:Permanent|Temp|SeeOther)?\s+(\d{3})?\s*(.+?)\s+(.+)$#i', $line, $m ) ) {
				$status = ! empty( $m[1] ) ? (int) $m[1] : ( preg_match( '/permanent/i', $line ) ? 301 : 302 );
				$src    = $m[2];
				$dst    = $m[3];
				$out[]  = self::make_record( $src, $dst, $status, 'htaccess', 1, __( 'Apache .htaccess rule — runs before WordPress loads.', 'site-map-redirects' ) );
			} elseif ( preg_match( '#^RedirectMatch\s+(\d{3})?\s*(.+?)\s+(.+)$#i', $line, $m ) ) {
				$status = ! empty( $m[1] ) ? (int) $m[1] : 302;
				$out[]  = self::make_record( $m[2], $m[3], $status, 'htaccess_regex', 1, __( 'Apache .htaccess pattern redirect — runs before WordPress loads.', 'site-map-redirects' ) );
			} elseif ( preg_match( '#^RewriteRule\s+(.+?)\s+(.+?)\s+\[.*?R=(\d{3}).*?\]#i', $line, $m ) ) {
				$out[] = self::make_record( $m[1], $m[2], (int) $m[3], 'htaccess_rewrite', 1, __( 'Apache mod_rewrite redirect — runs before WordPress loads.', 'site-map-redirects' ) );
			}
		}
		return $out;
	}

	/**
	 * Read the Redirection plugin's DB table (prefix_redirection_items).
	 * Redirection registers redirects at wp_loaded and matches on template_redirect.
	 *
	 * @return array[]
	 */
	public static function from_redirection_plugin() {
		global $wpdb;
		$out = array();

		// Redirection < 4.0 used wp_redirection_items; >=4.0 uses {prefix}redirection_items.
		$tables = array( $wpdb->prefix . 'redirection_items', $wpdb->prefix . 'redirection' );
		$found  = null;
		foreach ( $tables as $t ) {
			$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
			if ( $check === $t ) {
				$found = $t;
				break;
			}
		}
		if ( ! $found ) {
			return $out;
		}

		// Common columns across Redirection 3.x and 4.x.
		$rows = $wpdb->get_results(
			"SELECT url, action_url, action_type, action_code, match_type, regex, status, title
			 FROM {$found}
			 WHERE status = 'enabled' AND ( action_type = 'url' OR action_type = 'pass' )"
		);
		if ( empty( $rows ) ) {
			// Fallback: try a minimal column set for older schemas.
			$rows = $wpdb->get_results( "SELECT url, action_url, action_code FROM {$found} WHERE 1=1" );
		}

		foreach ( $rows as $row ) {
			$status  = ! empty( $row->action_code ) ? (int) $row->action_code : 301;
			$dest    = ! empty( $row->action_url ) ? $row->action_url : '';
			$regex   = ! empty( $row->regex ) && ( '1' === $row->regex || 1 === (int) $row->regex );
			$src     = $row->url;
			$explain = $regex
				? __( 'Redirection plugin pattern rule — matches many URLs at once.', 'site-map-redirects' )
				: __( 'Redirection plugin rule — created in WP Admin > Tools > Redirection.', 'site-map-redirects' );
			$out[]   = self::make_record( $src, $dest, $status, 'redirection_plugin', 2, $explain, $regex );
		}
		return $out;
	}

	/**
	 * WP core redirects: canonical redirects (e.g. trailing-slash normalization,
	 * attachment fallbacks), and wp_redirect() calls registered via plugins.
	 *
	 * We surface a curated set of common core behaviors so the UI can explain them,
	 * plus any wp_redirect targets captured at runtime via the smr_detected_redirects option.
	 *
	 * @return array[]
	 */
	public static function from_core_hooks() {
		$out = array();

		// Canonical trailing-slash normalization is conditional, so we describe it
		// generically rather than enumerating every URL.
		$out[] = array(
			'source_path'  => '*',
			'source_url'   => '*',
			'destination'  => __( '(normalized to the canonical permalink)', 'site-map-redirects' ),
			'status'       => 301,
			'type'         => 'wp_canonical',
			'priority'     => 3,
			'regex'        => true,
			'label'        => __( 'WordPress canonical redirect', 'site-map-redirects' ),
			'explainer'    => __( 'WordPress automatically fixes small URL mistakes — adding or removing a trailing slash, or pointing a non-canonical URL to the official permalink. Runs during page load.', 'site-map-redirects' ),
			'plain_english' => __( 'WordPress quietly fixes URL spelling and sends visitors to the official address.', 'site-map-redirects' ),
		);

		// Runtime-detected wp_redirect calls (captured by a companion mu-hook if present).
		$detected = get_option( 'smr_detected_redirects', array() );
		if ( ! empty( $detected ) ) {
			foreach ( $detected as $row ) {
				$src = isset( $row['source'] ) ? $row['source'] : '';
				$out[] = self::make_record(
					$src,
					isset( $row['destination'] ) ? $row['destination'] : '',
					isset( $row['status'] ) ? (int) $row['status'] : 302,
					'wp_redirect',
					3,
					__( 'A wp_redirect() call from WordPress or a plugin/theme.', 'site-map-redirects' )
				);
			}
		}

		return $out;
	}

	/**
	 * Normalize a redirect record.
	 *
	 * @param string $source    Source path or URL.
	 * @param string $dest      Destination URL or path.
	 * @param int    $status    HTTP status code.
	 * @param string $type      Source type slug.
	 * @param int    $priority  Priority (1 = first).
	 * @param string $explainer Plain-English explanation.
	 * @param bool   $regex     Whether source is a regex/pattern.
	 * @return array
	 */
	public static function make_record( $source, $dest, $status, $type, $priority, $explainer, $regex = false ) {
		$source = is_string( $source ) ? $source : '';
		$dest   = is_string( $dest ) ? $dest : '';

		// Normalize source to a path when it's a URL on this site.
		$source_path = $source;
		if ( 0 === strpos( $source, 'http' ) ) {
			$source_path = SMR_Indexer::url_to_path( $source );
		} elseif ( '' !== $source && '/' !== $source[0] && ! $regex ) {
			$source_path = '/' . ltrim( $source, '/' );
		}

		return array(
			'source_path'   => $source_path,
			'source_url'    => ( 0 === strpos( $source, 'http' ) ) ? $source : home_url( $source ),
			'destination'   => $dest,
			'status'        => (int) $status,
			'type'          => $type,
			'priority'      => (int) $priority,
			'regex'         => (bool) $regex,
			'label'         => self::type_label( $type ),
			'explainer'     => $explainer,
			'plain_english' => self::plain_english( $status, $dest ),
		);
	}

	/**
	 * Human label for a redirect source type.
	 */
	public static function type_label( $type ) {
		$labels = array(
			'htaccess'           => __( '.htaccess rule', 'site-map-redirects' ),
			'htaccess_regex'     => __( '.htaccess pattern', 'site-map-redirects' ),
			'htaccess_rewrite'   => __( '.htaccess rewrite', 'site-map-redirects' ),
			'redirection_plugin' => __( 'Redirection plugin', 'site-map-redirects' ),
			'wp_canonical'       => __( 'WordPress core', 'site-map-redirects' ),
			'wp_redirect'        => __( 'Plugin / theme redirect', 'site-map-redirects' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Plain-English summary of what a redirect does, keyed on HTTP status.
	 */
	public static function plain_english( $status, $dest ) {
		switch ( (int) $status ) {
			case 301:
				$verb = __( 'permanently moved to', 'site-map-redirects' );
				break;
			case 302:
				$verb = __( 'temporarily sent to', 'site-map-redirects' );
				break;
			case 303:
				$verb = __( 'redirected after submission to', 'site-map-redirects' );
				break;
			case 307:
				$verb = __( 'temporarily redirected (keeping method) to', 'site-map-redirects' );
				break;
			case 308:
				$verb = __( 'permanently redirected (keeping method) to', 'site-map-redirects' );
				break;
			default:
				$verb = __( 'redirected to', 'site-map-redirects' );
		}
		$dest_text = $dest ? $dest : __( 'a new address', 'site-map-redirects' );
		/* translators: 1: verb, 2: destination. */
		return sprintf( __( 'Visitors here are %1$s %2$s.', 'site-map-redirects' ), $verb, $dest_text );
	}
}
