<?php
/**
 * Menus Indexer
 *
 * Searches the WordPress admin menu tree.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches the admin menu tree.
 *
 * The admin menu is only available inside the wp-admin bootstrap, so this
 * indexer builds a cached snapshot on admin_init and searches that cache at
 * runtime.
 *
 * @since 1.0.0
 */
class Menus_Indexer extends Indexer {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'menus';

	/**
	 * Transient key used to cache the menus index.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const INDEX_TRANSIENT_KEY = 'wp_search_menus_index';

	/**
	 * Default index time-to-live in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_TTL = 86400;

	/**
	 * Maximum number of results to return.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RESULTS_LIMIT = 20;

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_build_index' ) );
	}

	/**
	 * Build the index automatically if it is missing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_build_index(): void {
		$index = get_transient( self::INDEX_TRANSIENT_KEY );
		if ( ! is_array( $index ) || empty( $index ) ) {
			$this->reindex();
		}
	}

	/**
	 * Return the source label for these results.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_source(): string {
		return self::SOURCE;
	}

	/**
	 * Build and cache the menus index.
	 *
	 * This must run after the admin menu globals are populated (admin_init).
	 *
	 * @since 1.0.0
	 * @return int Number of indexed records.
	 */
	public function reindex(): int {
		global $menu, $submenu;

		$index = $this->collect( $menu, $submenu );

		set_transient(
			self::INDEX_TRANSIENT_KEY,
			$index,
			absint( apply_filters( 'wp_search_menus_index_ttl', self::DEFAULT_TTL ) )
		);

		return count( $index );
	}

	/**
	 * Retrieve the cached index or rebuild it.
	 *
	 * @since 1.0.0
	 * @return array<mixed> List of indexed menu records.
	 */
	public function get_index(): array {
		$index = get_transient( self::INDEX_TRANSIENT_KEY );

		if ( ! is_array( $index ) || empty( $index ) ) {
			$index = array();
			$this->reindex();
			$index = get_transient( self::INDEX_TRANSIENT_KEY );
		}

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Search cached admin menu items by title.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$index = $this->get_index();
		if ( empty( $index ) ) {
			return array();
		}

		$query   = $this->normalize_query( $query );
		$results = array();

		foreach ( $index as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$title = $record['menu_title'] ?? '';

			if ( '' !== $query && false === strpos( $this->normalize_query( $title ), $query ) ) {
				continue;
			}

			$results[] = $this->normalize_record( $record );

			if ( count( $results ) >= self::RESULTS_LIMIT ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Collect menu and submenu records.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $menu    Global admin menu.
	 * @param array<mixed> $submenu Global admin submenu.
	 * @return array<mixed>
	 */
	private function collect( array $menu, array $submenu ): array {
		$records = array();

		foreach ( $menu as $item ) {
			$slug = $item[2] ?? '';
			if ( empty( $slug ) || 0 === strpos( $slug, 'separator' ) ) {
				continue;
			}

			$records[] = array(
				'title'       => $this->normalize_menu_title( $item[0] ?? '' ),
				'menu_title'  => $this->normalize_menu_title( $item[0] ?? '' ),
				'page_slug'   => $slug,
				'url'         => $this->resolve_menu_url( $slug ),
				'parent_menu' => '',
				'type'        => 'menu',
			);
		}

		foreach ( $submenu as $parent => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				$slug = $item[2] ?? '';
				if ( empty( $slug ) ) {
					continue;
				}

				$records[] = array(
					'title'       => $this->normalize_menu_title( $item[0] ?? '' ),
					'menu_title'  => $this->normalize_menu_title( $item[0] ?? '' ),
					'page_slug'   => $slug,
					'url'         => $this->resolve_submenu_url( $parent, $slug ),
					'parent_menu' => $parent,
					'type'        => 'submenu',
				);
			}
		}

		return $records;
	}

	/**
	 * Strip markup and trailing counts from a raw menu title.
	 *
	 * @since 1.0.0
	 * @param string $title Raw menu title.
	 * @return string
	 */
	private function normalize_menu_title( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = preg_replace( '/\s*\d+\s*$/', '', $title );
		return trim( $title );
	}

	/**
	 * Resolve a top-level menu URL.
	 *
	 * @since 1.0.0
	 * @param string $slug Menu slug.
	 * @return string
	 */
	private function resolve_menu_url( string $slug ): string {
		if ( false !== strpos( $slug, '.php' ) ) {
			return admin_url( $slug );
		}

		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
	}

	/**
	 * Resolve a submenu URL.
	 *
	 * @since 1.0.0
	 * @param string $parent_slug Parent menu slug.
	 * @param string $slug        Submenu slug.
	 * @return string
	 */
	private function resolve_submenu_url( string $parent_slug, string $slug ): string {
		if ( false !== strpos( $slug, '.php' ) ) {
			return admin_url( $slug );
		}

		$base_url = $this->resolve_menu_url( $parent_slug );

		if ( false !== strpos( $base_url, '?' ) ) {
			return $base_url . '&page=' . rawurlencode( $slug );
		}

		return admin_url( $parent_slug . '?page=' . rawurlencode( $slug ) );
	}
}
