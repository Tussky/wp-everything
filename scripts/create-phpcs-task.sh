#!/bin/bash
# Script to create child issue for PHPCS WordPress standards compliance
# Run this as part of the CTO delegation workflow

cat > /tmp/phpcs-task.json << 'EOF'
{
  "title": "PHPCS WordPress Standards Compliance",
  "description": "## Task: WordPress Coding Standards Audit and Fix\n\n**Parent Issue:** [IA-31](/IA/issues/IA-31)\n**Assigned to:** Marko Ebner (Senior Software Engineer)\n**Priority:** medium\n\n### Objective\nRun comprehensive WordPress coding standards audit on SiteMap Redirects plugin and fix all violations.\n\n### Files to Check\n- `wordpress-sandbox/wp-content/plugins/site-map-redirects/*.php` (all PHP files)\n- `assets/dist/admin.js` (JavaScript)\n- `assets/dist/admin.css` (CSS)\n\n### Tools Available\n- PHPCS configuration: `phpcs.xml`\n- WP-CLI command: `wp smr-phpcs`\n- Docker sandbox: `wordpress-sandbox/docker-compose.yml`\n\n### Acceptance Criteria\n- PHPCS audit with 0 errors\n- JavaScript follows WordPress standards\n- CSS follows WordPress standards\n- Proper DocBlocks on all PHP files\n- Consistent text domain usage",
  "assigneeAgentId": "ba0c1eae-4b1e-4b2a-b005-3fa42d7a086a",
  "parentId": "93b30d9e-1615-4c0d-9e4d-69a3d1c6535d",
  "goalId": "93b30d9e-1615-4c0d-9e4d-69a3d1c6535d",
  "status": "todo",
  "priority": "medium"
}
EOF

echo "Task definition created at /tmp/phpcs-task.json"
echo "To create the issue, run:"
echo "curl -X POST \\"
echo "  -H 'Authorization: Bearer \$PAPERCLIP_API_KEY' \\"
echo "  -H 'X-Paperclip-Run-Id: \$PAPERCLIP_RUN_ID' \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  '\$PAPERCLIP_API_URL/api/companies/f1a7cadb-7a77-4f0c-a9ae-8ffabcdd650b/issues' \\"
echo "  -d @/tmp/phpcs-task.json"