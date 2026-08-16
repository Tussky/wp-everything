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
class Users_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'users';

	/**
	 * Maximum number of results to return from the old search callback.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RESULTS_LIMIT = 20;

	/**
	 * Maximum number of users surfaced in the spotlight response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RECORDS_LIMIT = 100;

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
	 * Return every user as a spotlight record.
	 *
	 * Keeps the legacy search() method alive for existing callers; this method
	 * is the one consumed by the spotlight REST endpoint.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'list_users' ) ) {
			return array();
		}

		$args = array(
			'number' => self::RECORDS_LIMIT,
			'fields' => 'all',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		$records = array();
		$index   = 0;

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$index++;

			$role       = $this->primary_role_label( $user );
			$caps       = $this->user_capabilities( $user );
			$last_login = get_user_meta( $user->ID, '_last_login', true );
			$last_login = is_string( $last_login ) ? $last_login : '';
			if ( is_numeric( $last_login ) && (int) $last_login > 0 ) {
				$last_login = gmdate( 'Y-m-d H:i', (int) $last_login );
			}

			$terms = array_filter(
				array_unique(
					array_merge(
						array(
							$user->user_login,
							$user->display_name,
							$user->user_email,
							$role,
						),
						$caps
					)
				)
			);

			$records[] = array(
				'id'      => 'u-' . $index,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_values( array_map( 'strval', $terms ) ),
					'weight' => $this->role_weight( $user ),
				),
				'display' => array(
					'username'     => $user->user_login,
				'displayName'  => $user->display_name,
				'email'        => $user->user_email,
				'role'         => $role,
				'capabilities' => $caps,
				'registered'   => gmdate( 'Y-m-d', strtotime( $user->user_registered ) ),
				'lastLogin'    => $last_login,
				'avatarHue'    => ( (int) $user->ID * 47 ) % 360,
				'url'          => admin_url( 'user-edit.php?user_id=' . intval( $user->ID ) ),
			),
			);
		}

		return $records;
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

	/**
	 * Return the first role slug translated to its human label.
	 *
	 * @since 1.0.0
	 * @param \WP_User $user User object.
	 * @return string
	 */
	private function primary_role_label( \WP_User $user ): string {
		$roles = $user->roles;
		if ( empty( $roles ) ) {
			return '';
		}

		$role_slug = (string) reset( $roles );
		return translate_user_role( wp_roles()->get_names()[ $role_slug ] ?? $role_slug );
	}

	/**
	 * Return all capabilities the user effectively holds.
	 *
	 * @since 1.0.0
	 * @param \WP_User $user User object.
	 * @return array<string>
	 */
	private function user_capabilities( \WP_User $user ): array {
		if ( ! isset( $user->allcaps ) || ! is_array( $user->allcaps ) ) {
			return array();
		}

		$caps = array_keys( array_filter( $user->allcaps ) );
		usort( $caps, 'strnatcasecmp' );
		return $caps;
	}

	/**
	 * Pick a weight per user based on their primary role.
	 *
	 * @since 1.0.0
	 * @param \WP_User $user User object.
	 * @return int
	 */
	private function role_weight( \WP_User $user ): int {
		$roles = $user->roles;
		if ( empty( $roles ) ) {
			return 40;
		}

		$role = (string) reset( $roles );

		switch ( $role ) {
			case 'administrator':
				return 100;
			case 'shop_manager':
				return 90;
			case 'editor':
				return 80;
			case 'author':
				return 60;
			case 'subscriber':
				return 40;
			default:
				return 50;
		}
	}
}
