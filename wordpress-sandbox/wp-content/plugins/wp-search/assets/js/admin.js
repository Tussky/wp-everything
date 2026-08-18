/**
 * Spotlight Search — vanilla JS, no build step, no framework.
 *
 * Bootstraps itself into #wpss-root. Sample data lives in DATA below so the
 * screen is fully explorable; swap it for a wp_localize_script payload
 * (window.WPSS_DATA) to drive it from the real WordPress database.
 */
(function () {
	"use strict";

	/* ---------------------------------------------------------- styles ---- */
	/* All styling lives here so the plugin ships as a single JS file with no
	   external stylesheet dependency. Injected into <head> on boot. */

	var STYLES = "" +
		"#wpss-root{--wpss-users:#cbbcff;--wpss-plugins:#98efcf;--wpss-options:#ffcf9d;--wpss-settings:#a8d8ff}" +
		"#wpss-root *,#wpss-root *::before,#wpss-root *::after{box-sizing:border-box}" +
		"#wpss-root .wpss-overlay{position:fixed;inset:0;z-index:999999;display:none;align-items:flex-start;justify-content:center;padding:9vh 16px;font-family:'Inter',ui-sans-serif,system-ui,sans-serif;color:#f4f5f7;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;background:rgba(8,10,16,.34);background-image:radial-gradient(1000px 680px at 14% -6%,rgba(120,92,220,.2),transparent 58%),radial-gradient(900px 620px at 108% 10%,rgba(45,140,200,.2),transparent 56%),radial-gradient(780px 780px at 80% 116%,rgba(215,120,165,.18),transparent 60%);backdrop-filter:blur(4px) saturate(140%);-webkit-backdrop-filter:blur(4px) saturate(140%)}" +
		"#wpss-root .wpss-overlay.is-open{display:flex}" +
		"@media(min-width:640px){#wpss-root .wpss-overlay{padding-top:13vh;padding-bottom:13vh}}" +
		"#wpss-root ::selection{background:rgba(168,216,255,.28)}" +
		"#wpss-root .wpss-panel{width:100%;max-width:860px;overflow:hidden;border-radius:22px;border:1px solid rgba(255,255,255,.14);background:rgba(22,24,33,.42);box-shadow:inset 0 1px 0 rgba(255,255,255,.1),0 40px 120px -20px rgba(0,0,0,.75);-webkit-backdrop-filter:blur(8px) saturate(160%);backdrop-filter:blur(8px) saturate(160%);transition:transform .15s ease}" +
		"#wpss-root .wpss-panel.is-flash{transform:scale(.994)}" +
		"#wpss-root .wpss-search{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.07)}" +
		"#wpss-root .wpss-search-icon{flex-shrink:0;color:rgba(255,255,255,.4)}" +
		"#wpss-root .wpss-input{flex:1;min-width:0;border:none;background:transparent;outline:none;color:#fff;font-family:inherit;font-size:22px;font-weight:300;letter-spacing:-.01em}" +
		"#wpss-root .wpss-input::placeholder{color:rgba(255,255,255,.3)}" +
		"#wpss-root .wpss-clear{border:none;cursor:pointer;border-radius:999px;background:rgba(255,255,255,.08);padding:4px 10px;font-family:inherit;font-size:11px;color:rgba(255,255,255,.55);transition:background .15s ease,color .15s ease}" +
		"#wpss-root .wpss-clear:hover{background:rgba(255,255,255,.14);color:rgba(255,255,255,.8)}" +
		"#wpss-root .wpss-grid{display:grid;grid-template-columns:1fr}" +
		"@media(min-width:768px){#wpss-root .wpss-grid{grid-template-columns:1.15fr 1fr}}" +
		"#wpss-root .wpss-list{max-height:58vh;overflow-y:auto;padding:10px}" +
		"@media(min-width:768px){#wpss-root .wpss-list{max-height:62vh;border-right:1px solid rgba(255,255,255,.06)}}" +
		"#wpss-root .wpss-preview-pane{display:none;max-height:62vh;overflow-y:auto}" +
		"@media(min-width:768px){#wpss-root .wpss-preview-pane{display:block}}" +
		"#wpss-root .wpss-scroll{scrollbar-width:thin;scrollbar-color:transparent transparent}" +
		"#wpss-root .wpss-scroll::-webkit-scrollbar{width:8px;height:8px}" +
		"#wpss-root .wpss-scroll::-webkit-scrollbar-thumb{background:transparent;border-radius:999px}" +
		"#wpss-root .wpss-scroll:hover{scrollbar-color:rgba(255,255,255,.22) transparent}" +
		"#wpss-root .wpss-scroll:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,.22)}" +
		"#wpss-root .wpss-section{margin-bottom:6px}" +
		"#wpss-root .wpss-section-head{display:flex;align-items:center;gap:8px;padding:8px 10px 4px}" +
		"#wpss-root .wpss-section-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.09em}" +
		"#wpss-root .wpss-section-count{border-radius:999px;background:rgba(255,255,255,.06);padding:0 6px;font-size:10px;font-weight:500;color:rgba(255,255,255,.45)}" +
		"#wpss-root .wpss-rule{margin-left:4px;height:1px;flex:1;background:rgba(255,255,255,.06)}" +
		"#wpss-root .wpss-row{display:flex;align-items:center;gap:12px;width:100%;border:none;cursor:pointer;text-align:left;border-radius:11px;padding:8px 10px;background:transparent;color:inherit;font-family:inherit;transition:background .12s ease}" +
		"#wpss-root .wpss-row-body{min-width:0;flex:1}" +
		"#wpss-root .wpss-row-arrow{flex-shrink:0;color:rgba(255,255,255,.35)}" +
		"#wpss-root .wpss-row-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500;color:#fff}" +
		"#wpss-root .wpss-row-title--settings{font-weight:400}" +
		"#wpss-root .wpss-handle{font-family:'Geist Mono',ui-monospace,monospace;font-size:12px;font-weight:400;color:rgba(255,255,255,.45);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}" +
		"#wpss-root .wpss-row-sub{display:flex;align-items:center;gap:8px;margin-top:4px}" +
		"#wpss-root .wpss-row-code{margin-top:6px}" +
		"#wpss-root .wpss-dim{font-size:12px;color:rgba(255,255,255,.45)}" +
		"#wpss-root .wpss-dim2{color:rgba(255,255,255,.8)}" +
		"#wpss-root .wpss-avatar{display:grid;place-items:center;flex-shrink:0;width:32px;height:32px;border-radius:999px;font-size:12px;font-weight:600;color:#fff}" +
		"#wpss-root .wpss-avatar--lg{width:56px;height:56px;font-size:18px}" +
		"#wpss-root .wpss-glyph{display:grid;place-items:center;flex-shrink:0;border-radius:9px}" +
		"#wpss-root .wpss-pill{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:500;white-space:nowrap}" +
		"#wpss-root .wpss-version{border-radius:6px;background:rgba(255,255,255,.06);padding:2px 6px;font-family:'Geist Mono',ui-monospace,monospace;font-size:11px;color:rgba(255,255,255,.6)}" +
		"#wpss-root .wpss-value{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:6px;background:rgba(0,0,0,.25);padding:4px 8px;font-family:'Geist Mono',ui-monospace,monospace;font-size:12px;color:rgba(255,255,255,.8)}" +
		"#wpss-root .wpss-value--locked{display:inline-flex;align-items:center;gap:6px;white-space:normal;color:rgba(255,255,255,.45)}" +
		"#wpss-root .wpss-value-tag{font-size:10px;color:rgba(255,255,255,.35)}" +
		"#wpss-root .wpss-crumbs{display:inline-flex;flex-wrap:wrap;align-items:center;gap:4px;font-size:11px;color:rgba(255,255,255,.45)}" +
		"#wpss-root .wpss-crumb-sep{color:rgba(255,255,255,.25)}" +
		"#wpss-root .wpss-crumb-last{color:rgba(255,255,255,.7)}" +
		"#wpss-root .wpss-lang{border-radius:4px;background:rgba(255,255,255,.06);padding:1px 6px;font-family:'Geist Mono',ui-monospace,monospace;font-size:10px;text-transform:uppercase;letter-spacing:.03em}" +
		"#wpss-root .wpss-code{margin:0;overflow:hidden;border-radius:8px;background:rgba(0,0,0,.35);padding:12px 14px;font-family:'Geist Mono',ui-monospace,monospace;font-size:12.5px;line-height:1.6;color:rgba(255,255,255,.75)}" +
		"#wpss-root .wpss-code--compact{padding:8px 12px;font-size:11.5px}" +
		"#wpss-root .wpss-code code{white-space:pre-wrap;word-break:break-word}" +
		"#wpss-root .wpss-mark{border-radius:3px;padding:0 1px;background:rgba(255,255,255,.16);font-weight:600;color:#fff}" +
		"#wpss-root .wpss-preview{padding:20px}" +
		"#wpss-root .wpss-preview-eyebrow{display:flex;align-items:center;gap:8px;margin-bottom:16px}" +
		"#wpss-root .wpss-preview-eyebrow>span:first-child{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.09em}" +
		"#wpss-root .wpss-preview-head{display:flex;align-items:center;gap:14px;margin-bottom:16px}" +
		"#wpss-root .wpss-preview-head--tight{gap:8px;margin-bottom:8px}" +
		"#wpss-root .wpss-preview-title{font-size:17px;font-weight:600;color:#fff}" +
		"#wpss-root .wpss-handle--lg{font-size:12.5px;color:rgba(255,255,255,.5)}" +
		"#wpss-root .wpss-byline{font-size:12.5px;font-style:italic;color:rgba(255,255,255,.5)}" +
		"#wpss-root .wpss-preview-badges{flex-wrap:wrap;margin-bottom:12px}" +
		"#wpss-root .wpss-option-name{font-size:15px;font-weight:600;color:#fff}" +
		"#wpss-root .wpss-explainer{margin:0 0 12px;font-size:13px;font-style:italic;line-height:1.55;color:rgba(255,255,255,.6)}" +
		"#wpss-root .wpss-preview-crumbs{margin-bottom:12px}" +
		"#wpss-root .wpss-relaxed{line-height:1.6;color:rgba(255,255,255,.75)}" +
		"#wpss-root .wpss-field{border-top:1px solid rgba(255,255,255,.06);padding:10px 0}" +
		"#wpss-root .wpss-preview>.wpss-field:first-of-type,#wpss-root .wpss-grid2 .wpss-field{border-top:0}" +
		"#wpss-root .wpss-field-label{margin-bottom:4px;font-size:10.5px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35)}" +
		"#wpss-root .wpss-field-body{font-size:13px;color:rgba(255,255,255,.85)}" +
		"#wpss-root .wpss-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}" +
		"#wpss-root .wpss-cap-list{display:flex;flex-wrap:wrap;gap:6px}" +
		"#wpss-root .wpss-cap{border-radius:6px;background:rgba(255,255,255,.06);padding:2px 6px;font-family:'Geist Mono',ui-monospace,monospace;font-size:11px;color:rgba(255,255,255,.65)}" +
		"#wpss-root .wpss-mono{font-family:'Geist Mono',ui-monospace,monospace}" +
		"#wpss-root .wpss-italic{font-style:italic;color:rgba(255,255,255,.5)}" +
		"#wpss-root .wpss-not-italic{font-style:normal}" +
		"#wpss-root .wpss-truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}" +
		"#wpss-root .wpss-update-note{margin-top:8px;border-radius:8px;padding:8px 12px;font-size:12.5px;background:rgba(255,190,130,.11);color:#ffd6ac}" +
		"#wpss-root .wpss-index-note{margin:8px 0 0;font-size:12px;font-style:italic;line-height:1.55;color:rgba(255,255,255,.45)}" +
		"#wpss-root .wpss-footer{display:flex;align-items:center;justify-content:space-between;border-top:1px solid rgba(255,255,255,.07);padding:10px 20px;font-size:11px;color:rgba(255,255,255,.4)}" +
		"#wpss-root .wpss-fcounts{display:flex;align-items:center;gap:12px;flex-wrap:wrap}" +
		"#wpss-root .wpss-fdot{display:inline-flex;align-items:center;gap:6px}" +
		"#wpss-root .wpss-fdot b{font-weight:600;color:rgba(255,255,255,.6)}" +
		"#wpss-root .wpss-fdot-mark{display:inline-block;width:6px;height:6px;border-radius:999px}" +
		"#wpss-root .wpss-fhints{display:none;align-items:center;gap:10px}" +
		"@media(min-width:640px){#wpss-root .wpss-fhints{display:flex}}" +
		"#wpss-root .wpss-khint{display:inline-flex;align-items:center;gap:6px}" +
		"#wpss-root .wpss-khint kbd{display:grid;place-items:center;min-width:18px;border-radius:5px;background:rgba(255,255,255,.09);padding:1px 4px;font-family:'Geist Mono',ui-monospace,monospace;font-size:10.5px;color:rgba(255,255,255,.6)}" +
		"#wpss-root .wpss-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center}" +
		"#wpss-root .wpss-empty-icon{margin-bottom:12px;color:rgba(255,255,255,.2)}" +
		"#wpss-root .wpss-empty-title{margin:0;font-size:15px;color:rgba(255,255,255,.6)}" +
		"#wpss-root .wpss-empty-title span{font-weight:500;color:rgba(255,255,255,.85)}" +
		"#wpss-root .wpss-empty-hint{margin:4px 0 0;font-size:12.5px;color:rgba(255,255,255,.35)}" +
		"#wpss-root .wpss-empty-hint i{font-style:italic}" +
		"@media (prefers-reduced-transparency:reduce),(forced-colors:active){#wpss-root .wpss-overlay{background:rgba(13,16,24,.95);backdrop-filter:none;-webkit-backdrop-filter:none}#wpss-root .wpss-panel{background:rgba(22,24,33,.95);-webkit-backdrop-filter:none;backdrop-filter:none}}";

	function pluginBaseUrl() {
		var scripts = document.getElementsByTagName("script");
		for (var i = scripts.length - 1; i >= 0; i--) {
			var src = scripts[i].src || "";
			var idx = src.indexOf("assets/dist/wp-search-modal.js");
			if (idx !== -1) return src.slice(0, idx);
		}
		return "";
	}

	// Local @font-face (bundled woff2 in assets/fonts). URLs are resolved
	// absolute against the script src so they survive any admin path.
	function fontFaceCss(base) {
		return "" +
			"@font-face{font-family:'Inter';font-style:normal;font-weight:400 700;font-display:swap;src:url('" + base + "assets/fonts/inter-roman-latin.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}" +
			"@font-face{font-family:'Inter';font-style:normal;font-weight:400 700;font-display:swap;src:url('" + base + "assets/fonts/inter-roman-latin-ext.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}" +
			"@font-face{font-family:'Inter';font-style:italic;font-weight:400 500;font-display:swap;src:url('" + base + "assets/fonts/inter-italic-latin.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}" +
			"@font-face{font-family:'Inter';font-style:italic;font-weight:400 500;font-display:swap;src:url('" + base + "assets/fonts/inter-italic-latin-ext.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}" +
			"@font-face{font-family:'Geist Mono';font-style:normal;font-weight:400 600;font-display:swap;src:url('" + base + "assets/fonts/geist-mono-roman-latin.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}" +
			"@font-face{font-family:'Geist Mono';font-style:normal;font-weight:400 600;font-display:swap;src:url('" + base + "assets/fonts/geist-mono-roman-latin-ext.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}";
	}

	function injectStyles() {
		if (document.getElementById("wpss-styles")) return;
		var style = document.createElement("style");
		style.id = "wpss-styles";
		style.textContent = fontFaceCss(pluginBaseUrl()) + STYLES;
		(document.head || document.documentElement).appendChild(style);
	}

	/* ----------------------------------------------------------- data ---- */

	var DEFAULT_DATA = {
		users: [
			{ id: "u-1", username: "admin", displayName: "Dana Okafor", email: "dana@northwind-goods.com", role: "Administrator", capabilities: ["manage_options", "edit_users", "install_plugins", "update_core", "unfiltered_html"], registered: "2021-03-14", lastLogin: "2026-08-11 09:42", hue: 264 },
			{ id: "u-2", username: "editor_jane", displayName: "Jane Whitlock", email: "jane@northwind-goods.com", role: "Editor", capabilities: ["edit_others_posts", "publish_posts", "manage_categories", "moderate_comments"], registered: "2022-06-02", lastLogin: "2026-08-10 17:08", hue: 200 },
			{ id: "u-3", username: "shop_lee", displayName: "Lee Ramírez", email: "lee@northwind-goods.com", role: "Shop Manager", capabilities: ["manage_woocommerce", "edit_shop_orders", "view_woocommerce_reports"], registered: "2023-01-19", lastLogin: "2026-08-11 08:15", hue: 150 },
			{ id: "u-4", username: "author_marco", displayName: "Marco Bellini", email: "marco@northwind-goods.com", role: "Author", capabilities: ["publish_posts", "edit_posts", "upload_files"], registered: "2024-09-27", lastLogin: "2026-08-06 11:53", hue: 24 },
			{ id: "u-5", username: "amy_sub", displayName: "Amy Chen", email: "amy.chen@gmail.com", role: "Subscriber", capabilities: ["read"], registered: "2025-11-04", lastLogin: "2026-07-29 20:31", hue: 330 }
		],
		plugins: [
			{ id: "p-1", name: "WooCommerce", slug: "woocommerce/woocommerce.php", active: true, version: "8.9.1", updateAvailable: "9.0.2", author: "Automattic", description: "The open-source ecommerce platform powering the storefront, cart, and checkout." },
			{ id: "p-2", name: "WooCommerce Stripe Gateway", slug: "woocommerce-gateway-stripe/woocommerce-gateway-stripe.php", active: true, version: "8.1.0", updateAvailable: null, author: "Stripe", description: "Accept credit cards, Apple Pay, and Google Pay through Stripe at checkout." },
			{ id: "p-3", name: "Yoast SEO", slug: "wordpress-seo/wp-seo.php", active: true, version: "23.4", updateAvailable: null, author: "Team Yoast", description: "Search-engine optimization: title templates, XML sitemaps, and readability analysis." },
			{ id: "p-4", name: "Akismet Anti-Spam", slug: "akismet/akismet.php", active: true, version: "5.3.2", updateAvailable: null, author: "Automattic", description: "Filters comment and contact-form spam against the global Akismet database." },
			{ id: "p-5", name: "Elementor", slug: "elementor/elementor.php", active: true, version: "3.21.0", updateAvailable: "3.22.1", author: "Elementor.com", description: "Drag-and-drop page builder for landing pages and templates." },
			{ id: "p-6", name: "Advanced Custom Fields", slug: "advanced-custom-fields/acf.php", active: true, version: "6.2.10", updateAvailable: null, author: "WP Engine", description: "Adds custom field groups to posts, pages, and custom post types." },
			{ id: "p-7", name: "Contact Form 7", slug: "contact-form-7/wp-contact-form-7.php", active: false, version: "5.9.5", updateAvailable: null, author: "Takayuki Miyoshi", description: "Manage multiple contact forms with simple markup and mail templating." },
			{ id: "p-8", name: "WP Super Cache", slug: "wp-super-cache/wp-cache.php", active: false, version: "1.12.4", updateAvailable: null, author: "Automattic", description: "Generates static HTML files to serve pages faster under load." }
		],
		options: [
			{ id: "o-1", name: "siteurl", value: "https://northwind-goods.com", autoload: "yes", protected: false, explainer: "The WordPress address (URL) where core files live." },
			{ id: "o-2", name: "blogname", value: "Northwind Goods", autoload: "yes", protected: false, explainer: "The site title shown in the header and browser tab." },
			{ id: "o-3", name: "admin_email", value: "dana@northwind-goods.com", autoload: "yes", protected: false, explainer: "Address that receives administrative and system notifications." },
			{ id: "o-4", name: "active_plugins", value: 'a:6:{i:0;s:27:"woocommerce/woocommerce.php";i:1;s:19:"akismet/akismet.php";…}', autoload: "yes", protected: false, explainer: "Serialized list of plugin files WordPress loads on every request." },
			{ id: "o-5", name: "woocommerce_stripe_settings", value: "sk_live_51Hd9x2KpQ7mTn4bV8rYs…", autoload: "no", protected: true, explainer: "Stripe gateway config including the live secret API key." },
			{ id: "o-6", name: "akismet_api_key", value: "a4f7e29c8b13", autoload: "yes", protected: true, explainer: "Authenticates the site against Akismet's spam-filtering service." },
			{ id: "o-7", name: "permalink_structure", value: "/%postname%/", autoload: "yes", protected: false, explainer: "Pattern used to build human-readable post and page URLs." },
			{ id: "o-8", name: "woocommerce_currency", value: "USD", autoload: "yes", protected: false, explainer: "Default currency used for store prices and orders." },
			{ id: "o-9", name: "users_can_register", value: "0", autoload: "yes", protected: false, explainer: "Whether visitors may create their own accounts." },
			{ id: "o-10", name: "timezone_string", value: "America/New_York", autoload: "yes", protected: false, explainer: "Local timezone used for scheduling and timestamps." },
			{ id: "o-11", name: "template", value: "twentytwentyfour", autoload: "yes", protected: false, explainer: "Slug of the active theme's parent template." }
		],
		settings: [
			{ id: "s-1", source: "WordPress Core", sourceKind: "core", breadcrumb: ["Settings", "General"], language: "html", snippet: '<input name="blogname" type="text" value="Northwind Goods" class="regular-text" />\n<p class="description">In a few words, explain what this site is about.</p>' },
			{ id: "s-2", source: "WooCommerce", sourceKind: "plugin", breadcrumb: ["WooCommerce", "Settings", "Payments", "Stripe"], language: "php", snippet: "add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {\n    $gateways[] = 'WC_Gateway_Stripe';\n    return $gateways;\n} );" },
			{ id: "s-3", source: "Yoast SEO", sourceKind: "plugin", breadcrumb: ["Yoast SEO", "Search Appearance", "Content Types"], language: "html", snippet: '<label>Title template</label>\n<input class="yoast-field" value="%%title%% %%sep%% %%sitename%%" />\n<span class="help">Used for the SEO title of every post.</span>' },
			{ id: "s-4", source: "WordPress Core", sourceKind: "core", breadcrumb: ["Settings", "Reading"], language: "html", snippet: '<input name="posts_per_page" type="number" step="1" min="1" value="10" class="small-text" />\n<label>Blog pages show at most.</label>' },
			{ id: "s-5", source: "WordPress Core", sourceKind: "core", breadcrumb: ["Settings", "Permalinks"], language: "html", snippet: '<input type="radio" name="selection" value="/%postname%/" checked />\n<code>https://northwind-goods.com/sample-post/</code>' },
			{ id: "s-6", source: "Elementor", sourceKind: "plugin", breadcrumb: ["Elementor", "Settings", "Advanced"], language: "css", snippet: ".elementor-section {\n  --e-global-color-primary: #6c4bd8;\n  padding-block: clamp(2rem, 5vw, 6rem);\n}" },
			{ id: "s-7", source: "WordPress Core", sourceKind: "core", breadcrumb: ["Settings", "Discussion"], language: "html", snippet: '<input name="comment_moderation" type="checkbox" value="1" checked />\n<label>Comment must be manually approved before it appears.</label>' }
		]
	};

	var DATA = (typeof window !== "undefined" && window.WPSS_DATA) ? window.WPSS_DATA : DEFAULT_DATA;

	/* ----------------------------------------------------- facet config ---- */

	var FACET_ORDER = ["users", "plugins", "options", "settings"];

	var FACET_META = {
		users:    { label: "Users",    accent: "#cbbcff", chipBg: "rgba(180, 160, 255, 0.16)", chipText: "#d8ccff", tint: "rgba(180, 160, 255, 0.12)", icon: "person" },
		plugins:  { label: "Plugins",  accent: "#98efcf", chipBg: "rgba(120, 235, 195, 0.15)", chipText: "#a9f2d6", tint: "rgba(120, 235, 195, 0.11)", icon: "plug" },
		options:  { label: "Options",  accent: "#ffcf9d", chipBg: "rgba(255, 190, 130, 0.16)", chipText: "#ffd6ac", tint: "rgba(255, 190, 130, 0.11)", icon: "database" },
		settings: { label: "Settings", accent: "#a8d8ff", chipBg: "rgba(130, 200, 255, 0.16)", chipText: "#b6dfff", tint: "rgba(130, 200, 255, 0.11)", icon: "sliders" }
	};

	var LANG_COLOR = { php: "#c39bff", html: "#8fd0ff", css: "#ffb38f" };

	var ICONS = {
		search: "M11 4a7 7 0 1 0 4.2 12.6l4.1 4.1 1.4-1.4-4.1-4.1A7 7 0 0 0 11 4Zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z",
		person: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4 0-7 2-7 5v1h14v-1c0-3-3-5-7-5Z",
		plug: "M9 3v5H7v3a5 5 0 0 0 4 4.9V21h2v-5.1A5 5 0 0 0 17 11V8h-2V3h-2v5h-2V3H9Z",
		database: "M12 3c-4.4 0-8 1.3-8 3s3.6 3 8 3 8-1.3 8-3-3.6-3-8-3Zm8 5.5c0 1.7-3.6 3-8 3s-8-1.3-8-3V12c0 1.7 3.6 3 8 3s8-1.3 8-3V8.5Zm0 4.5c0 1.7-3.6 3-8 3s-8-1.3-8-3V16.5c0 1.7 3.6 3 8 3s8-1.3 8-3V13Z",
		sliders: "M4 6h9v2H4V6Zm12 0h4v2h-4V6Zm-2-2v6h-2V4h2ZM4 16h4v2H4v-2Zm10 0h6v2h-6v-2Zm-2-2v6h-2v-6h2Z",
		lock: "M12 2a5 5 0 0 0-5 5v3H6v11h12V10h-1V7a5 5 0 0 0-5-5Zm3 8H9V7a3 3 0 0 1 6 0v3Z",
		check: "M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z",
		arrow: "M13 5l7 7-7 7-1.4-1.4L16.2 13H4v-2h12.2l-4.6-4.6L13 5Z"
	};

	/* --------------------------------------------------------- helpers ---- */

	function esc(value) {
		return String(value)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function icon(name, size, cls) {
		size = size || 16;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="currentColor"' +
			(cls ? ' class="' + cls + '"' : "") + ' aria-hidden="true"><path d="' + (ICONS[name] || ICONS.search) + '"/></svg>';
	}

	// Escape text and wrap case-insensitive matches of `query` in <mark>.
	function hl(text, query) {
		var q = String(query || "").toLowerCase().trim();
		if (!q) return esc(text);
		var lower = String(text).toLowerCase();
		var out = "";
		var cursor = 0;
		var index = lower.indexOf(q, cursor);
		while (index !== -1) {
			if (index > cursor) out += esc(text.slice(cursor, index));
			out += '<mark class="wpss-mark">' + esc(text.slice(index, index + q.length)) + "</mark>";
			cursor = index + q.length;
			index = lower.indexOf(q, cursor);
		}
		if (cursor < text.length) out += esc(text.slice(cursor));
		return out;
	}

	function initials(name) {
		return name.split(" ").map(function (p) { return p.charAt(0); }).join("").slice(0, 2);
	}

	function firstMatchLine(snippet, query) {
		var q = String(query || "").toLowerCase().trim();
		var lines = snippet.split("\n");
		if (!q) return lines[0];
		for (var i = 0; i < lines.length; i++) {
			if (lines[i].toLowerCase().indexOf(q) !== -1) return lines[i];
		}
		return lines[0];
	}

	/* ---------------------------------------------------------- search ---- */

	function includesAny(haystacks, q) {
		for (var i = 0; i < haystacks.length; i++) {
			if (haystacks[i] && haystacks[i].toLowerCase().indexOf(q) !== -1) return true;
		}
		return false;
	}

	function search(query) {
		var q = String(query || "").toLowerCase().trim();
		if (!q) {
			return { users: DATA.users, plugins: DATA.plugins, options: DATA.options, settings: DATA.settings };
		}
		return {
			users: DATA.users.filter(function (u) {
				return includesAny([u.username, u.displayName, u.email, u.role].concat(u.capabilities), q);
			}),
			plugins: DATA.plugins.filter(function (p) {
				return includesAny([p.name, p.slug, p.author, p.description, p.version], q);
			}),
			options: DATA.options.filter(function (o) {
				return includesAny([o.name, o.explainer, o.protected ? "" : o.value], q);
			}),
			settings: DATA.settings.filter(function (s) {
				return includesAny([s.source, s.snippet].concat(s.breadcrumb), q);
			})
		};
	}

	/* ------------------------------------------------------- fragments ---- */

	function pill(inner, bg, color) {
		return '<span class="wpss-pill" style="background:' + bg + ';color:' + color + '">' + inner + "</span>";
	}

	function facetGlyph(facet, size) {
		var m = FACET_META[facet];
		return '<span class="wpss-glyph" style="width:' + size + "px;height:" + size + "px;background:" + m.chipBg +
			";color:" + m.accent + '">' + icon(m.icon, Math.round(size * 0.56)) + "</span>";
	}

	function statusPill(active) {
		return active
			? pill(icon("check", 12) + " Active", "rgba(120, 235, 195, 0.16)", "#98efcf")
			: pill("Inactive", "rgba(255, 255, 255, 0.08)", "rgba(255,255,255,0.55)");
	}

	function versionBadge(version) {
		return '<span class="wpss-version">v' + esc(version) + "</span>";
	}

	function updateBadge(version) {
		return pill(icon("arrow", 11) + " " + esc(version), "rgba(255, 190, 130, 0.16)", "#ffcf9d");
	}

	function roleBadge(role) {
		var m = FACET_META.users;
		return pill(esc(role), m.chipBg, m.chipText);
	}

	function autoloadChip(autoload) {
		var on = autoload === "yes";
		return pill('autoload: <span class="wpss-mono">' + autoload + "</span>",
			on ? "rgba(168, 216, 255, 0.14)" : "rgba(255,255,255,0.06)",
			on ? "#a8d8ff" : "rgba(255,255,255,0.5)");
	}

	function protectedValue(value, isProtected) {
		if (isProtected) {
			return '<span class="wpss-value wpss-value--locked">' + icon("lock", 12) +
				new Array(19).join("•") + '<span class="wpss-value-tag">protected</span></span>';
		}
		return '<span class="wpss-value">' + esc(value) + "</span>";
	}

	function sourceBadge(source, kind) {
		var m = FACET_META.settings;
		return pill(icon(kind === "plugin" ? "plug" : "sliders", 11) + esc(source), m.chipBg, m.chipText);
	}

	function breadcrumb(crumbs) {
		var parts = crumbs.map(function (crumb, i) {
			var sep = i > 0 ? '<span class="wpss-crumb-sep">›</span>' : "";
			var cls = i === crumbs.length - 1 ? "wpss-crumb-last" : "";
			return sep + '<span class="' + cls + '">' + esc(crumb) + "</span>";
		});
		return '<span class="wpss-crumbs">' + parts.join("") + "</span>";
	}

	function langTag(language) {
		return '<span class="wpss-lang" style="color:' + (LANG_COLOR[language] || "#fff") + '">' + esc(language) + "</span>";
	}

	function codeSnippet(snippet, query, compact) {
		var text = compact ? firstMatchLine(snippet, query) : snippet;
		return '<pre class="wpss-code' + (compact ? " wpss-code--compact" : "") + '"><code>' + hl(text, query) + "</code></pre>";
	}

	/* ----------------------------------------------------- list & rows ---- */

	function rowGlyph(facet, item) {
		if (facet === "users") {
			return '<span class="wpss-avatar" style="background:hsl(' + item.hue + ' 55% 42%)">' + esc(initials(item.displayName)) + "</span>";
		}
		return facetGlyph(facet, 32);
	}

	function rowPrimary(facet, item, query) {
		if (facet === "users") {
			return '<div class="wpss-row-title"><span>' + hl(item.displayName, query) +
				'</span><span class="wpss-handle">@' + hl(item.username, query) + "</span></div>";
		}
		if (facet === "plugins") {
			return '<div class="wpss-row-title"><span class="wpss-truncate">' + hl(item.name, query) + "</span>" + versionBadge(item.version) + "</div>";
		}
		if (facet === "options") {
			return '<div class="wpss-row-title wpss-mono wpss-truncate">' + hl(item.name, query) + "</div>";
		}
		return '<div class="wpss-row-title wpss-row-title--settings">' + langTag(item.language) + breadcrumb(item.breadcrumb) + "</div>";
	}

	function rowSecondary(facet, item, query) {
		if (facet === "users") {
			return '<div class="wpss-row-sub">' + roleBadge(item.role) +
				'<span class="wpss-truncate wpss-dim">' + hl(item.email, query) + "</span></div>";
		}
		if (facet === "plugins") {
			return '<div class="wpss-row-sub">' + statusPill(item.active) + (item.updateAvailable ? updateBadge(item.updateAvailable) : "") + "</div>";
		}
		if (facet === "options") {
			return '<div class="wpss-row-sub wpss-italic">' + autoloadChip(item.autoload) +
				'<span class="wpss-truncate wpss-not-italic">' + hl(item.explainer, query) + "</span></div>";
		}
		return '<div class="wpss-row-code">' + codeSnippet(item.snippet, query, true) + "</div>";
	}

	function resultRow(facet, item, query, selected) {
		var m = FACET_META[facet];
		var style = selected ? "background:" + m.tint + ";box-shadow:inset 0 0 0 1px " + m.chipBg : "";
		return '<button type="button" class="wpss-row' + (selected ? " is-selected" : "") + '" data-row="' + esc(item.id) +
			'" style="' + style + '">' +
			rowGlyph(facet, item) +
			'<div class="wpss-row-body">' + rowPrimary(facet, item, query) + rowSecondary(facet, item, query) + "</div>" +
			(selected ? icon("arrow", 14, "wpss-row-arrow") : "") +
			"</button>";
	}

	function sectionHeader(facet, count) {
		var m = FACET_META[facet];
		return '<div class="wpss-section-head">' +
			'<span class="wpss-section-label" style="color:' + m.accent + '">' + m.label + "</span>" +
			'<span class="wpss-section-count">' + count + "</span>" +
			'<span class="wpss-rule"></span></div>';
	}

	/* --------------------------------------------------------- preview ---- */

	function field(label, inner) {
		return '<div class="wpss-field"><div class="wpss-field-label">' + esc(label) + '</div><div class="wpss-field-body">' + inner + "</div></div>";
	}

	function userPreview(u, query) {
		var caps = u.capabilities.map(function (c) {
			return '<span class="wpss-cap">' + hl(c, query) + "</span>";
		}).join("");
		return "" +
			'<div class="wpss-preview-head">' +
				'<span class="wpss-avatar wpss-avatar--lg" style="background:hsl(' + u.hue + ' 55% 42%)">' + esc(initials(u.displayName)) + "</span>" +
				'<div><div class="wpss-preview-title">' + hl(u.displayName, query) + '</div><div class="wpss-handle wpss-handle--lg">@' + esc(u.username) + "</div></div>" +
			"</div>" +
			field("Role", roleBadge(u.role)) +
			field("Email", '<span class="wpss-dim2">' + hl(u.email, query) + "</span>") +
			field("Capabilities", '<div class="wpss-cap-list">' + caps + "</div>") +
			'<div class="wpss-grid2">' +
				field("Registered", '<span class="wpss-mono wpss-dim2">' + esc(u.registered) + "</span>") +
				field("Last login", '<span class="wpss-mono wpss-dim2">' + esc(u.lastLogin) + "</span>") +
			"</div>";
	}

	function pluginPreview(p, query) {
		var note = p.updateAvailable
			? '<div class="wpss-update-note">Update from <b>' + esc(p.version) + "</b> to <b>" + esc(p.updateAvailable) + "</b> available.</div>"
			: "";
		return "" +
			'<div class="wpss-preview-head">' + facetGlyph("plugins", 44) +
				'<div><div class="wpss-preview-title">' + hl(p.name, query) + '</div><div class="wpss-byline">by ' + esc(p.author) + "</div></div></div>" +
			'<div class="wpss-row-sub wpss-preview-badges">' + statusPill(p.active) + versionBadge(p.version) + (p.updateAvailable ? updateBadge(p.updateAvailable) : "") + "</div>" +
			field("Description", '<span class="wpss-relaxed">' + hl(p.description, query) + "</span>") +
			field("Plugin file", '<span class="wpss-mono wpss-dim2">' + hl(p.slug, query) + "</span>") +
			note;
	}

	function optionPreview(o, query) {
		return "" +
			'<div class="wpss-preview-head wpss-preview-head--tight">' + facetGlyph("options", 30) +
				'<span class="wpss-mono wpss-option-name">' + hl(o.name, query) + "</span></div>" +
			'<p class="wpss-explainer">' + hl(o.explainer, query) + "</p>" +
			field("Value", protectedValue(o.value, o.protected)) +
			'<div class="wpss-grid2">' +
				field("Autoload", autoloadChip(o.autoload)) +
				field("wp_options", '<span class="wpss-mono wpss-dim">option_name</span>') +
			"</div>";
	}

	function settingPreview(s, query) {
		return "" +
			'<div class="wpss-row-sub wpss-preview-badges">' + sourceBadge(s.source, s.sourceKind) + langTag(s.language) + "</div>" +
			'<div class="wpss-preview-crumbs">' + breadcrumb(s.breadcrumb) + "</div>" +
			field("Matched source", codeSnippet(s.snippet, query, false)) +
			'<p class="wpss-index-note">Indexed from the live settings screen rendered under <span class="wpss-not-italic wpss-dim2">' +
				esc(s.breadcrumb.join(" › ")) + "</span>.</p>";
	}

	function preview(facet, item, query) {
		var m = FACET_META[facet];
		var body =
			facet === "users" ? userPreview(item, query) :
			facet === "plugins" ? pluginPreview(item, query) :
			facet === "options" ? optionPreview(item, query) :
			settingPreview(item, query);
		return '<div class="wpss-preview">' +
			'<div class="wpss-preview-eyebrow"><span style="color:' + m.accent + '">' + m.label + '</span><span class="wpss-rule"></span></div>' +
			body + "</div>";
	}

	/* ------------------------------------------------------------ app ---- */

	var state = { query: "", selectedId: "", open: false };
	var refs = {};

	function flatten(results) {
		var out = [];
		FACET_ORDER.forEach(function (facet) {
			results[facet].forEach(function (item) { out.push({ facet: facet, id: item.id, item: item }); });
		});
		return out;
	}

	function total(results) {
		return results.users.length + results.plugins.length + results.options.length + results.settings.length;
	}

	function bodyHtml(results, flat, selected, query) {
		if (!flat.length) {
			return '<div class="wpss-empty">' + icon("search", 30, "wpss-empty-icon") +
				'<p class="wpss-empty-title">No matches for “<span>' + esc(query) + "</span>”</p>" +
				'<p class="wpss-empty-hint">Try <i>admin</i>, <i>woo</i>, <i>stripe</i>, <i>blogname</i>, or <i>permalink</i>.</p></div>';
		}

		var list = "";
		FACET_ORDER.forEach(function (facet) {
			var items = results[facet];
			if (!items.length) return;
			list += '<section class="wpss-section">' + sectionHeader(facet, items.length);
			items.forEach(function (item) {
				list += resultRow(facet, item, query, selected && item.id === selected.id);
			});
			list += "</section>";
		});

		var previewHtml = selected ? preview(selected.facet, selected.item, query) : "";
		return '<div class="wpss-grid">' +
			'<div class="wpss-list wpss-scroll" id="wpss-list">' + list + "</div>" +
			'<div class="wpss-preview-pane wpss-scroll">' + previewHtml + "</div></div>";
	}

	function footerHtml(results) {
		var dots = FACET_ORDER.map(function (facet) {
			var n = results[facet].length;
			var m = FACET_META[facet];
			return '<span class="wpss-fdot" style="opacity:' + (n ? 1 : 0.4) + '">' +
				'<span class="wpss-fdot-mark" style="background:' + m.accent + ";opacity:" + (n ? 1 : 0.25) + '"></span>' +
				m.label + ' <b>' + n + "</b></span>";
		}).join("");
		var hints =
			keyHint("navigate", "↑↓") + keyHint("open", "↩") + keyHint("clear", "esc");
		return '<div class="wpss-fcounts">' + dots + '</div><div class="wpss-fhints">' + hints + "</div>";
	}

	function keyHint(label, glyph) {
		return '<span class="wpss-khint"><kbd>' + glyph + "</kbd><span>" + label + "</span></span>";
	}

	function update() {
		var results = search(state.query);
		var flat = flatten(results);

		var exists = flat.some(function (f) { return f.id === state.selectedId; });
		if (!exists) state.selectedId = flat.length ? flat[0].id : "";
		var selected = flat.filter(function (f) { return f.id === state.selectedId; })[0] || null;

		// Rebuilding innerHTML resets scrollTop, so preserve the list's scroll
		// position across re-renders (e.g. pointer-driven selection changes).
		var queryChanged = state.query !== refs.lastQuery;
		var prevList = refs.body.querySelector("#wpss-list");
		var scrollTop = (prevList && !queryChanged) ? prevList.scrollTop : 0;

		refs.body.innerHTML = bodyHtml(results, flat, selected, state.query);
		refs.footer.innerHTML = footerHtml(results);
		refs.clear.hidden = !state.query;

		var newList = refs.body.querySelector("#wpss-list");
		if (newList) newList.scrollTop = scrollTop;

		refs.flat = flat;
		refs.lastQuery = state.query;
	}

	function move(delta) {
		var flat = refs.flat || [];
		if (!flat.length) return;
		var idx = -1;
		for (var i = 0; i < flat.length; i++) {
			if (flat[i].id === state.selectedId) { idx = i; break; }
		}
		var next = (idx + delta + flat.length) % flat.length;
		state.selectedId = flat[next].id;
		update();
		var row = refs.body.querySelector('[data-row="' + flat[next].id + '"]');
		if (row) row.scrollIntoView({ block: "nearest" });
	}

	function ensureRoot() {
		var root = document.getElementById("wpss-root");
		if (root) return root;
		root = document.createElement("div");
		root.id = "wpss-root";
		document.body.appendChild(root);
		return root;
	}

	function open() {
		if (state.open) return;
		state.open = true;
		if (refs.overlay) refs.overlay.classList.add("is-open");
		state.query = "";
		state.selectedId = "";
		if (refs.input) refs.input.value = "";
		update();
		if (refs.input) refs.input.focus();
	}

	function close() {
		if (!state.open) return;
		state.open = false;
		if (refs.overlay) refs.overlay.classList.remove("is-open");
	}

	function toggle() {
		state.open ? close() : open();
	}

	// Targeted selection change: updates only the two affected rows and the
	// preview pane, never rebuilding the list. This is the mousemove fix —
	// hover never triggers a full re-render.
	function setSelected(newId) {
		if (!newId || newId === state.selectedId) return;
		var flat = refs.flat || [];
		var prev = null, next = null;
		for (var i = 0; i < flat.length; i++) {
			if (flat[i].id === state.selectedId) prev = flat[i];
			if (flat[i].id === newId) next = flat[i];
		}
		state.selectedId = newId;
		if (prev) {
			var pr = refs.body.querySelector('[data-row="' + prev.id + '"]');
			if (pr) {
				pr.classList.remove("is-selected");
				pr.removeAttribute("style");
				var arr = pr.querySelector(".wpss-row-arrow");
				if (arr) arr.parentNode.removeChild(arr);
			}
		}
		if (next) {
			var nr = refs.body.querySelector('[data-row="' + next.id + '"]');
			if (nr) {
				var m = FACET_META[next.facet];
				nr.classList.add("is-selected");
				nr.setAttribute("style", "background:" + m.tint + ";box-shadow:inset 0 0 0 1px " + m.chipBg);
				if (!nr.querySelector(".wpss-row-arrow")) nr.insertAdjacentHTML("beforeend", icon("arrow", 14, "wpss-row-arrow"));
			}
			var pane = refs.body.querySelector(".wpss-preview-pane");
			if (pane) pane.innerHTML = next.facet ? preview(next.facet, next.item, state.query) : "";
		}
	}

	function mount(root) {
		root.innerHTML =
			'<div class="wpss-overlay">' +
				'<div class="wpss-panel" id="wpss-panel">' +
					'<div class="wpss-search">' +
						icon("search", 22, "wpss-search-icon") +
						'<input id="wpss-input" class="wpss-input" type="text" spellcheck="false" autocomplete="off" placeholder="Search users, plugins, options &amp; settings…" />' +
						'<button type="button" id="wpss-clear" class="wpss-clear" hidden>Clear</button>' +
					"</div>" +
					'<div id="wpss-body"></div>' +
					'<div id="wpss-footer" class="wpss-footer"></div>' +
				"</div>" +
			"</div>";

		refs.overlay = root.querySelector(".wpss-overlay");
		refs.panel = root.querySelector("#wpss-panel");
		refs.input = root.querySelector("#wpss-input");
		refs.clear = root.querySelector("#wpss-clear");
		refs.body = root.querySelector("#wpss-body");
		refs.footer = root.querySelector("#wpss-footer");

		refs.input.addEventListener("input", function (e) {
			state.query = e.target.value;
			update();
		});

		refs.input.addEventListener("keydown", function (e) {
			if (e.key === "ArrowDown") { e.preventDefault(); move(1); }
			else if (e.key === "ArrowUp") { e.preventDefault(); move(-1); }
			else if (e.key === "Enter") {
				e.preventDefault();
				refs.panel.classList.add("is-flash");
				setTimeout(function () { refs.panel.classList.remove("is-flash"); }, 320);
				var sel = null;
				var flat = refs.flat || [];
				for (var i = 0; i < flat.length; i++) {
					if (flat[i].id === state.selectedId) { sel = flat[i]; break; }
				}
				if (sel && sel.item && sel.item.url) {
					window.location.assign(sel.item.url);
				}
			} else if (e.key === "Escape") {
				e.preventDefault();
				close();
			}
		});

		refs.clear.addEventListener("click", function () {
			state.query = "";
			refs.input.value = "";
			update();
			refs.input.focus();
		});

		// Hover-to-select via a targeted update — no full re-render.
		refs.body.addEventListener("mousemove", function (e) {
			var row = e.target.closest ? e.target.closest("[data-row]") : null;
			if (row && row.getAttribute("data-row") !== state.selectedId) {
				setSelected(row.getAttribute("data-row"));
			}
		});

		// Click on the overlay backdrop (outside the panel) dismisses.
		refs.overlay.addEventListener("click", function (e) {
			if (!e.target.closest("#wpss-panel")) close();
		});

		update();
	}

	function bindGlobalEvents() {
		var cfg = (typeof window !== "undefined" && window.wp_search_modal_config) ? window.wp_search_modal_config : {};
		var shortcutKey = (cfg.shortcutKey || "j").toLowerCase();
		document.addEventListener("keydown", function (e) {
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === shortcutKey) {
				e.preventDefault();
				e.stopImmediatePropagation();
				toggle();
				return;
			}
			if (e.key === "Escape" && state.open) {
				e.preventDefault();
				close();
			}
		});

		var triggers = document.querySelectorAll("[data-wp-search-trigger]");
		for (var i = 0; i < triggers.length; i++) {
			(function (t) {
				t.addEventListener("click", function () { open(); });
				t.addEventListener("keydown", function (e) {
					if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
				});
			})(triggers[i]);
		}

		var barLink = document.querySelector("#wp-admin-bar-wp-search a");
		if (barLink) {
			barLink.addEventListener("click", function (e) {
				e.preventDefault();
				open();
			});
		}
	}

	function boot() {
		injectStyles();
		var root = ensureRoot();
		mount(root);
		bindGlobalEvents();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}

	// Exported for the Vite preview harness; harmless under WordPress.
	if (typeof window !== "undefined") {
		window.WPSS = { mount: mount, open: open, close: close, toggle: toggle };
	}
})();
