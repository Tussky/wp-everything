# Archive — obsolete plans and reports

**Nothing in this directory is a live instruction. Do not act on any of it.**

If you are an agent and you have been routed here, you have followed a stale
pointer. Stop, and read `/AGENTS.md` and `/CEO_WORK_ORDER_IA-126.md` instead.

## Why these were archived

Every document here describes one of two WordPress plugins that **no longer
exist in this repository**:

| Plugin | Removed in | Lines deleted |
|---|---|---|
| `site-map-redirects` (SMR) | `3c8d4c1`, `46ed7e6` — 2026-08-10 | 4,520 |
| `admin-search` (third-party, by Andrew Stichbury) | `13402f5` — 2026-08-10 | 6,740 |

The workspace pivoted to the **wp→search** plugin in `6065c0a` (2026-08-10).
That pivot removed `PRODUCTION_PLAN.md` but missed the documents below, which
were left in the repository root where an agent could — and would — pick them up
as current work.

## Contents

| File | Describes | Status |
|---|---|---|
| `DELEGATION_PLAN.md` | 20 subtasks `IA-24-1` … `IA-24-20` for SMR | Obsolete — target deleted |
| `CEO_STATUS_UPDATE.md` | IA-24 production plan, stalled at "AWAITING USER APPROVAL" since 2026-08-07 | Obsolete — never approved, never executed |
| `CTO_HIRE_ANNOUNCEMENT.md` | Lukas Hoffmann hired as CTO; assigns `IA-24-4/14/17/19` | Obsolete — assignments target deleted plugin |
| `SENIOR_DEV_HIRE_ANNOUNCEMENT.md` | Matija Brenner hired; assigns 8 `IA-24-*` items | Obsolete — assignments target deleted plugin |
| `IA-29-security-audit.md` | Security audit of SMR v0.1.0 | Historical — audited code no longer exists |
| `IA-30/ERROR_HANDLING_REPORT.md` | Error-handling changes to SMR | Historical — audited code no longer exists |
| `ui-status-update.md` | SMR admin tree/graph UI status | Historical — UI no longer exists |
| `IA-59_COMPLETION_NOTE.md` | Note recording a manual skill assignment | Superseded — no ongoing relevance |

## Issue-tracker follow-up

Archiving the files does not close the issues. Still outstanding:

- Close `IA-24` and all twenty `IA-24-*` children as **obsolete — target deleted
  in `3c8d4c1`**. Do not close them as `done`; they were never delivered against
  surviving code.
- `IA-24-1`, `IA-24-2` and `IA-24-3` may currently read as complete because
  `IA-29-security-audit.md` and `IA-30/ERROR_HANDLING_REPORT.md` exist. Those are
  documents about deleted code, not delivered software. Reclassify as obsolete.
- Close `IA-34` / `IA-35` (Reporter agent hire, blocked on `agents:create` since
  2026-08-07). See `/reporter-agent-spec/` — spec retained, hire not pursued.

## Org-chart inconsistency noted during archival

`CTO_HIRE_ANNOUNCEMENT.md` names **Lukas Hoffmann** as CTO (2026-08-07).
`Inquisiter_AGENTS.md` and `reporter-agent-spec/STATUS.md` both name
**Andrej Kohler** as CTO, same date. The repository did not record which was
current.

**Resolved 2026-08-12 by Isaac:** CEO **Actinolite**, CTO **Bayldonite**. Both
older names are dead and survive only in this folder. "Route to the CTO" means
Bayldonite. See `COMPANY_OPERATING_RULES.md` → *Current state*.

---

*Archived 2026-08-12 as part of the IA-126 workspace recovery.*
