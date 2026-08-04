# Hackathon - Isaac Anderson — Agent Instructions

You are working inside an AI Labs Cohort #2 Paperclip hackathon participant company.

## Role model

- The seeded `Chief of Staff` coordinates; you do not implement as the Chief.
- If the right specialist agent does not already exist, you **must create it** inside this same Paperclip company before assigning implementation or QA work.
- Keep work scoped to this company/workspace.
- Use concise issue comments with evidence and verification.

## Workspace assignment

- Company: `Hackathon - Isaac Anderson`
- Participant: `Isaac Anderson`
- Issue prefix: `IA`
- Default model: `openrouter/z-ai/glm-5.2`
- Budget: `$100/month` in Paperclip
- Static preview URL: `https://preview2.updraftailabs.com/isaac-anderson/`
- Static preview folder: `/srv/paperclip/previews/isaac-anderson/`
- Live backend URL prefix: `https://preview2.updraftailabs.com/live/isaac-anderson/`

## Safety and infrastructure boundaries

- Do not change Cloudflare, DNS, tunnel config, systemd services, firewall, SSH, package manager state, or other companies' files.
- Do not bind public ports or use ngrok. If a live backend is needed, ask James/admin for the assigned loopback port.
- Do not store secrets in the repo. Use Paperclip secrets/environments when available.
- Commit local work so changes are inspectable.

## Output expectations

For every task, include:

1. what changed;
2. where it changed;
3. how it was verified;
4. any remaining risks or admin needs.

## Preview publishing space

This participant has a dedicated static preview space:

- Public preview URL: https://preview2.updraftailabs.com/isaac-anderson/
- Server preview folder: /srv/paperclip/previews/isaac-anderson/

When creating browser-viewable demos, reports, screenshots, or static artifacts, write/copy the final public files into /srv/paperclip/previews/isaac-anderson/. Do not write into another participant's preview folder. The preview folder is owned by paperclip and is intended for this company only.
