# SiteMap Redirects — Developer Guide

## Plugin architecture

SiteMap Redirects is structured into small, single-responsibility classes. The bootstrap in `site-map-redirects.php` loads modules in dependency order:

1. `SMR_Logger` — structured logging and last-error storage.
2. `SMR_Safe` — defensive wrappers around WordPress APIs (transients, options, arrays).
3. `SMR_Indexer` — builds the nested URL-path tree.
4. `SMR_Redirect_Resolver` — coordinates discovery and caches the rule list.
5. `SMR_Redirect_Sources` — reads `.htaccess`, the Redirection plugin, and core canonical redirects.
6. `SMR_REST` — registers REST endpoints and assembles the API payload.
7. `SMR_Admin_Page` — admin menu, asset enqueue, and page shell.

## Coding conventions

- Follow the WordPress Coding Standards.
- Use `SMR_Safe` wrappers for transients and option writes.
- Always catch `Throwable` at module boundaries so a failure in one module cannot crash another.
- Record user-facing errors via `SMR_Logger::record_last_error()`.
- Escape output with `esc_html`, `esc_url`, `esc_attr`, etc.
- Sanitize REST args via `register_rest_route()` `args` schema.

## REST API

### `GET /wp-json/sitemap-redirects/v1/tree`

Returns the full admin payload: tree, redirects, counts, color legend, and last error.

#### Query parameters

| Name | Type | Default | Description |
|---|---|---|---|
| `force` | boolean | `false` | When `true`, bypasses the transient cache and rebuilds the tree. |

#### Example response

```json
{
  "tree": { ... },
  "redirects": [ ... ],
  "last_index": "2026-08-10 15:30:00",
  "home_url": "https://example.com/",
  "counts": { "nodes": 42, "redirects": 5 },
  "status_colors": {
    "301": "#d63638",
    "302": "#dcaa00",
    "307": "#996800",
    "308": "#8c1c1c",
    "other": "#50575e"
  },
  "version": "1.0.0",
  "last_error": null
}
```

### `POST /wp-json/sitemap-redirects/v1/reindex`

Drops the tree and redirect caches, rebuilds both, and returns the same payload as `/tree`.

Required capability: `manage_options`.

## WP-CLI

When WP-CLI is loaded, the plugin registers:

```bash
wp sitemap-redirects reindex
```

On success it prints:

```
Success: Re-indexed site: 42 URLs in tree.
```

## Actions and filters

### `smr_public_read`

Allow the `/tree` REST endpoint to be readable by unauthenticated visitors.

```php
add_filter( 'smr_public_read', '__return_true' );
```

Default: `false` (requires `read` capability).

### `smr_index_rebuilt`

Fires after a successful tree rebuild.

```php
add_action( 'smr_index_rebuilt', function ( array $tree ) {
    // Do something with the rebuilt tree, e.g. invalidate a CDN cache.
} );
```

### Future/extensible hooks

Modules are designed to accept additional sources without breaking existing ones. When adding a new redirect source, prefer to:

1. Add a new method to `SMR_Redirect_Sources`.
2. Normalize records with `SMR_Redirect_Sources::make_record()`.
3. Assign a priority number consistent with the real execution order.

## Data model

### Tree node

```php
[
    'name'     => string,  // URL segment or slug.
    'path'     => string,  // Full path, e.g. /blog/hello-world.
    'slug'     => string,
    'label'    => string,
    'type'     => string,  // home, post, page, cpt, taxonomy_*, author_archive, container, redirect_source.
    'url'      => string,
    'id'       => int,
    'editable' => bool,
    'children' => array,
    'redirects' => array, // Added by SMR_REST::annotate().
]
```

### Redirect record

```php
[
    'source_path'   => string,
    'source_url'    => string,
    'destination'   => string,
    'status'        => int,    // 301, 302, 303, 307, or 308.
    'type'          => string, // htaccess, htaccess_regex, htaccess_rewrite, redirection_plugin, wp_canonical, wp_redirect.
    'priority'      => int,    // 1 = highest.
    'regex'         => bool,
    'label'         => string,
    'explainer'     => string,
    'plain_english' => string,
]
```

## Admin JS contract

The server localizes `window.SiteMapRedirects` with:

```js
{
  restUrl:       string, // Endpoint base URL.
  nonce:         string, // wp_rest nonce.
  adminUrl:      string, // admin-post.php URL.
  labels:        object, // Translatable UI strings.
  errorMessages: object, // Code -> message map for friendly errors.
  lastError:     object|null
}
```

The JS bundle in `assets/dist/admin.js` mounts into `#smr-app` and consumes this contract.

## Adding new translatable strings

All user-facing strings use `__( ..., 'site-map-redirects' )` or `_e( ..., 'site-map-redirects' )`. After editing strings, regenerate the `.pot` file:

```bash
# From the WordPress sandbox:
wp i18n make-pot /var/www/html/wp-content/plugins/site-map-redirects

# Or from the repo, if WP-CLI is not available, use the bundled helper:
python3 scripts/extract-strings.py \
  --dir wordpress-sandbox/wp-content/plugins/site-map-redirects \
  --out wordpress-sandbox/wp-content/plugins/site-map-redirects/languages/site-map-redirects.pot
```

## PHPCS

The plugin uses WordPress-Extra and WordPress-Docs, configured in `phpcs.xml`. To run:

```bash
wp smr-phpcs /var/www/html/wp-content/plugins/site-map-redirects
```

Or directly with a local PHPCS install:

```bash
phpcs --standard=WordPress-Extra --extensions=php /var/www/html/wp-content/plugins/site-map-redirects
```

## Security notes

- REST write operations require `manage_options`.
- `.htaccess` parsing is read-only, size-capped to 256 KiB, and line-length-capped to 1,024 characters.
- Redirect status codes are whitelisted to `{301, 302, 303, 307, 308}`.
- D3.js is loaded from `d3js.org` with a Subresource Integrity (SRI) hash.
- No personal data leaves the site; no telemetry or phone-home.

## Contributing

1. Open a branch from `main`.
2. Keep module boundaries; do not let exceptions escape.
3. Update docs and translation template when UI strings change.
4. Run PHPCS and manual WP-CLI tests before marking a PR ready.
