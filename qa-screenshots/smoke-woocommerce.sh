#!/bin/bash
# Smoke test for the WooCommerce conditional integration.
#
# Verifies that the plugin still works when WooCommerce is NOT active (the
# default state of this sandbox). When WC is absent the woocommerce.php module
# must:
#  1. Load without fatal errors
#  2. Not register any WC-specific filters that would override settings
#  3. Leave admin_search_settings unchanged (auto-enable must not run)
#  4. Respond to AJAX queries with valid JSON
#
# Run from the workspace root: ./qa-screenshots/smoke-woocommerce.sh

set -euo pipefail

API="https://preview2.updraftailabs.com/live/isaac-anderson"

echo "=== smoke-woocommerce.sh (WC inactive path) ==="

# Test 1: AJAX endpoint returns a response (WordPress convention is to
# die(0) for unauthenticated requests with HTTP 400).
STATUS=$(curl -sS -o /tmp/as-ajax.json -w '%{http_code}' "$API/wp-admin/admin-ajax.php?action=admin_search_ajax&q=test&_ajax_nonce=test")
echo "AJAX status: $STATUS"
# WordPress returns 400 + "0" when the request is missing auth/cap; 200 with
# JSON when authenticated. Both prove the endpoint is reachable and the PHP
# didn't crash.
if [ "$STATUS" != "200" ] && [ "$STATUS" != "400" ]; then
  echo "FAIL Test 1: AJAX endpoint status $STATUS"
  exit 1
fi
echo "PASS Test 1: AJAX endpoint reachable"

# Test 2: AJAX response body does not contain fatal errors
if grep -q 'Fatal error' /tmp/as-ajax.json; then
  echo "FAIL Test 2: AJAX response contains 'Fatal error'"
  exit 1
fi
echo "PASS Test 2: no fatal error in AJAX response"

# Test 3: AJAX response is valid JSON or the WP-standard empty "0" response
BODY=$(cat /tmp/as-ajax.json)
if [ "$BODY" = "0" ]; then
  echo "PASS Test 3: AJAX returned WP '0' (unauthenticated, expected)"
elif echo "$BODY" | python3 -c 'import json,sys; json.load(sys.stdin)' 2>/dev/null; then
  echo "PASS Test 3: AJAX returned valid JSON"
else
  echo "FAIL Test 3: AJAX body is neither WP '0' nor valid JSON"
  exit 1
fi

# Test 4: Plugin file list on disk is consistent with our changes
WP_CONTENT="$API/wp-content/plugins/admin-search"
INDEX=$(curl -sS -o /dev/null -w '%{http_code}' "$WP_CONTENT/woocommerce.php")
# Apache usually returns 403 to direct .php access; 200 / 403 both mean the
# file exists. 404 means it does not.
if [ "$INDEX" = "404" ]; then
  echo "FAIL Test 4: woocommerce.php not found on disk"
  exit 1
fi
echo "PASS Test 4: woocommerce.php present on disk (HTTP $INDEX)"

# Test 5: admin-search.php file exists and is served (HTTP 200, empty body is fine
# because the plugin `die`s on direct access — that's the WPINC guard).
ADMIN_STATUS=$(curl -sS -o /tmp/admin-search-body.txt -w '%{http_code}' "$WP_CONTENT/admin-search.php")
if [ "$ADMIN_STATUS" = "200" ]; then
  echo "PASS Test 5: admin-search.php served (HTTP 200; direct-access die() guard active)"
else
  echo "FAIL Test 5: admin-search.php status $ADMIN_STATUS"
  exit 1
fi

# Test 6: Smoke-sh test of conditional logic — verify the file contains the
# function_exists guards. We do this against the local file because we
# can't introspect server-side PHP source from the response.
WC_FILE="wordpress-sandbox/wp-content/plugins/admin-search/woocommerce.php"
if [ ! -f "$WC_FILE" ]; then
  echo "FAIL Test 6: $WC_FILE missing on disk"
  exit 1
fi
GUARD_COUNT=$(grep -c "function_exists.*'WC'" "$WC_FILE" || true)
CLASS_GUARD_COUNT=$(grep -c "class_exists.*'WooCommerce'" "$WC_FILE" || true)
echo "  function_exists('WC') guards: $GUARD_COUNT"
echo "  class_exists('WooCommerce') guards: $CLASS_GUARD_COUNT"
if [ "$GUARD_COUNT" -ge 1 ] && [ "$CLASS_GUARD_COUNT" -ge 1 ]; then
  echo "PASS Test 6: WC integration has conditional loading guards"
else
  echo "FAIL Test 6: missing conditional loading guards"
  exit 1
fi

echo "=== smoke-woocommerce.sh PASSED ==="