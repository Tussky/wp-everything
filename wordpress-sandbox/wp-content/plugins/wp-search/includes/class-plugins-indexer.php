<?php
/**
 * Plugins Indexer
 *
 * Searches installed plugins by name, description and author.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches installed WordPress plugins.
 *
 * @since 1.0.0
 */
class Plugins_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'plugins';

	/**
	 * Maximum number of results to return.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RESULTS_LIMIT = 20;

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
	 * Search installed plugins by name, description or author.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( ! current_user_can( 'activate_plugins' ) || '' === trim( $query ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$query   = $this->normalize_query( $query );
		$results = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$name        = $plugin_data['Name'] ?? '';
			$description = wp_strip_all_tags( $plugin_data['Description'] ?? '' );
			$author      = wp_strip_all_tags( $plugin_data['Author'] ?? '' );

			if ( ! $this->record_matches( $name, $description, $author, $query ) ) {
				continue;
			}

$results[] = $this->normalize_record(
				array(
					'title'             => $name,
					'name'              => $name,
					'description'       => $description,
					'author'            => $author,
					'status'            => is_plugin_active( $plugin_file ) ? 'active' : 'inactive',
					'url'               => admin_url( 'plugins.php' ),
					'plugins_page_link' => admin_url( 'plugins.php' ),
				)
			);

			if ( count( $results ) >= self::RESULTS_LIMIT ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Return every installed plugin as a spotlight record.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		$records = array();
		$index   = 0;

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$index++;

			$name        = $plugin_data['Name'] ?? '';
			$description = wp_strip_all_tags( $plugin_data['Description'] ?? '' );
			$author      = wp_strip_all_tags( $plugin_data['Author'] ?? '' );
			$active      = is_plugin_active( $plugin_file );

			$terms = array_unique(
				array_filter(
					array(
						$name,
						$author,
					)
				)
			);

			$description_terms = array_filter( explode( ' ', $this->sanitize_term_text( $description ) ) );
			$terms             = array_values( array_unique( array_merge( $terms, $description_terms ) ) );

			$records[] = array(
				'id'      => 'p-' . $index,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_map( 'strval', $terms ),
					'weight' => $active ? 80 : 50,
				),
				'display' => array(
					'name'   => $name,
					'status' => $active ? 'active' : 'inactive',
					'url'    => admin_url( 'plugins.php' ),
				),
			);
		}

		return $records;
	}

	/**
	 * Plugins are queried live; no persistent cache is maintained.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function reindex(): int {
		return 0;
	}

	/**
	 * Make a block of text safe to use as searchable tokens.
	 *
	 * @since 1.0.0
	 * @param string $text Raw text.
	 * @return string
	 */
	private function sanitize_term_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[^a-zA-Z0-9\\-\\/_\\s]/', '', $text );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Check whether a plugin record matches the query.
	 *
	 * @since 1.0.0
	 * @param string $name        Plugin name.
	 * @param string $description Plugin description.
	 * @param string $author      Plugin author.
	 * @param string $query       Lowercase query.
	 * @return bool
	 */
	private function record_matches( string $name, string $description, string $author, string $query ): bool {
		foreach ( array( $name, $description, $author ) as $value ) {
			if ( false !== strpos( $this->normalize_query( $value ), $query ) ) {
				return true;
			}
		}

		return false;
	}
}
