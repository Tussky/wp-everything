# Design System — WordPress Admin Search Plugin

Design system and UI specification for the **Admin Search** plugin (slug `admin-search`),
a unified search across the WordPress admin interface.

Target issues: [IA-48](/IA/issues/IA-48) (design) and [IA-47](/IA/issues/IA-47) (build).

---

## 1. Design Principles

| Principle | How it shows up |
|---|---|
| **Recognition over recall** | Every result announces *what it is* (type chip: Settings / User / Product / Content) so users recognize the item without having to remember its URL or menu location. |
| **Progressive disclosure** | Search page shows one focused input first; type facets, result counts, and breadcrumbs unhide only as the user needs them. Never overwhelm first paint. |
| **Jakob's Law** | Reuse native WordPress admin affordances — `#wpadminbar`, `.wrap`, `.button`, `dashicons`, the WP color scheme — so the plugin feels like a built-in admin feature, not a third-party app. |
| **Fitts's Law** | Large, easy-to-hit input and result rows; keyboard-first navigation (arrow keys + Enter) so power users never leave the keyboard. |
| **Gestalt proximity** | Results grouped by content type so per-group meaning is legible at a glance; related actions sit next to the names they act on. |
| **Doherty Threshold** | Debounced-as-you-type search with sub-200 ms perceived response; skeleton/empty states never show a loop spinner that hides progress. |
| **Aesthetic-usability** | Clean admin type scale, generous spacing, subtle shadows and one accent color for actions — no decoration without purpose. |
| **Accessibility (WCAG 2.1 AA)** | Full keyboard operability, focus-visible rings at 3:1 against adjacent, minimum 4.5:1 text contrast, `aria-*` live regions for results. |

---

## 2. Layout

The plugin lives at `Tools → Admin Search` (menu position `tools.php?page=admin-search`), reachable from the **admin bar search box** on every admin screen.

### Entry points
1. **Admin bar search field** (top-right): a compact input `⌘ K / Ctrl K` opens full search page.
2. **Tools menu item**: full-width search page `admin.php?page=admin-search`.

### Page structure (full search page)
```
┌───────────────── wp-admin .wrap ─────────────────┐
│ H1  Admin Search                    [Add filter]  │
│ ┌───────────────────────────────────────────────┐ │
│ │  🔍  Search settings, users, products, …      │ │  ← large input (auto-focus)
│ │                       F / ⌘K   •  Esc clears  │ │
│ └───────────────────────────────────────────────┘ │
│  Filters: [All ▾] [Settings] [Users] [Products] [Content] […]  (chips)
│  Results (N items in 0.4 s)                      │
│ ┌───────────────────────────────────────────────┐ │
│ │  ▸ Settings  (3)                           ›  │ │
│ │   [Setting row]  name · description        …  │ │
│ │   [Setting row]                             │ │
│ │  ▸ Users  (2)                              ›  │ │
│ │   [User row]   name (@login) · role        …  │ │
│ └───────────────────────────────────────────────┘ │
│            ← keyboard up/down + Enter to open     │
└───────────────────────────────────────────────────┘
```

### Search popover (from admin bar)
A **floating card** under the admin-bar input, 480 px wide, lists the top ~8 matches
over all types, with a "See all results on full page" footer link. Escape closes.

---

## 3. Typography

Use the WordPress admin system font stack; inherit core so we never fight theme fonts.

| Token | Value | Use |
|---|---|---|
| `--as-font-base` | `-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;` | All head/body |
| `--as-font-size-root` | `13px` | Base admin text |
| `--as-font-size-lg` | `16px` | Search input, result titles |
| `--as-font-size-md` | 14px | Group headers, descriptions |
| `--as-font-size-sm` | 12px | Meta, badges, hints, legend |
| `--as-line-height` | 1.6 | Body |
| `--as-font-weight-strong` | 600 | Titles, group names |

---

## 4. Spacing, radii, borders, shadows

| Token | Value |
|---|---|
| `--as-space-1` … `--as-space-5` | 4, 8, 12, 16, 20 px scale |
| `--as-radius` | 4 px (elements), 8 px (popovers) |
| `--as-border` | 1px solid `#c3c4c7` |
| `--as-ring-focus` | 0 0 0 1.5px `#fff, 0 0 0 4px #2271b1` (WCAG 3:1) |
| `--as-shadow-card` | `0 1px 3px rgba(0,0,0,.06)` |

---

## 5. Components

### 5.1 Search input (`.as-search`)
- Height 44px+ (Fitts), radius 4px, clear (`✕`) button when non-empty.
- Keyboard: `/`, `F2`, `Ctrl K`, `Cmd K` focus; `Esc` clears then unfocuses.
- Live-region hint `aria-live="polite"` announcing result count.

### 5.2 Type facets (`.as-facet`)
- Chip buttons: `All Content` (default), `Settings`, `Users`, `Products`, `Content`.
- Active chip: filled with WordPress primary blue `#2271b1`, white text.
- Each results group may also include a sub-filter only when non-empty (`type` counts).

### 5.3 Result groups (`.as-group`)
- Header row: type name + count + chevron (expand/collapse); groups with 0 matches auto-collapse and are hidden.
- Group content: list of `.as-result`.

### 5.4 Result rows (`.as-result`)
```
┌──────────────────────────────────────────────┐
│  dashicon  Title                  badge ›    │
│  Subtitle: description / path / meta         │
└──────────────────────────────────────────────┘
```
- **Row attributes** for the selected row (hover or keyboard): raise fill `#f0f6fc`, show `›` chevron, `background` for focus.
- `href`/`a` wrapping the row opens the underlying admin URL (update / users.php+ / edit.php?post_type=product / edit.php …).

### 5.5 Type badges / chips
Yellow-ish subtle chip per type with one dashicon + lowercase type label.
- Settings: `#6f437e` tint `dashicons-admin-generic`
- Users: `#0a4b78`, `dashicons-admin-users`
- Products: `#0a7a0e`, `dashicons-cart` (WooCommerce)
- Content (posts/pages): `#b26e00`, `dashicons-admin-post`
- Tooltips unnecessary — type is always labelled.

### 5.6 Empty / loading / error states
- **Empty** (no query): onboard text "Search plugin settings, users, products, or posts. Try: 'permalinks', 'import', 'shop'." plus 3–4 suggested queries.
- **Loading**: row skeleton shimmer (max 200ms / debounced).
- **No results**: "No matches for `query`. Try different words or a type filter.", with type chips offered.
- **Error**: WordPress `.notice-error` banner with retry button; never a crash message.

---

## 6. Typography & density

Result rows 46–52 px tall, group headers 40 px. Right-aligned meta (path, count)
muted `#646970`, never secondary color for interactive row content.

---

## 7. Responsive behavior

- **≤ 782 px** (WP breakpoint): left admin sidebar collapses; search page stack is full width, chips wrap, group headers stack above content. Admin-bar search input becomes an icon button.
- **≥ 783 px**: centered column `max-width: 720px`, admin-bar popover kept 480 px.

---

## 8. Accessibility notes

- Focus visible on every interactive element (`.as-result:focus-visible`, `.as-` inputs), 3:1 contrast for focus ring.
- All interactive rows are real `<a>` links (real destinations).
- ARIA: `role="search"`, `aria-label`, `aria-live="polite"`, groups `aria-label`ed with count, `aria-pressed` on facet chips, `aria-expanded` on groups.
- Color is never the only channel: type is always also labeled with text.

---

## 9. File & code-plan handoff

The drop-in CSS file `admin-search.css` (see repo) implements every token above with
WordPress-prefixed selectors under `#wp-admin-as-*` scope. It has **zero build step**
(no npm, no preprocessor) — the plugin can `wp_enqueue_style` it directly. The search
page is a server-rendered partial (`.as-wrap`) + vanilla JS `as-admin-search.js`.

Deliverables location in this repo: `design/search-plugin/`
- `DESIGN-SYSTEM.md` — this document
- `admin-search.css` — component stylesheet (drop-in)
- `mockup/*.html` — static mockups
- `ACCESSIBILITY.md`, `WORDPRESS-INTEGRATION-GUIDE.md` — best-practice guides