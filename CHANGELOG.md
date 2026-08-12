# Changelog

All notable changes to this workspace are recorded here. Dates use UTC.
Plugin-level changes for SiteMap Redirects live in
`wordpress-sandbox/wp-content/plugins/site-map-redirects/readme.txt`.

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