=== wp->search ===
Contributors: paperclip
Tags: search, admin, settings, dashboard, tools
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress admin search plugin that indexes settings pages and exposes a REST endpoint.

== Description ==

wp->search indexes registered admin settings pages, sections and options and makes them searchable from the WordPress admin via a dedicated Tools page and a REST API endpoint.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-search/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Visit Tools > wp->search to start searching settings.

== Frequently Asked Questions ==

= What permissions are required? =

The Tools page and the `wp-search/v1/search` REST endpoint require the `manage_options` capability.

== Changelog ==

= 1.0.0 =
* Initial release.
* Plugin scaffolding.
* Settings indexer with transient cache.
* REST search endpoint.
* Minimal admin page under Tools.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
