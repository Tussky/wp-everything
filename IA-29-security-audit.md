# Security Audit Report — SiteMap Redirects Plugin

**Audit date:** 2026-08-10
**Auditor:** Marko Ebner (Coder agent, IA-29)
**Plugin:** SiteMap Redirects v0.1.0
**WordPress:** 6.4+ / PHP 7.4+
**Scope:** `wordpress-sandbox/wp-content/plugins/site-map-redirects/` (PHP + JS)

## Executive Summary

A targeted security review was performed against the SiteMap Redirects
WordPress plugin (v0.1.0). The audit focused on the seven risk areas called
out in the issue description: input validation, output escaping, capabilities
checks, CSRF protection, SQL injection, XSS, and an overall vulnerability
assessment.

**Overall posture:** The plugin already implements most WordPress security
best-practices — capabilities checks on every admin page and REST endpoint,
`wp_nonce` / `check_admin_referer` on form submissions, `esc_html` /
`esc_url` on every dynamic output, and `permission_callback` on the two
REST routes. The JS bundle avoids `innerHTML` and `eval` entirely, opting for
`textContent` + `setAttribute` with a URL allow-list for `<a href>`.

**Findings:** Two medium-severity issues and several low-severity
hardening opportunities were identified. **All issues are now fixed in
this commit.** The fixes are listed in §4.

## 1. Files audited

| File                                          | Lines | Notes |
|-----------------------------------------------|-------|-------|
| `site-map-redirects.php`                      | 99    | Plugin bootstrap, constants |
| `includes/class-logger.php`                  | 155   | Log + last-error writer |
| `includes/class-safe.php`                     | 161   | Safe wrappers around WP APIs |
| `includes/class-indexer.php`                 | 545   | Site tree indexer |
| `includes/class-redirect-resolver.php`       | 140   | Redirect rule discovery shell |
| `includes/class-redirect-sources.php`        | 525   | .htaccess / Redirection-plugin discovery |
| `includes/class-rest.php`                     | 360   | REST routes `/tree` + `/reindex` |
| `admin/class-admin-page.php`                 | 301   | Top-level admin page |
| `assets/dist/admin.js`                       | 694   | Tree render + REST client |
| `assets/dist/admin.css`                      | —     | Styles (no security impact) |

## 2. Methodology

- Manual code review of every PHP file under the plugin directory, with
  grep passes for high-risk sinks: `$_GET` / `$_POST` / `$_REQUEST` /
  `$_SERVER` / `$_COOKIE`, `eval`, `innerHTML`, raw `wpdb->query` /
  `get_var` / `get_results` calls, `file_get_contents`, `preg_*`, and
  output sinks missing an escape helper.
- Cross-checked the JS bundle for DOM-injection sinks
  (`innerHTML`, `outerHTML`, `document.write`, `eval`, `insertAdjacentHTML`).
- Walked every request path (admin page, admin-post, REST route, CLI,
  WP-Cron via activation hook) end-to-end.
- Verified that error paths do not bypass capability or nonce checks.

## 3. Findings

### F1 — Duplicate `define()` calls in plugin bootstrap (MEDIUM)

**Location:** `site-map-redirects.php` lines 33–37 (pre-fix)

The bootstrap declared `SMR_TRANSIENT_RULES` and `SMR_TRANSIENT_RULES_TTL`
twice — once with the canonical values (`smr_redirect_rules`,
`HOUR_IN_SECONDS`) and once again with different values
(`smr_index_rules`, `6 * HOUR_IN_SECONDS`). PHP emits `E_WARNING` for the
duplicate defines and the second value silently wins at runtime.

**Impact:** The earlier commit (`750d03d`) intended to dedupe the
constants but added the new defines *before* the duplicates instead of
removing the duplicates. The intended constants never took effect.

**Risk:** Any code path that depends on the canonical values (1-hour
rule cache TTL, key name `smr_redirect_rules`) was using the wrong
values. The cache hit rate on `smr_redirect_rules` was effectively zero
because the writes were going to a different key, which silently
defeats caching and could allow stale redirects to linger longer than
expected.

**Severity:** Medium — functional bug that masks a denial-of-service
amplification (every `/tree` call re-discovers rules) and a
cache-poisoning risk if the orphaned key is later repurposed.

**Fix applied:** Removed the duplicate defines. The bootstrap now
declares each constant exactly once with the intended value.

### F2 — Dead duplicate admin page registration (LOW–MEDIUM)

**Location:** `includes/class-admin.php` (whole file)

The file defines `SMR_Admin` with its own `add_menu_page()` call using
the same slug as `SMR_Admin_Page`. It is not `require`'d from
`site-map-redirects.php`, so today it is dead code. If a future change
ever adds a `require` for it (or autoloads `includes/`), WordPress
will refuse the second `add_menu_page()` for the same slug, breaking
admin navigation.

**Risk:** Inert today, but a foot-gun for the next refactor.

**Fix applied:** Deleted the file. The canonical admin page is
`admin/class-admin-page.php` which is the only one loaded.

### F3 — `.htaccess` parser accepts pathological input (LOW)

**Location:** `includes/class-redirect-sources.php::from_htaccess()`

The pre-fix parser had two weaknesses:

1. No upper bound on `.htaccess` size — a multi-MB `.htaccess` would
   be fully read into memory and line-split before regex parsing.
2. The regexes used non-bounded `(.+?)` followed by greedy constructs
   that can exhibit catastrophic backtracking on crafted input.
3. The parsed status code was coerced to `(int)` but never validated
   against the standard redirect set (301/302/303/307/308); a non-HTTP
   number would be passed verbatim into the REST payload.

**Risk:** DoS via `.htaccess` content if a non-admin can write to the
file (multi-site, compromised theme). Also a small data-integrity
risk: the JS legend renders status colours keyed off the status code
as a string, so a junk status would render in the "other" bucket but
the badge label could still mis-render.

**Fix applied:**

- `.htaccess` reads now bail out if the file is >256 KiB.
- Each line is capped at 1024 chars before regex parsing.
- Status codes are validated against `{301, 302, 303, 307, 308}` before
  a redirect is added to the result.
- Regexes were tightened to anchored, non-backtracking forms that
  reject anything longer than `1024` chars.

### F4 — Redirection-plugin DB lookup uses LIKE without escaping wildcards (LOW)

**Location:** `includes/class-redirect-sources.php::from_redirection_plugin()`

The pre-fix code did:

```php
$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
```

where `$t = $wpdb->prefix . 'redirection_items'`. The WordPress table
prefix is normally `[a-z0-9_]+`, but a custom prefix containing `%` or `_`
would be interpreted as a LIKE wildcard — `_` matches any single char
and `%` matches any sequence, so a misconfigured prefix could match an
unrelated table and feed its columns into the plugin.

**Risk:** Negligible in practice (prefix configuration requires
`wp-config.php` write access, which already grants code execution)
but trivially exploitable in defense-in-depth terms.

**Fix applied:** Escape `\` / `%` / `_` in the prefix before passing to
`LIKE`, and additionally run the matched table name through
`esc_sql()` and the existing `in_array()` allow-list before using it in
the raw SELECT.

### F5 — `make_record()` accepted arbitrary `status` / `priority` integers (LOW)

**Location:** `includes/class-redirect-sources.php::make_record()`

The pre-fix code stored `(int) $status` and `(int) $priority` verbatim.
For HTTP status the JS would still bucket anything outside the known
set under "other", but for priority the JS sorts records by priority —
an attacker-controllable row (e.g. from a `.htaccess` file containing a
`R=99999`) could break the sort and push the legend off-screen.

**Fix applied:** `status` is coerced to one of
`{301, 302, 303, 307, 308}` (else `302`), and `priority` is clamped to
`[1, 99]` before being stored in the record.

### F6 — `SMR_Logger` last-error context may leak internal info (LOW)

**Location:** `includes/class-logger.php::record_last_error()`

`record_last_error()` stored the entire context array verbatim and the
context was rendered into the admin UI (`esc_html()` is applied at the
template boundary, which is safe). However the `smr_current_error`
option is also JSON-encoded into the REST `/tree` payload, which is
served to any logged-in user with `read` capability (and to anonymous
users when the `smr_public_read` filter is opted-in). Context
entries often contained exception class names, raw exception
messages, and full file paths — useful for debugging, but a slow
information disclosure for users who shouldn't see them.

**Fix applied:** `record_last_error()` now runs the context through a
sanitizer that only keeps a fixed allow-list of safe keys
(`urls`, `key`, `type`). The full context still goes to the PHP error
log (via `write()`) so debugging is unaffected.

### F7 — `class-admin.php` registered a duplicate menu slug (LOW)

Covered by F2. The duplicate-slug risk is the reason F2 is more than
just a code-cleanliness issue.

### F8 — REST `read` permission defaults to logged-in read (INFORMATIONAL)

**Location:** `includes/class-rest.php::read_perm()`

The `/tree` endpoint requires `read` capability by default, but
exposes the tree publicly if the `smr_public_read` filter returns
`true`. This is opt-in and intentional, but the surface should be
documented.

**Fix applied:** None required. The filter is named clearly and is the
single documented entry point for public-tree mode.

## 4. Fixes shipped in this audit

| Fix | Files touched | Finding(s) addressed |
|-----|--------------|----------------------|
| Deduped `SMR_TRANSIENT_RULES` defines | `site-map-redirects.php` | F1 |
| Deleted dead `class-admin.php` | `includes/class-admin.php` (removed) | F2, F7 |
| `.htaccess` parser hardened (size cap, line cap, status whitelist, tightened regexes) | `includes/class-redirect-sources.php` | F3 |
| LIKE-pattern wildcard escaping + `esc_sql` on whitelisted table name | `includes/class-redirect-sources.php` | F4 |
| Status / priority coercion in `make_record()` | `includes/class-redirect-sources.php` | F5 |
| `SMR_Logger` context sanitized to allow-list | `includes/class-logger.php` | F6 |

## 5. Acceptance criteria checklist

- [x] **Input validation on all user inputs** — Plugin accepts no
  user-supplied data on the request paths. The only "input" is the
  `force` boolean on `/tree` (typed as `boolean` in `register_rest_route`,
  validated by `permission_callback`). The Redirection-plugin DB row
  values flow into `make_record()` which now coerces status / priority
  and stringifies source / destination.
- [x] **Output escaping for all dynamic content** — All PHP templates
  use `esc_html` / `esc_attr` / `esc_url`. The JS bundle uses
  `textContent` and `setAttribute`. Last-error messages in the admin
  notice are escaped with `esc_html`; last-error context in the REST
  payload is now allow-listed before storage.
- [x] **Proper capabilities checks for admin functions** —
  `manage_options` is checked at the top of every admin render
  (`render_page`, `maybe_render_error_notice`, `handle_dismiss`)
  and at the `permission_callback` of `/reindex`. `/tree` uses
  `read` capability (or public-read opt-in).
- [x] **CSRF protection on form submissions** — The dismiss-notice
  admin-post handler uses `check_admin_referer( 'smr_dismiss_last_error' )`
  on a `wp_nonce_url`. REST `/reindex` is gated by
  `permission_callback` which requires `manage_options` and the
  REST nonce supplied by `wp_create_nonce('wp_rest')` and shipped
  to JS via `wp_localize_script`.
- [x] **SQL injection prevention** — Only one raw-SQL site exists
  (Redirection plugin lookup) and now goes through LIKE-wildcard
  escaping + allow-list + `esc_sql`. All other DB calls go through
  `get_option` / `update_option` / `delete_option` / `get_transient` /
  `set_transient` which are prepared internally by WordPress.
- [x] **XSS vulnerability assessment** — No `innerHTML`, no `eval`,
  no `document.write`. URLs are allow-listed at the
  `setAttribute('href', …)` site.
- [x] **Security audit report with findings and fixes** — This document.

## 6. Residual risks

- The plugin reads `.htaccess` directly. On a server where another
  process (e.g. a compromised theme) can write to `.htaccess`, the
  parser can be coerced into reading attacker-supplied rules. The
  hardening in F3 limits the blast radius but cannot eliminate it.
  Recommendation: add a `wp_options` flag that lets admins opt out
  of `.htaccess` reading entirely.
- The `smr_public_read` filter exposes the entire site tree
  (including redirect source / destination URLs) to anonymous
  visitors. This is intentional but should be called out in the
  plugin's user-facing documentation so admins understand the
  implications of returning `true`.
- `SMR_Logger::record_last_error()` context allow-list is small and
  may need to grow as new error sites are added. Future contributors
  adding context keys must extend `SAFE_CONTEXT_KEYS` deliberately
  rather than widening it by default.

## 7. Out of scope

- A full WPScan-style automated scan was not run; the audit is manual
  code review.
- The third-party `redirection` and `admin-search` plugins in the same
  WordPress install were not in scope.
- The `assets/dist/admin.js` was reviewed for DOM sinks only; the
  D3.js bundle is loaded via SRI hash from `https://d3js.org/d3.v7.min.js`
  and was not audited itself.