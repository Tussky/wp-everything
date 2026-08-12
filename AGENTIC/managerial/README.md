# managerial/ — CEO instruction bundle

Drafts of Actinolite's (CEO) four instruction files. **Nothing here is live.**

Repository files do not change Paperclip state. Agent instructions are stored
server-side at:

```
/srv/paperclip/app-home/instances/default/companies/{companyId}/agents/{agentId}/instructions/
```

To take effect, these must be uploaded into that bundle through Paperclip. This
is the same pattern `reporter-agent-spec/` already uses — a spec has sat in that
folder since 2026-08-07 and the agent it describes has never existed, because
nobody made the API call.

Files are clean and paste-ready; the changelog below is the "what changed" view.

| File | Status |
|---|---|
| `AGENTS.md` | Revised — was live |
| `HEARTBEAT.md` | Revised — was live |
| `SOUL.md` | **New** — referenced as essential, did not exist |
| `TOOLS.md` | **New** — referenced as essential, was empty |

---

## The one change that matters

`HEARTBEAT.md` **step 4b — Sweep Delegated Work.**

The old heartbeat discovered work only through:

```
GET /api/companies/{companyId}/issues?assigneeAgentId={your-id}&status=...
```

Delegated subtasks are assigned to the CTO/CMO/UXDesigner, so
`assigneeAgentId={your-id}` excludes them **by construction**. The moment the CEO
delegated, the work left its field of view permanently — while `AGENTS.md` said
*"if a delegated task is blocked or stale, check in with the assignee"*, an
instruction no query in the heartbeat could satisfy.

That is the mechanism behind twenty `IA-24` subtasks sitting untouched for five
days. Not negligence — no mechanism.

Step 4b is the fix. Everything else is secondary.

---

## Changelog

### HEARTBEAT.md

| Change | Why |
|---|---|
| **NEW step 4b — child sweep** with the three-outcome expiry rule | Delegated work was structurally invisible |
| Step 4: *"never look for unassigned work"* → *"never **start** unassigned work; always review issues you created"* | The old wording blocked oversight; intent was to prevent scavenging |
| Step 5: `in_review` capped at one heartbeat, then proceed with smallest reversible slice | `in_review` was revisited only *"when you were woken by a comment on it"* — no comment meant never. `CEO_STATUS_UPDATE.md` stalled from Aug 7 exactly as specified |
| Step 6: subtask cap (4, depth 1), WIP limit (2 company-wide), explicit lifetimes, disjoint file ownership | No cap existed anywhere; `IA-24` grew to 20 children |
| Step 6: acceptance criteria must be a command + exact output, with banned vague words | *"acceptance criteria"* was unqualified — how "graceful error handling" passed review |
| Step 6: **NEW** fast path — small known-cause fixes skip planning entirely | Planning overhead exceeded the work |
| Step 6: plan approval narrowed to genuine strategy; unanswered confirmation ≠ blocker | The protocol had no timeout and no fallback |
| Step 6: **NEW** verification gate before docs/assets/release/audit | SMR completed full v1.0.0 release prep and was deleted the same day |
| Step 6: **NEW** provenance check before editing third-party plugins | 6,740 lines built on Andrew Stichbury's plugin before anyone read the header |
| Step 6: **NEW** delete-cascades-to-issues, overriding *"never cancel cross-team tasks"* | That rule made it impossible to close the 20 dead `IA-24` children |
| Step 8: cannot exit clean with open children | A fully-delegated CEO returned empty from step 4 and exited having done zero oversight, every heartbeat |
| Rules: no unrequested `.md` files | 1,292 lines of prose vs 2,572 shipped — and that excludes the deleted plugins' guides |

### AGENTS.md

| Change | Why |
|---|---|
| *"Company-wide artifacts (plans, shared docs) live in the project root"* → minimised, ask first | This line explicitly licensed `DELEGATION_PLAN.md`, `CEO_STATUS_UPDATE.md`, and both hire announcements |
| Hiring reframed — ask whether capacity is the real constraint | *"hire one before delegating"* made hiring the default unblock, on the critical path |
| **"Follow up"** now points explicitly at step 4b | Was previously unactionable |
| Delegation limits added, mirroring the heartbeat | — |
| Verification gate promoted to *"the reason you exist"* | — |
| *"Use `request_confirmation` instead of asking in markdown"* kept and sharpened | `CEO_STATUS_UPDATE.md` asked in markdown with Option A/B/C — a violation of the existing rule |
| `SOUL.md` added to references and listed first | — |

### SOUL.md — new

The bundle called it essential; it did not exist. It is the judgment layer, and
the CEO's job is almost entirely discretionary: set priorities, resolve
ambiguity, approve or reject. None of that had a stated basis — and *"aligned
with the company mission"* pointed at a mission written down nowhere.

An agent optimises what is specified. With only procedural files present, it
produced plans, announcements, and status documents, because those were the
artifacts the surviving instructions described how to make.

`SOUL.md` states what the company is for, that progress is measured in features
verified working, the 81%-deleted history as founding lesson, five tiebreakers
for when instructions run out, and which calls to make without asking.

### TOOLS.md — new

Was empty. Reconstructed from `HEARTBEAT.md`, `scripts/paperclip-upload-artifact.sh`,
`scripts/create-phpcs-task.sh`, and recorded API responses.

**Endpoints are split into Verified and Unverified.** No Paperclip API
documentation was available, so nothing is invented. Three things the workflow
needs but that are documented nowhere:

1. **The comment endpoint.** The heartbeat requires a comment before exit; no
   endpoint is specified anywhere. Probably `POST /api/issues/{id}/comments`.
2. **Listing children by `parentId`/`goalId`.** Step 4b needs it; only
   `assigneeAgentId` and `status` are documented as filters. Client-side
   filtering is the fallback — the sweep must happen either way.
3. **Updating issue status.** `/api/issues/{id}` was recorded as *"not
   accessible to update status programmatically"* during `IA-59`.

**Confirm these three before the first real heartbeat.** If step 4b cannot list
children, it needs the fallback path spelled out or it will silently no-op —
which would reproduce the exact bug this revision exists to fix.

---

## Deployment order

1. Confirm the three unverified endpoints above.
2. Upload all four files into Actinolite's (CEO) instruction bundle.
3. Upload `technical/SOUL.md` into Bayldonite's (CTO) bundle, and
   `engineering/SOUL.md` + `engineering/CODING_STANDARDS.md` into each coder's.
4. ~~Resolve the CTO ambiguity.~~ Resolved 2026-08-12: CEO **Actinolite**, CTO
   **Bayldonite**. Lukas Hoffmann and Andrej Kohler are dead names, live only in
   `/archive/`.
5. Close `IA-24` and its 20 children as obsolete; close `IA-34`/`IA-35`.
6. Create `IA-126` from `CEO_WORK_ORDER_IA-126.md` — paste it into the issue
   `description`, which is markdown.

Steps 4 and 5 need the delete-cascade override in the new heartbeat, or the CEO
will refuse to cancel cross-team issues.
