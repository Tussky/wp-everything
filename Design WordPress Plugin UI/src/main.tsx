// Preview harness only. The real deliverable is the framework-free plugin in
// ../wp-spotlight-search/assets/spotlight.js — a single vanilla JS file that
// injects its own styles and mounts itself. This just loads it so the Vite
// preview renders the identical code that ships to WordPress.
// @ts-expect-error — plain JS plugin asset, no type declarations.
import "../wp-spotlight-search/assets/spotlight.js"
