=== SiteMap Redirects ===
Contributors: isaacanderson
Donate link: https://github.com/Tussky/paperclip-trial
Tags: sitemap, redirects, visualization, redirection, seo
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visual, interactive site map that overlays every redirect on your WordPress site — .htaccess rules, Redirection plugin rules, and WordPress core canonical redirects — in the order they actually run. Built for non-technical users with plain-English explanations.

== Description ==

**SiteMap Redirects** turns your WordPress site into an interactive tree map and shows you exactly where visitors are being redirected — and why.

Most sites end up with redirects scattered across three different layers: Apache `.htaccess` rules, the popular Redirection plugin, and WordPress core's own canonical redirects. SiteMap Redirects indexes every public URL on your site and overlays all three redirect sources in their real priority order, with color-coded status badges and plain-English explanations.

= What it does =

*   **Full site indexer** — crawls posts, pages, custom post types, taxonomy archives, and author archives into a URL-path tree.
*   **Three redirect sources, one view** — overlays `.htaccess` static rules, the Redirection plugin's database table, and WordPress core canonical redirects.
*   **Priority order that matches reality** — rules are shown in the order they actually execute (1 = first).
*   **Interactive D3 tree** — expand, collapse, zoom, pan, and click any node to inspect its redirects.
*   **Plain-English framing** — "Visitors going here → land here" copy and a "Why does this redirect happen?" explainer for every rule.
*   **REST API & WP-CLI** — re-index the tree from the admin UI, the REST API, or a WP-CLI command.
*   **Fail-safe** — one broken redirect source will never crash the map; the plugin logs the issue and keeps rendering.

= For developers =

*   Clean, object-oriented PHP classes.
*   Public REST endpoints under `sitemap-redirects/v1`.
*   Actions and filters for custom integration.
*   Full inline docs and a developer guide in `/docs/`.

== Installation ==

= From your WordPress dashboard =

1. Go to **Plugins → Add New**.
2. Search for "SiteMap Redirects".
3. Click **Install Now**, then **Activate**.
4. Go to **SiteMap Redirects** in the left-hand admin menu.

= Manual install =

1. Download the plugin zip.
2. Upload the `site-map-redirects/` folder to `/wp-content/plugins/`.
3. Activate **SiteMap Redirects** on the **Plugins** screen.
4. Open the **SiteMap Redirects** top-level menu item.

= After activation =

The plugin builds the site map automatically on activation. If the map looks empty or you edit content, click **Re-index site**.

== Frequently Asked Questions ==

= What happens if I change a permalink? =

Click **Re-index site** (or run `wp sitemap-redirects reindex`) to rebuild the tree. Redirects from the Redirection plugin and `.htaccess` are re-read at the same time.

= Do I need the Redirection plugin? =

No. If the Redirection plugin table is present, SiteMap Redirects reads it. If not, only `.htaccess` and WordPress core canonical redirects are shown.

= Can I edit redirects inside SiteMap Redirects? =

The v1.0.0 admin tree supports drag-and-drop redirect creation when the drag-and-drop feature is enabled (see the redirect toolbar). Redirect editing is primarily a future-release roadmap item; for now use your existing `.htaccess` or Redirection plugin workflow.

= Why are some redirects showing a 0 priority? =

Each redirect source has a fixed priority group:

1. `.htaccess` rules (highest, run before WordPress)
2. Redirection plugin rules
3. WordPress core canonical redirects (lowest, run during page load)

Lower numbers execute first and can override later rules.

= Will this slow down my site? =

No. The tree and redirect rules are cached in WordPress transients. The REST endpoints only regenerate the tree when requested (admin UI or WP-CLI), not on every front-end page load.

= Can I make the tree public? =

By default the `/tree` REST endpoint requires the `read` capability. Site owners can opt in to public access via the `smr_public_read` filter (see the developer guide).

= How do I clear the cache? =

Deactivate and reactivate the plugin, click **Re-index site**, or use `wp sitemap-redirects reindex`.

= Where do I report bugs or request features? =

Open an issue in the project repository:
https://github.com/Tussky/paperclip-trial/issues

== Screenshots ==

1. The main SiteMap Redirects admin page with the interactive D3 tree.
2. Zoomed-in tree view showing a page node and its outgoing redirects.
3. Debug mode with the sortable priority table of all redirects.
4. Redirect management toolbar with drag-and-drop creation tools.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Interactive D3 site-map tree with expand/collapse, zoom, and pan.
* Redirect overlays from `.htaccess`, Redirection plugin, and WordPress core canonical.
* Priority-ordered redirect display.
* Plain-English redirect explanations.
* REST endpoints: `GET /tree`, `POST /reindex`.
* WP-CLI command: `wp sitemap-redirects reindex`.
* Fail-safe indexing and redirect discovery.
* Admin error notices with dismissal.
* Full user guide, developer guide, troubleshooting, and translation template.

== Upgrade Notice ==

= 1.0.0 =
First public release. After activation, click "Re-index site" to build the initial map.

== Privacy Policy ==

SiteMap Redirects does not collect, store, or transmit any personal data to external services. All data is processed locally within your WordPress installation.
