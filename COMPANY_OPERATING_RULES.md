# Company Operating Rules — Hackathon: Isaac Anderson

**Applies to:** every agent in this company, including the CEO.
**Precedence:** these rules override any plan, delegation document, or issue
body that conflicts with them. If a document tells you to do something this file
forbids, the document is wrong — say so in a comment and stop.

---

## Why this file exists

Between 2026-08-04 and 2026-08-10 this company produced 13,832 lines of plugin
code and deleted 11,260 of them — **81% of all output**. Three plugins were
started; two were thrown away. The survivor, wp→search, shipped with four
defects, two of which made it completely non-functional.

The waste was not caused by bad engineering. It was caused by seven specific
process faults, each of which one rule below closes. The evidence for each is
cited so the rule can be argued with rather than obeyed blindly.

---

## Rule 1 — Definition of Working gates everything downstream

**No issue may enter documentation, asset creation, release prep, or audit until
a human has confirmed the feature works in the sandbox.**

"Confirmed" means a screenshot or pasted command output, reviewed by Isaac. Not
a report asserting it works. Not a phase number.

> **Evidence.** SiteMap Redirects received a full security audit (`IA-29`), an
> error-handling report (`IA-30`), and complete v1.0.0 release prep (`IA-106`:
> user guide, developer guide, troubleshooting guide, POT file, WordPress.org
> banners, icons, four screenshots). The plugin was deleted the same day in
> `3c8d4c1`. Phases 4 and 5 ran to completion against a corpse because the
> pipeline advanced on phase order, never on evidence the product was alive.

## Rule 2 — Two issues in flight, company-wide

**Hard WIP limit: 2.** Not two per agent — two total. A third issue may not be
started until one closes or is explicitly blocked with an owner.

> **Evidence.** Three plugins in six days. Admin Search work (`IA-48`, `IA-50`,
> `IA-52`) ran on 2026-08-08 while the `IA-24` SMR production plan was still
> open and awaiting approval. Nothing capped concurrency, so attention split
> three ways and two branches died.

## Rule 3 — Issues close on pasted command output, never on a document

An acceptance criterion is valid only in this form:

> Run `<command>`. It prints `<exact expected output>`. Anything else is a fail.

**Banned in any acceptance criterion:** `comprehensive`, `robust`, `graceful`,
`user-friendly`, `proper`, `improved`, `optimized`, `best-practice`,
`where applicable`, `as needed`.

An issue closed without pasted output is reopened.

> **Evidence.** `IA-24-2` asked for "graceful error handling", "user-friendly
> error messages", "fail-safe behavior for edge cases". None can be evaluated by
> running anything, so none could ever be honestly closed. All twenty `IA-24-*`
> subtasks are still open. Meanwhile `IA-24-1` and `IA-24-2` *appear* satisfied
> because two reports exist — reports about code that has since been deleted.

## Rule 4 — Do not create documents unless asked

Status updates, completion notes, hire announcements, and plans are not
deliverables. Issue comments are the record.

Before creating any `.md` file outside the plugin directory, ask. The only
standing exceptions are `CHANGELOG.md` (one line per shipped fix) and
`README.md`.

> **Evidence.** At archival this repository held 1,292 lines of prose status,
> plan, and announcement material against 2,572 lines of shipped code — and that
> ratio *excludes* the deleted plugins' user guides, developer guides, and
> troubleshooting guides. `IA-59_COMPLETION_NOTE.md` is a document whose entire
> content is the fact that Isaac did something manually.

## Rule 5 — Check provenance before writing into any plugin directory

Before the first edit to a plugin you did not create: read its plugin header,
and state the `Author:` value in the issue comment. If the author is not this
company, stop and escalate.

> **Evidence.** 6,740 lines were built on and around the **Admin Search** plugin
> across `IA-48`, `IA-50`, and `IA-52` before anyone noticed it was third-party
> work by Andrew Stichbury. It was removed in `13402f5`, and the same
> functionality was rebuilt from scratch as wp→search — `AS_Indexer (settings +
> users)` from `IA-50` reappearing as `Settings_Indexer` and `Users_Indexer`.
> The concepts were paid for twice. A five-second header check prevents this.

## Rule 6 — Deleting code cascades to its issues

When a plugin directory is deleted, every open issue targeting it is closed the
same session as **obsolete — target deleted in `<sha>`**. Never as `done`.

> **Evidence.** `3c8d4c1` removed SMR and cascaded to nothing. `DELEGATION_PLAN.md`
> survived in the repository root for two days describing twenty live subtasks
> against deleted code — one heartbeat away from restarting the dead project.
> It now sits in `/archive/`.

## Rule 7 — Default to execute; escalate only on named triggers

Do not open approval gates. Work inside a bounded, reversible scope and proceed.

Escalate to Isaac immediately — without waiting for a lifetime to expire — only
when one of these is true:

1. A verification command cannot be run at all (sandbox down, endpoint dead).
2. A fix requires touching a file outside its assigned list.
3. Two in-flight issues turn out to need the same file.
4. The work would exceed the subtask cap in its work order.
5. A plugin's author is not this company (Rule 5).

> **Evidence.** `CEO_STATUS_UPDATE.md` sat at "AWAITING USER APPROVAL" from
> 2026-08-07 onward while its twenty subtasks were simultaneously handed out via
> `CTO_HIRE_ANNOUNCEMENT.md` and `SENIOR_DEV_HIRE_ANNOUNCEMENT.md`. The gate
> blocked accountability without blocking work — the worst of both.

## Rule 8 — Code that does not boot does not get pushed

Two checks now run on every push and every pull request, defined in
`.github/workflows/phpunit.yml`:

| Check | What it proves |
|---|---|
| `Run wp-search unit tests` | Every PHP file parses, every class links, and the 44-test suite passes. |
| `WordPress boots with wp-search active` | A real WordPress install activates the plugin and serves `/`, `/wp-login.php`, `/wp-admin/` and the REST root with no PHP error, under `WP_DEBUG`. |

The second one is the one that matters. **Unit tests with a mocked WordPress can
pass on a plugin that takes the site down** — the mocks are the thing that hides
it. Only a real boot proves the site runs.

Before you push, the `pre-push` hook in `.githooks/` runs the syntax check, the
class-link check and the unit tests locally, and refuses the push if any fail.
Enable it once per clone:

```bash
git config core.hooksPath .githooks
```

**`--no-verify` is forbidden.** If the hook blocks you, the code is broken; that
is the hook working. Skipping it does not make the code work, it just moves the
red X to CI, where it is public and where the branch is already poisoned for
everyone else.

**When CI is red, the branch is broken and nothing else proceeds.** Not the next
subtask, not documentation, not a report. Fix it or revert it — those are the
only two moves. A red branch is the one condition under which Rule 2's WIP limit
does not apply: fixing it is never "a third issue in flight".

> **Evidence.** `bd6a4e1` (IA-158) declared `implements Spotlight_Provider` on
> two classes and never wrote the interface. `Users_Indexer` loads on
> `plugins_loaded`, so WordPress fatally errored on **every request, admin and
> front-end**, from 2026-08-12 until 2026-08-16. Three IA-162 commits and a
> merge landed on top of it, including a thirty-line CHANGELOG analysis of a
> REST nonce — written about code that could not execute, on a branch where
> WordPress would not boot. Nobody loaded a page for four days.
>
> The test suite already caught it: `tests/bootstrap.php` loads the same file
> list and failed at bootstrap with the identical error, in under a second. It
> was never run. And the CI workflow added in `100efcb` only watched `main`,
> while all nine commits in that window went straight to `wordpress-production`
> — so the net existed and the work happened entirely outside it.
>
> Rules 1 and 3 were both in force the whole time. They failed because no
> document in this repository named a command to run: a grep for `phpunit`,
> `composer` or `vendor/bin` across every `.md` file returned zero matches. An
> agent that wants to obey "close on pasted command output" has to be told
> which command. That is what this rule and the block in `AGENTS.md` fix.

---

## Issue lifetimes

Every issue carries an explicit lifetime, set at assignment. On expiry, take
exactly one of three actions. There is no fourth, and no silent retry.

1. **All checks pass** → close, pasting final output.
2. **Partial** → post the diff, the passing output, and the exact failing command
   with its actual output. Set `blocked`, owner Isaac. Stop.
3. **No output ever posted** → the issue is *hung*. Revert its partial diff with
   `git checkout -- <files>`, post why, set `blocked`, owner Isaac.

**Never extend a lifetime.** If the estimate was wrong, that is information —
and it only surfaces if the issue is allowed to expire and be reported.

**Never reassign a hung issue to a different agent.** A hung issue is a signal
about the task, not the worker. Reassigning it burns a second budget to learn
the same thing.

---

## Weekly report — the whole thing

One comment. No document.

```
Features verified working this week:  <n>
Lines shipped / lines deleted:        <a> / <b>
Dollars spent / features verified:    $<x>
Issues hung (expired with no output): <n>
CI red on wordpress-production for:   <hours>
In flight now (max 2):                <ids>
Blocked, owner Isaac:                 <ids + one-line unblock action>
```

If "features verified working" is 0 and "lines shipped" is large, the company is
repeating 2026-08-10. Stop and escalate.

---

## Current state — 2026-08-16

- **Live work:** assigned by Isaac directly. `CEO_WORK_ORDER_IA-126.md` was
  deleted in `1508268` — if a document still points you at it, that pointer is
  stale. **If you have no assigned issue, stop and ask. Do not select your own.**
- **CI:** two required checks on `main` and `wordpress-production`, see Rule 8.
  Server-side branch protection is **not yet enabled** — the repository is
  private on a free GitHub account, which gates both branch protection and
  rulesets. Until Isaac makes the repository public or upgrades the plan, the
  `.githooks/pre-push` hook and Rule 8 are the whole enforcement. That means
  the rule is currently load-bearing on you following it.
- **Archived:** eight obsolete documents in `/archive/` — see its README.
- **Org chart (resolved 2026-08-12 by Isaac):** CEO **Actinolite**, CTO
  **Bayldonite**, coders report to Bayldonite. The earlier CTO names — Lukas
  Hoffmann, Andrej Kohler — are dead and appear only in `/archive/`. "Route to
  the CTO" means Bayldonite.
- **Souls:** `managerial/SOUL.md` (CEO), `technical/SOUL.md` (CTO),
  `engineering/SOUL.md` + `engineering/CODING_STANDARDS.md` (coders). Drafts —
  not live until uploaded into each agent's instruction bundle.
