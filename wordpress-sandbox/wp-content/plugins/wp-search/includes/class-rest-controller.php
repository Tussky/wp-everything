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
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_items' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'q' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_query' ),
					),
				),
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
	 * Search across settings, content, and products.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_items( \WP_REST_Request $request ) {
		$query   = sanitize_text_field( $request->get_param( 'q' ) );
		$results = array();

		foreach ( $this->get_indexers() as $indexer ) {
			if ( ! $indexer instanceof Indexer ) {
				continue;
			}
			$results = array_merge( $results, $indexer->search( $query ) );
		}

		return rest_ensure_response(
			array(
				'query'   => $query,
				'count'   => count( $results ),
				'results' => $results,
			)
		);
	}

	/**
	 * Return the indexers used by the search endpoint.
	 *
	 * @since 1.0.0
	 * @return array<Indexer>
	 */
	private function get_indexers(): array {
		return array(
			new Settings_Indexer(),
			new Posts_Indexer(),
			new Products_Indexer(),
		);
	}
}
