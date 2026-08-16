<?php
/**
 * Settings Indexer
 *
 * Discovers registered admin settings pages, sections and options and builds
 * a searchable index persisted as a transient.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes WordPress admin settings.
 *
 * @since 1.0.0
 */
class Settings_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'settings';

	/**
	 * Transient key used to cache the settings index.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const INDEX_TRANSIENT_KEY = 'wp_search_settings_index';

	/**
	 * Default index time-to-live in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_TTL = 86400;

	/**
	 * Stored record keys that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	private array $search_fields = array( 'title', 'description', 'keywords', 'type' );

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_schedule_reindex' ) );
		add_action( 'admin_init', array( $this, 'maybe_build_index' ) );
	}

	/**
	 * Schedule a reindex when an admin trigger is received.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_schedule_reindex(): void {
		if ( ! is_admin() ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['wp_search_reindex_nonce'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_search_reindex' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to reindex settings.', 'wp-search' ) );
		}

		$this->reindex();

		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings index rebuilt.', 'wp-search' ) . '</p></div>';
			}
		);
	}

	/**
	 * Build the index automatically if it is missing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_build_index(): void {
		if ( ! is_admin() ) {
			return;
		}

		$index = get_transient( self::INDEX_TRANSIENT_KEY );
		if ( ! is_array( $index ) || empty( $index ) ) {
			$this->reindex();
		}
	}

	/**
	 * Build and cache the settings index.
	 *
	 * @since 1.0.0
	 * @return int Number of indexed records.
	 */
	public function reindex(): int {
		$index = array_merge(
			$this->collect_settings_sections(),
			$this->collect_registered_settings()
		);

		$index = apply_filters( 'wp_search_settings_index', $index );

		set_transient( self::INDEX_TRANSIENT_KEY, $index, absint( apply_filters( 'wp_search_index_ttl', self::DEFAULT_TTL ) ) );

		return count( $index );
	}

	/**
	 * Retrieve the cached index or rebuild it.
	 *
	 * @since 1.0.0
	 * @return array<mixed> List of indexed settings records.
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
	 * Return the source label for these results.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_source(): string {
		return self::SOURCE;
	}

	/**
	 * Return the cached settings index as spotlight records.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$index   = $this->get_index();
		$records = array();
		$counter = 0;

		foreach ( $index as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$counter++;
			$type = $record['type'] ?? 'setting';

			$weight = 60;
			if ( 'menu' === $type ) {
				$weight = 80;
			} elseif ( 'section' === $type ) {
				$weight = 75;
			} elseif ( 'setting' === $type ) {
				$weight = 70;
			}

			$terms = array_values(
				array_filter(
					array_unique(
						array(
							(string) ( $record['title'] ?? '' ),
							(string) ( $record['keywords'] ?? '' ),
							(string) ( $record['type'] ?? '' ),
							(string) ( $record['description'] ?? '' ),
						)
					)
				)
			);

			$records[] = array(
				'id'      => 's-' . $counter,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => $terms,
					'weight' => $weight,
				),
				'display' => array(
					'title'       => (string) ( $record['title'] ?? '' ),
					'description' => (string) ( $record['description'] ?? '' ),
					'type'        => $type,
					'parent'      => (string) ( $record['parent'] ?? '' ),
					'url'         => (string) ( $record['url'] ?? '' ),
				),
			);
		}

		return $records;
	}

	/**
	 * Search the cached settings index.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		$index   = $this->get_index();
		$results = array();
		$term    = $this->normalize_query( $query );

		foreach ( $index as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			if ( '' === $term ) {
				$results[] = $this->normalize_record( $record );
				continue;
			}

			foreach ( $this->search_fields as $field ) {
				$value = isset( $record[ $field ] ) ? (string) $record[ $field ] : '';
				if ( false !== strpos( $this->normalize_query( $value ), $term ) ) {
					$results[] = $this->normalize_record( $record );
					break;
				}
			}
		}

		return $results;
	}

	/**
	 * Collect top-level and submenu admin items.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function collect_menu_items(): array {
		global $menu, $submenu;

		$records = array();

		// Menu globals are only available in admin context
		if ( ! is_array( $menu ) || ! is_array( $submenu ) ) {
			return $records;
		}

		if ( ! empty( $menu ) ) {
			foreach ( $menu as $item ) {
				$slug = $item[2] ?? '';
				if ( empty( $slug ) || strpos( $slug, 'separator' ) === 0 ) {
					continue;
				}

				$records[] = array(
					'title'       => $this->sanitize_menu_title( $item[0] ?? '' ),
					'description' => '',
					'keywords'    => $slug,
					'url'         => $this->resolve_menu_url( $slug ),
					'type'        => 'menu',
					'parent'      => '',
				);
			}
		}

		if ( ! empty( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				foreach ( $items as $item ) {
					$slug = $item[2] ?? '';
					if ( empty( $slug ) ) {
						continue;
					}

					$records[] = array(
						'title'       => $this->sanitize_menu_title( $item[0] ?? '' ),
						'description' => '',
						'keywords'    => $slug,
						'url'         => $this->resolve_submenu_url( $parent, $slug ),
						'type'        => 'submenu',
						'parent'      => $parent,
					);
				}
			}
		}

		return $records;
	}

	/**
	 * Resolve a top-level menu URL.
	 *
	 * @since 1.0.0
	 * @param string $slug Menu slug.
	 * @return string
	 */
	private function resolve_menu_url( string $slug ): string {
		if ( ! is_admin() ) {
			return '';
		}

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
		if ( ! is_admin() ) {
			return '';
		}

		$base_url = $this->resolve_menu_url( $parent_slug );

		if ( false !== strpos( $slug, '.php' ) ) {
			return admin_url( $slug );
		}

		if ( false !== strpos( $base_url, '?' ) ) {
			return $base_url . '&page=' . rawurlencode( $slug );
		}

		return admin_url( $parent_slug . '?page=' . rawurlencode( $slug ) );
	}

	/**
	 * Sanitize a raw menu title stripping markup and counts.
	 *
	 * @since 1.0.0
	 * @param string $title Raw menu title.
	 * @return string
	 */
	private function sanitize_menu_title( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = preg_replace( '/\s*\d+\s*$/', '', $title );
		return trim( $title );
	}

	/**
	 * Collect registered settings sections.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function collect_settings_sections(): array {
		global $wp_settings_sections;

		$records = array();

		if ( empty( $wp_settings_sections ) || ! is_array( $wp_settings_sections ) ) {
			return $records;
		}

		foreach ( $wp_settings_sections as $page => $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}

			foreach ( $sections as $section_id => $section ) {
				$records[] = array(
					'title'       => $this->sanitize_menu_title( $section['title'] ?? '' ),
					'description' => wp_strip_all_tags( $section['description'] ?? '' ),
					'keywords'    => $section_id,
					'url'         => admin_url( $page ),
					'type'        => 'section',
					'parent'      => $page,
				);
			}
		}

		return $records;
	}

	/**
	 * Collect registered settings (options).
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function collect_registered_settings(): array {
		global $wp_registered_settings;

		$records = array();

		if ( empty( $wp_registered_settings ) || ! is_array( $wp_registered_settings ) ) {
			return $records;
		}

		foreach ( $wp_registered_settings as $option_name => $setting ) {
			$args     = isset( $setting['args'] ) && is_array( $setting['args'] ) ? $setting['args'] : array();
			$label    = $args['label'] ?? '';
			$type     = $setting['type'] ?? 'unknown';
			$group    = $setting['group'] ?? '';
			$page     = $this->page_for_settings_group( $group );
			$keywords = implode( ' ', array_filter( array( $option_name, $group, $type ) ) );

			$records[] = array(
				'title'       => $label ? $this->sanitize_menu_title( $label ) : $option_name,
				'description' => '',
				'keywords'    => $keywords,
				'url'         => $page ? admin_url( $page ) : admin_url( 'options-general.php' ),
				'type'        => 'setting',
				'parent'      => $page,
			);
		}

		return $records;
	}

	/**
	 * Discover the options page for a settings group.
	 *
	 * @since 1.0.0
	 * @param string $group Group slug.
	 * @return string
	 */
	private function page_for_settings_group( string $group ): string {
		global $wp_settings_sections;

		if ( empty( $wp_settings_sections ) || ! is_array( $wp_settings_sections ) || empty( $group ) ) {
			return '';
		}

		foreach ( $wp_settings_sections as $page => $sections ) {
			if ( isset( $sections[ $group ] ) ) {
				return $page;
			}
		}

		return '';
	}
}
