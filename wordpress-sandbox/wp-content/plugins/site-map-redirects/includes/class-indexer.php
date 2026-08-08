<?php
/**
 * Site indexer: crawls posts, pages, CPTs, taxonomy archives, and front-end
 * URLs into a URL-path tree. Cached in a transient.
 *
 * Error handling contract:
 *   - collect_urls() never throws; per-source failures are skipped, logged,
 *     and the surviving nodes still return. Empty result is valid.
 *   - rebuild() catches Throwable around the whole pipeline and returns a
 *     minimal fallback tree (the root node only) so the rest of the plugin
 *     always has something to render. Failure is recorded as last-error.
 *   - get_tree() always returns an array. If both the transient and a fresh
 *     rebuild fail, it returns a barebones fallback tree.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Indexer {

	const TRANSIENT = SMR_TRANSIENT;
	const TTL        = 12 * HOUR_IN_SECONDS;

	/**
	 * Error code used when the indexer pipeline itself fails.
	 *
	 * @var string
	 */
	const ERR_PIPELINE = 'index_pipeline_failed';

	/**
	 * Error code used when the cache write fails.
	 *
	 * @var string
	 */
	const ERR_CACHE_WRITE = 'index_cache_write_failed';

	/**
	 * Error code used when collect_urls finds nothing.
	 *
	 * @var string
	 */
	const ERR_EMPTY = 'index_empty';

	public static function init() {
		// Allow CLI rebuilds.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'sitemap-redirects reindex', array( __CLASS__, 'cli_rebuild' ) );
		}
	}

	/**
	 * Get the cached tree, rebuilding on demand if missing.
	 *
	 * @param bool $force Rebuild regardless of cache.
	 * @return array Root node tree. Always an array; never WP_Error.
	 */
	public static function get_tree( $force = false ) {
		if ( $force ) {
			return self::rebuild();
		}
		$tree = SMR_Safe::get_transient( self::TRANSIENT );
		if ( false === $tree || ! is_array( $tree ) || empty( $tree['path'] ) ) {
			$tree = self::rebuild();
		}
		if ( ! is_array( $tree ) || empty( $tree['path'] ) ) {
			$tree = self::fallback_tree();
		}
		return $tree;
	}

	/**
	 * Rebuild and store the tree.
	 *
	 * @return array Tree. Always an array; on failure returns the fallback tree.
	 */
	public static function rebuild() {
		try {
			$urls = self::collect_urls();
			$tree = self::build_tree( $urls );

			if ( empty( $tree['children'] ) && empty( $urls ) ) {
				// Nothing to index — record this so the admin UI can show why
				// the tree looks empty.
				SMR_Logger::record_last_error(
					self::ERR_EMPTY,
					__( 'No pages were found while building the site map.', 'site-map-redirects' ),
					array( 'urls' => count( $urls ) )
				);
			} else {
				// Clear any previous error on a successful rebuild.
				SMR_Logger::clear_last_error();
			}

			$stored = SMR_Safe::set_transient( self::TRANSIENT, $tree, self::TTL );
			if ( false === $stored ) {
				SMR_Logger::warning( 'Index transient write failed', array( 'key' => self::TRANSIENT ) );
				SMR_Logger::record_last_error(
					self::ERR_CACHE_WRITE,
					__( 'Could not save the site map cache. The next page load will try again.', 'site-map-redirects' ),
					array( 'key' => self::TRANSIENT )
				);
			}

			try {
				update_option( 'smr_last_index', current_time( 'mysql' ) );
			} catch ( Throwable $e ) {
				// Updating the option is nice-to-have, not critical. Log and
				// continue — the tree itself is already built.
				SMR_Logger::exception( $e, 'update_option smr_last_index failed' );
			}

			/**
			 * Fires after the sitemap-redirects index is rebuilt.
			 *
			 * @param array $tree The rebuilt tree.
			 */
			do_action( 'smr_index_rebuilt', $tree );

			return is_array( $tree ) ? $tree : self::fallback_tree();
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'SMR_Indexer::rebuild failed' );
			SMR_Logger::record_last_error(
				self::ERR_PIPELINE,
				__( 'The site map could not be built. Showing the homepage only until you reindex again.', 'site-map-redirects' ),
				array( 'exception' => get_class( $e ), 'message' => $e->getMessage() )
			);
			return self::fallback_tree();
		}
	}

	/**
	 * Collect every front-end URL the site publishes, with metadata.
	 *
	 * Each source (post type, taxonomy, authors) is guarded so that one
	 * broken source cannot poison the rest of the list. Returns an array
	 * that always contains at least the home node.
	 *
	 * @return array[] List of nodes: {url, path, type, label, id, editable}.
	 */
	public static function collect_urls() {
		$nodes = array();
		$home  = trailingslashit( home_url() );

		// Root.
		$nodes[] = array(
			'url'     => $home,
			'path'    => '/',
			'type'    => 'home',
			'label'   => __( 'Home', 'site-map-redirects' ),
			'id'      => 0,
			'editable' => false,
		);

		$nodes = array_merge( $nodes, self::collect_posts() );
		$nodes = array_merge( $nodes, self::collect_taxonomies() );
		$nodes = array_merge( $nodes, self::collect_authors() );

		// Deduplicate by path (keep first occurrence).
		$seen  = array();
		$clean = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['path'] ) ) {
				continue;
			}
			$path = $node['path'];
			if ( isset( $seen[ $path ] ) ) {
				continue;
			}
			$seen[ $path ]   = true;
			$node['slug']    = trim( $path, '/' ) === '' ? '' : basename( rtrim( $path, '/' ) );
			$clean[]         = $node;
		}

		return $clean;
	}

	/**
	 * Collect nodes from every public post type.
	 *
	 * Per-post failures are skipped; per-post-type failures short-circuit
	 * that type entirely so we don't try to enumerate thousands of broken
	 * posts in a loop.
	 *
	 * @return array[]
	 */
	protected static function collect_posts() {
		$nodes = array();

		$types = SMR_Safe::array(
			function () {
				return get_post_types( array( 'public' => true ), 'objects' );
			},
			'get_post_types failed in SMR_Indexer',
			'index_post_types_failed'
		);

		foreach ( $types as $type ) {
			if ( ! is_object( $type ) || empty( $type->name ) ) {
				continue;
			}
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$type_name = $type->name;

			$posts = SMR_Safe::array(
				function () use ( $type_name ) {
					return get_posts(
						array(
							'post_type'      => $type_name,
							'post_status'    => 'publish',
							'posts_per_page' => 500,
							'orderby'        => 'menu_order title',
							'order'          => 'ASC',
							'no_found_rows'  => true,
						)
					);
				},
				'get_posts failed for post type ' . $type_name,
				'index_posts_failed'
			);

			foreach ( $posts as $post ) {
				try {
					if ( ! is_object( $post ) || empty( $post->ID ) ) {
						continue;
					}
					$permalink = get_permalink( $post );
					if ( ! $permalink || '0' === $permalink ) {
						continue;
					}
					$nodes[] = array(
						'url'      => $permalink,
						'path'     => self::url_to_path( $permalink ),
						'type'     => $type_name,
						'label'    => get_the_title( $post ),
						'id'       => (int) $post->ID,
						'editable' => true,
					);
				} catch ( Throwable $e ) {
					// Single-post failure should not abort the rest of the type.
					SMR_Logger::exception(
						$e,
						'Skipping post during indexing (post type ' . $type_name . ')'
					);
				}
			}
		}

		return $nodes;
	}

	/**
	 * Collect nodes from every public taxonomy.
	 *
	 * @return array[]
	 */
	protected static function collect_taxonomies() {
		$nodes = array();

		$taxos = SMR_Safe::array(
			function () {
				return get_taxonomies( array( 'public' => true ), 'objects' );
			},
			'get_taxonomies failed in SMR_Indexer',
			'index_taxonomies_failed'
		);

		foreach ( $taxos as $tax ) {
			if ( ! is_object( $tax ) || empty( $tax->name ) ) {
				continue;
			}
			$tax_name = $tax->name;

			$terms = SMR_Safe::array(
				function () use ( $tax_name ) {
					$res = get_terms(
						array(
							'taxonomy'   => $tax_name,
							'hide_empty' => false,
							'number'     => 500,
						)
					);
					return is_wp_error( $res ) ? array() : $res;
				},
				'get_terms failed for taxonomy ' . $tax_name,
				'index_terms_failed'
			);

			foreach ( $terms as $term ) {
				try {
					if ( ! is_object( $term ) || empty( $term->term_id ) ) {
						continue;
					}
					$link = get_term_link( $term );
					if ( ! $link || is_wp_error( $link ) ) {
						continue;
					}
					$nodes[] = array(
						'url'      => $link,
						'path'     => self::url_to_path( $link ),
						'type'     => 'taxonomy_' . $tax_name,
						'label'    => $term->name,
						'id'       => (int) $term->term_id,
						'editable' => true,
					);
				} catch ( Throwable $e ) {
					SMR_Logger::exception(
						$e,
						'Skipping term during indexing (taxonomy ' . $tax_name . ')'
					);
				}
			}
		}

		return $nodes;
	}

	/**
	 * Collect author archive nodes.
	 *
	 * @return array[]
	 */
	protected static function collect_authors() {
		$nodes = array();

		$authors = SMR_Safe::array(
			function () {
				$res = get_users( array( 'has_published_posts' => true, 'number' => 100 ) );
				return is_wp_error( $res ) ? array() : $res;
			},
			'get_users failed in SMR_Indexer',
			'index_authors_failed'
		);

		foreach ( $authors as $author ) {
			try {
				if ( ! is_object( $author ) || empty( $author->ID ) ) {
					continue;
				}
				$link = get_author_posts_url( $author->ID, $author->user_nicename );
				if ( ! $link ) {
					continue;
				}
				$nodes[] = array(
					'url'      => $link,
					'path'     => self::url_to_path( $link ),
					'type'     => 'author_archive',
					'label'    => sprintf( __( 'Author: %s', 'site-map-redirects' ), $author->display_name ),
					'id'       => (int) $author->ID,
					'editable' => false,
				);
			} catch ( Throwable $e ) {
				SMR_Logger::exception( $e, 'Skipping author during indexing' );
			}
		}

		return $nodes;
	}

	/**
	 * Convert a URL to a path relative to home_url().
	 *
	 * @param string $url Full URL.
	 * @return string Path beginning with '/'. Root is '/'.
	 */
	public static function url_to_path( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return '/';
		}
		try {
			$home = wp_parse_url( home_url(), PHP_URL_PATH );
			$home = $home ? rtrim( $home, '/' ) : '';

			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( null === $path || false === $path ) {
				return '/';
			}
			if ( $home && 0 === strpos( $path, $home ) ) {
				$path = substr( $path, strlen( $home ) );
			}
			if ( '' === $path ) {
				$path = '/';
			}
			// Normalize trailing slash except for root.
			if ( '/' !== $path ) {
				$path = '/' . trim( $path, '/' );
			}
			return $path;
		} catch ( Throwable $e ) {
			SMR_Logger::exception( $e, 'url_to_path failed for ' . $url );
			return '/';
		}
	}

	/**
	 * Build a nested tree from a flat list of path nodes.
	 *
	 * @param array[] $nodes Flat nodes with 'path'.
	 * @return array Root tree node. Always an array.
	 */
	public static function build_tree( $nodes ) {
		$root = array(
			'name'     => '/',
			'path'     => '/',
			'slug'     => '',
			'label'    => __( 'Home', 'site-map-redirects' ),
			'type'     => 'home',
			'url'      => home_url( '/' ),
			'id'       => 0,
			'editable' => false,
			'children' => array(),
		);

		if ( ! is_array( $nodes ) ) {
			return $root;
		}

		$by_path = array( '/' => &$root );

		// Sort by path so parents come before children.
		usort(
			$nodes,
			function ( $a, $b ) {
				$ap = isset( $a['path'] ) ? $a['path'] : '';
				$bp = isset( $b['path'] ) ? $b['path'] : '';
				return strcmp( $ap, $bp );
			}
		);

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['path'] ) ) {
				continue;
			}
			$path = $node['path'];

			if ( '/' === $path ) {
				// Home already set; enrich it.
				$root['url']      = isset( $node['url'] ) ? $node['url'] : $root['url'];
				$root['label']    = isset( $node['label'] ) ? $node['label'] : $root['label'];
				$root['editable'] = isset( $node['editable'] ) ? (bool) $node['editable'] : $root['editable'];
				$root['id']       = isset( $node['id'] ) ? (int) $node['id'] : $root['id'];
				continue;
			}

			$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
			if ( empty( $segments ) ) {
				continue;
			}
			$cur_path = '';
			$parent   = &$root;
			$max      = count( $segments );
			for ( $i = 0; $i < $max; $i++ ) {
				$seg      = $segments[ $i ];
				$cur_path .= '/' . $seg;
				if ( ! isset( $by_path[ $cur_path ] ) ) {
					// Intermediate container node (no real page here yet).
					$by_path[ $cur_path ] = array(
						'name'     => $seg,
						'path'     => $cur_path,
						'slug'     => $seg,
						'label'    => $seg,
						'type'     => 'container',
						'url'      => '',
						'id'       => 0,
						'editable' => false,
						'children' => array(),
					);
					$parent['children'][] = &$by_path[ $cur_path ];
				}
				$parent = &$by_path[ $cur_path ];
			}

			// Last segment is the real node — overwrite the container with real data.
			if ( isset( $by_path[ $path ] ) ) {
				$leaf             = &$by_path[ $path ];
				$leaf['url']      = isset( $node['url'] ) ? $node['url'] : '';
				$leaf['label']    = isset( $node['label'] ) ? $node['label'] : $seg;
				$leaf['type']     = isset( $node['type'] ) ? $node['type'] : 'page';
				$leaf['id']       = isset( $node['id'] ) ? (int) $node['id'] : 0;
				$leaf['editable'] = isset( $node['editable'] ) ? (bool) $node['editable'] : false;
				unset( $leaf );
			}
		}
		unset( $root );

		return $by_path['/'];
	}

	/**
	 * Return a minimal but valid tree. Used as the last-resort fallback when
	 * the indexer has failed completely.
	 *
	 * @return array
	 */
	public static function fallback_tree() {
		try {
			$home = home_url( '/' );
		} catch ( Throwable $e ) {
			$home = '';
		}
		return array(
			'name'     => '/',
			'path'     => '/',
			'slug'     => '',
			'label'    => __( 'Home', 'site-map-redirects' ),
			'type'     => 'home',
			'url'      => $home,
			'id'       => 0,
			'editable' => false,
			'children' => array(),
		);
	}

	/**
	 * WP-CLI handler.
	 */
	public static function cli_rebuild() {
		$tree  = self::rebuild();
		$count = self::count_nodes( $tree );
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::success( "Re-indexed site: {$count} URLs in tree." );
		}
		return $tree;
	}

	/**
	 * Count nodes in tree (recursive).
	 */
	public static function count_nodes( $node ) {
		$count = 1;
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				if ( is_array( $child ) ) {
					$count += self::count_nodes( $child );
				}
			}
		}
		return $count;
	}
}