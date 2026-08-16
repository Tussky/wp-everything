<?php
/**
 * REST API Controller
 *
 * Registers the search endpoint used by the admin interface.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for searching the settings index.
 *
 * @since 1.0.0
 */
class REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAMESPACE = 'wp-search/v1';

	/**
	 * Route for search requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROUTE = '/search';

	/**
	 * Route for grouped spotlight responses.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SPOTLIGHT_ROUTE = '/spotlight';

	/**
	 * Initialize REST endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		$args = array(
			'methods'             => array( 'GET', 'POST' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'q' => array(
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( $this, 'validate_query' ),
				),
			),
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array_merge(
				$args,
				array( 'callback' => array( $this, 'search_items' ) )
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::SPOTLIGHT_ROUTE,
			array_merge(
				$args,
				array( 'callback' => array( $this, 'get_spotlight_items' ) )
			)
		);
	}

	/**
	 * Validate the query argument.
	 *
	 * @since 1.0.0
	 * @param string           $value   Query value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public function validate_query( string $value, \WP_REST_Request $request, string $param ) {
		if ( 'q' !== $param ) {
			return new \WP_Error(
				'wp_search_invalid_param',
				__( 'Invalid search parameter.', 'wp-search' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $request instanceof \WP_REST_Request ) {
			return new \WP_Error(
				'wp_search_invalid_request',
				__( 'Invalid REST request.', 'wp-search' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $value ) > 200 ) {
			return new \WP_Error(
				'wp_search_query_too_long',
				__( 'Search query must not exceed 200 characters.', 'wp-search' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Ensure only administrators can search settings.
	 *
	 * @since 1.0.0
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'wp_search_bad_nonce',
				__( 'Invalid or expired REST nonce.', 'wp-search' ),
				array( 'status' => 403 )
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'wp_search_forbidden',
			__( 'You do not have permission to search settings.', 'wp-search' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Search across the spotlight facets and return flat results for the admin frontend.
	 *
	 * Each Spotlight_Provider returns its full record set; the Spotlight engine
	 * matches against `search.terms` and ranks by `search.weight`. The response is
	 * then flattened into a `{results, query}` shape consumed by admin.js.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_items( \WP_REST_Request $request ) {
		$query    = sanitize_text_field( $request->get_param( 'q' ) );
		$response = Spotlight::build_response( $this->collect_spotlight_records(), $query );

		return rest_ensure_response(
			array(
				'results' => self::flatten_spotlight_facets( $response, $query ),
				'query'   => $query,
			)
		);
	}

	/**
	 * Flatten a facet-grouped Spotlight response into a flat result list.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $response Spotlight response with _meta + facets.
	 * @param string       $query    Original search query.
	 * @return array<mixed>
	 */
	private static function flatten_spotlight_facets( array $response, string $query ): array {
		$results = array();
		$facets  = Spotlight::FACET_ORDER;

		foreach ( $facets as $facet ) {
			if ( empty( $response['facets'][ $facet ] ) || ! is_array( $response['facets'][ $facet ] ) ) {
				continue;
			}

			foreach ( $response['facets'][ $facet ] as $record ) {
				if ( ! is_array( $record ) || empty( $record['display'] ) ) {
					continue;
				}

				$display = $record['display'];

				$results[] = array(
					'source'      => $facet,
					'url'         => $display['url'] ?? $display['edit_url'] ?? $display['editURL'] ?? '',
					'title'       => $display['title'] ?? $display['displayName'] ?? $display['display_name'] ?? $display['name'] ?? $display['username'] ?? '',
					'description' => $display['description'] ?? $display['desc'] ?? '',
				);
			}
		}

		return $results;
	}

	/**
	 * Return the grouped Spotlight response for the /spotlight route.
	 *
	 * Response shape is `{ _meta, facets: { users, plugins, options, settings } }`.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_spotlight_items( \WP_REST_Request $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		$response = Spotlight::build_response( $this->collect_spotlight_records(), $query );

		return rest_ensure_response( $response );
	}

	/**
	 * Collect spotlight records from every provider that exposes them.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function collect_spotlight_records(): array {
		$records = array();

		foreach ( $this->get_indexers() as $indexer ) {
			if ( ! $indexer instanceof Spotlight_Provider ) {
				continue;
			}
			try {
				$records = array_merge( $records, $indexer->get_records() );
			} catch ( \Throwable $e ) {
				error_log( 'wp-search spotlight provider error: ' . $e->getMessage() );
				continue;
			}
		}

		return $records;
	}

	/**
	 * Return the indexers used by the search endpoint.
	 *
	 * @since 1.0.0
	 * @return array<Indexer>
	 */
	protected function get_indexers(): array {
		$factories = array(
			static fn() => new Settings_Indexer(),
			static fn() => new Users_Indexer(),
			static fn() => new Plugins_Indexer(),
			static fn() => new Options_Indexer(),
			static fn() => new Menus_Indexer(),
			static fn() => new Posts_Indexer(),
			static fn() => new Products_Indexer(),
		);

		$indexers = array();
		foreach ( $factories as $factory ) {
			try {
				$indexers[] = $factory();
			} catch ( \Throwable $e ) {
				error_log( 'wp-search: Failed to instantiate indexer: ' . $e->getMessage() );
			}
		}

		return $indexers;
	}
}
