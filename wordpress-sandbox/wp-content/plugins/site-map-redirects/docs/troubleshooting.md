# SiteMap Redirects — Troubleshooting Guide

## Map will not load

### Symptom

The admin page shows the message **"We couldn't load the site map. Please try again, or reindex."**

### Likely causes and fixes

1. **You are not logged in or do not have permission.**
   - The admin page requires `manage_options`.
   - The REST endpoint requires at least `read` (or `smr_public_read` to be filtered `true`).
   - Re-log in and try again.

2. **REST API is blocked.**
   - SiteMap Redirects calls `/wp-json/sitemap-redirects/v1/tree`. Some security plugins block custom REST routes.
   - Whitelist routes under `sitemap-redirects/v1` in your firewall/security plugin.
   - Check **Settings → Permalinks** is saved at least once so pretty permalinks are enabled.

3. **JavaScript error in the admin UI.**
   - Open the browser console and look for errors.
   - If D3.js is blocked by your CSP, allow `d3js.org` as a script source or use the `script_loader_src` filter to host D3 locally.

4. **Transient cache is corrupted.**
   - Click **Re-index site** or run:
     ```bash
     wp sitemap-redirects reindex
     ```

## Map is empty

### Symptom

The tree only shows the homepage, or it has no nodes at all.

### Likely causes and fixes

1. **No public content yet.**
   - Publish at least one post or page with a public permalink.

2. **Permalinks are set to "Plain".**
   - With plain permalinks (`?p=123`) the tree is less useful because every URL uses query parameters.
   - Use a pretty permalink structure under **Settings → Permalinks**.

3. **Custom post types are not public.**
   - The indexer only collects post types with `public => true`.
   - Verify the post type arguments in your theme or plugin.

4. **Memory limit hit during indexing.**
   - For very large sites (1000+ URLs) increase `WP_MEMORY_LIMIT` and `max_execution_time`.
   - Consider increasing the batching limit by filtering `get_posts`/`get_terms` if you have installed a custom batching filter.

## Redirects are missing

### `.htaccess` redirects do not appear

1. **Apache is not used.**
   - `.htaccess` rules are read-only and only parse Apache directives. If you use Nginx + FPM, these rules do not exist at the WordPress layer.

2. **`.htaccess` is unreadable or too large.**
   - SiteMap Redirects caps the file at 256 KiB and skips lines longer than 1,024 characters.
   - Make sure the WordPress user can read the `.htaccess` file or reduce its size.

3. **Rule format is unsupported.**
   - Supported: `Redirect`, `RedirectPermanent`, `RedirectTemp`, `RedirectSeeOther`, `RedirectMatch`, and `RewriteRule ... [R=xxx]`.
   - `RewriteCond` chains or `mod_alias` flags not including `R` are ignored.

4. **Virtual-host or server-level config.**
   - Redirects in the main Apache/Nginx virtual-host config are outside WordPress and cannot be read by the plugin.
   - Move the rules to `.htaccess` or document them manually in the debug table.

### Redirection plugin redirects do not appear

1. **Redirection plugin is not installed or activated.**
   - The plugin detects the `{prefix}redirection_items` or older `{prefix}redirection` table.

2. **Rules are disabled or have a different action type.**
   - SiteMap Redirects reads rows where `status = 'enabled'` and `action_type = 'url'` or `'pass'`.

3. **Database permissions.**
   - If Redirection's table exists but the MySQL user cannot read it, check the PHP error log for `[smr]` entries.

### WordPress core canonical redirects do not appear

1. **Only one canonical rule is shown by default.**
   - The plugin injects a curated canonical rule (`*`) that describes trailing-slash and permalink normalization.
   - Additional runtime `wp_redirect()` calls are only captured if `smr_detected_redirects` is populated by a companion hook or future module. If this is missing, only the generic canonical rule is shown.

2. **Theme/plugin is bypassing `wp_redirect()`.**
   - Core canonical redirect discovery relies on standard WordPress hooks. Custom rewrite/redirect logic may not be visible.

## Performance issues on large sites

### Symptom

Re-indexing times out or the tree renders slowly.

### Fixes

1. **Run reindex via WP-CLI.**
   - CLI avoids HTTP time limits:
     ```bash
     wp sitemap-redirects reindex
     ```

2. **Increase PHP limits temporarily.**
   - Add to `wp-config.php`:
     ```php
     define( 'WP_MEMORY_LIMIT', '512M' );
     set_time_limit( 300 ); // For admin requests only.
     ```

3. **Reduce the number of indexed post types/taxonomies.**
   - Use the post-type registration or a custom filter to limit private post types that should not appear in the map.

4. **Enable lazy tree rendering if available.**
   - The D3 tree supports progressive rendering. If your release includes lazy loading, only expanded branches are drawn.

## Conflicts with other plugins

### Security / firewall plugins

- Look for plugin settings that block custom REST namespaces.
- Whitelist the `sitemap-redirects/v1` namespace.

### Caching plugins

- Object caches can store stale transients. Clear object cache after major content changes.
- The plugin stores tree data in transients (`smr_index_tree`) and redirect rules in `smr_redirect_rules`.

### Multisite

- Transients are site-specific. Switch to the correct site before running diagnostics.
- On plugin deletion, the uninstall routine removes per-site options, transients, and per-user meta across all blogs.

## Error notices

If the plugin shows an admin notice:

- Read the message carefully — it is designed to be user-facing.
- Check the PHP error log for `[smr]` prefixed lines for technical detail.
- Dismiss the notice if the problem is understood; it will reappear if the same error happens again.

## FAQ

### Does the plugin modify `.htaccess`?

No. It reads `.htaccess` only. All `.htaccess` edits must be made manually or by another plugin.

### Does the plugin create redirects?

The v1.0.0 tree supports drag-and-drop redirect creation if the redirect-management feature is enabled by the UI bundle. Custom redirects are stored in WordPress options. If your install does not show the toolbar buttons, the feature is being prepared for a follow-up release.

### How do I completely remove the plugin data?

Deactivate the plugin, then delete it through **Plugins → Delete**. The uninstall handler removes:

- Options: `smr_last_index`, `smr_detected_redirects`, `smr_last_error`, `smr_current_error`.
- Transients: `smr_index_tree`, `smr_redirect_rules`.
- User meta: `smr_dismiss_last_error`.

### Where is the debug log?

SiteMap Redirects writes log lines using WordPress `error_log()`. On most hosts this goes to the PHP error log. The line format is:

```
[smr] ERROR message here | context={"key":"value"}
```

## Still stuck?

1. Reproduce the issue while watching:
   - Browser console (JS errors).
   - Network tab (REST calls to `/wp-json/sitemap-redirects/v1/tree`).
   - PHP error log (`[smr]` entries).
2. Note the error code shown in the admin notice or REST payload.
3. Open an issue with those details:
   https://github.com/Tussky/paperclip-trial/issues
