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

# WordPress sandbox request option

Do **not** set up WordPress unless the participant's project actually needs it.

If a WordPress/WooCommerce/plugin/theme sandbox is needed, request the single approved sandbox for this company by writing this file:

```bash
mkdir -p wordpress-sandbox
cat > wordpress-sandbox/request.json <<'JSON'
{
  "action": "start",
  "slug": "isaac-anderson",
  "reason": "WordPress is needed for this participant project"
}
JSON
```

Then wait up to a minute and check:

```bash
cat wordpress-sandbox/result.json
```

The sandbox URL is:

```text
https://preview2.updraftailabs.com/live/isaac-anderson/
```

To check or stop it, write a new request with `"action": "status"` or `"action": "stop"` and the same slug, then read `wordpress-sandbox/result.json` again.

Rules:

- There is **one WordPress sandbox per participant/company**. Do not try to create multiple sites.
- Use this only when WordPress is genuinely relevant to the participant's build.
- Do not run `sudo`, raw `docker`, or raw `docker compose` directly.
- Do not add custom Docker options, images, ports, host mounts, privileged containers, Docker socket mounts, public ports, tunnels, DNS, or Cloudflare routes.
- Keep WordPress project work inside this workspace's `wordpress-sandbox/` folder and normal project files.
- If you need persistent public hosting or a non-standard WordPress setup, escalate to James/admin first.
