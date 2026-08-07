# SiteMap Redirects

A WordPress plugin that indexes your site into an interactive visual tree map and overlays redirects with their priority order, HTTP status, and destination — explained in plain English for non-technical users.

Built for Isaac Anderson — AI Labs Cohort #2 hackathon.

## What it does

- **Site indexer** — crawls posts, pages, custom post types, taxonomy archives, and author archives into a URL-path tree. Cached in a transient; refreshable with a **Re-index** button or `wp sitemap-redirects reindex`.
- **Redirect sources** (in priority order — the order they actually run):
  1. `.htaccess` static rules (read-only parse) — runs at Apache level, before WordPress.
  2. The **Redirection** plugin's DB table — the most popular WP redirect plugin.
  3. WordPress core canonical redirects (trailing-slash fixes, permalink normalization) — run during page load.
- **Interactive tree UI** — admin page under a top-level **SiteMap Redirects** menu. D3-powered tree with expand/collapse, zoom/pan, click a node to see its redirects, priority order, HTTP status, and destination.
- **Non-technical framing** — plain-English labels ("Visitors going here → land here"), color-coded redirect status, and a "Why does this redirect happen?" explainer for every rule.

## Installation

1. Copy the `site-map-redirects/` folder to `wp-content/plugins/`.
2. Activate **SiteMap Redirects** in *Plugins*.
3. Go to the **SiteMap Redirects** top-level admin menu.

The index builds automatically on activation; use the **Re-index site** button to refresh.

## REST API

| Method | Route | Description |
|--------|-------|-------------|
| `GET`  | `/wp-json/sitemap-redirects/v1/tree`  | Returns the indexed tree + all redirects + legend colors. |
| `POST` | `/wp-json/sitemap-redirects/v1/reindex` | Rebuilds the index and returns the fresh payload. Requires `manage_options`. |

## WP-CLI

```bash
wp sitemap-redirects reindex
```

## Tech

- PHP backend, single plugin, no build step.
- Frontend: vanilla JS + D3 v7 loaded from CDN (no bundler required).

## License

GPL-2.0-or-later.
