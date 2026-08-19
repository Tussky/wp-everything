<?php
/**
 * Settings Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use WP_Search\Settings_Indexer;

/**
 * Tests for Settings_Indexer.
 */
class Settings_Indexer_Tests extends Test_Case {

	/**
	 * In-memory transient store.
	 *
	 * @var array<mixed>
	 */
	private static $transients = array();

	/**
	 * Set up generic function stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		self::$transients = array();

		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'get_registered_settings' )->justReturn( array() );

		// Discovery reads the admin globals; isolate them per test.
		$GLOBALS['submenu']              = array();
		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl = 0 ) {
				self::$transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return self::$transients[ $key ] ?? false;
			}
		);
	}

	/**
	 * Source constant must be settings.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'settings', Settings_Indexer::SOURCE );
	}

	/**
	 * Reindex should build the index and return the record count.
	 *
	 * @return void
	 */
	public function test_reindex_counts_records(): void {
		$indexer = new Settings_Indexer();

		$count = $indexer->reindex();
		$this->assertGreaterThan( 0, $count );
		$this->assertSame( $count, count( get_transient( Settings_Indexer::INDEX_TRANSIENT_KEY ) ) );
	}

	/**
	 * Two consecutive cold-cache rebuilds produce the same records.
	 *
	 * @return void
	 */
	public function test_reindex_is_deterministic(): void {
		$indexer = new Settings_Indexer();

		self::$transients = array();
		$first  = $indexer->get_index();
		self::$transients = array();
		$second = $indexer->get_index();

		$this->assertSame( $first, $second );
	}

	/**
	 * Empty query should return every indexed record.
	 *
	 * @return void
	 */
	public function test_search_empty_query_returns_all(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( '' );
		$this->assertIsArray( $results );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'settings', $results[0]['source'] );
	}

	/**
	 * Searching should match records by field id/label.
	 *
	 * @return void
	 */
	public function test_search_matches_title(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'General' );
		$this->assertNotEmpty( $results );
	}

	/**
	 * Searching should match records by keywords.
	 *
	 * @return void
	 */
	public function test_search_matches_keywords(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'blogname' );
		$this->assertNotEmpty( $results );
	}

	/**
	 * No-match query should return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_no_match(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'xyz-123-nope' );
		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * get_index should rebuild the index when the transient is empty.
	 *
	 * @return void
	 */
	public function test_get_index_rebuilds_when_empty(): void {
		$indexer = new Settings_Indexer();

		$index = $indexer->get_index();
		$this->assertIsArray( $index );
		$this->assertNotEmpty( $index );
	}

	/**
	 * get_records should expose the cached index as spotlight records.
	 *
	 * @return void
	 */
	public function test_get_records_returns_spotlight_records(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$this->assertIsArray( $records );
		$this->assertNotEmpty( $records );

		foreach ( $records as $record ) {
			$this->assertSame( 'settings', $record['facet'] );
			$this->assertArrayHasKey( 'search', $record );
			$this->assertArrayHasKey( 'display', $record );
			$this->assertArrayHasKey( 'url', $record['display'] );
			$this->assertNotEmpty( $record['display']['url'] );
		}
	}

	/**
	 * Every settings record must carry the locked W2 display shape.
	 *
	 * @return void
	 */
	public function test_records_have_locked_display_shape(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$this->assertNotEmpty( $records );

		foreach ( $records as $record ) {
			$d = $record['display'];
			$this->assertNotEmpty( $d['snippet'], 'Snippet must not be empty.' );
			$this->assertContains( $d['language'], array( 'html', 'php', 'css' ) );
			$this->assertContains( $d['sourceKind'], array( 'core', 'plugin' ) );
			$this->assertIsArray( $d['breadcrumb'] );
			$this->assertGreaterThanOrEqual( 2, count( $d['breadcrumb'] ) );
			$this->assertNotEmpty( $d['url'] );
		}
	}

	/**
	 * All six core options pages appear in the settings facet.
	 *
	 * @return void
	 */
	public function test_core_settings_pages_covered(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records   = $indexer->get_records();
		$seen      = array();
		$want      = array( 'General', 'Writing', 'Reading', 'Discussion', 'Media', 'Permalinks' );
		$want_keys = array_flip( $want );

		foreach ( $records as $record ) {
			$last = end( $record['display']['breadcrumb'] );
			if ( isset( $want_keys[ $last ] ) ) {
				$seen[ $last ] = true;
			}
		}

		foreach ( $want as $page ) {
			$this->assertArrayHasKey( $page, $seen, 'Missing core settings page: ' . $page );
		}
	}

	/**
	 * Searching for "blogname" returns Settings > General first.
	 *
	 * @return void
	 */
	public function test_blogname_query_returns_settings_general(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$match   = null;
		foreach ( $records as $record ) {
			if ( in_array( 'blogname', $record['search']['terms'], true ) ) {
				$match = $record;
				break;
			}
		}

		$this->assertNotNull( $match );
		$this->assertSame( array( 'Settings', 'General' ), $match['display']['breadcrumb'] );
	}

	/**
	 * Every core Settings screen is indexed, Privacy included.
	 *
	 * @return void
	 */
	public function test_all_core_settings_screens_are_indexed(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$pages = array();
		foreach ( $indexer->get_index() as $record ) {
			$pages[ $record['pageSlug'] ] = true;
		}

		foreach ( array(
			'options-general.php',
			'options-writing.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-permalink.php',
			'options-privacy.php',
		) as $slug ) {
			$this->assertArrayHasKey( $slug, $pages, 'Missing core Settings screen: ' . $slug );
		}
	}

	/**
	 * Controls core prints itself, which no earlier build reached, are indexed.
	 *
	 * @return void
	 */
	public function test_core_controls_beyond_the_old_hardcoded_sample_are_indexed(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$fields = array();
		foreach ( $indexer->get_index() as $record ) {
			$fields[ $record['fieldId'] ] = true;
		}

		// One representative previously-missing control from each screen.
		foreach ( array(
			'date_format',
			'start_of_week',
			'default_role',
			'default_category',
			'mailserver_url',
			'blog_public',
			'page_for_posts',
			'avatar_default',
			'thread_comments',
			'disallowed_keys',
			'medium_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',
			'tag_base',
			'wp_page_for_privacy_policy',
		) as $field_id ) {
			$this->assertArrayHasKey( $field_id, $fields, 'Missing core settings control: ' . $field_id );
		}
	}

	/**
	 * The index must exceed the per-facet response cap, otherwise the cap and
	 * the index size are indistinguishable and this suite proves nothing.
	 *
	 * @return void
	 */
	public function test_index_is_larger_than_the_facet_cap(): void {
		$indexer = new Settings_Indexer();

		$this->assertGreaterThan( \WP_Search\Spotlight::FACET_CAP, $indexer->reindex() );
	}

	/**
	 * A field past the facet cap is still reachable by query.
	 *
	 * get_records() used to stop at the 50th record before any matching ran,
	 * which made everything after it permanently unsearchable.
	 *
	 * @return void
	 */
	public function test_fields_past_the_facet_cap_are_still_searchable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$index   = $indexer->get_index();

		// Nothing is dropped on the way to the matcher.
		$this->assertCount( count( $index ), $records );

		// The last record in the index answers a query for itself.
		$last    = end( $index );
		$payload = \WP_Search\Spotlight::to_flat_payload( $records, $last['fieldId'], 'settings' );

		$this->assertNotEmpty( $payload['settings'], 'Last indexed field is unreachable: ' . $last['fieldId'] );
	}

	/**
	 * A browse request is still capped, so the cap moved rather than vanished.
	 *
	 * @return void
	 */
	public function test_browse_response_is_capped_after_matching(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$payload = \WP_Search\Spotlight::to_flat_payload( $indexer->get_records(), '', 'settings' );

		$this->assertCount( \WP_Search\Spotlight::FACET_CAP, $payload['settings'] );
	}

	/**
	 * A secret-bearing field never carries its stored value into the index.
	 *
	 * @return void
	 */
	public function test_secret_bearing_fields_are_masked(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			function ( $name ) {
				return 'mailserver_pass' === $name ? 'hunter2-in-the-clear' : '';
			}
		);

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$this->assertStringNotContainsString( 'hunter2-in-the-clear', (string) json_encode( $indexer->get_index() ) );
		$this->assertStringNotContainsString( 'hunter2-in-the-clear', (string) json_encode( $indexer->get_records() ) );
	}

	/**
	 * Plugin pages under Settings are discovered from the admin menu.
	 *
	 * @return void
	 */
	public function test_plugin_settings_pages_are_discovered_from_the_admin_menu(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$GLOBALS['submenu'] = array(
			'options-general.php' => array(
				array( 'General', 'manage_options', 'options-general.php' ),
				array( 'My Widget Co', 'manage_options', 'widgetco-settings' ),
			),
		);

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$found = null;
		foreach ( $indexer->get_index() as $record ) {
			if ( 'widgetco-settings' === $record['pageSlug'] ) {
				$found = $record;
				break;
			}
		}

		$this->assertNotNull( $found, 'Plugin Settings page was not discovered.' );
		$this->assertSame( 'plugin', $found['sourceKind'] );
		$this->assertSame( array( 'Settings', 'My Widget Co' ), $found['breadcrumb'] );
	}

	/**
	 * Fields registered through the Settings API are indexed.
	 *
	 * @return void
	 */
	public function test_settings_api_fields_are_indexed(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$GLOBALS['submenu'] = array(
			'options-general.php' => array(
				array( 'My Widget Co', 'manage_options', 'widgetco-settings' ),
			),
		);

		$GLOBALS['wp_settings_sections'] = array(
			'widgetco-settings' => array(
				'wc_main' => array(
					'id'    => 'wc_main',
					'title' => 'Connection',
				),
			),
		);

		$GLOBALS['wp_settings_fields'] = array(
			'widgetco-settings' => array(
				'wc_main' => array(
					'widgetco_endpoint' => array(
						'id'    => 'widgetco_endpoint',
						'title' => 'API Endpoint',
					),
				),
			),
		);

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$found = null;
		foreach ( $indexer->get_index() as $record ) {
			if ( 'widgetco_endpoint' === $record['fieldId'] ) {
				$found = $record;
				break;
			}
		}

		$this->assertNotNull( $found, 'Settings API field was not indexed.' );
		$this->assertSame( 'API Endpoint', $found['fieldLabel'] );
		$this->assertSame( 'Connection', $found['sectionTitle'] );
	}

	/**
	 * No field is indexed twice for the same page.
	 *
	 * @return void
	 */
	public function test_no_duplicate_field_records(): void {
		$GLOBALS['submenu'] = array(
			'options-general.php' => array(
				array( 'General', 'manage_options', 'options-general.php' ),
			),
		);

		Functions\when( 'get_registered_settings' )->justReturn(
			array(
				// Already covered by the core map; must not double up.
				'blogname' => array(
					'group' => 'general',
					'label' => 'Site Title',
					'type'  => 'string',
				),
			)
		);

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$seen = array();
		foreach ( $indexer->get_index() as $record ) {
			$identity = $record['pageSlug'] . '|' . $record['fieldId'];
			$this->assertArrayNotHasKey( $identity, $seen, 'Duplicate settings record: ' . $identity );
			$seen[ $identity ] = true;
		}
	}

	/**
	 * Controls are harvested out of rendered admin markup, including pages
	 * that register nothing through the Settings API.
	 *
	 * @return void
	 */
	public function test_rendered_controls_are_parsed_from_markup(): void {
		$html = '<div class="wrap"><h1>Acme</h1>'
			. '<form><input type="hidden" name="_wpnonce" value="abc123" />'
			. '<table class="form-table">'
			. '<tr><th scope="row">Acme API Key</th><td>'
			. '<input type="text" name="acme_api_key" id="acme_api_key" value="LIVE-KEY-9999" class="regular-text" />'
			. '<p class="description">Your Acme subscription key.</p></td></tr>'
			. '<tr><th scope="row">Mode</th><td>'
			. '<label><input type="radio" name="acme_mode" value="live" checked /> Live</label>'
			. '<label><input type="radio" name="acme_mode" value="test" /> Test</label>'
			. '</td></tr></table>'
			. '<h2>Advanced</h2>'
			. '<label for="acme_timeout">Timeout</label>'
			. '<select id="acme_timeout" name="acme_timeout"><option>30</option></select>'
			. '<textarea name="acme_notes"></textarea>'
			. '<input type="submit" name="submit" value="Save" />'
			. '</form></div>';

		$page = array(
			'slug'    => 'acme-settings',
			'title'   => 'Acme',
			'group'   => 'acme-settings',
			'kind'    => 'plugin',
			'cap'     => 'manage_options',
			'order'   => 9,
			'partial' => false,
		);

		$indexer = new Settings_Indexer();
		$method  = new \ReflectionMethod( Settings_Indexer::class, 'parse_controls' );
		$method->setAccessible( true );

		$records = $method->invoke( $indexer, $html, $page );

		$by_id = array();
		foreach ( $records as $record ) {
			$by_id[ $record['fieldId'] ] = $record;
		}

		// Every real control is harvested.
		$this->assertArrayHasKey( 'acme_api_key', $by_id );
		$this->assertArrayHasKey( 'acme_mode', $by_id );
		$this->assertArrayHasKey( 'acme_timeout', $by_id );
		$this->assertArrayHasKey( 'acme_notes', $by_id );

		// Nonces and submit buttons are not settings.
		$this->assertArrayNotHasKey( '_wpnonce', $by_id );
		$this->assertArrayNotHasKey( 'submit', $by_id );

		// Labels come from the form-table row header and the for= association.
		$this->assertSame( 'Acme API Key', $by_id['acme_api_key']['fieldLabel'] );
		$this->assertSame( 'Timeout', $by_id['acme_timeout']['fieldLabel'] );

		// The nearest preceding heading becomes the section.
		$this->assertSame( 'Advanced', $by_id['acme_timeout']['sectionTitle'] );

		// A repeated radio name is one record, not one per option.
		$this->assertCount( 1, array_filter( $records, static fn( $r ) => 'acme_mode' === $r['fieldId'] ) );

		// A key-bearing control keeps its markup but loses its value.
		$this->assertStringNotContainsString( 'LIVE-KEY-9999', $by_id['acme_api_key']['snippet'] );
	}

	/**
	 * Tab links printed by a page are followed and their controls indexed.
	 *
	 * @return void
	 */
	public function test_secondary_tabs_are_discovered_from_nav_tab_links(): void {
		$html = '<h1>Tabbed</h1>'
			. '<h2 class="nav-tab-wrapper">'
			. '<a class="nav-tab" href="options-general.php?page=tp&amp;tab=general">General</a>'
			. '<a class="nav-tab" href="options-general.php?page=tp&amp;tab=advanced">Advanced</a>'
			. '<a class="nav-tab" href="options-general.php?page=tp&amp;tab=advanced">Advanced again</a>'
			. '</h2>';

		$indexer = new Settings_Indexer();
		$method  = new \ReflectionMethod( Settings_Indexer::class, 'discover_tabs' );
		$method->setAccessible( true );

		$this->assertSame( array( 'general', 'advanced' ), $method->invoke( $indexer, $html ) );
	}

	/**
	 * A page with no tab links yields no tabs, so nothing is re-rendered.
	 *
	 * @return void
	 */
	public function test_untabbed_page_yields_no_tabs(): void {
		$indexer = new Settings_Indexer();
		$method  = new \ReflectionMethod( Settings_Indexer::class, 'discover_tabs' );
		$method->setAccessible( true );

		$this->assertSame( array(), $method->invoke( $indexer, '<h1>Plain</h1><input name="a" />' ) );
	}

	/**
	 * A tab bar is markup-level heading but not a section, and the tab name is
	 * the crumb that actually locates the control.
	 *
	 * @return void
	 */
	public function test_tab_bar_is_not_mistaken_for_a_section(): void {
		// The shared stub discards its argument; this test asserts on the path.
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . $path;
			}
		);

		$html = '<h2 class="nav-tab-wrapper">'
			. '<a class="nav-tab" href="?page=tp&amp;tab=general">General</a>'
			. '<a class="nav-tab" href="?page=tp&amp;tab=advanced">Advanced</a>'
			. '</h2>'
			. '<label for="tp_ttl">Cache TTL</label><input id="tp_ttl" name="tp_ttl" />';

		$page = array(
			'slug'    => 'tp',
			'title'   => 'Tabbed',
			'group'   => 'tp',
			'kind'    => 'plugin',
			'cap'     => 'manage_options',
			'order'   => 9,
			'partial' => false,
		);

		$indexer = new Settings_Indexer();
		$method  = new \ReflectionMethod( Settings_Indexer::class, 'parse_controls' );
		$method->setAccessible( true );

		$records = $method->invoke( $indexer, $html, $page, 'advanced' );
		$this->assertCount( 1, $records );

		$record = $records[0];
		$this->assertNotSame( 'GeneralAdvanced', $record['sectionTitle'] );
		$this->assertSame( array( 'Settings', 'Tabbed', 'Advanced' ), $record['breadcrumb'] );
		$this->assertStringContainsString( 'tab=advanced', $record['url'] );
		$this->assertStringContainsString( '#tp_ttl', $record['url'] );
	}

	/**
	 * The index is dropped when the set of Settings pages can have changed.
	 *
	 * @return void
	 */
	public function test_invalidate_drops_the_cached_index(): void {
		$deleted = array();
		Functions\when( 'delete_transient' )->alias(
			function ( $key ) use ( &$deleted ) {
				$deleted[] = $key;
				unset( self::$transients[ $key ] );
				return true;
			}
		);

		$indexer = new Settings_Indexer();
		$indexer->reindex();
		$this->assertNotEmpty( get_transient( Settings_Indexer::INDEX_TRANSIENT_KEY ) );

		$indexer->invalidate();

		$this->assertSame( array( Settings_Indexer::INDEX_TRANSIENT_KEY ), $deleted );
		$this->assertFalse( get_transient( Settings_Indexer::INDEX_TRANSIENT_KEY ) );
	}
}
