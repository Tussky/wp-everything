# Spotlight Search — Facets & Data Procurement Plan

This document describes the four search facets and, for each, an **elementary**
architecture for collecting its data inside WordPress. It is a companion to
[`spotlight-data.json`](./spotlight-data.json), which defines the exact shape
each collected record must be transformed into before it reaches the frontend.

The collection layer runs server-side (PHP), assembles each facet, maps it onto
the JSON envelope (`id`, `facet`, `search.terms`, `search.weight`, `display`),
and hands the result to the browser via `wp_localize_script( 'wpss-spotlight',
'WPSS_DATA', $payload )`. **Protected values must never enter `search.terms` or
`display.value`** — mask or drop them during collection.

---

## The facets

- **Users** — the user database displaying admin info, credentials, and username.
- **Plugins** — the plugin database: each plugin, whether it is activated, and its version.
- **Options** — the `wp_options` table: the option, its value (unless protected, like an API key), the autoload flag, and a one-sentence explainer of what it does.
- **Settings** — WordPress settings and plugin settings (the active HTML/PHP/CSS an admin sees). Results show the highlighted part of the page that matches and which plugin/area it comes from.

---

## 1. Users

**Where it lives:** `wp_users` (core identity) + `wp_usermeta` (roles,
capabilities, last-login if tracked). WordPress exposes this without touching
tables directly.

**How to procure (general):**
- Query accounts with `get_users()` / `WP_User_Query`.
- For each `WP_User`: read `user_login`, `display_name`, `user_email`, and
  `->roles` for the role label; pull the capability list from `->allcaps` (keep
  the granted ones).
- Registration date comes from `user_registered`. "Last login" is **not** a core
  field — either omit it or read a custom meta key your plugin records on the
  `wp_login` hook.

**What to collect → `display`:** `username`, `displayName`, `email`, `role`,
`capabilities[]`, `registered`, `lastLogin`, `avatarHue` (derive locally).
**`search.terms`:** username, display name, email, role, and capabilities.
**Credentials note:** never send the password hash to the browser. The demo's
`passwordHash` is illustrative only — for a real plugin, drop it or show a
non-secret status (e.g. "password last changed").

---

## 2. Plugins

**Where it lives:** the plugins directory on disk, plus the `active_plugins`
option and the update transient. Load `wp-admin/includes/plugin.php` if calling
these outside an admin screen.

**How to procure (general):**
- `get_plugins()` returns every installed plugin keyed by its file
  (`slug/main.php`) with `Name`, `Version`, `Author`, `Description`.
- `is_plugin_active( $file )` gives the activated boolean.
- `get_site_transient( 'update_plugins' )` — its `->response` map keyed by plugin
  file tells you which have an update and the `new_version`.

**What to collect → `display`:** `name`, `slug` (the plugin file), `active`,
`version`, `updateAvailable` (or `null`), `author`, `description`.
**`search.terms`:** name, slug, author, description keywords, version.

---

## 3. Options

**Where it lives:** the `wp_options` table (`option_name`, `option_value`,
`autoload`).

**How to procure (general):**
- Read individual options with `get_option( $name )`.
- To enumerate, use the autoloaded set via `wp_load_alloptions()`, or query
  `wp_options` with `$wpdb` for the `autoload` column. Curate a whitelist of
  meaningful options rather than dumping all of them.
- `autoload` ("yes"/"no") comes straight from the table row.
- The one-sentence **explainer** is authored content — maintain a small lookup
  map from option name → description (core options are well-documented).

**What to collect → `display`:** `name`, `value`, `autoload`, `protected`,
`explainer`.
**`search.terms`:** option name + explainer keywords (and value **only when not
protected**).
**Protected values:** maintain a list/pattern of sensitive option names (API
keys, secrets, tokens — e.g. `*_api_key`, `*_stripe_settings`). For those, set
`protected: true`, `value: null`, and exclude the value from `search.terms`.

---

## 4. Settings

The most technical facet: an index of the settings screens (core + plugin) and
the markup/fields shown on them, so a match can surface the exact snippet and
its location.

**Where it lives:** the Settings API registries, populated as admin pages render:
- `global $wp_settings_sections` and `$wp_settings_fields` — sections and fields
  registered via `add_settings_section()` / `add_settings_field()`, keyed by page.
- `get_registered_settings()` (`WP_REST_Settings` / `register_setting`) for
  option-backed settings and their descriptions.
- Menu/breadcrumb context from the admin menu registries
  (`$menu` / `$submenu`) or the page slug.

**How to procure (general):**
- Walk the registered sections/fields per settings page to build records: each
  field's label and the option it controls give you the searchable text and the
  **breadcrumb** (page → section → field).
- The **snippet** is the rendered form control / markup for that field. The
  simplest approach is to capture the field's render callback output (buffer it)
  or store a representative snippet; tag it with `language` (`html`/`php`/`css`).
- Attribute each record to its **source** (core vs a specific plugin) using the
  page/slug that registered it, and set `sourceKind` accordingly.

**What to collect → `display`:** `source`, `sourceKind`, `breadcrumb[]`,
`language`, `snippet`, `matchField` (which field the highlight targets — usually
`"snippet"`).
**`search.terms`:** source name, breadcrumb parts, and the snippet text.

---

## Assembly checklist

1. Collect each facet with the APIs above (load `plugin.php` where needed).
2. Map every item to the JSON envelope; compute `search.terms` and a sensible
   `search.weight` (100 for exact-name assets, lower for peripheral matches).
3. Strip/mask all secrets before they leave the server.
4. `wp_localize_script( 'wpss-spotlight', 'WPSS_DATA', $payload )` — the frontend
   already prefers `window.WPSS_DATA` over its built-in sample data.
