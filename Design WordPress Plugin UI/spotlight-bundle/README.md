# Spotlight Search — portable bundle

Everything needed to reproduce the Spotlight Search frontend on any site. No
build step, no framework, no backend, no Vite.

## Contents

| File | Purpose |
|------|---------|
| `spotlight.js` | The entire app — styles, sample data, search, and rendering — in one vanilla JS file. It injects its own CSS and mounts itself. |
| `spotlight-data.json` | The search data as a standalone, match-optimized export (see its `_meta` block for the schema). |
| `index.html` | A minimal standalone page that loads `spotlight.js`. |

## Facets

A **facet** is one category of searchable thing. Spotlight fans a single query
out across all facets at once and shows only the facets that have matches, each
grouped under its own heading. This build ships four:

- **Users** — the user database: admin info, credentials, and username.
- **Plugins** — each plugin, whether it is activated, and its version.
- **Options** — a `wp_options` row: the option, its value (unless protected, like
  an API key), the autoload flag, and a one-sentence explainer.
- **Settings** — WordPress and plugin settings (the active HTML/PHP/CSS an admin
  sees): the matching snippet, its location, and which plugin/area it came from.

### Record structure (what a backend must produce)

Every record — regardless of facet — shares the same envelope so the frontend can
search and rank without special-casing each facet:

```jsonc
{
  "id": "p-1",                 // globally unique, prefixed by facet (u-, p-, o-, s-)
  "facet": "plugins",          // users | plugins | options | settings
  "search": {
    "terms": ["WooCommerce", "woocommerce/woocommerce.php", "ecommerce"],
    "weight": 100              // higher sorts first within its facet
  },
  "display": { /* facet-specific fields the UI renders */ }
}
```

Rules that keep it working well with a backend:

- **`search.terms` is the only text the matcher scans.** Put every string a user
  might type here (names, slugs, emails, capabilities, keywords). The frontend
  never inspects `display` for matches, so a field is searchable *only* if it
  appears in `terms`.
- **`search.weight` orders results within a facet** (use `100` for exact-name
  assets, lower for peripheral matches). Ordering *between* facets is fixed by
  `_meta.facetOrder`.
- **`display` is decoupled and facet-shaped.** Users carry
  `username`/`displayName`/`role`/`capabilities`, plugins carry
  `name`/`active`/`version`, options carry `name`/`value`/`autoload`/`explainer`,
  settings carry `source`/`breadcrumb`/`language`/`snippet`. Change what shows
  without touching the index.
- **Never leak secrets.** For protected records (API keys, password hashes) set
  `protected: true`, send `value: null`, and keep the secret out of
  `search.terms`. The UI masks it and still lets the name/explainer match.

The full field list per facet and the collection APIs live in
`../wp-spotlight-search/data/facets-plan.md`; `spotlight-data.json`'s `_meta`
block documents the same schema inline.

## Run it (standalone)

Open `index.html` in a browser, or serve the folder:

```bash
npx serve .        # or: python3 -m http.server
```

That's it — `spotlight.js` ships with sample data built in, so it works with
zero configuration.

## Drop it into another site

Add the script and (optionally) a mount point:

```html
<div id="wpss-root"></div>
<script src="/path/to/spotlight.js"></script>
```

If `#wpss-root` is missing, the script falls back to `#root` or creates its own
container, so the `<script>` tag alone is enough.

## Use your own data

`spotlight.js` reads `window.WPSS_DATA` if it is defined **before** the script
runs; otherwise it uses its built-in sample data. Provide your own by either
inlining it or loading `spotlight-data.json`:

```html
<script>
  fetch("/path/to/spotlight-data.json")
    .then(function (r) { return r.json(); })
    .then(function (data) { window.WPSS_DATA = data; });
</script>
<script src="/path/to/spotlight.js"></script>
```

> Note: the built-in sample data is a flat shape, while `spotlight-data.json`
> uses the richer `{ id, facet, search, display }` envelope. If you feed the
> JSON in, adapt the matcher to read `record.search.terms` and `record.display.*`
> (the JSON's `_meta` documents every field).

## Keyboard

- `↑` / `↓` — move selection
- `↩` — open (demo flash)
- `esc` — clear the query
