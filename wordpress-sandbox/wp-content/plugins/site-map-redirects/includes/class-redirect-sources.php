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
 * Error handling contract:
 *   - get_all() never throws. Per-source failures are skipped and logged; the
 *     surviving sources still return. An empty result is valid (no redirects).
 *   - Each from_*() method is independently guarded so a broken .htaccess
 *     cannot poison the Redirection plugin discovery, and vice versa.
 *   - On per-source failure we record a last-error entry with a user-facing
 *     message, but the plugin keeps working with whatever the surviving
 *     sources returned.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Redirect_Sources {

	/**
	 * Error code: .htaccess could not be read or parsed.
	 *
	 * @var string
	 */
	const ERR_HTACCESS = 'redirect_source_htaccess_failed';

	/**
	 * Error code: the Redirection plugin table query failed.
	 *
	 * @var string
	 */
	const ERR_REDIRECTION_PLUGIN = 'redirect_source_plugin_failed';

	/**
	 * Error code: WP core redirect discovery (canonical / detected) failed.
	 *
	 * @var string
	 */
	const ERR_CORE = 'redirect_source_core_failed';

	/**
	 * Error code: per-record normalisation failed (one row was malformed).
	 *
	 * @var string
	 */
	const ERR_RECORD = 'redirect_source_record_failed';

	public static function init() {}

	/**
	 * Collect all known redirects, sorted by priority (1 = first).
	 *
	 * Per-source failures are caught and logged; an individual broken source
	 * does not stop the others from contributing. The list is always sorted
	 * before returning so callers can rely on the priority ordering.
	 *
	 * @return array[] Redirect records.
	 */
	public static function get_all() {
		$redirects = array();

		// 1) .htaccess — runs at Apache level, before WP. Highest priority (lowest number).
		$htaccess = self::from_htaccess();
		if ( is_array( $htaccess ) ) {
			$redirects = array_merge( $redirects, $htaccess );
		}

		// 2) Redirection plugin — registered via wp_loaded, runs on template_redirect.
		$plugin = self::from_redirection_plugin();
		if ( is_array( $plugin ) ) {
			$redirects = array_merge( $redirects, $plugin );
		}

		// 3) WP core canonical / wp_redirect hooks — run during template_redirect.
		$core = self::from_core_hooks();
		if ( is_array( $core ) ) {
			$redirects = array_merge( $redirects, $core );
		}

		// Defensive: drop any non-array entries a partial merge may have produced.
		$clean = array();
		foreach ( $redirects as $r ) {
			if ( is_array( $r ) && ! empty( $r['source_path'] ) ) {
				$clean[] = $r;
			}
		}

		// Sort by priority then source path.
		usort(
			$clean,
			function ( $a, $b ) {
				$ap = isset( $a['priority'] ) ? (int) $a['priority'] : 999;
				$bp = isset( $b['priority'] ) ? (int) $b['priority'] : 999;
				if ( $ap === $bp ) {
					return strcmp( (string) $a['source_path'], (string) $b['source_path'] );
				}
				return $ap <=> $bp;
			}
		);

		return $clean;
	}

	/**
	 * Group redirects by source path for quick node lookup.
	 *
	 * @return array<string,array[]> Map of source_path => redirect[].
	 */
	public static function get_by_path() {
		$map = array();
		foreach ( self::get_all() as $r ) {
			if ( ! is_array( $r ) || empty( $r['source_path'] ) ) {
				continue;
			}
			$map[ $r['source_path'] ][] = $r;
		}
		return $map;
	}

	/**
	 * Parse .htaccess for Redirect / RedirectMatch / RewriteRule [R=...] directives.
	 * Read-only. Returns list of redirects with priority 1.
	 *
	 * Wrapped in try/catch so a malformed .htaccess or unreadable file is
	 * logged as a last-error with a user-facing message but never escapes
	 * the function. Returns an empty array on any failure.
	 *
	 * Hardening:
	 *   - The file size is capped at 256 KiB before parsing. .htaccess is
	 *     normally a few KB; a larger file is almost certainly hostile or
	 *     mis-configured, and we don't want to feed it to preg_* regexes.
	 *   - Each parsed line is length-bounded.
	 *   - Status codes are restricted to the standard redirect set
	 *     (301/302/303/307/308) before the record is added.
	 *
	 * @return array[]
	 */
	public static function from_htaccess() {
		$out = array();
		// Status whitelist — anything outside this set is ignored.
		$status_ok = function ( $code ) {
			return in_array( (int) $code, array( 301, 302, 303, 307, 308 ), true );
		};
		// Max length of a single line we will try to parse.
		$max_line = 1024;

		try {
			if ( ! function_exists( 'get_home_path' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'get_home_path' ) ) {
				return $out;
			}

			$home_path = get_home_path();
			if ( ! is_string( $home_path ) || '' === $home_path ) {
				return $out;
			}

			$htaccess = $home_path . '.htaccess';
			if ( ! is_string( $htaccess ) || ! is_readable( $htaccess ) ) {
				return $out;
			}

			// Cap .htaccess at a sane size so we never parse attacker-controlled
			// megabytes of regex fodder.
			$size = @filesize( $htaccess );
			if ( false === $size || $size > 256 * 1024 ) {
				return $out;
			}

			$contents = file_get_contents( $htaccess );
			if ( false === $contents || ! is_string( $contents ) ) {
				SMR_Logger::warning(
					'Could not read .htaccess for redirect discovery',
					array( 'path' => $htaccess )
				);
				SMR_Logger::record_last_error(
					self::ERR_HTACCESS,
					__( 'Could not read the .htaccess file. Apache-level redirects were skipped; the rest of the site map is unaffected.', 'site-map-redirects' ),
					array( 'path' => $htaccess )
				);
				return $out;
			}

			// Redirect 301 /old /new
			// RedirectPermanent /old /new
			// RedirectMatch 301 ^/old/(.*)$ /new/$1
			$lines = preg_split( '/\r\n|\r|\n/', $contents );
			if ( ! is_array( $lines ) ) {
				return $out;
			}

			foreach ( $lines as $line ) {
				if ( ! is_string( $line ) ) {
					continue;
				}
				$line = trim( $line );
				if ( '' === $line || '#' === $line[0] ) {
					continue;
				}
				// Skip lines that are absurdly long; protects the regex engine
				// from catastrophic backtracking on hostile inputs.
				if ( strlen( $line ) > $max_line ) {
					continue;
				}

				try {
					if ( preg_match( '#^Redirect(?:Permanent|Temp|SeeOther)?\s+(\d{3})?\s*(\S+)\s+(\S+)$#i', $line, $m ) ) {
						$status = ! empty( $m[1] ) ? (int) $m[1] : ( preg_match( '/permanent/i', $line ) ? 301 : 302 );
						if ( ! $status_ok( $status ) ) {
							continue;
						}
						$out[] = self::make_record( $m[2], $m[3], $status, 'htaccess', 1, __( 'Apache .htaccess rule — runs before WordPress loads.', 'site-map-redirects' ) );
					} elseif ( preg_match( '#^RedirectMatch\s+(\d{3})?\s*(\S+)\s+(\S+)$#i', $line, $m ) ) {
						$status = ! empty( $m[1] ) ? (int) $m[1] : 302;
						if ( ! $status_ok( $status ) ) {
							continue;
						}
						$out[] = self::make_record( $m[2], $m[3], $status, 'htaccess_regex', 1, __( 'Apache .htaccess pattern redirect — runs before WordPress loads.', 'site-map-redirects' ) );
					} elseif ( preg_match( '#^RewriteRule\s+(\S+)\s+(\S+)\s+\[[^\]]*R=(\d{3})[^\]]*\]#i', $line, $m ) ) {
						$status = (int) $m[3];
						if ( ! $status_ok( $status ) ) {
							continue;
						}
						$out[] = self::make_record( $m[1], $m[2], $status, 'htaccess_rewrite', 1, __( 'Apache mod_rewrite redirect — runs before WordPress loads.', 'site-map-redirects' ) );
					}
				} catch ( Throwable $e ) {
					// One bad line should not abort the whole .htaccess scan.
					SMR_Logger::exception(
						$e,
						'Skipping malformed .htaccess line during redirect discovery'
					);
				}
			}
			return $out;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_Redirect_Sources::from_htaccess failed' );
			SMR_Logger::record_last_error(
				self::ERR_HTACCESS,
				__( 'Could not read the .htaccess file. Apache-level redirects were skipped; the rest of the site map is unaffected.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			return array();
		}
	}

	/**
	 * Read the Redirection plugin's DB table (prefix_redirection_items).
	 * Redirection registers redirects at wp_loaded and matches on template_redirect.
	 *
	 * Wrapped in try/catch so a DB failure (missing table, schema mismatch,
	 * permission denied) never breaks the rest of the discovery. Returns an
	 * empty array and records a last-error on failure.
	 *
	 * @return array[]
	 */
	public static function from_redirection_plugin() {
		global $wpdb;
		$out = array();

		try {
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
				return $out;
			}

			$prefix = isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';

			// Redirection < 4.0 used wp_redirection_items; >=4.0 uses {prefix}redirection_items.
			$tables = array( $prefix . 'redirection_items', $prefix . 'redirection' );
			$found  = null;
			foreach ( $tables as $t ) {
				try {
					// Escape LIKE wildcards in the prefix so this is an exact
					// match even if `$wpdb->prefix` ever contains a `%` or `_`.
					$like_pattern = str_replace(
						array( '\\', '%', '_' ),
						array( '\\\\', '\\%', '\\_' ),
						$t
					);
					$check = $wpdb->get_var(
						$wpdb->prepare( 'SHOW TABLES LIKE %s', $like_pattern ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- LIKE pattern is pre-escaped.
					);
				} catch ( Throwable $e ) {
					SMR_Logger::exception(
						$e,
						'SHOW TABLES failed while locating the Redirection plugin table'
					);
					continue;
				}
				if ( isset( $check ) && $check === $t ) {
					$found = $t;
					break;
				}
			}
			if ( ! $found ) {
				return $out;
			}

			// Whitelist the table name against an allow-list before interpolation.
			// The Redirection plugin only ever ships with these two table names.
			if ( ! in_array( $found, $tables, true ) ) {
				SMR_Logger::warning(
					'Refusing to read unexpected Redirection plugin table',
					array( 'table' => $found )
				);
				return $out;
			}

			// Common columns across Redirection 3.x and 4.x.
			// $found has been whitelisted above; still run esc_sql on the
			// identifier out of defense-in-depth (wpdb allows it).
			$table = esc_sql( $found );
			try {
				$rows = $wpdb->get_results(
					"SELECT url, action_url, action_type, action_code, match_type, regex, status, title
					 FROM {$table}
					 WHERE status = 'enabled' AND ( action_type = 'url' OR action_type = 'pass' )"
				);
			} catch ( Throwable $e ) {
				SMR_Logger::exception(
					$e,
					'Primary Redirection plugin query failed; trying minimal fallback'
				);
				try {
					$rows = $wpdb->get_results( "SELECT url, action_url, action_code FROM {$table} WHERE 1=1" );
				} catch ( Throwable $e2 ) {
					SMR_Logger::exception( $e2, 'Fallback Redirection plugin query failed' );
					SMR_Logger::record_last_error(
						self::ERR_REDIRECTION_PLUGIN,
						__( 'Could not read the Redirection plugin\'s rule table. The site map is still built, just without Redirection plugin rules.', 'site-map-redirects' ),
						array( 'table' => $found, 'exception' => get_class( $e2 ), 'message' => $e2->getMessage() )
					);
					return $out;
				}
			}

			if ( empty( $rows ) ) {
				return $out;
			}

			foreach ( $rows as $row ) {
				try {
					if ( ! is_object( $row ) ) {
						continue;
					}
					$status  = ! empty( $row->action_code ) ? (int) $row->action_code : 301;
					$dest    = ! empty( $row->action_url ) ? (string) $row->action_url : '';
					$regex   = ! empty( $row->regex ) && ( '1' === $row->regex || 1 === (int) $row->regex );
					$src     = isset( $row->url ) ? (string) $row->url : '';
					if ( '' === $src ) {
						continue;
					}
					$explain = $regex
						? __( 'Redirection plugin pattern rule — matches many URLs at once.', 'site-map-redirects' )
						: __( 'Redirection plugin rule — created in WP Admin > Tools > Redirection.', 'site-map-redirects' );
					$out[]   = self::make_record( $src, $dest, $status, 'redirection_plugin', 2, $explain, $regex );
				} catch ( Throwable $e ) {
					// A single malformed row should not abort the whole table scan.
					SMR_Logger::exception(
						$e,
						'Skipping malformed Redirection plugin row during redirect discovery'
					);
					SMR_Logger::record_last_error(
						self::ERR_RECORD,
						__( 'One rule in the Redirection plugin\'s table was skipped because it could not be read.', 'site-map-redirects' ),
						array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
					);
				}
			}
			return $out;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_Redirect_Sources::from_redirection_plugin failed' );
			SMR_Logger::record_last_error(
				self::ERR_REDIRECTION_PLUGIN,
				__( 'Could not read the Redirection plugin\'s rule table. The site map is still built, just without Redirection plugin rules.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			return $out;
		}
	}

	/**
	 * WP core redirects: canonical redirects (e.g. trailing-slash normalization,
	 * attachment fallbacks), and wp_redirect() calls registered via plugins.
	 *
	 * We surface a curated set of common core behaviors so the UI can explain them,
	 * plus any wp_redirect targets captured at runtime via the smr_detected_redirects option.
	 *
	 * The get_option() call is guarded so a corrupted option value can't take the
	 * admin UI down. Runtime detections that fail to normalise are skipped.
	 *
	 * @return array[]
	 */
	public static function from_core_hooks() {
		$out = array();

		try {
			// Canonical trailing-slash normalization is conditional, so we describe it
			// generically rather than enumerating every URL.
			$out[] = array(
				'source_path'   => '*',
				'source_url'    => '*',
				'destination'   => __( '(normalized to the canonical permalink)', 'site-map-redirects' ),
				'status'        => 301,
				'type'          => 'wp_canonical',
				'priority'      => 3,
				'regex'         => true,
				'label'         => __( 'WordPress canonical redirect', 'site-map-redirects' ),
				'explainer'     => __( 'WordPress automatically fixes small URL mistakes — adding or removing a trailing slash, or pointing a non-canonical URL to the official permalink. Runs during page load.', 'site-map-redirects' ),
				'plain_english' => __( 'WordPress quietly fixes URL spelling and sends visitors to the official address.', 'site-map-redirects' ),
			);

			// Runtime-detected wp_redirect calls (captured by a companion mu-hook if present).
			$detected = get_option( 'smr_detected_redirects', array() );
			if ( ! is_array( $detected ) || empty( $detected ) ) {
				return $out;
			}

			foreach ( $detected as $row ) {
				try {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$src = isset( $row['source'] ) ? (string) $row['source'] : '';
					if ( '' === $src ) {
						continue;
					}
					$out[] = self::make_record(
						$src,
						isset( $row['destination'] ) ? (string) $row['destination'] : '',
						isset( $row['status'] ) ? (int) $row['status'] : 302,
						'wp_redirect',
						3,
						__( 'A wp_redirect() call from WordPress or a plugin/theme.', 'site-map-redirects' )
					);
				} catch ( Throwable $e ) {
					SMR_Logger::exception(
						$e,
						'Skipping malformed runtime-detected redirect during discovery'
					);
				}
			}

			return $out;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_Redirect_Sources::from_core_hooks failed' );
			SMR_Logger::record_last_error(
				self::ERR_CORE,
				__( 'WordPress core redirects could not be loaded. Plugin and .htaccess redirects will still appear in the site map.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			// Best-effort: at least surface the canonical rule so the legend still has it.
			return array(
				array(
					'source_path'   => '*',
					'source_url'    => '*',
					'destination'   => __( '(normalized to the canonical permalink)', 'site-map-redirects' ),
					'status'        => 301,
					'type'          => 'wp_canonical',
					'priority'      => 3,
					'regex'         => true,
					'label'         => __( 'WordPress canonical redirect', 'site-map-redirects' ),
					'explainer'     => __( 'WordPress automatically fixes small URL mistakes — adding or removing a trailing slash, or pointing a non-canonical URL to the official permalink. Runs during page load.', 'site-map-redirects' ),
					'plain_english' => __( 'WordPress quietly fixes URL spelling and sends visitors to the official address.', 'site-map-redirects' ),
				),
			);
		}
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

		// Coerce status to a known redirect code. Anything else falls back to
		// 302 so a hostile redirect row can never inject a non-HTTP number
		// into the REST payload that the JS would render verbatim.
		$status_int = (int) $status;
		if ( ! in_array( $status_int, array( 301, 302, 303, 307, 308 ), true ) ) {
			$status_int = 302;
		}

		// Coerce priority into a sane numeric range [1..99] so the JS can
		// always rely on `>` / `<` ordering and an attacker-controllable row
		// can't push an absurd number that breaks the sort.
		$priority_int = (int) $priority;
		if ( $priority_int < 1 ) {
			$priority_int = 99;
		} elseif ( $priority_int > 99 ) {
			$priority_int = 99;
		}

		// Normalize source to a path when it's a URL on this site.
		$source_path = $source;
		if ( 0 === strpos( $source, 'http' ) ) {
			if ( class_exists( 'SMR_Indexer' ) ) {
				$source_path = SMR_Indexer::url_to_path( $source );
			}
		} elseif ( '' !== $source && '/' !== $source[0] && ! $regex ) {
			$source_path = '/' . ltrim( $source, '/' );
		}

		// Strip trailing slash to match the indexer's path normalization
		// (root stays '/', everything else has no trailing slash).
		if ( ! $regex && '/' !== $source_path && '' !== $source_path ) {
			$source_path = '/' . trim( $source_path, '/' );
		}

		return array(
			'source_path'   => $source_path,
			'source_url'    => ( 0 === strpos( $source, 'http' ) ) ? $source : home_url( $source ),
			'destination'   => $dest,
			'status'        => $status_int,
			'type'          => $type,
			'priority'      => $priority_int,
			'regex'         => (bool) $regex,
			'label'         => self::type_label( $type ),
			'explainer'     => $explainer,
			'plain_english' => self::plain_english( $status_int, $dest ),
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