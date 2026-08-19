# Changelog

All notable changes to this workspace are recorded here. Dates use UTC.
Plugin-level changes for SiteMap Redirects live in
`wordpress-sandbox/wp-content/plugins/site-map-redirects/readme.txt`.

## 2026-08-18

- Settings indexing rewritten from a hardcoded list to live discovery. The
  index was 19 fields typed into `$core_settings_map` across 6 pages; that is
  the whole reason "some settings pages work, some don't". On a core-only site
  it is now 81 records across all 7 pages. 55 core settings that could not be
  found at any query now can, including `date_format`, `blog_public`,
  `avatar_default`, `tag_base`, `medium_size_w`, every `mailserver_*`,
  `thread_comments`, `disallowed_keys`, `WPLANG` and
  `wp_page_for_privacy_policy`. Privacy (`options-privacy.php`) was absent
  entirely and is now covered.

  Discovery runs in four layers, deduplicated on `pageSlug|fieldId`:

  1. `$submenu['options-general.php']` — every page under Settings, core and
     plugin alike, so the page itself is findable by name.
  2. `get_registered_settings()` plus `$wp_settings_sections` /
     `$wp_settings_fields` — anything registered through the Settings API.
  3. A core field map mirroring `$allowed_options` in `wp-admin/options.php`.
     Core prints its own screens instead of registering fields, so they cannot
     be discovered any other way.
  4. Rendering each plugin Settings page into an output buffer and parsing the
     form controls with `DOMXPath` — the backstop for plugin pages that print
     raw HTML and register nothing.

- Fixed: settings records were capped at 50 *before* the query was applied.
  `get_records()` walked the index in insertion order and stopped at
  `RECORDS_LIMIT`, so with a full index everything past the 50th record would
  have been unreachable no matter what was typed. Matching now runs first and
  `Spotlight::FACET_CAP` bounds the response, which is where a cap belongs.
  Searchability no longer depends on a record's position in the index.

- Fixed: nothing dropped the index when the set of Settings pages changed, so a
  newly activated plugin's Settings page stayed unfindable for up to 24h until
  the TTL lapsed. Now invalidated on `activated_plugin`, `deactivated_plugin`,
  `upgrader_process_complete` and `switch_theme`.

- Multi-tab plugin pages: the crawler follows the `nav-tab` links a page prints
  and renders each tab, so controls outside the default tab are indexed. Result
  URLs deep-link to the tab and anchor the field
  (`?page=x&tab=advanced#field_id`).

- Page callbacks that gate on `$_GET['page']` before printing now render, as
  the crawler presents the request state WordPress would have when serving that
  screen. `$_GET`/`$_REQUEST` are saved and restored around every render.

- Secret handling: snippets embed live option values, so fields whose names
  match `pass`, `secret`, `token`, `api_key`, `license` and similar are indexed
  with the value stripped. Verified no leak for `mailserver_pass` or a plugin's
  rendered API-key control.

- Crawl safety: reentrancy guard, `Throwable` capture, output-buffer unwind in
  `finally`, per-page capability check, and a hard skip on any non-GET or
  `$_POST` request so a crawl can never fire mid-save.
  `add_filter( 'wp_search_settings_crawl', '__return_false' )` disables it.

- Measured on a 25-plugin-page / 500-control site: cold rebuild 25 ms (once per
  24h), warm query path 2.6 ms including a 471 KB transient unserialize.
  `vendor/bin/phpunit` → OK (86 tests, 1341 assertions); baseline was 72 / 461.

- Not covered, deliberately: settings screens outside the Settings menu
  (WooCommerce, Yoast and similar register their own top-level menus);
  JS-rendered settings pages, where the server sends an empty mount point;
  raw HTML injected onto a *core* Settings page, since crawling those would
  mean including `wp-admin/options-*.php` mid-request.

- NOT YET VERIFIED IN THE SANDBOX. The runner did not pick up
  `wordpress-sandbox/request.json` on two attempts. Rule 1 applies: this does
  not ship until a human confirms it there. Command to run:
  `wp wp-search spotlight --facet=settings --format=json`.

## 2026-08-16

- Fixed the fatal that took WordPress down on every request since 2026-08-12:
  `Spotlight_Provider` was declared by two classes in `bd6a4e1` and never
  written. Added the interface; `vendor/bin/phpunit` → OK (44 tests).
- Removed `passwordHash` from `Users_Indexer::get_records()` — it selected
  `user_pass` into a REST payload. Never leaked; the method has no callers.
- CI: new `WordPress boots with wp-search active` job installs WordPress,
  activates the plugin and asserts `/`, `/wp-login.php`, `/wp-admin/` and the
  REST root render with no PHP error. Both checks now required on this branch.
- Added `.githooks/pre-push` (syntax, class-link, unit tests) and Rule 8.
  Enable with `git config core.hooksPath .githooks`.

## 2026-08-12

- IA-162: Debug-mode Cmd/Ctrl+K redirect fixed. Three defects, for anyone
  picking this up later:

  1. **Root cause — missing nonce, not the JS.** The redirect was a top-level
     browser navigation, which cannot send the `X-WP-Nonce` header the modal's
     `fetch()` uses. Core's `rest_cookie_check_errors()` sees no nonce, calls
     `wp_set_current_user( 0 )`, and `REST_Controller::check_permission()`
     then fails its `manage_options` check and returns HTTP 403
     `wp_search_forbidden`. The endpoint returned JSON, so this read as
     almost-working, but it was never search results. The fix passes the
     already-localized `rest_nonce` as a `_wpnonce` query param, which core
     reads from `$_REQUEST`.
  2. **The query had been dropped.** `355668f` replaced the `prompt()` call
     with a bare `window.location.assign( config.rest_url )`, sending no `q`
     at all. Restored.
  3. **Wrong keybind seam.** Both earlier attempts intercepted the Cmd/Ctrl+K
     keydown itself, so the modal never opened and there was nowhere to type.
     The debug branch now lives in `onKeydown()` on Enter: Cmd/Ctrl+K opens
     the modal, you type, Enter navigates to the raw JSON. Empty input is a
     no-op.

  `.prompt()` was a red herring — it fired correctly; the request died
  server-side. Note both `assets/js/admin.js` and
  `assets/dist/wp-search-modal.js` must be edited: they are byte-identical
  with no build step, and only `dist/` is enqueued
  (`includes/class-admin.php:89`), so patching only the source changes
  nothing at runtime. Also note `wp_localize_script()` casts scalars to
  strings, so `debug_mode` arrives in JS as `"1"` or `""`, never a real
  boolean — the checks are truthiness checks and a `=== true` would fail.

  Not yet verified in the sandbox by a human; see operating rule 1 before
  this feeds any docs or release work.

## 2026-08-10

- IA-110: Board halt order — all SiteMap Redirects plugin work paused and
  removed from workspace. Pivot to wp→search per board directive.
- IA-111: SMR cleanup complete — removed `site-map-redirects/` directory,
  `smr-phpcs-runner.php` mu-plugin, and all SMR log files. All 10 SMR
  issues cancelled. Final stray file committed in follow-up.
- IA-119: wp→search production plan created — 7-phase implementation plan
  (foundation → search engine → UI/UX → testing → docs → security → deploy).
- IA-121: Phase 1 kicked off — Marko Ebner scaffolding plugin structure,
  settings indexer, and REST endpoint.
- IA-122: Phase 3 (UI) planned — Nina Wallner + Jana Richter on search
  modal and keyboard shortcut (blocked by IA-121/Phase 1+2).
- IA-95: Admin Search plugin (third-party, by Andrew Stichbury) removed from
  workspace per board directive. Redirected team to SiteMap Redirects
  production in [IA-96](/IA/issues/IA-96). (Superseded by IA-110 halt.)

## 2026-08-08

- IA-69: connect workspace to GitHub — add `LICENSE` (GPL-2.0-or-later),
  workspace `README.md`, this `CHANGELOG.md`, and a WordPress-aware
  `.gitignore` (excludes sandbox queue files, sandbox docker-compose,
  third-party Redirection plugin, PHPCS vendor tree, and `.claude/` local
  config).

## 2026-08-07

- IA-50: SiteMap Redirects `AS_Indexer` — settings page + user indexer +
  verification against the live sandbox.
- IA-48: Design system and guidance captured for search plugin UI (superseded —
  Admin Search plugin removed per [IA-95](/IA/issues/IA-95)).
- IA-52: Admin Search UI work — admin page, search UI, keyboard shortcut
  (superseded — Admin Search plugin removed per [IA-95](/IA/issues/IA-95)).

## 2026-08-06

- IA-34: reporter-agent spec delegated to CEO.
- IA-24: CEO production plan and delegation structure created.

## 2026-08-05

- IA-2: overlay redirect sources on the sitemap tree (priority order:
  `.htaccess` → Redirection DB → core canonical).
- IA-2 checkpoint: SiteMap Redirects plugin v0.1.0 added.
- WordPress sandbox request flow introduced and used to provision the
  cohort sandbox.

## 2026-08-04

- Initial Isaac Anderson workspace created.