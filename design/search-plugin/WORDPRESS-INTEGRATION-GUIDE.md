# WordPress Admin Integration Guide — Admin Search

Companion to the `admin-search.css` stylesheet and the mockups in `mockup/`.
Written for the build team ([IA-47](/IA/issues/IA-47)) consuming the design in [IA-48](/IA/issues/IA-48).

## 1. Where the UI lives

| Surface | Delivery | Notes |
|---|---|---|
| Admin bar quick search | Injected into `#wpadminbar` on every admin screen | Compact input `⌘K / Ctrl K` opens search page; popover with top matches while typing |
| Full search page | `admin.php?page=admin-search` under **Tools** | Primary destination; also linked from "See all" in popover |

## 2. Enqueue

```php
wp_enqueue_style( 'as-admin-search', plugin_dir_url( __FILE__ ) . 'assets/admin-search.css', array(), '1.0' );
wp_enqueue_script( 'as-admin-search', plugin_dir_url( __FILE__ ) . 'assets/as-admin-search.js', array(), '1.0', true );
```

Do **not** load plugin CSS on the front end; only on `is_admin()`. The stylesheet in
`design/search-plugin/admin-search.css` is ready to drop into the plugin's
`assets/` folder as-is (zero build step, no preprocessor, plain CSS spec).

## 3. Class naming

Every component is scoped with the `as-` prefix and all page-level rules are nested under
`.as-wrap` (page) and `#wp-admin-bar-as-search` (admin bar). This avoids collisions with
core/themes and keeps specificity predictable. Never use generic selectors like `.button` or `input` bare.

## 4. Markup contract (page)

The server-rendered partial:

```html
<div class="as-wrap" role="search" aria-label="Admin search">
  <h1 class="as-title">Admin Search</h1>

  <!-- input -->
  <div class="as-search">
    <input class="as-search-input" type="search" value="" aria-label="Search the admin" autocomplete="off">
    <span class="as-search-icon" aria-hidden="true">⌕</span>
    <button class="as-clear" type="button" aria-label="Clear search">✕</button>
    <div class="as-hints">…</div>
    <span class="as-live" aria-live="polite"></span>
  </div>

  <!-- facets -->
  <div class="as-facets">
    <button class="as-facet" aria-pressed="true">All <span class="cnt">7</span></button>
  </div>

  <p class="as-meta"><b>N results</b> for "term" · time</p>

  <!-- one group per result type that has matches -->
  <section class="as-group">
    <div class="as-group-head"><h2>Settings</h2><span class="cnt">2</span></div>
    <ul class="as-list">
      <li><a class="as-result" href="…"><span class="as-result-more">›</span>
        <span class="as-result-top"><span class="as-result-title">Label</span><span class="as-badge settings">Settings</span></span>
        <span class="as-result-sub"><span class="as-result-desc">Description…</span><span class="as-result-path">Tools ▸ Import</span></span>
      </a></li>
    </ul>
  </section>
</div>
```

## 5. Vanilla JS behavior expected

`as-admin-search.js` (no build step, no framework):

- Debounced `input` events (150–250 ms) calling the REST endpoint
  `GET /wp-json/admin-search/v1/search?q={term}[&type=settings|users|products|content]`.
- Keyboard shortcuts: `Ctrl K` / `Cmd K` / `F2` focus the input anywhere in admin; `↑`/`↓`
  move the active row; `Enter` opens the active row; `Esc` clears then unfocuses.
- `aria-live="polite"` region announces result count; active row gets
  visible focus ring + `background: #f0f0f0`.
- Results click-through to real admin URLs (`options-general.php…`, `users.php…`,
  `edit.php?post_type=product…`, `edit.php?post_type=page…`) — never fake hrefs.
- Debounce also applies to the popover; popover closes on `Esc` or outside click.

## 6. Responsive

- `≥ 783 px`: content column `max-width: 760px` centered; admin bar has a 300 px field.
- `≤ 782 px`: WP collapses the sidebar; facets wrap; admin-bar field collapses to an
  icon that opens the popover. Already handled by the media query in the CSS.
- Results are always list rows — never tables on mobile; paths truncate with `white-space: nowrap; overflow:hidden; text-overflow:ellipsis`.

## 7. Empty / loading / error states

- **Menu visit, no query yet**: show the onboarding `.as-empty` block with 3 suggested
  queries (e.g. `permalinks`, `import`, `shipping`).
- **Searching**: keep previous results visible and dim them (skeleton row, `opacity:.5`),
  rather than a spinner; the input itself is the progress indicator.
- **No results**: `.as-noresults` with spelled search term and offered facets.
- **Error**: WordPress `.notice-error` banner with a Retry button. Never a raw exception.

## 8. Tuning notes for the build team

- The design uses **WP core colors** so it inherits admin color schemes including dark mode
  only if `wp_enqueue_style( 'admin-search', …, ['wp-edit'] )` chain is honored. Prefer CSS
  custom props with hard fallbacks to keep contrast AA on all schemes.
- Type badge tints are **tint + dark text** (≥ 4.5:1) — verified against WCAG AA.
- Do not add a spinner on every keystroke; perceived latency is what matters.