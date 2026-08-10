# Isaac Anderson — AI Labs Cohort #2 Workspace

This repository tracks the active development workspace for Isaac Anderson's
AI Labs Cohort #2 hackathon project.

The headline deliverable is the **SiteMap Redirects** WordPress plugin. It lives
under `wordpress-sandbox/wp-content/plugins/` so it can be exercised against
the cohort's shared WordPress sandbox without separate hosting.

## What's in here

- `wordpress-sandbox/wp-content/plugins/site-map-redirects/` — main plugin
- `wordpress-sandbox/wp-content/mu-plugins/smr-phpcs-runner.php` — sandbox-side
  WP-CLI helper used to run PHPCS against the plugin from inside the sandbox
- `AGENTS.md` — workspace agent instructions for the cohort
- `PROJECT.md` — short project brief for cohort tooling
- `LICENSE` — GPL-2.0-or-later
- `CHANGELOG.md` — what landed, in order

The `wordpress-sandbox/` folder also hosts the cohort-managed Docker sandbox
queue (see `AGENTS.md` for the request/result protocol). It is intentionally
not the WordPress install — the install is provisioned on demand.

## SiteMap Redirects plugin

The plugin is documented in detail under
`wordpress-sandbox/wp-content/plugins/site-map-redirects/readme.txt`. Highlights:

- Indexes posts, pages, CPTs, taxonomy archives, and author archives into a
  URL-path tree (cached in a transient; refreshable from the admin or via
  `wp sitemap-redirects reindex`).
- Overlays three redirect sources in their real priority order: `.htaccess`
  static rules, the **Redirection** plugin's DB table, then WordPress core
  canonical redirects.
- Plain-English framing for non-technical users ("Visitors going here → land
  here"), with color-coded HTTP status and a per-rule "Why does this redirect
  happen?" explainer.
- REST endpoints under `sitemap-redirects/v1` (`GET /tree`, `POST /reindex`).

Install locally by copying the plugin folder to a WordPress install and
activating it; the indexer rebuilds on activation.

## Development notes

- PHP coding standard: WordPress Coding Standards (WPCS), runnable from inside
  the sandbox via `wp smr-phpcs <path>` (see `wordpress-sandbox/.gitignore`
  and `wordpress-sandbox/wp-content/mu-plugins/`).
- The PHPCS vendor tree under `wordpress-sandbox/wp-content/phpcs-vendor/`
  is intentionally excluded from git; the sandbox provides it.
- The third-party **Redirection** plugin is also excluded — it is pulled in by
  the sandbox to exercise real `.htaccess`-aware redirect parsing, not authored
  here.

## License

GPL-2.0-or-later. See `LICENSE`.