# SiteMap Redirects

A WordPress plugin that indexes your entire site into an interactive, visual tree map and overlays every active redirect — `.htaccess` rules, Redirection plugin rules, and WordPress core canonical redirects — in the order they actually execute.

Built by Isaac Anderson for AI Labs Cohort #2.

## Features

- **Interactive site tree** — D3-powered visualization of posts, pages, custom post types, taxonomies, and author archives.
- **Three-layer redirect overlay** — see `.htaccess`, Redirection plugin, and WordPress core rules in one place.
- **Real priority order** — rules are rendered first-to-last, matching how your server resolves them.
- **Plain-English explanations** — every redirect shows a human-readable "why" tooltip.
- **Color-coded status badges** — instantly spot permanent (301), temporary (302/307/308), or unknown redirects.
- **REST API** — `GET /wp-json/sitemap-redirects/v1/tree` and `POST /reindex`.
- **WP-CLI** — `wp sitemap-redirects reindex` for scripted rebuilds.
- **Fail-safe** — a broken redirect source never breaks the map; the plugin survives and reports the problem.

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher
- Modern browser for the admin UI (uses D3 v7 from a CDN, loaded with SRI)

## Installation

1. Upload the `site-map-redirects/` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Open the **SiteMap Redirects** top-level admin menu.

The map builds automatically on activation. Click **Re-index site** any time you want to refresh the tree or reload redirects.

## Development

### Local code layout

```
site-map-redirects/
├── site-map-redirects.php   # Main plugin file and bootstrap
├── includes/
│   ├── class-indexer.php           # Site tree indexer
│   ├── class-redirect-resolver.php # Rule discovery coordinator
│   ├── class-redirect-sources.php  # .htaccess / Redirection / core parsers
│   ├── class-rest.php              # REST routes
│   ├── class-logger.php            # Error logging
│   └── class-safe.php              # Safe WordPress API wrappers
├── admin/
│   └── class-admin-page.php        # Admin menu, asset enqueue, page container
├── assets/
│   └── dist/                       # Compiled admin JS/CSS
├── docs/                           # User guide, developer guide, troubleshooting
├── languages/                      # Translation template
└── readme.txt                      # WordPress.org plugin listing
```

### Coding standards

The plugin follows the WordPress Coding Standards (WPCS) and WordPress-Docs. To run PHPCS from inside the cohort Docker sandbox:

```bash
wp smr-phpcs --path="/path/to/site-map-redirects" --standard=WordPress-Extra
```

Or by file:

```bash
wp smr-phpcs /var/www/html/wp-content/plugins/site-map-redirects/includes/class-indexer.php
```

See `phpcs.xml` for the plugin-level configuration.

## REST API

All routes are registered under `sitemap-redirects/v1`.

| Method | Route | Description | Permission |
|--------|-------|-------------|------------|
| `GET`  | `/tree`    | Returns the indexed tree, redirect overlays, counts, colors, and the last recorded error. | Logged-in `read` by default; override with `smr_public_read` |
| `POST` | `/reindex` | Drops the caches, rebuilds the tree, and returns a fresh `/tree` payload. | `manage_options` |

Example request:

```bash
curl -H "X-WP-Nonce: <nonce>" \
  https://example.com/wp-json/sitemap-redirects/v1/tree
```

## WP-CLI

```bash
wp sitemap-redirects reindex
```

Rebuilds the site map and caches the result. Useful for CI/CD or scheduled maintenance windows.

## Documentation

- [User Guide](docs/user-guide.md)
- [Developer Guide](docs/developer-guide.md)
- [Troubleshooting](docs/troubleshooting.md)
- [WordPress.org readme](readme.txt)

## Hooks and Filters

See the [Developer Guide](docs/developer-guide.md) for the full list.

Key filters:

- `smr_public_read` — opt in to public read access for the `/tree` endpoint.

Key actions:

- `smr_index_rebuilt` — fires after the site map has been rebuilt.

## Contributing

This is a hackathon project. If you are part of the workspace:

1. Branch from the current `main`/`master`.
2. Run PHPCS before opening a PR.
3. Keep the fail-safe contract: every module must survive its own failure.
4. Update `readme.txt`, user docs, and the translation template when UI strings change.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

- Isaac Anderson — project lead and development.
- AI Labs Cohort #2 — Paperclip hackathon workspace and mentoring.
- D3.js — interactive tree rendering (loaded from d3js.org with SRI).
