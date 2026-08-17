<?php
/**
 * Spotlight flat-contract unit tests.
 *
 * Asserts the on-the-wire shape emitted by Spotlight::to_flat_payload(): the
 * exact field list per facet, no passwordHash anywhere, protected options
 * carry value === null, every record carries a non-empty url, the per-facet
 * cap of 50, and the server-side facet filter.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use WP_Search\Spotlight;

/**
 * Tests for the flat Spotlight contract.
 */
class Spotlight_Contract_Tests extends Test_Case {

	/**
	 * Build a nested internal record for a facet.
	 *
	 * @param string $facet   Facet name.
	 * @param string $id      Record id.
	 * @param array  $display Display fields to include.
	 * @param int    $weight  Search weight.
	 * @return array<mixed>
	 */
	private function rec( string $facet, string $id, array $display, int $weight = 50 ): array {
		return array(
			'id'      => $id,
			'facet'   => $facet,
			'search'  => array( 'terms' => array( $id ), 'weight' => $weight ),
			'display' => $display,
		);
	}

	/**
	 * The payload must be a flat object with exactly the four facet keys, in
	 * canonical order, and no _meta/facets wrapper.
	 *
	 * @return void
	 */
	public function test_payload_has_four_facets_in_order_no_wrappers(): void {
		$records = array(
			$this->rec( 'users', 'u-1', array( 'url' => 'https://x/u' ) ),
			$this->rec( 'plugins', 'p-1', array( 'url' => 'https://x/p' ) ),
			$this->rec( 'options', 'o-1', array( 'url' => 'https://x/o' ) ),
			$this->rec( 'settings', 's-1', array( 'url' => 'https://x/s' ) ),
		);

		$payload = Spotlight::to_flat_payload( $records, '' );

		$this->assertSame( array( 'users', 'plugins', 'options', 'settings' ), array_keys( $payload ) );
		$this->assertArrayNotHasKey( '_meta', $payload );
		$this->assertArrayNotHasKey( 'facets', $payload );
	}

	/**
	 * Each facet record must carry exactly its locked field list, in order,
	 * with no extra keys and no internal wrappers leaking through.
	 *
	 * @return void
	 */
	public function test_field_list_per_facet_is_exact(): void {
		$records = array(
			$this->rec(
				'users',
				'u-1',
				array(
					'hue'          => 264,
					'displayName'  => 'Admin',
					'username'    => 'admin',
					'role'        => 'Administrator',
					'email'       => 'a@example.com',
					'capabilities' => array( 'manage_options' ),
					'registered'  => '2026-01-01',
					'lastLogin'   => '2026-08-17',
					'url'         => 'https://x/u',
				)
			),
			$this->rec(
				'plugins',
				'p-1',
				array(
					'name'            => 'A',
					'slug'            => 'a/a.php',
					'active'          => true,
					'version'         => '1.0',
					'updateAvailable' => null,
					'author'          => 'Author',
					'description'     => 'Desc',
					'url'             => 'https://x/p',
				)
			),
			$this->rec(
				'options',
				'o-1',
				array(
					'name'      => 'blogname',
					'value'     => 'Site',
					'autoload'  => 'yes',
					'protected' => false,
					'explainer' => 'Site title.',
					'url'       => 'https://x/o',
				)
			),
			$this->rec(
				'settings',
				's-1',
				array(
					'source'     => 'WordPress Core',
					'sourceKind' => 'core',
					'breadcrumb' => array( 'Settings', 'General' ),
					'language'   => 'html',
					'snippet'    => '<label/>',
					'url'        => 'https://x/s',
				)
			),
		);

		$payload = Spotlight::to_flat_payload( $records, '' );

		foreach ( Spotlight::FACET_FIELDS as $facet => $fields ) {
			$this->assertNotEmpty( $payload[ $facet ], "Facet {$facet} empty" );
			$row = $payload[ $facet ][0];
			$this->assertSame( $fields, array_keys( $row ), "Facet {$facet} field list mismatch" );
			$this->assertArrayNotHasKey( 'facet', $row );
			$this->assertArrayNotHasKey( 'search', $row );
			$this->assertArrayNotHasKey( 'display', $row );
		}
	}

	/**
	 * passwordHash must never appear as a key anywhere in the payload, even if
	 * a provider mistakenly placed it in display.
	 *
	 * @return void
	 */
	public function test_no_password_hash_key_anywhere(): void {
		$records = array(
			$this->rec(
				'users',
				'u-1',
				array(
					'passwordHash' => '$P$Baaaa',
					'displayName'  => 'Admin',
					'username'    => 'admin',
					'role'        => 'Administrator',
					'email'       => 'a@example.com',
					'capabilities' => array(),
					'registered'  => '',
					'lastLogin'   => '',
					'hue'         => 10,
					'url'         => 'https://x/u',
				)
			),
		);

		$payload = Spotlight::to_flat_payload( $records, '' );

		$flat = json_encode( $payload );
		$this->assertStringNotContainsString( 'passwordHash', $flat );
		$this->assertStringNotContainsString( '$P$Baaaa', $flat );
	}

	/**
	 * options records with protected === true must have value === null; the
	 * real value must not appear anywhere in the payload.
	 *
	 * @return void
	 */
	public function test_protected_options_have_null_value(): void {
		$records = array(
			$this->rec(
				'options',
				'o-1',
				array(
					'name'      => 'akismet_api_key',
					'value'     => null,
					'autoload'  => 'yes',
					'protected' => true,
					'explainer' => 'Akismet key.',
					'url'       => 'https://x/o',
				)
			),
			$this->rec(
				'options',
				'o-2',
				array(
					'name'      => 'blogname',
					'value'     => 'My Site',
					'autoload'  => 'yes',
					'protected' => false,
					'explainer' => 'Site title.',
					'url'       => 'https://x/o2',
				)
			),
		);

		$payload = Spotlight::to_flat_payload( $records, '' );

		$by_name = array_column( $payload['options'], null, 'name' );
		$this->assertNull( $by_name['akismet_api_key']['value'] );
		$this->assertTrue( $by_name['akismet_api_key']['protected'] );
		$this->assertSame( 'My Site', $by_name['blogname']['value'] );
		$this->assertStringNotContainsString( 'supersecret', json_encode( $payload ) );
	}

	/**
	 * Every record in every facet must carry a non-empty url.
	 *
	 * @return void
	 */
	public function test_every_record_has_url(): void {
		$records = array();
		foreach ( Spotlight::FACET_ORDER as $i => $facet ) {
			$records[] = $this->rec( $facet, $facet[0] . '-1', array( 'url' => 'https://x/' . $facet ) );
		}

		$payload = Spotlight::to_flat_payload( $records, '' );

		foreach ( Spotlight::FACET_ORDER as $facet ) {
			foreach ( $payload[ $facet ] as $record ) {
				$this->assertIsString( $record['url'] );
				$this->assertNotEmpty( $record['url'] );
			}
		}
	}

	/**
	 * Each facet is capped at Spotlight::FACET_CAP (50).
	 *
	 * @return void
	 */
	public function test_facet_cap_is_50(): void {
		$records = array();
		for ( $i = 1; $i <= 80; $i++ ) {
			$records[] = $this->rec(
				'options',
				'o-' . $i,
				array(
					'name'      => 'opt_' . $i,
					'value'     => 'v',
					'autoload'  => 'yes',
					'protected' => false,
					'explainer' => 'e',
					'url'       => 'https://x/o' . $i,
				),
				100 - $i
			);
		}

		$payload = Spotlight::to_flat_payload( $records, '' );

		$this->assertCount( Spotlight::FACET_CAP, $payload['options'] );
	}

	/**
	 * The facet filter restricts to one array and empties the others, while
	 * keeping all four keys present.
	 *
	 * @return void
	 */
	public function test_facet_filter_restricts_to_one_facet(): void {
		$records = array(
			$this->rec( 'users', 'u-1', array( 'url' => 'https://x/u' ) ),
			$this->rec( 'plugins', 'p-1', array( 'url' => 'https://x/p' ) ),
			$this->rec( 'options', 'o-1', array( 'url' => 'https://x/o' ) ),
			$this->rec( 'settings', 's-1', array( 'url' => 'https://x/s' ) ),
		);

		$payload = Spotlight::to_flat_payload( $records, '', 'options' );

		$this->assertSame( array( 'users', 'plugins', 'options', 'settings' ), array_keys( $payload ) );
		$this->assertEmpty( $payload['users'] );
		$this->assertEmpty( $payload['plugins'] );
		$this->assertNotEmpty( $payload['options'] );
		$this->assertEmpty( $payload['settings'] );
	}

	/**
	 * A query matches against search.terms and filters out non-matching records.
	 *
	 * @return void
	 */
	public function test_query_filters_by_search_terms(): void {
		$records = array(
			$this->rec( 'options', 'o-1', array( 'name' => 'a', 'value' => 'v', 'autoload' => 'yes', 'protected' => false, 'explainer' => 'e', 'url' => 'https://x/1' ), 50 ),
			$this->rec( 'options', 'o-2', array( 'name' => 'b', 'value' => 'v', 'autoload' => 'yes', 'protected' => false, 'explainer' => 'e', 'url' => 'https://x/2' ), 50 ),
		);

		$matched = Spotlight::to_flat_payload( $records, 'o-2' );
		$this->assertCount( 1, $matched['options'] );
		$this->assertSame( 'o-2', $matched['options'][0]['id'] );
	}
}
