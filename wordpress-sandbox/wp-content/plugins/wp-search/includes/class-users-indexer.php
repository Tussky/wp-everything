<?php
/**
 * Users Indexer
 *
 * Searches WordPress users by login, display name and email.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches WordPress users.
 *
 * @since 1.0.0
 */
class Users_Indexer extends Indexer {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'users';

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
	 * Search users by login, display name or email.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( ! current_user_can( 'list_users' ) || '' === trim( $query ) ) {
			return array();
		}

		$args = array(
			'number'         => self::RESULTS_LIMIT,
			'fields'         => 'all',
			'search'         => '*' . sanitize_text_field( $query ) . '*',
			'search_columns' => array( 'user_login', 'display_name', 'user_email' ),
		);

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		$results = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$avatar_url = get_avatar_url( $user->ID, array( 'size' => 48 ) );

			$results[] = $this->normalize_record(
				array(
					'title'        => $user->display_name,
					'display_name' => $user->display_name,
					'user_login'   => $user->user_login,
					'email'        => $user->user_email,
					'avatar_url'   => is_string( $avatar_url ) ? $avatar_url : '',
					'url'          => admin_url( 'user-edit.php?user_id=' . intval( $user->ID ) ),
					'edit_url'     => admin_url( 'user-edit.php?user_id=' . intval( $user->ID ) ),
				)
			);
		}

		return $results;
	}

	/**
	 * Users are queried live; no persistent cache is maintained.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function reindex(): int {
		return 0;
	}
}
