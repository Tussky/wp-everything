<?php
/**
 * REST routes for SiteMap Redirects.
 *
 * GET /wp-json/sitemap-redirects/v1/tree    -> {tree, redirects, last_index, counts}
 * POST /wp-json/sitemap-redirects/v1/reindex -> rebuilds the tree, returns fresh payload.
 *
 * Error handling contract:
 *   - The /tree and /reindex endpoints ALWAYS return a 200 JSON response,
 *     even when the indexer or resolver failed. Failures are surfaced
 *     inside the payload (last_error, error_codes[]) so the JS bundle can
 *     render a graceful error state without having to handle 5xx responses.
 *   - Hard errors (a thrown exception that we couldn't recover from, or an
 *     invalid permission context) DO return a WP_Error so WP returns the
 *     proper 4xx/5xx JSON the JS bundle already understands.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route registration and payload assembly.
 *
 * @package SiteMapRedirects
 */
class SMR_REST {

	const NS = SMR_REST_NAMESPACE;

	/**
	 * Permission code returned when a non-admin tries to reindex.
	 *
	 * @var string
	 */
	const ERR_FORBIDDEN = 'smr_forbidden';

	/**
	 * Permission code returned when the permission check itself fails.
	 *
	 * @var string
	 */
	const ERR_PERM_CHECK = 'smr_permission_check_failed';

	/**
	 * Permission code returned when an unexpected exception escapes.
	 *
	 * @var string
	 */
	const ERR_INTERNAL = 'smr_internal_error';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_rest_route(
			self::NS,
			'/tree',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_tree' ),
				'permission_callback' => array( __CLASS__, 'read_perm' ),
				'args'                => array(
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/reindex',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reindex' ),
				'permission_callback' => array( __CLASS__, 'manage_perm' ),
			)
		);
	}

	/**
	 * Read permission: public tree is read-only, but the admin UI requires auth.
	 * Expose publicly only when the filter opts in; default to logged-in read.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public static function read_perm( WP_REST_Request $request ) {
		try {
			if ( apply_filters( 'smr_public_read', false ) ) {
				return true;
			}
			return current_user_can( 'read' );
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_REST::read_perm failed' );
			return new WP_Error(
				self::ERR_PERM_CHECK,
				__( 'Could not verify read permission.', 'site-map-redirects' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Write permission: requires `manage_options`.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public static function manage_perm( WP_REST_Request $request ) {
		try {
			$can = current_user_can( 'manage_options' );
			if ( ! $can ) {
				return new WP_Error(
					self::ERR_FORBIDDEN,
					__( 'You do not have permission to reindex the site map.', 'site-map-redirects' ),
					array( 'status' => 403 )
				);
			}
			return true;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_REST::manage_perm failed' );
			return new WP_Error(
				self::ERR_PERM_CHECK,
				__( 'Could not verify manage permission.', 'site-map-redirects' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * GET `/tree` handler.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_tree( WP_REST_Request $request ) {
		$force = (bool) $request->get_param( 'force' );
		return self::safe_payload( $force );
	}

	/**
	 * POST `/reindex` handler.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reindex( WP_REST_Request $request ) {
		// Always drop cached rules too — a reindex should re-discover.
		SMR_Safe::delete_transient( SMR_TRANSIENT_RULES );
		return self::safe_payload( true );
	}

	/**
	 * Wrap `payload()` in a final try/catch so an exception here becomes a
	 * WP_Error with a user-facing message rather than a 500 with no body.
	 *
	 * @param bool $force Whether to force-rebuild the index.
	 * @return WP_REST_Response|WP_Error
	 */
	protected static function safe_payload( $force ) {
		try {
			return self::payload( $force );
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'REST payload build failed' );
			SMR_Logger::record_last_error(
				self::ERR_INTERNAL,
				__( 'The site map could not be assembled. Please try again, or reindex.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			return new WP_Error(
				self::ERR_INTERNAL,
				__( 'The site map could not be assembled. Please try again, or reindex.', 'site-map-redirects' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Assemble the API payload: tree + redirect overlays + counts.
	 *
	 * @param bool $force Whether to force-rebuild the index.
	 * @return WP_REST_Response Response payload.
	 */
	protected static function payload( $force ) {
		$tree      = SMR_Indexer::get_tree( $force );
		$redirects = SMR_Redirect_Resolver::get_rules( $force );

		if ( ! is_array( $tree ) ) {
			$tree = SMR_Indexer::fallback_tree();
		}
		if ( ! is_array( $redirects ) ) {
			$redirects = array();
		}

		// Map redirects by source path. Virtual "redirect source" URLs that
		// don't correspond to a real page are injected into the tree so the
		// map visualizes them as nodes — this is the whole point of the overlay.
		$by_path        = array();
		$to_inject      = array();
		$existing_paths = self::collect_paths( $tree );
		foreach ( $redirects as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$sp = isset( $r['source_url'] ) ? self::url_to_path( $r['source_url'] ) : '';
			if ( '' === $sp ) {
				continue; // Empty — handled in legend.
			}
			$by_path[ $sp ][] = $r;
			// Inject concrete source paths that don't already exist.
			if ( ! isset( $existing_paths[ $sp ] ) ) {
				$to_inject[ $sp ] = $r;
			}
		}

		// Inject virtual redirect-source nodes into the tree.
		if ( $to_inject ) {
			// Sort by path so parents precede children.
			$paths = array_keys( $to_inject );
			sort( $paths );
			foreach ( $paths as $path ) {
				try {
					self::inject_node( $tree, $path, $to_inject[ $path ] );
				} catch ( Throwable $e ) {
					SMR_Logger::exception( $e, 'inject_node failed for path ' . $path );
				}
			}
		}

		self::annotate( $tree, $by_path );

		$last_error = SMR_Logger::get_last_error();

		return rest_ensure_response(
			array(
				'tree'           => $tree,
				'redirects'      => $redirects,
				'last_index'     => get_option( 'smr_last_index', '' ),
				'home_url'       => home_url( '/' ),
				'counts'         => array(
					'nodes'     => SMR_Indexer::count_nodes( $tree ),
					'redirects' => count( $redirects ),
				),
				'status_colors'  => array(
					'301'    => '#d63638', // Red — permanent.
					'302'    => '#dcaa00', // Amber — temporary.
					'303'    => '#2271b1', // Blue — see other.
					'307'    => '#996800', // Dark amber — temp keep method.
					'308'    => '#8c1c1c', // Dark red — perm keep method.
					'other'  => '#50575e',
				),
				'version'        => SMR_VERSION,
				'last_error'     => $last_error,
			)
		);
	}

	/**
	 * Recursively attach a `redirects` array to each tree node that has matching redirects.
	 */
	protected static function annotate( &$node, $by_path ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		$path                = isset( $node['path'] ) ? $node['path'] : '/';
		$node['redirects']    = isset( $by_path[ $path ] ) ? $by_path[ $path ] : array();
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as &$child ) {
				self::annotate( $child, $by_path );
			}
			unset( $child );
		}
	}

	/**
	 * Collect a set of all paths currently in the tree.
	 */
	protected static function collect_paths( $node ) {
		$paths = array();
		if ( ! is_array( $node ) ) {
			return $paths;
		}
		$paths[ isset( $node['path'] ) ? $node['path'] : '/' ] = true;
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				if ( is_array( $child ) ) {
					$paths = $paths + self::collect_paths( $child );
				}
			}
		}
		return $paths;
	}

	/**
	 * Convert a URL to a path relative to home_url().
	 */
	protected static function url_to_path( $url ) {
		return SMR_Indexer::url_to_path( $url );
	}

	/**
	 * Insert a virtual "redirect source" node at $path, creating intermediate
	 * container nodes as needed.
	 */
	protected static function inject_node( &$tree, $path, $redirect ) {
		if ( ! is_array( $tree ) || '/' === $path || '' === $path ) {
			return false;
		}
		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		if ( empty( $segments ) ) {
			return false;
		}
		$parent   = &$tree;
		$cur_path = '';
		$created  = false;
		$max      = count( $segments );
		for ( $i = 0; $i < $max; $i++ ) {
			$seg      = $segments[ $i ];
			$cur_path .= '/' . $seg;
			$child_idx = null;
			if ( ! empty( $parent['children'] ) && is_array( $parent['children'] ) ) {
				foreach ( $parent['children'] as $idx => $child ) {
					if ( isset( $child['path'] ) && $child['path'] === $cur_path ) {
						$child_idx = $idx;
						break;
					}
				}
			}
			if ( null === $child_idx ) {
				// Create intermediate container, or leaf at final segment.
				$is_leaf  = ( $i === ( count( $segments ) - 1 ) );
				$new_node = array(
					'name'     => $seg,
					'path'     => $cur_path,
					'slug'     => $seg,
					'label'    => $is_leaf ? ( '/ ' . $seg ) : $seg,
					'type'     => $is_leaf ? 'redirect_source' : 'container',
					'url'      => $is_leaf ? home_url( $cur_path ) : '',
					'id'       => 0,
					'editable' => false,
					'children' => array(),
				);
				if ( ! isset( $parent['children'] ) || ! is_array( $parent['children'] ) ) {
					$parent['children'] = array();
				}
				$parent['children'][] = $new_node;
				$child_idx             = count( $parent['children'] ) - 1;
				$created               = true;
			}
			$parent = &$parent['children'][ $child_idx ];
		}
		return $created;
	}
}