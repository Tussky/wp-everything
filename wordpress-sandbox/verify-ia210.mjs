/**
 * verify-ia210.mjs
 *
 * Playwright verification script for IA-210: visualize serialized option value
 * in spotlight preview.
 *
 * Usage: node verify-ia210.mjs
 * Requires: WordPress sandbox running at http://localhost:8080 (or via docker)
 *
 * The script:
 * 1. Logs into the WordPress sandbox (admin/admin)
 * 2. Opens the spotlight (Ctrl+J / Cmd+J)
 * 3. Selects the active_plugins row in the Options facet
 * 4. Clicks the Visualize button
 * 5. Asserts the pop-up shows:
 *    - option name "active_plugins"
 *    - a list of plugin file paths with slug chips and basenames
 *    - count >= 1
 *    - a Raw <details> element with the original serialized string
 * 6. Presses Escape and asserts the pop-up closes, returning to the preview
 * 7. Asserts no Visualize button appears for plain string options (blogname)
 * 8. Asserts no Visualize button appears for protected options (woocommerce_stripe_settings)
 */

import { chromium } from "playwright";

const BASE_URL = process.env.SANDBOX_URL || "https://preview2.updraftailabs.com/live/isaac-anderson";
const ADMIN_USER = process.env.ADMIN_USER || "isaac";
const ADMIN_PASS = process.env.ADMIN_PASS || "Bayldonite2026!";

let errors = [];

function assert(condition, message) {
	if (!condition) {
		console.error("FAIL: " + message);
		errors.push(message);
	} else {
		console.log("PASS: " + message);
	}
}

async function run() {
	console.log("Starting IA-210 verification...");
	console.log("Target: " + BASE_URL);

	const browser = await chromium.launch({
		headless: true,
		args: ["--no-sandbox", "--disable-setuid-sandbox"]
	});

	const context = await browser.newContext({
		baseURL: BASE_URL
	});

	const page = await context.newPage();

	try {
// Step 1: Log into WordPress
	console.log("\n--- Step 1: Logging into WordPress ---");
	await page.goto(BASE_URL + "/wp-login.php", { waitUntil: "networkidle" });
	await page.fill("#user_login", ADMIN_USER);
	await page.fill("#user_pass", ADMIN_PASS);
	await page.click("#wp-submit");
	await page.waitForURL("**/wp-admin/**", { timeout: 15000 });
	console.log("Logged in successfully");

		// Step 2: Open spotlight with Ctrl+J
		console.log("\n--- Step 2: Opening spotlight ---");
		await page.keyboard.press("Control+j");
		await page.waitForSelector("#wpss-root .wpss-overlay.is-open", { timeout: 5000 });
		console.log("Spotlight opened");

		// Step 3: Search for "active_plugins"
		console.log("\n--- Step 3: Searching for active_plugins ---");
		await page.fill("#wpss-input", "active_plugins");
		await page.waitForTimeout(300); // debounce

// Step 4: Click on the active_plugins row
	console.log("\n--- Step 4: Selecting active_plugins row ---");
	const row = page.locator('.wpss-row:has-text("active_plugins")').first();
	await row.waitFor({ timeout: 8000 });
	await row.click();

		// Verify preview pane shows active_plugins
		const previewText = await page.locator(".wpss-preview-pane").textContent();
		assert(previewText.includes("active_plugins"), "Preview shows active_plugins option name");

		// Step 5: Click the Visualize button
		console.log("\n--- Step 5: Clicking Visualize button ---");
		const vizBtn = page.locator(".wpss-sviz-btn");
		const vizBtnVisible = await vizBtn.isVisible({ timeout: 2000 }).catch(() => false);
		assert(vizBtnVisible, "Visualize button is visible for serialized option");

		if (vizBtnVisible) {
			await vizBtn.click();

			// Wait for popup
			await page.waitForSelector(".wpss-sviz-popup.is-open", { timeout: 3000 });
			console.log("Visualize popup opened");

			// Step 6: Assert popup contents
			console.log("\n--- Step 6: Verifying popup contents ---");

			// Check option name in header
			const popupName = await page.locator(".wpss-sviz-name").textContent();
			assert(popupName === "active_plugins", "Popup shows correct option name 'active_plugins'");

			// Check kind chip exists
			const kindChip = page.locator(".wpss-sviz-kind");
			const kindText = await kindChip.textContent();
			assert(kindText.includes("array"), "Kind chip shows 'array' for serialized array");

			// Check list of plugin paths with slug chips
			const svizList = page.locator(".wpss-sviz-list");
			const listExists = await svizList.isVisible();
			assert(listExists, "Plugin path list is visible");

			const listItems = page.locator(".wpss-sviz-item");
			const itemCount = await listItems.count();
			assert(itemCount >= 1, "List has at least 1 plugin entry (actual: " + itemCount + ")");

			// Check slug chips exist and contain actual plugin slugs
			const slugChips = page.locator(".wpss-sviz-slug");
			const slugCount = await slugChips.count();
			assert(slugCount >= 1, "At least one slug chip visible (actual: " + slugCount + ")");

			// Assert slug text equals a real plugin slug
			const slugTexts = await slugChips.allTextContents();
			const validSlugs = ["woocommerce", "akismet", "jetpack", "wordpress-seo", "wp-seo", "contact-form-7", "elementor"];
			const hasValidSlug = slugTexts.some(t => validSlugs.includes(t));
			assert(hasValidSlug, "Slug text equals a real plugin slug (got: " + slugTexts.join(", ") + ")");

			// Check path chips exist and contain actual basenames
			const pathChips = page.locator(".wpss-sviz-path");
			const pathCount = await pathChips.count();
			assert(pathCount >= 1, "At least one path chip visible (actual: " + pathCount + ")");

			// Assert path text equals the basename (.php file)
			const pathTexts = await pathChips.allTextContents();
			const validPaths = pathTexts.filter(t => t.endsWith(".php"));
			assert(validPaths.length >= 1, "Path text equals a basename with .php extension (got: " + pathTexts.join(", ") + ")");

			// Check Raw <details> element
			const rawDetails = page.locator(".wpss-sviz-raw");
			const rawVisible = await rawDetails.isVisible();
			assert(rawVisible, "Raw <details> element is visible");

// Check the raw content contains original serialized string
		const rawContent = await page.locator(".wpss-sviz-raw-content").textContent();
		assert(rawContent.includes("a:"), "Raw content contains original serialized array prefix 'a:'");
		assert(/a:\d+:\{/.test(rawContent), "Raw content is a serialized array literal a:N:{...}");

			// Step 7: Press Escape and verify popup closes, returning to preview
			console.log("\n--- Step 7: Testing Escape key closes popup ---");
			await page.keyboard.press("Escape");
			await page.waitForTimeout(300);

			const popupStillOpen = await page.locator(".wpss-sviz-popup.is-open").isVisible().catch(() => false);
			assert(!popupStillOpen, "Popup closes on Escape");

			// Verify spotlight is still open (not closed)
			const spotlightOpen = await page.locator("#wpss-root .wpss-overlay.is-open").isVisible();
			assert(spotlightOpen, "Spotlight remains open after closing popup");

			// Verify preview pane still shows active_plugins (not empty spotlight)
			const previewStillThere = await page.locator(".wpss-preview-pane").textContent();
			assert(previewStillThere.includes("active_plugins"), "Preview still shows active_plugins after closing popup");
		}

// Step 8: Verify NO Visualize button for plain string options (blogname)
	console.log("\n--- Step 8: Verifying no Visualize button for plain strings ---");
	await page.fill("#wpss-input", "blogname");
	await page.waitForTimeout(400);

	const blogRow = page.locator('.wpss-row:has-text("blogname")').first();
	await blogRow.waitFor({ timeout: 8000 });
	await blogRow.click();
	await page.waitForTimeout(200);

	const blogPreviewText = await page.locator(".wpss-preview-pane").textContent();
	assert(blogPreviewText.includes("blogname"), "Preview shows blogname option name");

	const blogVizBtn = page.locator(".wpss-sviz-btn");
	const blogVizBtnVisible = await blogVizBtn.isVisible({ timeout: 1000 }).catch(() => false);
	assert(!blogVizBtnVisible, "No Visualize button for plain string option (blogname)");

	// Step 9: Verify NO Visualize button for protected options
	console.log("\n--- Step 9: Verifying no Visualize button for protected options ---");
	await page.fill("#wpss-input", "woocommerce_stripe");
	await page.waitForTimeout(400);

	const stripeRow = page.locator('.wpss-row:has-text("woocommerce_stripe")').first();
	await stripeRow.waitFor({ timeout: 8000 });
	await stripeRow.click();
	await page.waitForTimeout(200);

	const stripePreviewText = await page.locator(".wpss-preview-pane").textContent();
	assert(/protected|locked/i.test(stripePreviewText), "Preview shows protected indicator for stripe settings");

	const stripeVizBtn = page.locator(".wpss-sviz-btn");
	const stripeVizBtnVisible = await stripeVizBtn.isVisible({ timeout: 1000 }).catch(() => false);
	assert(!stripeVizBtnVisible, "No Visualize button for protected option (woocommerce_stripe_settings)");

		console.log("\n=== Verification Complete ===");

	} catch (err) {
		console.error("Error during verification:", err.message);
		errors.push(err.message);
	} finally {
		await browser.close();
	}

	if (errors.length > 0) {
		console.error("\n" + errors.length + " assertion(s) failed:");
		errors.forEach((e, i) => console.error("  " + (i+1) + ". " + e));
		process.exit(1);
	} else {
		console.log("\nAll assertions passed!");
		process.exit(0);
	}
}

run().catch(err => {
	console.error("Fatal error:", err);
	process.exit(1);
});