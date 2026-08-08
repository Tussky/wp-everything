# Accessibility — Admin Search

WCAG 2.1 AA compliance notes for the Admin Search plugin UI. These pivot off
`DESIGN-SYSTEM.md` and are verified against the mockups in `mockup/`.

## Colour & contrast

- Badge tints are **tint + dark text** — measured contrast ≥ 4.5:1:
  - Settings `#f3eaf7` / `#5b3a70` — 6.7:1
  - Users `#e7f2fc` / `#0a4b78` — 8.6:1
  - Products `#e7f6e8` / `#0b5b0e` — 7.1:1
  - Content `#fcf2e6` / `#8a4b08` — 6.0:1
- Primary button / active facet: `#2271b1` on `#fff` — 5.2:1, meets AA.
- Type is NEVER communicated by colour alone — every result also carries a text
  badge whose label names the type.
- Muted text `#646970` on `#fff` is used only for meta that is also present in
  higher-contrast text nearby (title + path); description text is `#50575e` (7:1).

## Keyboard operability

- Whole interface is operated from a single **search input**; no mouse required.
- Shortcuts: `Ctrl K`/`Cmd K`/`F2` focus search from anywhere; `↑`/`↓` move the
  active row; `Enter` opens active row; `Esc` clears then defocuses.
- Move rings: every interactive element (`as-search-input`, `.as-facet`, `.as-result`)
  shows `--as-ring` (0 0 0 1.5px #fff inset + 4px #2271b1) always ≥ 3:1 against neighbours.
- Tab order is linear and predictable; group headers are `<h2>`/`legend` not links.

## Semantics (ARIA)

- Page wrapper: `role="search"` + `aria-label="Admin search"`.
- Live region: `.as-live { aria-live="polite" }` announces "N results for 'term'"
  after each render — never a raw "busy" list.
- Facet chips: `aria-pressed` reflects active facet state.
- Groups: labelled headings + count read together in the `<h2>`; search results
  list is a real `<ul><li>`.
- Popover: `role="dialog"` + `aria-label="Search results"` when open, `aria-hidden`
  when closed; focus moves into the input on open and returns to the trigger on close.

## Forms & behaviour

- The search field is a real `type="search"` `<input>` (usable by voice control
  and autofill); `aria-label` is the accessible name.
- Result links are **real anchors** with meaningful destinations — no `onclick` spans.
- Debounce 150–250 ms so the live region updates once per stable query.
- No flashing, no auto-playing, no motion that loops more than 3 times
  (`transition` only on a single user action).

## Reduced motion

- All transitions are limited to 120 ms and only trigger on hover/focus — respect
  `prefers-reduced-motion: reduce` by disabling the small transitions and the
  skeleton shimmer.