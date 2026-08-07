<?php
/**
 * Site indexer: crawls posts, pages, CPTs, taxonomy archives, and front-end
 * URLs into a URL-path tree. Cached in a transient.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Indexer {

	const TRANSIENT = SMR_TRANSIENT;
	const TTL        = 12 * HOUR_IN_SECONDS;

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
	 * @return array {root node tree}.
	 */
	public static function get_tree( $force = false ) {
		if ( $force ) {
			return self::rebuild();
		}
		$tree = get_transient( self::TRANSIENT );
		if ( false === $tree ) {
			$tree = self::rebuild();
		}
		return $tree;
	}

	/**
	 * Rebuild and store the tree.
	 *
	 * @return array Tree.
	 */
	public static function rebuild() {
		$urls = self::collect_urls();
		$tree = self::build_tree( $urls );
		set_transient( self::TRANSIENT, $tree, self::TTL );
		update_option( 'smr_last_index', current_time( 'mysql' ) );
		/**
		 * Fires after the sitemap-redirects index is rebuilt.
		 *
		 * @param array $tree The rebuilt tree.
		 */
		do_action( 'smr_index_rebuilt', $tree );
		return $tree;
	}

	/**
	 * Collect every front-end URL the site publishes, with metadata.
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

		// Public post types (posts, pages, CPTs).
		$types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $types as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $type->name,
					'post_status'    => 'publish',
					'posts_per_page' => 500,
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $posts as $post ) {
				$permalink = get_permalink( $post );
				if ( ! $permalink || '0' === $permalink ) {
					continue;
				}
				$nodes[] = array(
					'url'      => $permalink,
					'path'     => self::url_to_path( $permalink ),
					'type'     => $type->name,
					'label'    => get_the_title( $post ),
					'id'       => (int) $post->ID,
					'editable' => true,
				);
			}
		}

		// Public taxonomy term archives.
		$taxos = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxos as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
					'number'     => 500,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$nodes[] = array(
					'url'      => $link,
					'path'     => self::url_to_path( $link ),
					'type'     => 'taxonomy_' . $tax->name,
					'label'    => $term->name,
					'id'       => (int) $term->term_id,
					'editable' => true,
				);
			}
		}

		// Author archives (front-end URL coverage).
		$authors = get_users( array( 'has_published_posts' => true, 'number' => 100 ) );
		foreach ( $authors as $author ) {
			$link = get_author_posts_url( $author->ID, $author->user_nicename );
			if ( $link ) {
				$nodes[] = array(
					'url'      => $link,
					'path'     => self::url_to_path( $link ),
					'type'     => 'author_archive',
					'label'    => sprintf( __( 'Author: %s', 'site-map-redirects' ), $author->display_name ),
					'id'       => (int) $author->ID,
					'editable' => false,
				);
			}
		}

		// Deduplicate by path (keep first occurrence).
		$seen  = array();
		$clean = array();
		foreach ( $nodes as $node ) {
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
	 * Convert a URL to a path relative to home_url().
	 *
	 * @param string $url Full URL.
	 * @return string Path beginning with '/'. Root is '/'.
	 */
	public static function url_to_path( $url ) {
		$home = wp_parse_url( home_url(), PHP_URL_PATH );
		$home = $home ? rtrim( $home, '/' ) : '';

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( null === $path ) {
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
	}

	/**
	 * Build a nested tree from a flat list of path nodes.
	 *
	 * @param array[] $nodes Flat nodes with 'path'.
	 * @return array Root tree node.
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

		$by_path = array( '/' => &$root );

		// Sort by path so parents come before children.
		usort(
			$nodes,
			function ( $a, $b ) {
				return strcmp( $a['path'], $b['path'] );
			}
		);

		foreach ( $nodes as $node ) {
			$path = $node['path'];
			if ( '/' === $path ) {
				// Home already set; enrich it.
				$root['url']      = $node['url'];
				$root['label']    = $node['label'];
				$root['editable'] = $node['editable'];
				$root['id']       = $node['id'];
				continue;
			}

			$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
			$cur_path = '';
			$parent   = &$root;
			for ( $i = 0; $i < count( $segments ); $i++ ) {
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
			$leaf                 = &$by_path[ $path ];
			$leaf['url']          = $node['url'];
			$leaf['label']        = $node['label'];
			$leaf['type']         = $node['type'];
			$leaf['id']           = $node['id'];
			$leaf['editable']     = $node['editable'];
			unset( $leaf );
		}
		unset( $root );

		return $by_path['/'];
	}

	/**
	 * WP-CLI handler.
	 */
	public static function cli_rebuild() {
		$tree = self::rebuild();
		$count = self::count_nodes( $tree );
		if ( function_exists( 'WP_CLI' ) ) {
			\WP_CLI::success( "Re-indexed site: {$count} URLs in tree." );
		}
		return $tree;
	}

	/**
	 * Count nodes in tree (recursive).
	 */
	public static function count_nodes( $node ) {
		$count = 1;
		if ( ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$count += self::count_nodes( $child );
			}
		}
		return $count;
	}
}
