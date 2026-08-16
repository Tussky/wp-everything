<?php
/**
 * Spotlight Provider
 *
 * Contract for indexers that can emit spotlight records in addition to their
 * plain search results. A spotlight record carries its own display payload and
 * ranking weight, so a consumer can render it without re-reading the source.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by indexers that publish spotlight records.
 *
 * @since 1.0.0
 */
interface Spotlight_Provider {

	/**
	 * Return this provider's records in spotlight shape.
	 *
	 * Each record is an array with `id`, `facet`, `search` (`terms`, `weight`)
	 * and `display` keys.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array;
}
