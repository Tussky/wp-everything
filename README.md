# Isaac Anderson — AI Labs Cohort #2 Workspace

This repository tracks the active development workspace for Isaac Anderson's
AI Labs Cohort #2 hackathon project.

The headline deliverable is the **wp→search** WordPress plugin. It lives
under `wordpress-sandbox/wp-content/plugins/wp-search/`.

## What's in here

- `wordpress-sandbox/wp-content/plugins/wp-search/` — main plugin
- `AGENTS.md` — workspace agent instructions for the cohort
- `PROJECT.md` — short project brief for cohort tooling
- `LICENSE` — GPL-2.0-or-later
- `CHANGELOG.md` — what landed, in order

The `wordpress-sandbox/` folder also hosts the cohort-managed Docker sandbox
queue (see `AGENTS.md` for the request/result protocol). It is intentionally
not the WordPress install — the install is provisioned on demand.

## wp→search plugin

- Searches plugin settings tabs and deep-links to exact option locations
- Searches WooCommerce products, pages, and posts
- Keyboard-first admin workflow (`Cmd/Ctrl+K` from anywhere in wp-admin)
- Beautiful, visual search modal with grouped results

## Development notes

- PHP coding standard: WordPress Coding Standards (WPCS).
- No framework dependencies — vanilla PHP + vanilla JS.
- The third-party **Redirection** and **Akismet** plugins are not authored here.

## License

GPL-2.0-or-later. See `LICENSE`.