/**
 * WP Search Modal
 *
 * Vanilla JS command-palette search modal for WordPress admin.
 *
 * @package WP_Search
 */

(function() {
	'use strict';

	/**
	 * Display configuration for each source type.
	 *
	 * @type {Object<string, {label: string, icon: string, badgeClass: string}>}
	 */
	const SOURCE_CONFIG = {
		content:  { label: 'Posts',   icon: 'dashicons-admin-post',    badgeClass: 'wp-search-badge--posts' },
		users:    { label: 'Users',   icon: 'dashicons-admin-users',   badgeClass: 'wp-search-badge--users' },
		plugins:  { label: 'Plugins', icon: 'dashicons-admin-plugins', badgeClass: 'wp-search-badge--plugins' },
		settings: { label: 'Settings pages', icon: 'dashicons-admin-generic', badgeClass: 'wp-search-badge--options' },
		options:  { label: 'Options', icon: 'dashicons-admin-settings', badgeClass: 'wp-search-badge--options' },
		menus:    { label: 'Menus',   icon: 'dashicons-menu',           badgeClass: 'wp-search-badge--menus' },
		products: { label: 'Products',icon: 'dashicons-cart',           badgeClass: 'wp-search-badge--products' },
	};

	/**
	 * Default display order for source groups.
	 *
	 * @type {Array<string>}
	 */
	const SOURCE_ORDER = ['content', 'users', 'plugins', 'settings', 'options', 'menus', 'products'];

	/**
	 * REST client for search requests.
	 */
	class WPSearchRestClient {
		/**
		 * @param {string} restUrl
		 * @param {string} restNonce
		 */
		constructor(restUrl, restNonce) {
			this.restUrl = restUrl;
			this.restNonce = restNonce;
		}

		/**
		 * Search the REST endpoint via POST.
		 *
		 * @param {string} query
		 * @param {AbortSignal|null} signal
		 * @return {Promise<Object>}
		 */
		search(query, signal) {
			return fetch(this.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.restNonce,
					'Accept': 'application/json',
				},
				body: JSON.stringify({ q: query }),
				signal: signal,
			}).then(function (r) {
				if (!r.ok) {
					throw new Error('Request failed');
				}
				return r.json();
			});
		}
	}

	/**
	 * Search modal controller.
	 */
	class WPSearchModal {
		/**
		 * @param {Object} config
		 */
		constructor(config) {
			this.config = config || {};
			this.client = new WPSearchRestClient(
				this.config.rest_url || '',
				this.config.rest_nonce || ''
			);
			this.isOpen = false;
			this.items = [];
			this.selectedIndex = -1;
			this.debounceTimer = null;
			this.abortController = null;
			this.lastFocused = null;
		}

		/**
		 * Bootstrap the modal.
		 */
		init() {
			this.buildDOM();
			this.bindGlobalKeyboard();
			this.bindTriggers();
		}

		/**
		 * Build the modal DOM and append to body.
		 */
		buildDOM() {
			const overlay = document.createElement('div');
			overlay.id = 'wp-search-modal-overlay';
			overlay.className = 'wp-search-modal-overlay';
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');
			overlay.setAttribute('aria-label', 'Search');

			const strings = this.config.strings || {};
			const placeholder = this._esc(strings.placeholder || 'Search...');

			overlay.innerHTML =
				'<div class="wp-search-modal" role="document">' +
					'<div class="wp-search-modal__input-wrap">' +
						'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>' +
						'<input type="search" class="wp-search-modal__input" placeholder="' + placeholder + '" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-autocomplete="list" aria-controls="wp-search-results-list" aria-activedescendant="" />' +
						'<div class="wp-search-modal__spinner" aria-hidden="true"></div>' +
						'<kbd class="wp-search-modal__shortcut-hint" aria-hidden="true">Esc</kbd>' +
					'</div>' +
					'<div class="wp-search-modal__results" id="wp-search-results-list" role="listbox"></div>' +
					'<div class="wp-search-modal__footer">' +
						'<span><kbd>↑</kbd> <kbd>↓</kbd> ' + this._esc(strings.navigate || 'navigate') + '</span>' +
						'<span><kbd>↵</kbd> ' + this._esc(strings.select || 'select') + '</span>' +
						'<span class="wp-search-modal__footer-shortcut">' + this._esc(strings.shortcutHint || 'Ctrl K') + '</span>' +
					'</div>' +
				'</div>';

			document.body.appendChild(overlay);

			this.overlay = overlay;
			this.input = /** @type {HTMLInputElement} */ (overlay.querySelector('.wp-search-modal__input'));
			this.resultsEl = /** @type {HTMLElement} */ (overlay.querySelector('.wp-search-modal__results'));
			this.spinner = /** @type {HTMLElement} */ (overlay.querySelector('.wp-search-modal__spinner'));
		}

		/**
		 * Listen for the global Cmd/Ctrl+K shortcut.
		 */
		bindGlobalKeyboard() {
			document.addEventListener('keydown', (e) => {
				const isEditable = /^(input|textarea|select)$/i.test(e.target.tagName) || e.target.isContentEditable;
				if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k' && !isEditable) {
					e.preventDefault();
					this.open();
				}
			});
		}

		/**
		 * Bind click/keyboard events for triggers, overlay, and input.
		 */
		bindTriggers() {
			const triggers = document.querySelectorAll('[data-wp-search-trigger]');
			triggers.forEach((t) => {
				t.addEventListener('click', () => this.open());
				t.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						this.open();
					}
				});
			});

			// Fallback: admin bar node click.
			const adminBarLink = document.querySelector('#wp-admin-bar-wp-search a');
			if (adminBarLink) {
				adminBarLink.addEventListener('click', (e) => {
					e.preventDefault();
					this.open();
				});
			}

			this.overlay.addEventListener('click', (e) => {
				if (e.target === this.overlay) {
					this.close();
				}
			});

			this.input.addEventListener('input', () => this.onInput());
			this.overlay.addEventListener('keydown', (e) => this.onKeydown(e));
		}

		/**
		 * Open the modal.
		 */
		open() {
			if (this.isOpen) {
				return;
			}
			this.isOpen = true;
			this.lastFocused = document.activeElement;
			this.overlay.classList.add('is-open');
			document.body.classList.add('wp-search-modal-open');
			this.input.value = '';
			this.input.focus();
			this.renderEmpty();

			// Allow CSS transition to start before clearing selection.
			requestAnimationFrame(() => {
				this.input.focus();
			});
		}

		/**
		 * Close the modal.
		 */
		close() {
			if (!this.isOpen) {
				return;
			}
			this.isOpen = false;
			this.overlay.classList.remove('is-open');
			document.body.classList.remove('wp-search-modal-open');
			if (this.lastFocused && typeof this.lastFocused.focus === 'function') {
				this.lastFocused.focus();
			}
		}

		/**
		 * Handle input changes with debounce.
		 */
		onInput() {
			const query = this.input.value.trim();
			this.selectedIndex = -1;
			clearTimeout(this.debounceTimer);

			if (this.abortController) {
				this.abortController.abort();
			}

			if (!query) {
				this.renderEmpty();
				return;
			}

			this.spinner.classList.add('is-visible');
			this.debounceTimer = setTimeout(() => {
				this.performSearch(query);
			}, 150);
		}

		/**
		 * Execute the search request.
		 *
		 * @param {string} query
		 */
		performSearch(query) {
			if (this.abortController) {
				this.abortController.abort();
			}
			this.abortController = new AbortController();

			this.client.search(query, this.abortController.signal)
				.then((data) => {
					this.renderResults(data);
				})
				.catch((err) => {
					if (err.name !== 'AbortError') {
						this.renderError();
					}
				})
				.finally(() => {
					this.spinner.classList.remove('is-visible');
				});
		}

		/**
		 * Navigate to the raw REST JSON for a query (debug mode).
		 *
		 * The nonce travels as a query param because a top-level navigation
		 * cannot send the X-WP-Nonce header the modal's fetch() uses. Without
		 * it, core's rest_cookie_check_errors() resets the current user to 0
		 * and the endpoint's manage_options check returns 403.
		 *
		 * @param {string} query
		 */
		redirectToRawJson(query) {
			if (!query) {
				return;
			}

			const url = new URL(this.config.rest_url, window.location.origin);
			url.searchParams.set('q', query);
			url.searchParams.set('_wpnonce', this.config.rest_nonce || '');
			window.location.assign(url.toString());
		}

		/**
		 * Handle keyboard navigation inside the modal.
		 *
		 * @param {KeyboardEvent} e
		 */
		onKeydown(e) {
			if (!this.isOpen) {
				return;
			}

			if (e.key === 'Escape') {
				e.preventDefault();
				this.close();
				return;
			}

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				this.moveSelection(1);
				return;
			}

			if (e.key === 'ArrowUp') {
				e.preventDefault();
				this.moveSelection(-1);
				return;
			}

			if (e.key === 'Enter') {
				e.preventDefault();

				if (this.config.debug_mode) {
					this.redirectToRawJson(this.input.value.trim());
					return;
				}

				this.activateSelection();
				return;
			}
		}

		/**
		 * Move the active selection.
		 *
		 * @param {number} delta
		 */
		moveSelection(delta) {
			const next = this.selectedIndex + delta;
			if (next < 0 || next >= this.items.length) {
				return;
			}
			this.setSelection(next);
		}

		/**
		 * Set the active selection by index.
		 *
		 * @param {number} index
		 */
		setSelection(index) {
			if (this.selectedIndex >= 0 && this.items[this.selectedIndex]) {
				this.items[this.selectedIndex].classList.remove('is-selected');
				this.items[this.selectedIndex].removeAttribute('aria-selected');
			}

			this.selectedIndex = index;

			if (this.items[index]) {
				this.items[index].classList.add('is-selected');
				this.items[index].setAttribute('aria-selected', 'true');
				this.items[index].scrollIntoView({ block: 'nearest' });
				this.input.setAttribute('aria-activedescendant', this.items[index].id);
			} else {
				this.input.removeAttribute('aria-activedescendant');
			}
		}

		/**
		 * Activate the currently selected item.
		 */
		activateSelection() {
			const item = this.items[this.selectedIndex];
			if (!item) {
				return;
			}
			const url = item.getAttribute('data-url');
			if (url) {
				window.location.assign(url);
			}
		}

		/**
		 * Render the empty state.
		 */
		renderEmpty() {
			const text = this._esc((this.config.strings || {}).empty || 'No results found.');
			this.resultsEl.innerHTML = '<div class="wp-search-modal__empty">' + text + '</div>';
			this.items = [];
			this.selectedIndex = -1;
			this.input.removeAttribute('aria-activedescendant');
		}

		/**
		 * Render the error state.
		 */
		renderError() {
			const text = this._esc((this.config.strings || {}).error || 'Something went wrong.');
			this.resultsEl.innerHTML = '<div class="wp-search-modal__error">' + text + '</div>';
			this.items = [];
			this.selectedIndex = -1;
			this.input.removeAttribute('aria-activedescendant');
		}

		/**
		 * Render grouped results.
		 *
		 * @param {Object} data
		 */
		renderResults(data) {
			const results = data && data.results ? data.results : [];
			const query = data && data.query ? data.query : '';
			const groups = this.groupResults(results);

			if (!groups.length) {
				this.renderEmpty();
				return;
			}

			let html = '';
			let globalIndex = 0;

			groups.forEach((group) => {
				const config = SOURCE_CONFIG[group.source] || { label: group.source, icon: 'dashicons-admin-generic', badgeClass: '' };
				html += '<div class="wp-search-modal__group" role="group" aria-labelledby="wp-search-group-' + this._esc(group.source) + '">';
				html += '<div class="wp-search-modal__group-label" id="wp-search-group-' + this._esc(group.source) + '">' +
						'<span class="dashicons ' + this._esc(config.icon) + '" aria-hidden="true"></span>' +
						this._esc(config.label) +
					'</div>';

				group.items.forEach((item) => {
					const id = 'wp-search-result-' + globalIndex++;
					const url = this._esc(item.url || '');
					const title = this.highlight(this._esc(item.title || '(no title)'), this._esc(query));
					const description = item.description ? '<div class="wp-search-modal__item-meta">' + this._esc(item.description) + '</div>' : '';
					const breadcrumb = Array.isArray(item.breadcrumb) && item.breadcrumb.length
						? '<div class="wp-search-modal__item-breadcrumb">' + this._esc(item.breadcrumb.join(' › ')) + '</div>'
						: '';
					const snippetText = item.snippet ? item.snippet.replace(/<[^>]*>/g, '') : '';
					const snippet = snippetText
						? '<div class="wp-search-modal__item-snippet"><code>' + this.highlight(this._esc(snippetText), this._esc(query)) + '</code></div>'
						: '';
					const typeLabel = (SOURCE_CONFIG[item.source] || {}).label || this._esc(item.source || '');
					const badgeClass = (SOURCE_CONFIG[item.source] || {}).badgeClass || '';

					html += '<a href="' + url + '" class="wp-search-modal__item" id="' + id + '" data-url="' + url + '" role="option" tabindex="-1">' +
							'<div class="wp-search-modal__item-body">' +
								'<div class="wp-search-modal__item-title">' + title + '</div>' +
								breadcrumb +
								description +
								snippet +
							'</div>' +
							'<span class="wp-search-modal__item-source ' + this._esc(badgeClass) + '">' + typeLabel + '</span>' +
						'</a>';
				});

				html += '</div>';
			});

			this.resultsEl.innerHTML = html;
			this.items = Array.from(this.resultsEl.querySelectorAll('.wp-search-modal__item'));
			if (this.items.length) {
				this.setSelection(0);
			}
		}

		/**
		 * Group a flat list of results by source.
		 *
		 * @param {Array<Object>} results
		 * @return {Array<Object>}
		 */
		groupResults(results) {
			/** @type {Object<string, Array<Object>>} */
			const map = {};
			results.forEach((r) => {
				const s = r.source || 'settings';
				if (!map[s]) {
					map[s] = [];
				}
				map[s].push(r);
			});

			return SOURCE_ORDER
				.filter((k) => map[k] && map[k].length)
				.map((k) => ({
					source: k,
					items: map[k],
				}));
		}

		/**
		 * Highlight matching query text.
		 *
		 * @param {string} text
		 * @param {string} query
		 * @return {string}
		 */
		highlight(text, query) {
			if (!query) {
				return text;
			}
			const safeQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			const parts = text.split(new RegExp('(' + safeQuery + ')', 'gi'));
			return parts
				.map((p) => {
					if (p.toLowerCase() === query.toLowerCase()) {
						return '<mark>' + this._esc(p) + '</mark>';
					}
					return this._esc(p);
				})
				.join('');
		}

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} text
		 * @return {string}
		 */
		_esc(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	}

	// Expose for manual initialization or testing.
	window.WPSearchModal = WPSearchModal;
	window.WPSearchRestClient = WPSearchRestClient;

	// Auto-init when DOM is ready.
	function init() {
		if (window.wp_search_modal_config) {
			const modal = new WPSearchModal(window.wp_search_modal_config);
			modal.init();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
