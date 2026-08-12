# TOOLS — Actinolite, CEO

Everything below is reconstructed from **observed usage** — `HEARTBEAT.md`, the
two `scripts/paperclip-*` files, and API responses recorded in the workspace.
No official Paperclip API documentation was available when this was written.

Endpoints are split into **Verified** (seen in working code or a recorded
response) and **Unverified** (needed by the workflow but never documented
anywhere in the workspace). **Confirm the unverified ones before relying on
them.** If one turns out not to exist, say so in the issue rather than
improvising a substitute.

---

## Environment

| Variable | Purpose |
|---|---|
| `PAPERCLIP_API_URL` | API base. All calls go to `$PAPERCLIP_API_URL/api/...` |
| `PAPERCLIP_API_KEY` | Bearer token |
| `PAPERCLIP_RUN_ID` | Sent as `X-Paperclip-Run-Id`. **Required on every mutating call** |
| `PAPERCLIP_COMPANY_ID` | This company |
| `PAPERCLIP_TASK_ID` | Issue this run was woken for, if any |
| `PAPERCLIP_WAKE_REASON` | Why you were woken |
| `PAPERCLIP_WAKE_COMMENT_ID` | Comment that woke you, if any |
| `PAPERCLIP_APPROVAL_ID` | Set when woken by an approval |
| `AGENT_HOME` | Your personal `life/` + `memory/` (PARA) root |

Company ID for this workspace: `f1a7cadb-7a77-4f0c-a9ae-8ffabcdd650b`

Standard headers:

```
Authorization: Bearer $PAPERCLIP_API_KEY
X-Paperclip-Run-Id: $PAPERCLIP_RUN_ID
Content-Type: application/json
```

---

## Verified endpoints

### Identity
```
GET /api/agents/me
```
Returns id, role, budget, chainOfCommand. Run first every heartbeat.

### Find your assigned work
```
GET /api/companies/{companyId}/issues
      ?assigneeAgentId={yourId}
      &status=todo,in_progress,in_review,blocked
```
**Only returns issues assigned to you.** Work you delegated is assigned to your
reports and will *not* appear here. See "Unverified" for the child sweep.

### Claim an issue
```
POST /api/issues/{issueId}/checkout
```
`409` means another agent owns it. **Never retry a 409.** Scoped wakes may have
already checked the issue out before your run started — only call this when you
deliberately switch issues.

### Create a subtask
```
POST /api/companies/{companyId}/issues
```
```json
{
  "title": "...",
  "description": "markdown — the full work order goes here",
  "assigneeAgentId": "<uuid>",
  "parentId": "<uuid>",
  "goalId": "<uuid>",
  "status": "todo",
  "priority": "medium"
}
```
Always set `parentId` and `goalId`. `description` is markdown, so a work order
can be pasted in whole. Optional: `inheritExecutionWorkspaceFromIssueId` for
follow-ups that must stay on the same checkout/worktree; `blockedByIssueIds`
when another issue is the blocker.

Statuses: `todo`, `in_progress`, `in_review`, `blocked`, `done`, `cancelled`.

### Ask the board something
```
POST /api/issues/{issueId}/interactions
```
`kind` is one of `suggest_tasks`, `ask_user_questions`, `request_confirmation`.
Set `continuationPolicy: "wake_assignee"` when the answer should wake you.

`ask_user_questions` and `request_confirmation` default
`supersedeOnUserComment: true` — a later board comment invalidates the pending
request. Set `false` only when the request should survive discussion.

Use this instead of asking in markdown. A markdown document containing
"Option A / Option B / Option C" is the wrong tool and has failed here before.

### Attach a file
```
POST /api/companies/{companyId}/issues/{issueId}/attachments
```
Multipart: `file=@path;type=<content-type>`.

### Record a work product
```
POST /api/issues/{issueId}/work-products
```
```json
{
  "type": "artifact",
  "provider": "paperclip",
  "title": "...",
  "status": "ready_for_review",
  "reviewState": "none",
  "isPrimary": true,
  "healthStatus": "unknown",
  "summary": "...",
  "createdByRunId": "$PAPERCLIP_RUN_ID",
  "metadata": {
    "attachmentId": "...", "contentType": "...", "byteSize": 0,
    "contentPath": "...", "openPath": "...", "downloadPath": "...",
    "originalFilename": "..."
  }
}
```

**Use the script rather than doing this by hand:**
```bash
scripts/paperclip-upload-artifact.sh FILE --title "..." --summary "..."
scripts/paperclip-upload-artifact.sh FILE --dry-run    # inspect first
```
It uploads the attachment and creates the work product in one step.

### Hire an agent
```
POST /api/companies/{companyId}/agent-hires
```
Use the `paperclip-create-agent` skill rather than calling this directly.
**Observed failure:** returns `deny_missing_grant` for the CTO — hire authority
is CEO-only. Board approval is required
(`requireBoardApprovalForNewAgents: true`).

---

## Unverified — confirm before relying on

### Commenting on an issue
`HEARTBEAT.md` requires a comment on every in-progress issue before exit, but
**no comment endpoint is documented anywhere in this workspace.** Likely
`POST /api/issues/{issueId}/comments`. Confirm it. If commenting is handled by
the Paperclip skill rather than a raw call, use the skill.

### Listing children of an issue
The child sweep in `HEARTBEAT.md` step 4b needs to list issues by parent or
goal. **Only `assigneeAgentId` and `status` are documented as filters.** Try:
```
GET /api/companies/{companyId}/issues?goalId={goalId}&status=...
GET /api/companies/{companyId}/issues?parentId={issueId}&status=...
```
If neither filter is supported, fall back to listing all company issues and
filtering client-side. **Do not skip the sweep because the query is awkward** —
it is the step that stops delegated work from being lost.

### Updating issue status
`/api/issues/{issueId}` appears to exist but was recorded as *"not accessible to
update status programmatically"* during `IA-59`. Method and permissions unknown.
If a status change fails, say so in a comment and escalate — do not write a
document about it instead.

---

## Skills

| Skill | Use for |
|---|---|
| **Paperclip skill** | Coordination and the full heartbeat procedure. Always use it for coordination. |
| **`para-memory-files`** | All memory operations — facts, daily notes, entities, weekly synthesis, recall, plans. |
| **`paperclip-create-agent`** | Hiring. Board approval required. |

---

## WordPress sandbox

One sandbox per company. Drive it by writing `wordpress-sandbox/request.json`,
waiting up to a minute, then reading `wordpress-sandbox/result.json`.

```json
{ "action": "start|status|stop|wp", "slug": "isaac-anderson",
  "reason": "...", "wpArgs": ["plugin", "list"] }
```

- Live URL: `https://preview2.updraftailabs.com/live/isaac-anderson/`
- `wp` takes **argument arrays only**. `wp shell`, `wp eval`, `wp eval-file` are
  **blocked** — verification must use ordinary WP-CLI subcommands.
- No `sudo`, no raw `docker`, no custom Docker options, ports, or mounts.

Useful for verification:
```json
{"action":"wp","slug":"isaac-anderson","wpArgs":["plugin","activate","wp-search"]}
{"action":"wp","slug":"isaac-anderson","wpArgs":["plugin","list","--name=wp-search","--field=status"]}
```

## Static previews

Public: `https://preview2.updraftailabs.com/isaac-anderson/`
Server: `/srv/paperclip/previews/isaac-anderson/`

Write browser-viewable demos, screenshots, and reports there. Never write into
another participant's preview folder.

---

## A caution about scripts

`scripts/create-phpcs-task.sh` writes a JSON payload to `/tmp` and then **only
prints** the `curl` command — it never executes it. It has created zero issues.

Treat it as a template, and check any script actually performs the mutation
before assuming work was dispatched. Writing a file is not the same as making
an API call. That distinction is the difference between a delegated task and a
document about a delegated task.
