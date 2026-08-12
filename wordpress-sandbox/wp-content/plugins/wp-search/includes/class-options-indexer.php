<?php
/**
 * Options Indexer
 *
 * Reads the wp_options table directly and publishes selected option families
 * as spotlight records. Protected options (API keys, gateway secrets) are
 * surfaced with masked values and are excluded from search terms.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches WordPress options.
 *
 * @since 1.0.0
 */
class Options_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'options';

	/**
	 * Option families surfaced by this indexer, with weight and explainer.
	 *
	 * Weights mirror spotlight-data.json so important options sort first.
	 *
	 * @since 1.0.0
	 * @var array<string, array{weight: int, explainer: string}>
	 */
	private array $known_options = array(
		'siteurl'                     => array(
			'weight'    => 90,
			'explainer' => 'The WordPress address (URL) where core files live.',
		),
		'home'                        => array(
			'weight'    => 90,
			'explainer' => 'The public address (URL) visitors see.',
		),
		'blogname'                    => array(
			'weight'    => 90,
			'explainer' => 'The site title shown in the header and browser tab.',
		),
		'admin_email'                 => array(
			'weight'    => 80,
			'explainer' => 'Address that receives administrative and system notifications.',
		),
		'active_plugins'              => array(
			'weight'    => 70,
			'explainer' => 'Serialized list of plugin files WordPress loads on every request.',
		),
		'woocommerce_stripe_settings' => array(
			'weight'    => 75,
			'explainer' => 'Stripe gateway config including the live secret API key.',
		),
		'akismet_api_key'             => array(
			'weight'    => 65,
			'explainer' => 'Authenticates the site against Akismet\'s spam-filtering service.',
		),
		'permalink_structure'         => array(
			'weight'    => 70,
			'explainer' => 'Pattern used to build human-readable post and page URLs.',
		),
		'woocommerce_currency'        => array(
			'weight'    => 60,
			'explainer' => 'Default currency used for store prices and orders.',
		),
		'users_can_register'          => array(
			'weight'    => 55,
			'explainer' => 'Whether visitors may create their own accounts.',
		),
		'timezone_string'             => array(
			'weight'    => 55,
			'explainer' => 'Local timezone used for scheduling and timestamps.',
		),
		'template'                    => array(
			'weight'    => 50,
			'explainer' => 'Slug of the active theme\'s parent template.',
		),
		'stylesheet'                  => array(
			'weight'    => 50,
			'explainer' => 'Slug of the active theme stylesheet.',
		),
	);

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
	 * Search options by name, explainer keywords or unprotected value.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( ! current_user_can( 'manage_options' ) || '' === trim( $query ) ) {
			return array();
		}

		$records = $this->get_records();
		if ( empty( $records ) ) {
			return array();
		}

		$term    = $this->normalize_query( $query );
		$results = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['search']['terms'] ) || ! is_array( $record['search']['terms'] ) ) {
				continue;
			}

			foreach ( $record['search']['terms'] as $haystack ) {
				if ( ! is_string( $haystack ) ) {
					continue;
				}

				if ( false !== strpos( $this->normalize_query( $haystack ), $term ) ) {
					$results[] = $record;
					break;
				}
			}
		}

		return $results;
	}

	/**
	 * Build spotlight records for selected options from the real database.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		global $wpdb;

		$option_names = array_keys( $this->known_options );
		$placeholders = implode( ', ', array_fill( 0, count( $option_names ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- live options, trivial row count.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
				...$option_names
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$by_name = array();
		foreach ( $rows as $row ) {
			$by_name[ $row->option_name ] = $row;
		}

		// Stable ID assignment regardless of row order.
		uksort( $by_name, 'strnatcasecmp' );

		$records = array();
		$index   = 0;

		foreach ( $by_name as $name => $row ) {
			$index++;
			$known     = $this->known_options[ $name ] ?? array(
				'weight'    => 40,
				'explainer' => 'WordPress option.',
			);
			$protected = $this->is_protected_option( $name );
			$value     = $protected ? null : (string) $row->option_value;

			$terms = array(
				$name,
			);

			$explainer_terms = array_filter( explode( ' ', $this->sanitize_term_text( $known['explainer'] ) ) );
			foreach ( $explainer_terms as $explainer_term ) {
				$terms[] = $explainer_term;
			}

			if ( ! $protected && null !== $value ) {
				$terms[] = $value;
			}

			$records[] = array(
				'id'      => 'o-' . $index,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_values( array_filter( array_unique( $terms ) ) ),
					'weight' => (int) $known['weight'],
				),
				'display' => array(
					'name'      => $name,
					'value'     => $value,
					'autoload'  => $this->normalize_autoload( $row->autoload ?? 'yes' ),
					'protected' => $protected,
					'explainer' => $known['explainer'],
				),
			);
		}

		return $records;
	}

	/**
	 * Options are queried live; no persistent cache is maintained.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function reindex(): int {
		return 0;
	}

	/**
	 * Decide whether an option value must be masked from search terms.
	 *
	 * @since 1.0.0
	 * @param string $name Option name.
	 * @return bool
	 */
	private function is_protected_option( string $name ): bool {
		$explicit = array(
			'akismet_api_key',
			'woocommerce_stripe_settings',
		);

		if ( in_array( $name, $explicit, true ) ) {
			return true;
		}

		$sensitive = array(
			'api_key',
			'_api_key',
			'secret',
			'token',
			'password',
			'private_key',
			'stripe_settings',
		);

		$lower = $this->normalize_query( $name );
		foreach ( $sensitive as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return true;
			}
		}

		return false;
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
	 * Convert MariaDB's ON/OFF autoload values back to WordPress yes/no convention.
	 *
	 * @since 1.0.0
	 * @param string $autoload Raw autoload value.
	 * @return string
	 */
	private function normalize_autoload( string $autoload ): string {
		$autoload = $this->normalize_query( $autoload );
		if ( 'on' === $autoload ) {
			return 'yes';
		}
		if ( 'off' === $autoload ) {
			return 'no';
		}
		return $autoload;
	}
}
