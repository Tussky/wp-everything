=== Admin Search ===
Contributors: andrewstichbury
Donate link: https://www.buymeacoffee.com/andrewstichbury
Tags: advanced, admin, search
Requires at least: 4.9.2
Tested up to: 7.0.1
Requires PHP: 5.2
Stable tag: 1.4.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Admin Search adds a simple, easy-to-use interface to your WordPress admin site that gives you and your admin users the ability to search across multiple post types, taxonomies and more in one place.

== Description ==

Admin Search makes searching your WordPress website easy by bringing results from all your post types, media, taxonomies, comments, users and admin pages together in a single, simple-to-use interface, seamlessly integrated into the WordPress admin interface.

Choose which post types and taxonomies are searched and the number of results displayed for each. Admin Search also supports custom post types and taxonomies.

Admin Search is a lightweight plugin with filter hooks for deep customization.

== Features ==

* Search everything on your WordPress site, anywhere
* Simple and easy to use
* Sources of search results are customizable
* Extend the search query with filter hooks

== Installation ==

1. Visit Plugins > Add New
2. Search for “Admin Search”
3. Activate Admin Search from your Plugins page
4. Go to Settings > Admin Search and choose what to search on your WordPress website

== Screenshots ==

1. Search by keyword to find posts, pages, media and more with matching titles or content
2. Find media by title, alt text, caption or description
3. Choose result sources and more with settings to tailor your search experience

== Configure & Extend ==

Admin Search can be extended by using filter hooks. The following filters are available:

* `admin_search_query` to modify the search query string. The filter argument supplied is a string containing the search query
* `admin_search_sources` to modify the search sources. The filter argument supplied is an array of sources, add, remove or modify sources
* `admin_search_posts_query` to modify the [`WP_Query`](https://developer.wordpress.org/reference/classes/wp_query/) arguments array for all searched post types. The filter argument supplied is an array of `WP_Query` arguments, add, remove or modify supported arguments
* `admin_search_{Post Type}_query` to modify the [`WP_Query`](https://developer.wordpress.org/reference/classes/wp_query/) arguments array for a specific post type. The filter argument supplied is an array of `WP_Query` arguments, add, remove or modify supported arguments. Replace _{Post Type}_ with the name of the post type to modify
* `admin_search_terms_query` to modify the [`get_terms`](https://developer.wordpress.org/reference/functions/get_terms/) arguments array for all searched terms (taxonomies). The filter argument supplied is an array of `get_terms` arguments, add, remove or modify supported arguments
* `admin_search_{Term}_query` to modify the [`get_terms`](https://developer.wordpress.org/reference/functions/get_terms/) arguments array for a specific term (taxonomy). The filter argument supplied is an array of `get_terms` arguments, add, remove or modify supported arguments. Replace _{Term}_ with the name of the term to modify
* `admin_search_comments_query` to modify the [`WP_Comment_Query`](https://developer.wordpress.org/reference/classes/wp_comment_query/) arguments array for all searched comments. The filter argument supplied is an array of `WP_Comment_Query` arguments, add, remove or modify supported arguments
* `admin_search_users_query` to modify the [`WP_User_Query`](https://developer.wordpress.org/reference/classes/WP_User_Query/) arguments array for all searched users. The filter argument supplied is an array of `admin_search_users_query` arguments, add, remove or modify supported arguments
* `admin_search_website_titles` to modify the labels array for external websites. The filter argument supplied is an array of predefined domains and titles, add, remove or modify domains and titles
* `admin_search_pre_results` and `admin_search_post_results` to modify the results array before or after results are appended to the array. The filter argument supplied is an empty array for `admin_search_pre_results` or search results for a given query for `admin_search_post_results`, add, remove or modify result items
* `admin_search_fields` to modify the searchable fields array. The filter argument supplied is an array of searchable fields (`post_title`, `post_name`, `post_excerpt` and `post_content`)
* `admin_search_meta_queries` to add custom fields to the searchable fields array. The filter argument supplied is an empty array. Use this filter instead of `admin_search_fields` when adding custom fields
* `admin_search_meta_labels` to provide a human-readable display label for a meta key in the settings UI. The filter argument is an associative array of `meta_key => label`. Keys not in the map fall back to the default display. WooCommerce integration uses this filter to render e.g. `_regular_price` as "Regular price".

== WooCommerce Integration ==

When WooCommerce is loaded, Admin Search automatically:

* Pre-enables the `product`, `shop_order`, and `shop_coupon` post types in the post type toggle UI (so the new fields are searchable without manually configuring settings).
* Registers the searchable meta keys for each post type — products (SKU, regular/sale/effective price, stock status, stock quantity, manage stock, backorders, weight, dimensions, product type, attributes, visibility, featured, virtual, downloadable, tax status, tax class, average rating, review count), orders (order number, order key, order total, tax, shipping, payment method/title, transaction ID, billing and shipping names/emails/addresses/phone/company), and coupons (discount type, amount, free shipping, usage limits, usage count, expiry date, min/max spend, individual use, exclude sale items, applicable/excluded products).
* Renders human-readable labels for each meta key in the settings UI (e.g. `_regular_price` → "Regular price").

The entire integration lives in `woocommerce.php` and is gated on `function_exists( 'WC' )` / `class_exists( 'WooCommerce' )`. When WooCommerce is not active, none of the WC code runs.

Examples:

Modify the query string before a search is initiated

	// Correct the spelling of dog when searching
	add_filter( 'admin_search_query', function( $query ) {
		if ( 'dog' === $query ) {
			$query = 'doggo';
		}

		return $query;
	} );

Modify the `WP_Query` parameters before a search is initiated

	// Exclude post with the ID 96
	add_filter( 'admin_search_posts_query', function( $query ) {
		$query['post__not_in'] = array( 96 );

		return $query;
	} );

Add a custom field to the searchable fields

	// Add the price custom field to the searchable fields
	add_filter( 'admin_search_meta_queries', function( $fields, $post_type ) {
		if ( 'post' === $post_type ) {
			$fields[] = 'price';
		}

		return $fields;
	}, 10, 2 );

Modify the search results after a search has initiated

	// Add custom results from external API
	add_filter( 'admin_search_post_results', function( $results, $q ) {
		$results[ 'custom' ][ 'post_type' ] => array(
			'name' => 'custom',
			'label' => 'Custom'
		);

		$json = file_get_contents( 'https://example.com?search=' . $q );
		$obj = json_decode( $json );

		foreach ( $obj as $item ) {
			$results[ 'custom' ][ 'posts' ][] = array(
				…
			);
		}

		return $results;
	}, 10, 2 );

== Changelog ==

= 1.5.0 =
* New Feature: Choose what fields to search in settings. By default, Admin Search searches post titles and bodies, searches can now be extended to any public custom fields
* New Feature: Search by taxonomies
* New Feature: Full WooCommerce integration. When WooCommerce is active, the `product`, `shop_order`, and `shop_coupon` post types are auto-enabled and the searchable meta fields (SKU, price, stock status, stock quantity, product type, visibility, weight, dimensions, order number, billing name/email, order total, payment method, transaction ID, coupon code, discount type/amount, usage limits, expiry date, etc.) are registered with human-readable labels in the settings UI. The integration is fully conditional — no WC code runs unless WooCommerce is loaded.
* New filter hook `admin_search_meta_labels` to replace the default "meta key with leading `_` stripped" display label with a human-readable label (e.g. `_regular_price` → "Regular price"). WooCommerce registers its labels through this filter in `woocommerce.php`.
* Extended natural language searches. More date options, including "between 01/01/20 and 01/01/26", "before 01/01/26", "after 01/01/20", "last 2 weeks". These can be combined with query searches such as "corgi facts between 01/01/20 and 01/01/26"
* Tested on WordPress 7

= 1.4.2 =
* Fixed an issue where pasting text from some sources may also paste the style

= 1.4.1 =
* Tested up to 6.7
* Added a filter hook to add taxonomies to the searchable fields. This can be hooked into using `admin_search_taxonomies`, this hook has two arguments: `$taxonomies` (a list of taxonomies being searched, this is empty initially) and `$post_type` (returns the current post type being searched so you can optionally apply the filter to a specific post type). Searching taxonomies significantly delays the return of results, it is not recommended you use this unless you're willing to wait for your results.
* Fixed issue with javascript function toggle instantly closing search box after opening

= 1.4.0 =
* New Feature: Introducing search suggestions! Now, whenever you search using a term with 9 or more characters and it returns results, it will be added to a database. The more you search that term, the higher it will rank. When you start typing 3 or more characters into the search box, autocomplete will display if it matches a term in the database, hit the right arrow key to apply it. This feature is disabled by default, go to Settings > Admin Search to enable it
* A new setting to enable or disable auto searching has been added. Typically, search results start displaying as you type, you can now disable this and only show results by hitting the enter key
* Many UI updates and some improvements to the mobile interface
* Fixed issues with hotkey handling. Navigating the results with the up and down keys is improved, hitting the right arrow key and the enter key behave better depending on what is in focus
* The search input has been switched from a traditional HTML input field and form to a contenteditable div, this shouldn't affect most users but may affect OS or browser settings and extensions that alter the way inputs work. Testing is ongoing
* Added support for WP_Block post type
* Removed the user display as setting. Users will be displayed using their username from now on
* Reduced the default result count to 5. This can still be increased to show more results initially
* Disabled Ctrl + S and Cmmd + S keyboard shortcut due to conflicts with WordPress and browser functionality
* Fixed PHP8 related issues
* Fixed an issue where default settings weren't applied when first installing and activating the plugin
* Fixed an issue where no message is displayed if there're no results
* Fixed an issue where multiple results are focussed initially
* Fixed an issue with an undefined value displaying on the account setting page
* Tested on WordPress up to version 6.5.3

= 1.3.3 =
* Fixed visual issues with media results
* Updated donation link

= 1.3.2 =
* Tested on WordPress up to version 6.4.4a
* Added Woocommerce SKU field as searchable field

= 1.3.1 =
* Minor fixes to broken text styles

= 1.3.0 =
* Results can be previewed from within the search box
* Extended search to include post names (this is also known as the post slug, typically used in the post's permalink)
* Added the `admin_search_fields` filter to include or exclude searchable fields. Current searchable fields include: post_title, post_name, post_excerpt and post_content
* Added the `admin_search_meta_queries` filter to include meta queries as searchable fields. This can be used to add custom fields to the list of searchable fields
* These changes necessitated the addition of a custom WP_Query parameter, replacing `s` with `as_s`. If you have previously added a filter which references or modifies the `s` parameter, it will need to be updated to `as_s` (as simple as replacing old with new)
* The Search Box is now moveable

= 1.2.3 =
* Fixed an issue with alignment when viewing on the front end
* Fixed an issue where admin menus with no sub menus were not working correctly in the search results

= 1.2.2 =
* Fixed an issue that could cause results not to be displayed if no post types are selected in settings

= 1.2.1 =
* Tested on WordPress 6.1.2
* Pressing the escape key with search results open will now clear the search rather than closing the search box. Hitting the escape key with no results will close the search box completely
* Fixed an issue that could cause an error to be displayed on the settings page if no post types are enabled
* Fixed an issue that could throw a 500 error if a taxonomy did not have an associated post type

= 1.2.0 =
* New Feature: Additional search results can now be loaded within the search box. If a source has more results for a query than the Result Count limit, a load more button will appear below those results
* New Feature: An option has been added to the user profile page which allows users to hide their profile from searches. Admins and other users with the `edit_user` capability can toggle this option for other users
* New Feature: Search for items by their ID by typing a hashtag followed by the ID. Eg. Searching _#889_ will list the item with that ID
* New Feature: A link to the native WordPress admin search page is displayed at the top of each results group
* New Feature: A shortcut to the Admin Search settings page now appears in the search box when hovering over the search bar
* New Feature: Add _status:_ followed by a post status such as _published_, _draft_ or _pending_ after your search term to only see results with that status. This is limited to post type results
* Update: Tweaks to the design, including an increased width and height, larger typography and improvements to colors
* Update: External websites can now be labelled using a new filter. For example: _google.com_ appears as _Google_. To add a label, use: `admin_search_website_titles`, learn more about this filter on the plugin page on WordPress.org
* Update: New filter hook `admin_search_sources` allows developers to add, modify or remove search sources
* Update: New filter hook `admin_search_{Post Type}_query` allows developers to add, modify or remove arguments from the WP_Query arguments for a specific post type. This is a dynamic filter, replace {Post Type} with the name of the post type you wish to filter
* Update: New filter hook `admin_search_{Term}_query` allows developers to add, modify or remove arguments from the WP_Query arguments for a specific term. This is a dynamic filter, replace {Term} with the name of the term you wish to filter
* Update: New filter hook `admin_search_pre_results` and `admin_search_post_results` allows developers to add or modify items in the results array before or after other results are appended to the array respectively
* Update: Links for plugin help and ways to support the plugin have been added to the footer on the plugin settings page
* Update: The Result Count options have been changed and the unlimited option removed entirely. The default Result Count value is now 10, your current Result Limit is unchanged until you update it on the settings page
* Update: The minimum query length has been decreased from 3 to 2 to accomodate ID searches
* Update: Users are now searchable by ID
* Fix: Addressed an issue causing post, page and attachment results to display in searches when all post types are disabled
* Fix: Addressed inconsistencies in the accessibility of the search box
* Fix: Started adding support for keyword highlighting on comments, users and admin page results. This is still WIP and may not show for every result, especially if the keyword is in the content but not in the title
* Fix: Improved user access. Eg. now only users who have the capability to edit other users' posts can use Admin Search
* Fix: The query string has been added to all filter hooks allowing developers to reference the search query in the filter hook callback

= 1.1.4 =
* Fix: Addressed an issue causing the search box to display when typing in an input
* Fix: Addressed an issue causing result titles to be hidden when a forward slash was used in the search query
 
= 1.1.3 =
* Update: Tested on version 5.9 of WordPress
 
= 1.1.2 =
* Update: Tested on version 5.8 of WordPress
 
= 1.1.1 =
* Update: The search UI is now centered and the scrim is removed. This makes it easier to access or view content on the page when the search UI is open
 
= 1.1.0 =
* New Feature: Users are now searchable (by username, display name, email address or role)
* New Feature: Posts can now be searched for by author
* New Feature: Posts can now be searched for by date (year, month, or day posted, or posted before or after a date). This supports most date formats
* New Feature: External websites can be added to search results. When performing a search, links to the external websites will be displayed, this is useful for including links to search results on other websites
* New Feature: The Admin Search is now accessible on the front end of the website. This can be enabled or disabled in the Admin Search settings
* Update: Taxonomies are now individually selectable
* Update: Admin menu searches now search the actual admin menus rather than a static list. This is still in beta and is disabled by default, this does not currently work when searching from the front end of the website
* Update: A new filter has been added to modify the search query string: `admin_search_query`
* Update: A new filter has been added to modify the users query:  `admin_search_users_query`
* Update: The colors of the search UI have been updated to match the WordPress color update. Additionally, developers can customize the search UI colors using CSS variables
* Update: The search UI colors now match the current admin color scheme (except for the Light scheme)
* Update: General CSS updates (mostly for mobile and smaller browser sizes)
 
= 1.0.3 =
* Fix: Addressed javascript error thrown when Admin Search is disabled
* Update: Tested on version 5.7 of WordPress
 
= 1.0.2 =
* Fix: Added correct version number to stylesheet and script URLs
* Update: Tested on version 5.4 of WordPress
 
= 1.0.1 =
* Fix: Limit is now applied to each post type, not the total number of posts returned
* Update: Plugin has been tested back to WordPress 4.9.2