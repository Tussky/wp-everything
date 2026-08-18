<?php
/**
 * Settings Indexer
 *
 * Builds a deterministic, admin-context-independent index of WordPress core
 * settings pages and their representative field snippets. The index is cached
 * in a transient and emitted as spotlight records.
 *
 * Plugin settings pages beyond the six core options pages are intentionally
 * deferred to a follow-up so this indexer stays deterministic.
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
	 * Maximum number of settings records surfaced in the spotlight response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RECORDS_LIMIT = 50;

	/**
	 * Maximum snippet length in bytes.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_SNIPPET_LENGTH = 1200;

	/**
	 * Stored record keys that are searchable by the legacy search() method.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	private array $search_fields = array( 'fieldId', 'fieldLabel', 'sectionTitle', 'pageTitle', 'snippetText' );

	/**
	 * Core WordPress options pages and their representative fields.
	 *
	 * Keeping this list explicit makes the index deterministic: the same fields
	 * are produced from WP-CLI, REST, and admin requests regardless of which
	 * admin page is currently rendering.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $core_settings_map = array(
		'options-general.php'    => array(
			'pageTitle'    => 'General',
			'sectionId'    => 'default',
			'sectionTitle' => 'General Settings',
			'fields'       => array(
				'blogname'              => array(
					'label'       => 'Site Title',
					'description' => 'In a few words, explain what this site is about.',
					'type'        => 'text',
					'class'       => 'regular-text',
				),
				'blogdescription'       => array(
					'label'       => 'Tagline',
					'description' => 'In a few words, explain what this site is about.',
					'type'        => 'text',
					'class'       => 'regular-text',
				),
				'admin_email'           => array(
					'label'       => 'Administration Email Address',
					'description' => 'This address is used for admin purposes.',
					'type'        => 'email',
					'class'       => 'regular-text',
				),
				'users_can_register'      => array(
					'label'       => 'Anyone can register',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
				'timezone_string'         => array(
					'label'       => 'Timezone',
					'description' => 'Choose a city in the same timezone as you.',
					'type'        => 'select',
					'class'       => '',
				),
			),
		),
		'options-writing.php'    => array(
			'pageTitle'    => 'Writing',
			'sectionId'    => 'default',
			'sectionTitle' => 'Writing Settings',
			'fields'       => array(
				'default_post_format' => array(
					'label'       => 'Default Post Format',
					'description' => '',
					'type'        => 'select',
					'class'       => '',
				),
				'default_editor'      => array(
					'label'       => 'Default Editor',
					'description' => '',
					'type'        => 'select',
					'class'       => '',
				),
			),
		),
		'options-reading.php'    => array(
			'pageTitle'    => 'Reading',
			'sectionId'    => 'default',
			'sectionTitle' => 'Reading Settings',
			'fields'       => array(
				'show_on_front'  => array(
					'label'       => 'Your homepage displays',
					'description' => '',
					'type'        => 'radio',
					'class'       => '',
				),
				'posts_per_page' => array(
					'label'       => 'Blog pages show at most',
					'description' => 'posts',
					'type'        => 'number',
					'class'       => 'small-text',
				),
				'posts_per_rss'  => array(
					'label'       => 'Syndication feeds show the most recent',
					'description' => 'items',
					'type'        => 'number',
					'class'       => 'small-text',
				),
			),
		),
		'options-discussion.php' => array(
			'pageTitle'    => 'Discussion',
			'sectionId'    => 'default',
			'sectionTitle' => 'Discussion Settings',
			'fields'       => array(
				'default_pingback_flag'  => array(
					'label'       => 'Attempt to notify any blogs linked to from the post',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
				'default_comment_status' => array(
					'label'       => 'Allow people to submit comments on new posts',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
				'require_name_email'     => array(
					'label'       => 'Comment author must fill out name and email',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
				'comment_moderation'     => array(
					'label'       => 'Comment must be manually approved before it appears',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
			),
		),
		'options-media.php'      => array(
			'pageTitle'    => 'Media',
			'sectionId'    => 'default',
			'sectionTitle' => 'Media Settings',
			'fields'       => array(
				'thumbnail_size_w' => array(
					'label'       => 'Thumbnail width',
					'description' => 'pixels',
					'type'        => 'number',
					'class'       => 'small-text',
				),
				'thumbnail_size_h' => array(
					'label'       => 'Thumbnail height',
					'description' => 'pixels',
					'type'        => 'number',
					'class'       => 'small-text',
				),
				'thumbnail_crop'   => array(
					'label'       => 'Crop thumbnail to exact dimensions',
					'description' => '',
					'type'        => 'checkbox',
					'class'       => '',
				),
			),
		),
		'options-permalink.php'  => array(
			'pageTitle'    => 'Permalinks',
			'sectionId'    => 'default',
			'sectionTitle' => 'Permalink Settings',
			'fields'       => array(
				'permalink_structure' => array(
					'label'       => 'Custom Structure',
					'description' => 'Enter a custom structure for your permalink URLs above.',
					'type'        => 'text',
					'class'       => 'regular-text',
				),
				'category_base'       => array(
					'label'       => 'Category base',
					'description' => '',
					'type'        => 'text',
					'class'       => 'regular-text',
				),
			),
		),
	);

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
		$index = $this->build_core_settings_index();

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

		$index = $this->get_index();
		if ( ! is_array( $index ) ) {
			return array();
		}

		$records = array();
		$counter = 0;

		foreach ( $index as $record ) {
			if ( $counter >= self::RECORDS_LIMIT ) {
				break;
			}

			if ( ! is_array( $record ) ) {
				continue;
			}

			$counter++;

			$source      = (string) ( $record['source'] ?? __( 'WordPress Core', 'wp-search' ) );
			$source_kind = (string) ( $record['sourceKind'] ?? 'core' );
			$breadcrumb  = is_array( $record['breadcrumb'] ?? null ) ? $record['breadcrumb'] : array( __( 'Settings', 'wp-search' ), (string) ( $record['pageTitle'] ?? '' ) );
			$language    = (string) ( $record['language'] ?? 'html' );
			$snippet      = (string) ( $record['snippet'] ?? '' );
			$snippet_text = wp_strip_all_tags( $snippet );
			$url          = (string) ( $record['url'] ?? '' );

			$terms = array_filter(
				array_unique(
					array_merge(
						array( $source ),
						$breadcrumb,
						array( $snippet_text ),
						array_filter(
							array(
								(string) ( $record['pageTitle'] ?? '' ),
								(string) ( $record['fieldId'] ?? '' ),
								(string) ( $record['fieldLabel'] ?? '' ),
							)
						)
					)
				)
			);

			$records[] = array(
				'id'      => 's-' . $counter,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_values( array_map( 'strval', $terms ) ),
					'weight' => (int) ( $record['weight'] ?? 70 ),
				),
				'display' => array(
					'source'     => $source,
					'sourceKind' => $source_kind,
					'breadcrumb' => $breadcrumb,
					'language'   => $language,
					'snippet'    => $snippet,
					'url'        => $url,
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

			$haystack = implode(
				' ',
				array(
					(string) ( $record['fieldId'] ?? '' ),
					(string) ( $record['fieldLabel'] ?? '' ),
					(string) ( $record['sectionTitle'] ?? '' ),
					(string) ( $record['pageTitle'] ?? '' ),
					(string) ( $record['snippetText'] ?? '' ),
				)
			);

			if ( false !== strpos( $this->normalize_query( $haystack ), $term ) ) {
				$results[] = $this->normalize_record( $record );
			}
		}

		return $results;
	}

	/**
	 * Build the curated, deterministic core settings index.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function build_core_settings_index(): array {
		$records = array();

		foreach ( $this->core_settings_map as $slug => $page ) {
			$page_title    = $page['pageTitle'];
			$section_id    = $page['sectionId'];
			$section_title = $page['sectionTitle'];

			foreach ( $page['fields'] as $field_id => $field ) {
				$snippet     = $this->build_field_snippet( $field_id, $field );
				$snippet_text = wp_strip_all_tags( $snippet );

				$records[] = array(
					'source'       => __( 'WordPress Core', 'wp-search' ),
					'sourceKind'   => 'core',
					'pageSlug'     => $slug,
					'pageTitle'    => $page_title,
					'sectionId'    => $section_id,
					'sectionTitle' => $section_title,
					'fieldId'      => $field_id,
					'fieldLabel'   => $field['label'],
					'snippet'      => $snippet,
					'snippetText'  => $snippet_text,
					'language'     => $this->detect_language( $snippet ),
					'weight'       => 80,
					'url'          => admin_url( $slug ) . ( '' !== $field_id ? '#' . $field_id : '' ),
					'breadcrumb'   => array( __( 'Settings', 'wp-search' ), $page_title ),
				);
			}
		}

		return $records;
	}

	/**
	 * Build a representative HTML snippet for a settings field.
	 *
	 * Uses the live option value where available so the snippet resembles the
	 * rendered admin control without depending on which admin page is loading.
	 *
	 * @since 1.0.0
	 * @param string       $field_id Field identifier.
	 * @param array<mixed> $field    Field definition.
	 * @return string
	 */
	private function build_field_snippet( string $field_id, array $field ): string {
		$label       = $field['label'] ?? '';
		$description = $field['description'] ?? '';
		$type        = $field['type'] ?? 'text';
		$class       = $field['class'] ?? '';
		$value       = get_option( $field_id );
		if ( false === $value ) {
			$value = '';
		}
		$value = (string) $value;

		$input = '';
		switch ( $type ) {
			case 'checkbox':
				$checked = in_array( strtolower( $value ), array( '1', 'yes', 'true', 'on' ), true ) ? ' checked' : '';
				$input   = '<input name="' . esc_attr( $field_id ) . '" type="checkbox" value="1"' . $checked . ' />';
				break;
			case 'number':
				$input = '<input name="' . esc_attr( $field_id ) . '" type="number" step="1" min="0" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
				break;
			case 'select':
				$input = '<select name="' . esc_attr( $field_id ) . '" class="' . esc_attr( $class ) . '">';
				$input .= '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>';
				$input .= '</select>';
				break;
			case 'radio':
				$input = '<label><input type="radio" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $value ) . '" checked /> ' . esc_html( $value ) . '</label>';
				break;
			default:
				$input = '<input name="' . esc_attr( $field_id ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
				break;
		}

		$markup = '';
		if ( '' !== $label ) {
			$markup .= '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>' . "\n";
		}
		$markup .= $input;
		if ( '' !== $description ) {
			$markup .= "\n" . '<p class="description">' . esc_html( $description ) . '</p>';
		}

		$markup = $this->sanitize_snippet( $markup );

		// Ensure we always have a non-empty snippet even with no label/value.
		if ( '' === $markup ) {
			$markup = '<input name="' . esc_attr( $field_id ) . '" type="text" class="regular-text" />';
		}

		return $markup;
	}

	/**
	 * Categorize the dominant language of a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet Snippet text.
	 * @return string
	 */
	private function detect_language( string $snippet ): string {
		$text = (string) preg_replace( '/<[^>]+>/', '', $snippet );

		if ( false !== strpos( $snippet, '<?php' ) || preg_match( '/\b(add_filter|add_action|function\s+[a-zA-Z0-9_]+)\s*\(/', $text ) ) {
			return 'php';
		}

		if ( preg_match( '/[.#]?[a-zA-Z0-9_-]+\s*\{[^}]*:[^}]*\}/', $snippet ) ) {
			return 'css';
		}

		return 'html';
	}

	/**
	 * Clean a captured snippet while keeping it deterministic.
	 *
	 * @since 1.0.0
	 * @param string $snippet Raw markup.
	 * @return string
	 */
	private function sanitize_snippet( string $snippet ): string {
		$snippet = preg_replace( '/\R+/', "\n", $snippet );
		$snippet = preg_replace( '/[ \t]+/', ' ', $snippet );
		$snippet = trim( $snippet );

		if ( strlen( $snippet ) > self::MAX_SNIPPET_LENGTH ) {
			$snippet = substr( $snippet, 0, self::MAX_SNIPPET_LENGTH ) . '…';
		}

		return $snippet;
	}
}
