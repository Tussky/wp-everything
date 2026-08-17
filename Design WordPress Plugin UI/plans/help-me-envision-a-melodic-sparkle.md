# WordPress "Spotlight" Search Plugin — UI/UX Plan

## Context

The user wants to envision the frontend for a WordPress admin plugin that searches across many
facets of the WP ecosystem, presented as an experience heavily inspired by **Apple Spotlight**.
A single query fans out across four heterogeneous data sources; only facets with matches appear.
This is a **frontend demo only** — no real backend — so we fabricate truthful, realistic WP data
and filter it live in the browser. Chosen direction: **dark glass panel + right-hand preview pane**,
with pastel per-facet highlights, tasteful bold/italic typography, and inline data widgets.

The four facets and what each result must convey:

1. **Users** (`wp_users`) — username, display/admin info, and credential detail (role/capabilities,
   masked password hash, email, last login).
2. **Plugins** — plugin name, activation status, and version (plus update-available state).
3. **Options** (`wp_options`) — `option_name`, `option_value` (masked when protected, e.g. API keys),
   `autoload` flag, and a one-sentence plain-language explainer.
4. **Settings** — the most technical: indexes WP + plugin settings-page source (the html/php/css an
   admin sees). A result shows the **highlighted matching snippet**, its **breadcrumb location**, and
   **which plugin/core area** it comes from.

## Aesthetic direction

- **Stance:** faithful Apple Spotlight recreation (kinetic/Apple-reveal feel). Full commitment, dark glass.
- **Ground:** a subtle macOS-style wallpaper backdrop (CSS gradient/mesh, no external image dependency)
  with the Spotlight panel floating centered, heavy `backdrop-blur`, translucent dark fill, hairline
  border, large corner radius, soft drop shadow.
- **Fonts (Google, faithful SF substitutes):**
  - `Inter` — UI + body (SF Pro stand-in). Use its bold + italic weights for the "tasteful bold facing
    and italics."
  - `Geist Mono` (mono) — option names/values, version numbers, credential hashes, code snippets.
  - Wired via CSS2 `@import` at the very top of `src/index.css` (before all other statements).
- **Pastel facet palette** (highlight chips, category headers, selection tint):
  - Users → lavender, Plugins → mint, Options → peach/apricot, Settings → sky blue.
  - Each facet gets a soft translucent chip bg + slightly brighter text/icon on dark glass.
- **Micro-craft:** live filtering, arrow-key + hover selection, animated selection tint, `⌘`-style
  key hints in the footer, custom hidden scrollbars, match text emphasized (bold) within snippets.

## Approach

Single-page composition built by extending `src/App.tsx` (the documented entrypoint). Tailwind v4
utilities for layout; design tokens + fonts + wallpaper defined in `src/index.css`. Data and small
presentational widgets split into a few local modules for clarity. No router, no backend.

### Files

- `src/index.css` — add Google Font `@import`s (top), `@theme` tokens for the pastel facet colors and
  glass surface variables, wallpaper background, hidden-scrollbar utility, selection color.
- `src/App.tsx` — top-level Spotlight shell: wallpaper backdrop + centered floating panel; owns query
  state, flattened+grouped filtered results, keyboard navigation (↑/↓/⌘ scoping/Enter), and the
  active-selection index. Composes the search bar, results list, and preview pane.
- `src/spotlight/data.ts` — the fabricated but truthful dataset for all four facets (typed). Realistic
  WP content: e.g. plugins like *WooCommerce 8.9.1 (active)*, *Yoast SEO 23.4*, *Akismet Anti-Spam*;
  options like `siteurl`, `blogname`, `active_plugins`, `woocommerce_stripe_settings` (protected API
  key masked), each with `autoload` + explainer; users like `admin` / `editor_jane` with roles,
  capabilities, masked `$P$B...` hashes; settings snippets from *Settings → General*, *WooCommerce →
  Payments*, *Yoast → Search Appearance* with the surrounding html/php/css line and a match target.
- `src/spotlight/types.ts` — shared facet types + a `FacetKey` union and per-facet theme metadata
  (label, pastel token, icon).
- `src/spotlight/search.ts` — pure filtering helper: given a query, returns per-facet matches (facet
  omitted entirely when empty), with match ranges for snippet highlighting. Reuses a single tokenizer.
- `src/spotlight/widgets.tsx` — small inline widgets used in list rows and the preview pane:
  - `RoleBadge`, `CapabilityMeter`, masked `CredentialField` (reveal-on-hover dots) — Users.
  - `StatusPill` (Active/Inactive), `VersionBadge`, `UpdateDot` — Plugins.
  - `AutoloadChip`, `ProtectedValue` (lock + masked mono), `OptionExplainer` — Options.
  - `SnippetHighlight` (renders code/html with the matched substring bolded), `SourceBadge`,
    `Breadcrumb` — Settings.
  - `FacetChip` + `FacetHeader` shared by the list.

### Layout / interaction

- **Panel:** ~760px wide, two columns — left results list (grouped, scrollable), right preview pane
  showing the rich widget detail for the currently selected result. Search bar spans the top with a
  magnifier glyph and blinking-caret feel; footer strip shows facet counts + key hints.
- **Empty query:** show a "Top hits / recents" style default grouping so the demo looks alive.
- **Grouping:** results rendered per facet in a fixed order (Top Hits → Users → Plugins → Options →
  Settings), each section only rendered when it has ≥1 match; pastel `FacetHeader` with count.
- **Selection:** first result auto-selected; ↑/↓ moves across the flattened list, Enter is a no-op
  demo action (subtle confirmation flash), hover also selects. Selected row gets its facet's pastel
  tint; preview pane reflects it.
- **Responsive:** below ~1000px the preview pane collapses under the list (single column) so the panel
  stays usable; wallpaper and panel padding adjust.

## Verification

- Dev server is already running on `$PORT`; open the preview.
- Type representative queries and confirm correct facet fan-out + omission of non-matching facets:
  - `admin` → Users (+ maybe Options `active_plugins`).
  - `woo` → Plugins (WooCommerce), Options (`woocommerce_*`), Settings (Payments snippet).
  - `api` / `stripe` → Options showing a **protected/masked** value, Settings snippet.
  - `blogname` → Options with autoload + explainer widget.
  - a term in a settings page → Settings result with bolded matched snippet + source plugin badge.
- Confirm keyboard nav (↑/↓ selection, preview updates), hover selection, pastel facet coloring,
  bold/italic emphasis, masked credentials/protected options, and the ~1000px responsive collapse.
- No build/typecheck needed for this localized, self-contained UI unless an error surfaces.
