# SiteMap Redirects — User Guide

## Overview

SiteMap Redirects shows you every page on your WordPress site as an interactive tree map and overlays every redirect that points to (or from) those pages. The map helps non-technical users understand **where visitors land** and **why**.

## Opening the map

1. Log in to your WordPress admin.
2. Find **SiteMap Redirects** in the left-hand admin menu.
3. Click it. The map loads automatically.

The first load may take a few seconds if you have many posts or pages. The site index is cached, so later loads are faster.

## Reading the tree

- **Root** — the homepage (`/`).
- **Branches** — folders/categories implied by URL paths (e.g. `/blog/`).
- **Leaves** — real pages, posts, custom post types, taxonomy archives, or author archives.
- **Virtual redirect-source nodes** — URLs that redirect somewhere but no longer have a real page. They appear as ghost nodes so you can still see the redirect relationship.

Hover or click a node to open the detail panel.

## Detail panel

When you select a node the side panel shows:

- **URL** — the full front-end URL.
- **Type** — Page, Post, Custom Post, Category / Tag, Archive, or Redirect Source.
- **Redirects** — one row per active redirect that starts from this URL.
- **Status** — the HTTP status code (301, 302, 307, 308, or unknown).
- **Destination** — where visitors are sent.
- **Why?** — a plain-English explanation of the redirect source.

If a node has no redirects, the panel says so.

## Color legend

| Color | Status | Meaning |
|---|---|---|
| Red | 301 | Permanent redirect. Search engines pass most ranking to the target. |
| Amber | 302 / 307 | Temporary redirect. Use for short-lived or campaign URLs. |
| Dark red | 308 | Permanent redirect that keeps the original request method. |
| Blue | 303 | See Other — typically after a form submission. |
| Gray | Other | A redirect was detected but its status is not standard. |

## Toolbar

The toolbar at the top of the page provides:

- **Re-index site** — rebuilds the cached site tree and re-reads all redirect sources.
- **Debug mode** — switches to a sortable, filterable table view of every redirect on the site.
- **Export** — downloads the redirect list as CSV / JSON.
- **Import** — uploads a redirect list from a CSV file.

### Re-indexing

Click **Re-index site** after you:

- publish or delete pages/posts,
- change permalinks,
- add or update `.htaccess` rules,
- add or remove rules in the Redirection plugin.

A progress message appears while the index rebuilds. The map refreshes when finished.

### Debug mode

Debug mode displays every discovered redirect in a table:

- **Source URL** — where the redirect starts.
- **Target URL** — where it points.
- **Status Code** — 301, 302, etc.
- **Priority** — execution order (1 = first / highest).
- **Source** — `.htaccess`, Redirection plugin, WordPress core, or custom.
- **Status indicator** — active or overridden by a higher-priority rule.

Use the column headers to sort. Use the filter dropdowns to narrow by status code or source type.

### Redirect chains

If URL A redirects to URL B, and URL B also redirects to URL C, the debug table (and node detail panel) shows the full chain: `A → B → C`. A warning badge appears on chained redirects because they add latency and can confuse search engines.

### Drag-and-drop redirect creation

You can create a new redirect by dragging one node onto another:

1. Hover over a source node.
2. Drag its connection handle onto the target node.
3. Choose the status code (301/302) and optional note.
4. Save. The redirect is stored and appears in both the tree and the debug table.

To delete or edit a custom redirect, open the source node's detail panel and use the action buttons.

## Search and zoom

- **Scroll / pinch** — zoom in and out.
- **Drag the background** — pan around the map.
- **Ctrl+F / Cmd+F** — opens a quick-search box to jump to a path or label.

## Common workflows

### Find broken or outdated redirects

1. Open **Debug mode**.
2. Sort by **Status Code**.
3. Look for unexpected 302 or unknown-status rows.
4. Inspect the **Target URL** and **Source** columns to decide whether to update the rule in Redirection, `.htaccess`, or your theme/plugin.

### Check whether `.htaccess` is blocking a Redirection rule

1. Find the source URL in the tree.
2. Open the detail panel.
3. If you see an `.htaccess` rule with **Priority 1** pointing elsewhere, that rule runs first and the Redirection rule is effectively hidden.

### Share the map with a teammate

Send them the admin URL: `https://yoursite.com/wp-admin/admin.php?page=site-map-redirects`. Only users with `manage_options` can open it by default.

## Keyboard shortcuts

| Shortcut | Action |
|---|---|
| `R` | Re-index site |
| `D` | Toggle Debug mode |
| `+` / `-` | Zoom in / out |
| `0` | Fit the whole tree to the viewport |
| `Esc` | Close the detail panel |

## Need more help?

See the [Troubleshooting Guide](troubleshooting.md). Developers should read the [Developer Guide](developer-guide.md).
