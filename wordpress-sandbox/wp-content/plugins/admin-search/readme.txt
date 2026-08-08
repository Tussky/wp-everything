=== Admin Search ===
Contributors: isaacanderson
Tags: search, admin, woocommerce, utility
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified keyboard-driven search across plugin settings pages, admin users, WooCommerce products, and posts/pages.

== Description ==

Admin Search adds a single keyboard shortcut (Ctrl/Cmd+K) that opens a fast, typeahead search box anywhere in the WordPress admin. As you type, results stream in from four sources at once and are grouped so you can scan them quickly:

* **Settings** — every registered admin settings page (top-level and submenu), so non-technical users stop hunting through nested plugin menus to find "where do I turn that thing on".
* **People** — admins, editors, authors, contributors, and shop managers, matched against display name, username, and email.
* **Products** — WooCommerce products (title, SKU, excerpt, content). Gracefully hidden when WooCommerce is not active.
* **Content** — published posts and pages, matched against title, excerpt, and body.

Results are grouped by source type, ranked by where your query hit (title > snippet > breadcrumb > body), and support arrow-key + Enter navigation with full keyboard accessibility.

= Why non-technical users want it =

WordPress admins spend a lot of time answering "where is that setting?" for themselves and their clients. Admin Search turns those questions into a single keystroke: press Ctrl+K, type two or three letters, jump straight to the screen they meant. For a small site with a handful of plugins this is a quality-of-life win; for a WooCommerce store with hundreds of products and many admin users, it's the difference between searching and giving up.

= Design notes =

* Read-only MVP — no CRUD, no analytics, no replacements. The plugin never edits your data.
* Index is rebuilt on activation and re-runnable via the REST endpoint or `wp admin-search reindex`.
* REST endpoints are namespaced under `admin-search/v1` and gated by `manage_options` / `edit_posts` capability checks plus WP nonces.
* Output is escaped (`esc_html`, `esc_attr`, `esc_url`) and the index payload is stripped of HTML before scoring, so a malicious display name cannot inject markup into the result list.
* WooCommerce is an optional dependency — the plugin detects it via `class_exists('WooCommerce')` and skips the products source when it is missing.

== Installation ==

1. Upload the `admin-search` folder to `/wp-content/plugins/`, or install the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. (Optional) Activate WooCommerce if you want product results.
4. Press **Ctrl+K** (or **Cmd+K** on macOS) anywhere in the admin, or click the magnifier icon in the admin bar, to open the search box.

The index is built automatically on activation; you can rebuild it from the REST endpoint `POST /wp-json/admin-search/v1/search/reindex` (admin only) or from WP-CLI:

```bash
wp admin-search reindex
```

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

Yes. Admin Search detects whether WooCommerce is active using `class_exists('WooCommerce')` and hides the *Products* result group when WooCommerce is not installed. All other sources (settings, users, posts, pages) work normally. You do not need to install WooCommerce to use the plugin.

= Does it replace the default WordPress admin search? =

No. The plugin adds a new shortcut and admin-bar entry; the existing *Posts* / *Pages* search boxes in the admin sidebar are left untouched. Admin Search is read-only and never modifies content.

= Is it safe on large sites? =

The MVP uses `posts_per_page => -1` for indexing. That is fine for the hackathon MVP scale (a few hundred posts, a few hundred products) and a hard cap will be added in a follow-up. If your site has tens of thousands of posts, expect the first activation to take a few seconds.

= Does it support custom post types? =

Posts and pages are indexed out of the box. Other public post types can be added by extending the `index_content()` source — see `includes/class-indexer.php`.

= Where is the search index stored? =

As a single WordPress option (`as_index_v1`) with a content hash and timestamp. Rebuilding refreshes both the option and the cached stats. Uninstalling the plugin deletes the option.

== Screenshots ==

1. `screenshot-1.png` — Ctrl+K modal opened from the admin bar, empty state.
2. `screenshot-2.png` — Grouped results across Settings, People, Products, and Content for a sample query.
3. `screenshot-3.png` — Keyboard navigation: arrow keys cycle, Enter opens the selected record.
4. `demo.gif` — End-to-end recording of open → type → grouped results → arrow + Enter navigation.

The full QA attachment set lives on issue [IA-54](/IA/issues/IA-54) of the Admin Search hackathon project.

== Changelog ==

= 0.1.0 =
* Initial MVP release.
* Keyboard shortcut (Ctrl/Cmd+K) opens a unified search modal from anywhere in the admin.
* Indexes four sources: plugin settings pages, admin users, WooCommerce products (when active), and published posts/pages.
* REST API under `admin-search/v1` with `/search`, `/search/reindex`, and `/stats` endpoints.
* Admin-bar magnifier icon as a mouse fallback for the keyboard shortcut.
* Grouped result rendering with arrow-key + Enter keyboard navigation and ARIA combobox semantics.
== Upgrade Notice ==

= 0.1.0 =
Initial release. No upgrade steps required.