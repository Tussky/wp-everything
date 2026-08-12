# CEO Work Order — IA-126: wp→search Defect Recovery

**To:** Jarvis (CEO)
**From:** Isaac Anderson (owner)
**Scope:** `wordpress-sandbox/wp-content/plugins/wp-search/`
**Status:** PRE-APPROVED — execute immediately. Do not open an approval gate.

---

## 0. Read this before you plan anything

This work order is deliberately shaped to prevent the two failure modes this
company has already produced:

1. **`archive/DELEGATION_PLAN.md`** — 20 subtasks with acceptance criteria like
   "graceful error handling", "user-friendly error messages", "robust
   validation". None of those can be evaluated by running a command, so none of
   them can ever be *closed*. They are still open.
2. **`archive/CEO_STATUS_UPDATE.md`** — a full plan that terminated in
   "AWAITING USER APPROVAL" and shipped zero lines of code.

Both are now archived. `COMPANY_OPERATING_RULES.md` is binding on this work
order; where the two overlap, the rules file wins.

You are not being asked to plan. The diagnosis is complete, the patches are
written out below, and the verification commands are supplied. Your job is
delegation, liveness, and verification — nothing else.

### Hard constraints (violating any of these fails the work order)

| Constraint | Value |
|---|---|
| Maximum child issues you may create | **4** (IA-126-1 … IA-126-4) |
| Maximum nesting depth | **1** — child issues may not create children |
| New investigation/analysis/strategy issues | **0** |
| Approval gates | **0** |
| Issues whose DoD is not a runnable command | **0** |

If you conclude a 5th issue is needed: **do not create it.** Post one comment on
IA-126 naming what you think is missing, and stop. Scope growth is my decision,
not yours.

### Banned words in any acceptance criterion you write

`comprehensive`, `robust`, `graceful`, `user-friendly`, `proper`, `improved`,
`optimized`, `best-practice`, `where applicable`, `as needed`.

A criterion is valid only if it takes the form:

> Run `<command>`. It prints `<exact expected output>`. Anything else is a fail.

---

## 1. What is actually broken

Four defects, confirmed by reading the source and `git log -p`. **None of them
are caused by WooCommerce** — that was coincidental timing. Do not let any agent
spend a heartbeat investigating WooCommerce compatibility.

| ID | Defect | File | Introduced by |
|---|---|---|---|
| FIX-1 | REST route registers under HTTP method `WOWT`; every search 404s | `includes/class-rest-controller.php:60` | `b3951f1` (IA-125) |
| FIX-2 | Activation hook fatals — `Indexer` base class never required | `wp-search.php:76-79`, `88-91` | `9ba1266` (IA-123) |
| FIX-3 | User + plugin results render with empty `href`; unclickable | `includes/class-indexer.php:68` and consumers | `ee72b98` (IA-124) |
| FIX-4 | Every admin menu item is indexed twice, appears twice in the modal | `includes/class-settings-indexer.php:123` | `9ba1266` (IA-123) |

**File ownership is disjoint by design.** No two child issues touch the same
file, so there is no merge coordination and no serialisation hazard:

- FIX-1 → `class-rest-controller.php`
- FIX-2 → `wp-search.php`
- FIX-3 → `class-indexer.php`, `class-users-indexer.php`, `class-plugins-indexer.php`
- FIX-4 → `class-settings-indexer.php`

If an agent reports needing to edit a file outside its list, that is a signal to
stop and comment on IA-126 — not to widen the diff.

---

## 2. Sequencing

```
IA-126-1 (FIX-1)  ─┐
IA-126-2 (FIX-2)  ─┴─→ both must be VERIFIED before ─→ IA-126-3 (FIX-3)
                                                        IA-126-4 (FIX-4)
```

- **IA-126-1 and IA-126-2 run in parallel, immediately.** They are the two
  defects that make the plugin non-functional.
- **IA-126-3 and IA-126-4 are dispatched only after both gates verify green.**
  Rationale: FIX-3 and FIX-4 are verified through the search modal, which cannot
  produce results at all until FIX-1 lands. Dispatching them early guarantees a
  hung issue.

Assign all four to the CTO (Andrej Kohler) or a coder agent. Do **not** create a
new specialist agent for this — every role needed already exists.

---

## 3. The four child issues

Each block below is the complete issue body. Copy it verbatim. Do not expand it,
do not add phases, do not add "success metrics" beyond the DoD given.

---

### IA-126-1 — Fix REST route HTTP method

**Owner:** CTO
**Lifetime:** 1 heartbeat / 45 minutes wall-clock
**Files:** `includes/class-rest-controller.php` only

**Cause:** `WP_REST_Server::READABLE` and `::CREATABLE` are the strings `'GET'`
and `'POST'`, not bit flags. `'GET' | 'POST'` performs a byte-wise string OR and
evaluates to `"WOWT"`. WordPress registers the route under that method, so the
POST sent by `assets/js/admin.js:55` matches no handler and returns
404 `rest_no_route`. The JS turns that into the "Please try again" error state.

**Patch:**

```php
// includes/class-rest-controller.php:60
- 'methods'             => \WP_REST_Server::READABLE | \WP_REST_Server::CREATABLE,
+ 'methods'             => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
```

**Definition of Done** — all three must pass:

```bash
# 1. The bogus method is gone from the route index.
curl -s https://preview2.updraftailabs.com/live/isaac-anderson/wp-json/wp-search/v1 \
  | grep -c WOWT
# expected: 0

# 2. The route accepts POST. Unauthenticated POST must return 403 (permission
#    denied), NOT 404 (no route). 403 proves the method now matches.
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  https://preview2.updraftailabs.com/live/isaac-anderson/wp-json/wp-search/v1/search
# expected: 403

# 3. Syntax clean.
php -l wordpress-sandbox/wp-content/plugins/wp-search/includes/class-rest-controller.php
# expected: No syntax errors detected
```

Paste all three commands **and their raw output** into the issue comment. An
issue closed without pasted output is reopened automatically.

---

### IA-126-2 — Fix fatal error on plugin activation

**Owner:** CTO
**Lifetime:** 1 heartbeat / 45 minutes wall-clock
**Files:** `wp-search.php` only

**Cause:** `wp_search_activate()` requires `class-settings-indexer.php` but not
`class-indexer.php`. Since `Settings_Indexer extends Indexer`, PHP fatals with
`Class "WP_Search\Indexer" not found`. On the activation request the plugin is
not yet in `active_plugins`, so `plugins_loaded` has not run and
`wp_search_load()` has not loaded the base class. `wp_search_deactivate()` has
the same latent fault — it references `Settings_Indexer::INDEX_TRANSIENT_KEY`
with no require at all.

**Patch** — extract a shared loader and use it in all three entry points:

```php
function wp_search_require_files(): void {
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-settings-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-users-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-plugins-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-menus-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-posts-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-products-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-rest-controller.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-admin.php';
}
```

`wp_search_load()`, `wp_search_activate()`, and `wp_search_deactivate()` all call
it first.

**Also in scope (one line, same file):** `wp_search_activate()` must **not** call
`reindex()`. The `$menu`/`$submenu` globals are empty during activation — they
populate on `admin_menu`, much later — so reindexing there caches an empty index
for 24h. Replace the reindex call with:

```php
delete_transient( WP_Search\Settings_Indexer::INDEX_TRANSIENT_KEY );
```

`Settings_Indexer::maybe_build_index()` then rebuilds it correctly on the next
`admin_init`.

**Definition of Done:**

```bash
# 1. Syntax clean.
php -l wordpress-sandbox/wp-content/plugins/wp-search/wp-search.php
# expected: No syntax errors detected

# 2. Deactivate → activate round-trip succeeds with no fatal.
#    Use the sandbox wp action (wpArgs arrays only; wp eval/shell are blocked).
#    request.json: {"action":"wp","slug":"isaac-anderson",
#                   "wpArgs":["plugin","deactivate","wp-search"]}
#    then:         {"action":"wp","slug":"isaac-anderson",
#                   "wpArgs":["plugin","activate","wp-search"]}
#    then:         {"action":"wp","slug":"isaac-anderson",
#                   "wpArgs":["plugin","list","--name=wp-search","--field=status"]}
# expected final output: active
```

Paste the contents of `wordpress-sandbox/result.json` for all three calls.

---

### IA-126-3 — Give every result record the same shape

**Owner:** CTO
**Lifetime:** 2 heartbeats / 90 minutes wall-clock
**Files:** `includes/class-indexer.php`, `includes/class-users-indexer.php`, `includes/class-plugins-indexer.php`
**Blocked until:** IA-126-1 and IA-126-2 both verified

**Cause:** `Indexer::normalize_record()` guarantees only the `source` key. Every
indexer invents its own field names. The renderer at `assets/js/admin.js:406`
reads exactly one navigation field, `item.url`. But `Users_Indexer` emits its
link as `edit_url` (`class-users-indexer.php:86`) and `Plugins_Indexer` emits
`plugins_page_link` (`class-plugins-indexer.php:85`). Both therefore render with
`href=""` and a dead `data-url`, so pressing Enter on them does nothing.

**Patch:** make the base class enforce the contract rather than trusting callers.

```php
protected function normalize_record( array $record ): array {
	return array(
		'title'       => (string) ( $record['title'] ?? '' ),
		'description' => (string) ( $record['description'] ?? '' ),
		'url'         => (string) ( $record['url'] ?? '' ),
		'type'        => (string) ( $record['type'] ?? '' ),
		'source'      => $this->get_source(),
		'meta'        => $record['meta'] ?? array(),
	);
}
```

Then `Users_Indexer` passes its admin link as `url` (moving `user_login`,
`email`, `avatar_url` under `meta`), and `Plugins_Indexer` passes
`admin_url( 'plugins.php' )` as `url` (moving `author`, `status` under `meta`).

**Do not** change `assets/js/admin.js` in this issue — it already reads `url`
correctly. Touching it widens the diff for no gain.

**Definition of Done:**

```bash
# 1. No indexer emits a non-standard navigation key any more.
grep -rn "edit_url\|plugins_page_link" \
  wordpress-sandbox/wp-content/plugins/wp-search/includes/
# expected: no output (exit 1)

# 2. Syntax clean across all three files.
for f in class-indexer class-users-indexer class-plugins-indexer; do
  php -l wordpress-sandbox/wp-content/plugins/wp-search/includes/$f.php
done
# expected: 3× No syntax errors detected

# 3. Behavioural: open the modal in wp-admin, search a term matching a user
#    and a plugin. Screenshot showing a Users result and a Plugins result.
#    Click one of each; the browser must navigate to user-edit.php and
#    plugins.php respectively.
# expected: screenshot attached + both navigations confirmed in the comment
```

---

### IA-126-4 — Remove duplicate menu results

**Owner:** CTO
**Lifetime:** 1 heartbeat / 45 minutes wall-clock
**Files:** `includes/class-settings-indexer.php` only
**Blocked until:** IA-126-1 and IA-126-2 both verified

**Cause:** `Settings_Indexer::collect_menu_items()` (line 203) and
`Menus_Indexer::collect()` (line 163) walk the same `$menu`/`$submenu` globals
with near-identical logic. Both indexers run in the same REST fan-out
(`class-rest-controller.php:163`), so every admin menu item is returned twice —
once badged "Options", once badged "Menus". WooCommerce adds roughly 30 menu
entries, which is why this became visually obvious right when it was installed.

**Patch:** `Menus_Indexer` is the correct owner of menu data. Delete
`collect_menu_items()`, `resolve_menu_url()`, `resolve_submenu_url()` and their
call from `reindex()` (line 123) in `Settings_Indexer`. It keeps only
`collect_settings_sections()` and `collect_registered_settings()`.

**Definition of Done:**

```bash
# 1. The duplicated collector is gone.
grep -c "collect_menu_items" \
  wordpress-sandbox/wp-content/plugins/wp-search/includes/class-settings-indexer.php
# expected: 0

# 2. Syntax clean.
php -l wordpress-sandbox/wp-content/plugins/wp-search/includes/class-settings-indexer.php
# expected: No syntax errors detected

# 3. Behavioural: search "Dashboard" in the modal. Screenshot must show it
#    appearing exactly once, under the Menus group only.
# expected: screenshot attached
```

---

## 4. Liveness protocol — how work stops being lost

This section is the reason the work order exists. Apply it literally.

**Every child issue carries an explicit lifetime** (stated above). The clock
starts when you assign it.

**Every heartbeat, each in-flight issue must receive a comment containing the
raw output of at least one DoD command.** A comment that says "in progress",
"working on it", or "looks good" does not count and does not reset the clock.

**On lifetime expiry, you take exactly one of these three actions — never a
fourth, and never a silent retry:**

1. **All DoD commands pass** → close the issue. Paste the final output.
2. **Some pass, some fail** → post the diff so far, the passing output, and the
   exact failing command with its actual output. Set the issue `blocked` with
   owner `Isaac Anderson`. Stop working it.
3. **No output was ever posted** → the issue is *hung*. Reclaim it: revert any
   partial diff with `git checkout -- <the issue's files>`, post the reason,
   set `blocked` with owner `Isaac Anderson`. Do not reassign it to a different
   agent and do not retry — a hung issue is a signal about the task, not the
   worker.

**Never extend a lifetime.** If 45 minutes was wrong, that is information I need,
and it surfaces only if you let the issue expire and report it.

**Escalate to me immediately, without waiting for expiry, if:**
- a DoD command cannot be run at all (sandbox down, endpoint unreachable)
- a fix requires touching a file outside its assigned list
- two child issues turn out to need the same file
- you believe a 5th issue is required

---

## 5. Closing IA-126

The parent closes when, and only when, this exact command sequence is pasted into
it with clean output:

```bash
# All PHP parses.
for f in wordpress-sandbox/wp-content/plugins/wp-search/*.php \
         wordpress-sandbox/wp-content/plugins/wp-search/includes/*.php; do
  php -l "$f"
done

# Route is correctly registered.
curl -s https://preview2.updraftailabs.com/live/isaac-anderson/wp-json/wp-search/v1 | grep -c WOWT
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  https://preview2.updraftailabs.com/live/isaac-anderson/wp-json/wp-search/v1/search

# No stale field names.
grep -rn "edit_url\|plugins_page_link\|collect_menu_items" \
  wordpress-sandbox/wp-content/plugins/wp-search/includes/
```

Expected: all `php -l` clean, `0`, `403`, and no grep output.

Plus one screenshot of the search modal showing grouped results across at least
three sources, with no duplicated entries.

**No status document.** Do not write `IA-126_STATUS.md`, `IA-126_PLAN.md`, or a
completion note. `CHANGELOG.md` gets one line per fix. The issue comments are the
record.

---

## 6. Explicit non-goals

Do not, under this work order:

- investigate WooCommerce compatibility (the correlation was coincidental)
- refactor the indexer architecture beyond the `normalize_record()` change
- add tests, CI, or a test harness
- touch `assets/css/admin.css` or `assets/dist/`
- bump the plugin version or tag a release
- revive, re-scope, or reference anything in `/archive/` — those documents target
  two plugins that were deleted on 2026-08-10
- create any new agent

There is a real double-escaping bug in `admin.js:407` (`highlight()` escapes text
that `_esc()` already escaped, so `&` renders as `&amp;amp;`). It is cosmetic and
**out of scope**. I am naming it here so nobody "discovers" it and widens a diff.
