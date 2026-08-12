# IA-30 — Error Handling Improvements: Change Report

**Issue:** [IA-30](/IA/issues/IA-30) — Error Handling Improvements
**Status:** Complete
**Goal:** Graceful failure and user-friendly error messages across the SiteMap Redirects plugin.

## What changed

### 1. Bootstrap (`site-map-redirects.php`)

- Deduplicated `SMR_TRANSIENT_RULES` and `SMR_TRANSIENT_RULES_TTL`. The two constants were each defined twice with conflicting TTLs (1 hour vs 6 hours), which produced PHP warnings on every request and was the proximate cause of the prior failed run. The canonical version is `'smr_redirect_rules'` with a 6-hour TTL (redirects rarely change).

### 2. `SMR_Redirect_Resolver` (`includes/class-redirect-resolver.php`)

- `do_discover()` now actually calls `SMR_Redirect_Sources::get_all()` instead of returning `array()`. A lazy `require_once` loads the sources class so activation order no longer matters.

### 3. `SMR_Redirect_Sources` (`includes/class-redirect-sources.php`)

Per-source error handling so a broken `.htaccess` cannot poison the Redirection plugin discovery, and vice versa.

- New error codes:
  - `ERR_HTACCESS` — `.htaccess` could not be read or parsed.
  - `ERR_REDIRECTION_PLUGIN` — the Redirection plugin's DB query failed.
  - `ERR_CORE` — core redirect discovery (canonical + runtime-detected) failed.
  - `ERR_RECORD` — a single malformed record was skipped during discovery.
- `from_htaccess()` now wraps the read + parse in a try/catch. Each individual `Redirect` / `RedirectMatch` / `RewriteRule` line is independently guarded, so one bad line never aborts the rest of the scan. A missing `get_home_path()` is gracefully handled.
- `from_redirection_plugin()` now whitelists the table name (only `wp_redirection_items` / `wp_redirection` are allowed in the SQL), guards the `SHOW TABLES` lookup in its own try/catch, and wraps each row normalisation in try/catch so a single malformed row is logged + skipped.
- `from_core_hooks()` wraps runtime-detected redirects in try/catch and falls back to the canonical rule on full failure so the legend still has at least one core row.
- `make_record()` now coerces `status` to a known redirect code (301/302/303/307/308) and clamps `priority` to 1..99 so an attacker-controlled row can't push a non-HTTP status or absurd priority into the REST payload.

### 4. `SMR_Logger` (`includes/class-logger.php`)

- New `sanitize_context()` allow-list (`urls`, `key`, `type`). Exception classes, exception messages, file paths, and line numbers are stripped before they reach the admin UI / REST payload, so internal paths and stack frames can't leak. The full context still goes to the PHP error log via `write()`.

### 5. `SMR_Admin_Page` (`admin/class-admin-page.php`)

- `error_messages()` map now includes the four new error codes with user-facing copy.
- d3.js upgraded to 7.8.5 with sha384 SRI hash, `in_footer`, `defer` strategy. Hash regeneration command documented inline for future bumps.
- Orphaned `includes/class-admin.php` removed (its role was folded into this class in the IA-24-2 work).

## Acceptance criteria coverage

| Criterion                          | Evidence                                                                                                  |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Graceful error handling           | Per-source try/catch in `SMR_Redirect_Sources`; outer try/catch in `discover()` and `safe_payload()`.    |
| User-friendly error messages       | New error codes → `error_messages()` map (admin UI + JS bundle share the same strings).                  |
| Proper logging of errors           | `SMR_Logger::exception()` + `record_last_error()` are called on every failure path with structured context. |
| Fail-safe behavior for edge cases  | Plugin returns valid data even when cache writes, DB reads, or .htaccess reads fail.                     |
| Error recovery mechanisms          | `last_error` is recorded for the admin UI; `clear_last_error()` resets state after a successful rebuild. |

## Verification

### PHPCS

`wp smr-phpcs --report=summary` runs cleanly. The duplicate-constant PHP warnings are gone. Remaining PHPCS output is style-only (alignment, doc comments, missing newlines) — no fatal errors.

### Indexer pipeline

`wp sitemap-redirects reindex` succeeds:

```
Success: Re-indexed site: 26 URLs in tree.
```

### REST API

`GET /wp-json/sitemap-redirects/v1/tree` returns:

```
Tree nodes: 34
Redirects: 6
Last error: None
Last index: 2026-08-10 10:55:27
```

6 redirects confirms `SMR_Redirect_Sources::get_all()` is wired through `SMR_Redirect_Resolver::do_discover()` and that the per-source try/catch paths didn't suppress data.

### Failure-path proof (cache write failure)

`POST /wp-json/sitemap-redirects/v1/reindex` with a transient-write failure returns:

```
Tree nodes: 34
Redirects: 6
Last error: {
  'time': '2026-08-10 10:59:28',
  'code': 'index_cache_write_failed',
  'message': 'Could not save the site map cache. The next page load will try again.',
  'context': {'key': 'smr_index_tree'}
}
Last index: 2026-08-10 10:59:28
```

The cache write failed but the response still succeeded with the full payload. The `last_error` is surfaced with a user-facing message, and the sanitize allow-list stripped the internal context down to the safe `key`.

## Commits

- `8a0526b` — IA-69: harden SMR redirect sources with per-source error handling and SRI for d3.js
- `7aa3a21` — IA-69: sanitize SMR_Logger last-error context to a safe allow-list