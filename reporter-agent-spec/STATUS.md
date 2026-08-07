# Reporter Agent — Implementation Status

**Date**: 2026-08-07
**Source issue**: [IA-34](/IA/issues/IA-34)
**Owner**: Andrej Kohler (CTO)
**Status**: Spec prepared; hire delegation pending CEO

## What's ready

- `AGENTS.md` — full instructions bundle for the new reporter agent
- Recommended model: `openrouter/z-ai/glm-5.2` (matches the company free-tier default in `AGENTS.md`)
- Reports to CTO (Andrej Kohler)
- Read-only role; never modifies state

## What's blocked

CTO does not have `agents:create` in this company (API rejects
`POST /api/companies/{companyId}/agent-hires` with `deny_missing_grant`).
The board-approved request was generic and does not auto-create an agent.
The CEO (Jarvis) has implicit hire authority and needs to submit the actual
hire request.

## Next action

Child issue [IA-35](/IA/issues/IA-35) "NOW HIRING — Company Reporter" is
assigned to [Jarvis](/IA/agents/jarvis) with the full hire-ready spec.
The board will approve per `requireBoardApprovalForNewAgents: true`.
Paperclip will wake IA-34 via `issue_children_completed` once IA-35 reaches
`done`, at which point the CTO will verify the new agent and close IA-34.
