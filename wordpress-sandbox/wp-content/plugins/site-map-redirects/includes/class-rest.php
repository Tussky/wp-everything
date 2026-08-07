<?php
/**
 * REST routes for SiteMap Redirects.
 *
 * GET /wp-json/sitemap-redirects/v1/tree        -> {tree, redirects, last_index, counts}
 * POST /wp-json/sitemap-redirects/v1/reindex    -> rebuilds the tree, returns fresh payload.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_REST {

	const NS = SMR_REST_NAMESPACE;

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
	 */
	public static function read_perm( WP_REST_Request $request ) {
		if ( apply_filters( 'smr_public_read', false ) ) {
			return true;
		}
		return current_user_can( 'read' );
	}

	public static function manage_perm( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	public static function get_tree( WP_REST_Request $request ) {
		$force = (bool) $request->get_param( 'force' );
		return self::payload( $force );
	}

	public static function reindex( WP_REST_Request $request ) {
		return self::payload( true );
	}

	protected static function payload( $force ) {
		$tree      = SMR_Indexer::get_tree( $force );
		$redirects = SMR_Redirect_Sources::get_all();

		// Map redirects by source path. Virtual "redirect source" URLs that
		// don't correspond to a real page are injected into the tree so the
		// map visualizes them as nodes — this is the whole point of the overlay.
		$by_path = array();
		$to_inject = array();
		$existing_paths = self::collect_paths( $tree );
		foreach ( $redirects as $r ) {
			$sp = $r['source_path'];
			if ( '*' === $sp ) {
				continue; // Global canonical rule — handled in legend.
			}
			$by_path[ $sp ][] = $r;
			// Inject only concrete (non-regex) source paths that don't already exist.
			if ( empty( $r['regex'] ) && ! isset( $existing_paths[ $sp ] ) ) {
				$to_inject[ $sp ] = $r;
			}
		}

		// Inject virtual redirect-source nodes into the tree.
		if ( $to_inject ) {
			// Sort by path so parents precede children.
			$paths = array_keys( $to_inject );
			sort( $paths );
			foreach ( $paths as $path ) {
				self::inject_node( $tree, $path, $to_inject[ $path ] );
			}
		}

		self::annotate( $tree, $by_path );

		return rest_ensure_response(
			array(
				'tree'        => $tree,
				'redirects'   => $redirects,
				'last_index'  => get_option( 'smr_last_index', '' ),
				'home_url'    => home_url( '/' ),
				'counts'      => array(
					'nodes'      => SMR_Indexer::count_nodes( $tree ),
					'redirects'  => count( $redirects ),
				),
				'status_colors' => array(
					'301' => '#d63638', // red — permanent
					'302' => '#dcaa00', // amber — temporary
					'303' => '#2271b1', // blue — see other
					'307' => '#996800', // dark amber — temp keep method
					'308' => '#8c1c1c', // dark red — perm keep method
					'other' => '#50575e',
				),
				'version'     => SMR_VERSION,
			)
		);
	}

	/**
	 * Recursively attach a `redirects` array to each tree node that has matching redirects.
	 */
	protected static function annotate( &$node, $by_path ) {
		$path = isset( $node['path'] ) ? $node['path'] : '/';
		$node['redirects'] = isset( $by_path[ $path ] ) ? $by_path[ $path ] : array();
		if ( ! empty( $node['children'] ) ) {
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
		$paths[ isset( $node['path'] ) ? $node['path'] : '/' ] = true;
		if ( ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$paths = $paths + self::collect_paths( $child );
			}
		}
		return $paths;
	}

	/**
	 * Insert a virtual "redirect source" node at $path, creating intermediate
	 * container nodes as needed. The node is marked type 'redirect_source' and
	 * carries no real URL — it exists so the overlay shows where redirects fire from.
	 */
	protected static function inject_node( &$tree, $path, $redirect ) {
		if ( '/' === $path || '' === $path ) {
			return;
		}
		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		if ( empty( $segments ) ) {
			return;
		}
		$parent     = &$tree;
		$cur_path   = '';
		$created    = false;
		for ( $i = 0; $i < count( $segments ); $i++ ) {
			$seg      = $segments[ $i ];
			$cur_path .= '/' . $seg;
			$child_idx = null;
			if ( ! empty( $parent['children'] ) ) {
				foreach ( $parent['children'] as $idx => $child ) {
					if ( isset( $child['path'] ) && $child['path'] === $cur_path ) {
						$child_idx = $idx;
						break;
					}
				}
			}
			if ( null === $child_idx ) {
				// Create intermediate container, or leaf at final segment.
				$is_leaf = ( $i === ( count( $segments ) - 1 ) );
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
				$parent['children'][] = $new_node;
				$child_idx = count( $parent['children'] ) - 1;
				$created   = true;
			}
			$parent = &$parent['children'][ $child_idx ];
		}
		return $created;
	}
}
