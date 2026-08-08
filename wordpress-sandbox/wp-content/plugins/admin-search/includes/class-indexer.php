<?php
/**
 * AS_Indexer — collects the four data sources (settings pages, admin users,
 * WooCommerce products, posts/pages) into a single normalized array of records
 * persisted in the as_index_v1 option.
 *
 * Record shape:
 *   { id, type, title, snippet, url, breadcrumb, payload }
 *
 * @package AdminSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_Indexer {

	const OPTION_INDEX    = AS_OPTION_INDEX;
	const OPTION_STATS    = AS_OPTION_STATS;
	const OPTION_QUERIES  = AS_OPTION_QUERIES;
	const OPTION_FIXTURE  = AS_OPTION_FIXTURE_QUERY;

	const STALE_DRIFT_PCT = 5;     // rebuild if drift > 5%.
	const STALE_MAX_AGE   = 6 * HOUR_IN_SECONDS;

	/**
	 * Source counts are cheap to recompute, so cap the in-flight rebuild
	 * rate. Posts_per_page caps follow the IA-47 plan: 200 for posts, -1 for
	 * users/products (cheap).
	 */
	const CAP_POSTS = 200;

	public static function init() {
		// Hooks intentionally minimal at scaffold stage.
	}

	/**
	 * Rebuild all four sources, deduplicate by id, persist, and write stats.
	 *
	 * @return array { records, counts, total }.
	 */
	public static function rebuild() {
		$records   = array();
		$records   = array_merge( $records, self::index_settings_pages() );
		$records   = array_merge( $records, self::index_users() );
		$records   = array_merge( $records, self::index_products() );
		$records   = array_merge( $records, self::index_content() );

		// Deduplicate by id, keep first occurrence.
		$seen  = array();
		$clean = array();
		foreach ( $records as $r ) {
			if ( isset( $seen[ $r['id'] ] ) ) {
				continue;
			}
			$seen[ $r['id'] ] = true;
			$clean[]         = $r;
		}

		// Record types use singular ('user', 'product', 'content', 'settings')
		// while the counts dictionary exposes plural 'users' / 'products' for
		// REST consumers. Map once before counting so the two stay in sync.
		$type_to_count = array(
			'settings' => 'settings',
			'user'     => 'users',
			'product'  => 'products',
			'content'  => 'content',
		);

		$counts = array(
			'settings' => 0,
			'users'    => 0,
			'products' => 0,
			'content'  => 0,
		);
		foreach ( $clean as $r ) {
			$type = isset( $r['type'] ) ? $r['type'] : '';
			if ( isset( $type_to_count[ $type ] ) ) {
				$counts_key = $type_to_count[ $type ];
				$counts[ $counts_key ]++;
			}
		}

		$payload = array(
			'records' => $clean,
			'total'   => count( $clean ),
			'counts'  => $counts,
		);

		update_option( self::OPTION_INDEX, $payload, false );
		update_option(
			self::OPTION_STATS,
			array(
				'last_built_at' => current_time( 'mysql' ),
				'counts'        => $counts,
				'total'         => count( $clean ),
			),
			false
		);

		/**
		 * Fires after the admin-search index is rebuilt.
		 *
		 * @param array $payload {records, counts, total}.
		 */
		do_action( 'admin_search_index_rebuilt', $payload );

		return $payload;
	}

	/**
	 * Whether the persisted index is stale.
	 *
	 * Drift > 5% between stored counts and a fresh cheap query, OR the index is
	 * older than 6 hours.
	 *
	 * @return bool
	 */
	public static function is_stale() {
		$stats = get_option( self::OPTION_STATS, null );
		if ( empty( $stats ) || empty( $stats['last_built_at'] ) ) {
			return true;
		}

		$age = time() - strtotime( $stats['last_built_at'] . ' UTC' );
		if ( $age > self::STALE_MAX_AGE ) {
			return true;
		}

		$stored = isset( $stats['counts'] ) ? $stats['counts'] : array();
		$fresh  = self::cheap_counts();

		foreach ( $fresh as $key => $count ) {
			$prev = isset( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
			if ( 0 === $prev && 0 === $count ) {
				continue;
			}
			if ( 0 === $prev ) {
				return true;
			}
			$drift = abs( $count - $prev ) / max( 1, $prev ) * 100;
			if ( $drift > self::STALE_DRIFT_PCT ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cheap counts for the staleness check. Doesn't load full records.
	 *
	 * @return array { settings, users, products, content }.
	 */
	protected static function cheap_counts() {
		$counts = array(
			'settings' => self::count_admin_menu_slugs(),
			'users'    => count(
				get_users(
					array(
						'role__in' => array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' ),
						'fields'   => 'ID',
					)
				)
			),
			'products' => 0,
			'content'  => 0,
		);

		// Posts/pages count from wp_count_posts.
		$post_counts = wp_count_posts( 'post' );
		if ( isset( $post_counts->publish ) ) {
			$counts['content'] += (int) $post_counts->publish;
		}
		$page_counts = wp_count_posts( 'page' );
		if ( isset( $page_counts->publish ) ) {
			$counts['content'] += (int) $page_counts->publish;
		}

		// WC products if available.
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ) ) {
			$counts['products'] = count(
				wc_get_products(
					array(
						'status'  => 'publish',
						'limit'   => -1,
						'return'  => 'ids',
						'orderby' => 'ID',
						'order'   => 'ASC',
					)
				)
			);
		}

		return $counts;
	}

	/**
	 * Approximate count of distinct admin menu slugs registered. Cheap because
	 * it reads the already-populated $menu / $submenu globals.
	 *
	 * @return int
	 */
	protected static function count_admin_menu_slugs() {
		global $menu, $submenu;
		$slugs = array();
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! empty( $item[2] ) ) {
					$slugs[ $item[2] ] = true;
				}
			}
		}
		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $children ) {
				if ( is_array( $children ) ) {
					foreach ( $children as $item ) {
						if ( ! empty( $item[2] ) ) {
							$slugs[ $item[2] ] = true;
						}
					}
				}
			}
		}
		return count( $slugs );
	}

	/**
	 * Index settings pages: walk $menu and $submenu and emit a record per
	 * distinct slug that points at an admin page.
	 *
	 * @return array List of records.
	 */
	public static function index_settings_pages() {
		global $menu, $submenu;

		$records = array();
		$slugs   = array();

		// $menu / $submenu are populated by the admin_menu action. Outside a
		// real admin page load (WP-CLI, activation hook, REST request) those
		// globals are empty, so dispatch the action here before walking them.
		if ( ! is_array( $menu ) || empty( $menu ) ) {
			do_action( 'admin_menu' );
		}

		// Walk $menu first to capture top-level entries.
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( empty( $item[2] ) ) {
					continue;
				}
				$slug = $item[2];
				if ( isset( $slugs[ $slug ] ) ) {
					continue;
				}
				$slugs[ $slug ] = true;

				$title = self::strip_html( isset( $item[0] ) ? $item[0] : $slug );
				$cap   = isset( $item[1] ) ? $item[1] : '';
				$url   = self::menu_url( $slug );

				$records[] = array(
					'id'         => 'settings-' . md5( $slug ),
					'type'       => 'settings',
					'title'      => $title,
					'snippet'    => self::capability_label( $cap ),
					'url'        => $url,
					'breadcrumb' => 'Settings',
					'payload'    => array(
						'capability' => $cap,
						'slug'       => $slug,
					),
				);
			}
		}

		// Walk $submenu to capture sub-pages.
		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent_slug => $children ) {
				if ( ! is_array( $children ) ) {
					continue;
				}
				$parent_label = self::parent_menu_label( $parent_slug );
				foreach ( $children as $item ) {
					if ( empty( $item[2] ) ) {
						continue;
					}
					$slug = $item[2];
					if ( isset( $slugs[ $slug ] ) ) {
						continue;
					}
					$slugs[ $slug ] = true;

					$title = self::strip_html( isset( $item[0] ) ? $item[0] : $slug );
					$cap   = isset( $item[1] ) ? $item[1] : '';
					$url   = self::menu_url( $slug );

					$records[] = array(
						'id'         => 'settings-' . md5( $slug ),
						'type'       => 'settings',
						'title'      => $title,
						'snippet'    => self::capability_label( $cap ),
						'url'        => $url,
						'breadcrumb' => 'Settings › ' . $parent_label,
						'payload'    => array(
							'capability' => $cap,
							'slug'       => $slug,
							'parent'     => $parent_slug,
						),
					);
				}
			}
		}

		return $records;
	}

	/**
	 * Resolve a menu slug to an admin URL.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	protected static function menu_url( $slug ) {
		// Strip any leading query args to detect a path.
		if ( false !== strpos( $slug, '?' ) ) {
			$slug = substr( $slug, 0, strpos( $slug, '?' ) );
		}
		if ( false !== strpos( $slug, '.php' ) ) {
			return admin_url( $slug );
		}
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Look up the parent menu label by slug.
	 *
	 * @param string $parent_slug Parent slug.
	 * @return string
	 */
	protected static function parent_menu_label( $parent_slug ) {
		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === $parent_slug && ! empty( $item[0] ) ) {
					return self::strip_html( $item[0] );
				}
			}
		}
		return ucwords( str_replace( array( '-', '_', '.' ), ' ', $parent_slug ) );
	}

	/**
	 * Index admin users.
	 *
	 * @return array List of records.
	 */
	public static function index_users() {
		$records = array();
		$users   = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' ),
			)
		);
		foreach ( $users as $user ) {
			$role  = self::primary_role( $user );
			$email = isset( $user->user_email ) ? $user->user_email : '';
			$login = isset( $user->user_login ) ? $user->user_login : '';
			$records[] = array(
				'id'         => 'user-' . (int) $user->ID,
				'type'       => 'user',
				'title'      => self::strip_html( $user->display_name ),
				'snippet'    => trim( $login . ' · ' . $email ),
				'url'        => admin_url( 'user-edit.php?user_id=' . (int) $user->ID ),
				'breadcrumb' => 'Users › ' . self::role_label( $role ),
				'payload'    => array(
					'email' => $email,
					'roles' => $role,
				),
			);
		}
		return $records;
	}

	/**
	 * Pick the user's primary role.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	protected static function primary_role( $user ) {
		if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
			return reset( $user->roles );
		}
		return '';
	}

	/**
	 * Map role slug to a readable label.
	 *
	 * @param string $role Role slug.
	 * @return string
	 */
	protected static function role_label( $role ) {
		$labels = array(
			'administrator' => 'Administrator',
			'editor'        => 'Editor',
			'author'        => 'Author',
			'contributor'   => 'Contributor',
			'shop_manager'  => 'Shop Manager',
		);
		return isset( $labels[ $role ] ) ? $labels[ $role ] : ucwords( str_replace( '_', ' ', $role ) );
	}

	/**
	 * Index WooCommerce products when WC is active.
	 *
	 * @return array List of records.
	 */
	public static function index_products() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
			// WC not active: log once per rebuild and return empty.
			error_log( 'admin-search: WooCommerce not active, products source empty' );
			return array();
		}

		$records = array();
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		foreach ( $products as $product ) {
			$sku = $product->get_sku();
			$price = $product->get_price();
			$type = $product->get_type();
			$snippet = $product->get_short_description();
			if ( empty( $snippet ) ) {
				$snippet = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 30 );
			}
			$records[] = array(
				'id'         => 'product-' . (int) $product->get_id(),
				'type'       => 'product',
				'title'      => self::strip_html( $product->get_name() ),
				'snippet'    => self::strip_html( $snippet ),
				'url'        => admin_url( 'post.php?post=' . (int) $product->get_id() . '&action=edit' ),
				'breadcrumb' => 'WooCommerce › Products',
				'payload'    => array(
					'sku'   => (string) $sku,
					'price' => (string) $price,
					'type'  => (string) $type,
				),
			);
		}
		return $records;
	}

	/**
	 * Index posts and pages. Capped at CAP_POSTS records per type to bound
	 * memory; production rollouts must re-evaluate posts_per_page => -1.
	 *
	 * NOTE: posts_per_page is intentionally capped at AS_Indexer::CAP_POSTS for
	 * MVP. The full plan flags this as a known constraint that must be
	 * re-evaluated before any production rollout.
	 *
	 * @return array List of records.
	 */
	public static function index_content() {
		$records = array();
		$posts   = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => self::CAP_POSTS,
			)
		);
		foreach ( $posts as $post ) {
			$excerpt = $post->post_excerpt;
			if ( empty( $excerpt ) ) {
				$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
			}
			$records[] = array(
				'id'         => 'content-' . (int) $post->ID,
				'type'       => 'content',
				'title'      => self::strip_html( get_the_title( $post ) ),
				'snippet'    => self::strip_html( $excerpt ),
				'url'        => admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' ),
				'breadcrumb' => 'Posts › ' . $post->post_type,
				'payload'    => array(
					'post_type' => (string) $post->post_type,
					'author'    => isset( $post->post_author ) ? (int) $post->post_author : 0,
				),
			);
		}
		return $records;
	}

	/**
	 * Get the cached index, rebuilding on demand if missing or stale.
	 *
	 * @param bool $force Force rebuild.
	 * @return array {records, counts, total}.
	 */
	public static function get_index( $force = false ) {
		if ( $force || self::is_stale() ) {
			return self::rebuild();
		}
		$payload = get_option( self::OPTION_INDEX, null );
		if ( empty( $payload ) || ! isset( $payload['records'] ) ) {
			return self::rebuild();
		}
		return $payload;
	}

	/**
	 * Get the persisted stats.
	 *
	 * @return array
	 */
	public static function get_stats() {
		return get_option(
			self::OPTION_STATS,
			array(
				'last_built_at' => '',
				'counts'        => array(
					'settings' => 0,
					'users'    => 0,
					'products' => 0,
					'content'  => 0,
				),
				'total'         => 0,
			)
		);
	}

	/**
	 * Record a recent query for diagnostics. Capped at 50 entries.
	 *
	 * @param string $q           Query string.
	 * @param int    $result_count Result count.
	 * @param int    $took_ms      Took ms.
	 */
	public static function record_query( $q, $result_count, $took_ms ) {
		$history = get_option( self::OPTION_QUERIES, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		array_unshift(
			$history,
			array(
				'ts'           => current_time( 'mysql' ),
				'q'            => $q,
				'result_count' => (int) $result_count,
				'took_ms'      => (int) $took_ms,
			)
		);
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, 0, 50 );
		}
		update_option( self::OPTION_QUERIES, $history, false );
	}

	/**
	 * Map a capability slug to a friendly label.
	 *
	 * @param string $cap Capability slug.
	 * @return string
	 */
	protected static function capability_label( $cap ) {
		$cap = (string) $cap;
		if ( '' === $cap ) {
			return '';
		}
		// Strip the role-prefix to get a more readable label.
		$clean = strtolower( str_replace( '_', ' ', $cap ) );
		return trim( $clean );
	}

	/**
	 * Strip HTML tags and trim whitespace. Used for everything that lands in
	 * title/snippet/payload text to keep payloads text-only.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected static function strip_html( $text ) {
		if ( is_array( $text ) || is_object( $text ) ) {
			return '';
		}
		$text = (string) $text;
		$text = wp_strip_all_tags( $text, true );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * CLI handler.
	 */
	public static function cli_rebuild() {
		$payload = self::rebuild();
		$counts  = $payload['counts'];
		$total   = $payload['total'];
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::success(
				sprintf(
					'Re-indexed admin search: total=%d settings=%d users=%d products=%d content=%d',
					$total,
					$counts['settings'],
					$counts['users'],
					$counts['products'],
					$counts['content']
				)
			);
		}
		return $payload;
	}
}