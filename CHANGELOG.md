# Changelog

All notable changes to this workspace are recorded here. Dates use UTC.
Plugin-level changes for SiteMap Redirects live in
`wordpress-sandbox/wp-content/plugins/site-map-redirects/readme.txt`.

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