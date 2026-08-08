/**
 * Admin Search — vanilla JS (module pattern, no framework, no build step).
 *
 * Slice C / IA-47. Boots against the `ASAdminSearch` object localized by
 * AS_Admin_Page. Provides:
 *   - Ctrl/Cmd+K opens the search modal from any admin screen; Esc closes.
 *   - 200 ms debounced GET /wp-json/admin-search/v1/search?q=…&limit=15.
 *   - Grouped rendering by record type with per-group labels from strings.
 *   - ↑/↓ moves the active row, Enter opens its url (top-level frame).
 *   - Tab is trapped inside the modal; focus returns to the opener element.
 *   - REST errors render an inline <p role="alert">, never window.alert.
 *
 * Accessibility: input uses combobox semantics (aria-autocomplete), results
 * use role=listbox/option, the active row carries aria-selected="true", and a
 * visually-hidden aria-live region announces result counts.
 */

( function () {
	'use strict';

	var Cfg = window.ASAdminSearch || {};

	var defaults = {
		placeholder: 'Search the admin…',
		empty: 'No matches',
		searching: 'Searching…',
		resultsFor: 'results for',
		noResultsFor: 'No results for',
		error: 'Something went wrong. Please try again.',
		retry: 'Try again',
		seeAll: 'See all results on the search page',
		types: { settings: 'Settings', user: 'People', product: 'Products', content: 'Content' }
	};

	function labelForType( type ) {
		var map = ( Cfg.strings && Cfg.strings.types ) || defaults.types;
		return map[ type ] || type;
	}

	function str( key, fallback ) {
		return ( Cfg.strings && Cfg.strings[ key ] ) || fallback;
	}

	var REST_URL = Cfg.restUrl || '/wp-json/admin-search/v1/search';
	var LIMIT = 15;
	var DEBOUNCE_MS = 200;

	var overlay = null;
	var modal = null;
	var openerEl = null;
	var pageShell = null;
	var modalShell = null;
	var debounceTimer = 0;
	var activeIndex = -1;

	// ---------------------------------------------------------------------------
	// DOM helpers
	// ---------------------------------------------------------------------------

	function el( tag, attrs, parent ) {
		var node = document.createElement( tag );
		if ( 'string' === typeof attrs ) {
			node.className = attrs;
		} else if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				if ( 'class' === key ) {
					node.className = attrs[ key ];
				} else if ( 'text' === key ) {
					node.textContent = attrs[ key ];
				} else if ( 'html' === key ) {
					node.innerHTML = attrs[ key ];
				} else {
					node.setAttribute( key, attrs[ key ] );
				}
			} );
		}
		if ( parent ) {
			parent.appendChild( node );
		}
		return node;
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	// ---------------------------------------------------------------------------
	// Search shell — one input + results box + live region, reused for the
	// full page and the modal.
	// ---------------------------------------------------------------------------

	function buildShell( parent, withHints ) {
		var shell = {};

		var search = el( 'div', 'as-search', parent );
		el( 'span', { 'class': 'as-search-icon', 'aria-hidden': 'true', 'text': '⌕' }, search );

		var input = el( 'input', 'as-search-input', search );
		input.type = 'search';
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-label', str( 'placeholder', defaults.placeholder ) );
		input.setAttribute( 'aria-controls', 'as-results-0' );
		input.placeholder = str( 'placeholder', defaults.placeholder );
		input.autocomplete = 'off';

		var results = el( 'div', 'as-results', parent );
		var live = el( 'span', { 'class': 'as-live', 'aria-live': 'polite' }, parent );

		shell.input = input;
		shell.results = results;
		shell.live = live;

		if ( withHints ) {
			var hints = el( 'div', 'as-hints', search );
			hints.innerHTML = '<span><kbd class="kbd">Ctrl</kbd>/<kbd class="kbd">Cmd</kbd>+<kbd class="kbd">K</kbd> open</span>' +
				'<span><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> navigate</span>' +
				'<span><kbd class="kbd">Enter</kbd> open</span>' +
				'<span><kbd class="kbd">Esc</kbd> clear · close</span>';
		}

		input.addEventListener( 'input', function () {
			debouncedRequest( shell );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			handleInputKey( e, shell );
		} );

		return shell;
	}

	function currentShell() {
		if ( isModalOpen() ) {
			return modalShell;
		}
		return pageShell;
	}

	// ---------------------------------------------------------------------------
	// Data & rendering (shared)
	// ---------------------------------------------------------------------------

	function debouncedRequest( shell, immediate ) {
		if ( debounceTimer ) {
			clearTimeout( debounceTimer );
		}
		var run = function () {
			request( shell, shell.input.value.trim() );
		};
		if ( immediate ) {
			run();
		} else {
			debounceTimer = setTimeout( run, DEBOUNCE_MS );
		}
	}

	function request( shell, term ) {
		if ( ! term ) {
			renderEmpty( shell );
			return;
		}
		shell.live.textContent = str( 'searching', defaults.searching );
		shell.input.setAttribute( 'aria-expanded', 'true' );

		fetch( REST_URL + '?q=' + encodeURIComponent( term ) + '&limit=' + LIMIT, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': Cfg.nonce || '' }
		} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				return res.json();
			} )
			.then( function ( data ) {
				renderResults( shell, ( data && data.results ) || [], term );
			} )
			.catch( function () {
				renderError( shell );
			} );
	}

	function renderResults( shell, results, term ) {
		shell.results.textContent = '';

		if ( results.length === 0 ) {
			renderNoResults( shell, term );
			return;
		}

		activeIndex = -1;

		var groups = {};
		results.forEach( function ( r ) {
			( groups[ r.type ] = groups[ r.type ] || [] ).push( r );
		} );

		var total = 0;

		Object.keys( groups ).forEach( function ( type ) {
			var items = groups[ type ];
			total += items.length;

			var group = el( 'div', 'as-group', shell.results );
			var head = el( 'div', 'as-group-head', group );
			el( 'h3', { 'text': labelForType( type ) }, head );
			el( 'span', { 'class': 'cnt', 'text': String( items.length ) }, head );

			var list = el( 'ul', 'as-list', group );
			items.forEach( function ( rec ) {
				var li = el( 'li', 'as-list-item', list );
				var a = el( 'a', 'as-result', li );
				a.href = rec.url || '#';
				a.target = '_top';
				a.setAttribute( 'role', 'option' );
				a.setAttribute( 'aria-selected', 'false' );
				a.setAttribute( 'aria-label', labelForType( type ) + ': ' + ( rec.title || '' ) );

				var top = el( 'span', 'as-result-top', a );
				el( 'span', 'as-result-title', top ).textContent = rec.title || '';
				el( 'span', 'as-badge ' + esc( type ), top ).textContent = labelForType( type );

				var sub = el( 'span', 'as-result-sub', a );
				el( 'span', 'as-result-desc', sub ).textContent = rec.snippet || '';
				if ( rec.breadcrumb ) {
					el( 'span', 'as-result-path', sub ).textContent = rec.breadcrumb;
				}

				a.addEventListener( 'focus', function () {
					setActiveRow( shell, a );
				} );
			} );
		} );

		shell.live.textContent = total + ' ' + str( 'resultsFor', defaults.resultsFor ) + ' “' + term + '”';
	}

	function rowsOf( shell ) {
		return shell.results.querySelectorAll( 'a.as-result' );
	}

	function setActiveRow( shell, a ) {
		var rows = rowsOf( shell );
		for ( var i = 0; i < rows.length; i++ ) {
			rows[ i ].setAttribute( 'aria-selected', rows[ i ] === a ? 'true' : 'false' );
		}
		activeIndex = Array.prototype.indexOf.call( rows, a );
		shell.input.setAttribute( 'aria-expanded', 'true' );
	}

	function moveActive( shell, delta ) {
		var rows = rowsOf( shell );
		if ( rows.length === 0 ) {
			return;
		}
		var idx = activeIndex < 0 ? 0 : activeIndex;
		idx = ( idx + delta + rows.length ) % rows.length;
		var target = rows[ idx ];
		target.focus();
		setActiveRow( shell, target );
	}

	function renderNoResults( shell, term ) {
		var none = el( 'div', 'as-noresults', shell.results );
		el( 'h3', 'noresults-title', none ).textContent = str( 'empty', defaults.empty );
		el( 'p', '', none ).textContent = str( 'noResultsFor', defaults.noResultsFor ) + ' “' + term + '”.';
		shell.live.textContent = str( 'empty', defaults.empty );
	}

	function renderEmpty( shell ) {
		shell.results.textContent = '';
		var empty = el( 'div', 'as-empty', shell.results );
		el( 'p', 'big', empty ).textContent = str( 'placeholder', defaults.placeholder );
		el( 'p', 'sub', empty ).textContent = str( 'emptyHint', 'Search plugin settings, users, products, or posts.' );
	}

	function renderError( shell ) {
		shell.results.textContent = '';
		var box = el( 'div', 'as-error', shell.results );
		el( 'p', { 'role': 'alert' }, box ).textContent = str( 'error', defaults.error );
		var retry = el( 'button', { 'class': 'as-retry', 'type': 'button' }, box );
		retry.textContent = str( 'retry', defaults.retry );
		retry.addEventListener( 'click', function () {
			request( shell, shell.input.value.trim() );
		} );
	}

	// ---------------------------------------------------------------------------
	// Keyboard
	// ---------------------------------------------------------------------------

	function handleInputKey( e, shell ) {
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			moveActive( shell, 1 );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			moveActive( shell, -1 );
		} else if ( e.key === 'Enter' ) {
			var rows = rowsOf( shell );
			if ( activeIndex >= 0 && rows[ activeIndex ] ) {
				e.preventDefault();
				window.top.location = rows[ activeIndex ].href;
			}
		} else if ( e.key === 'Escape' ) {
			e.preventDefault();
			if ( shell.input.value ) {
				shell.input.value = '';
				renderEmpty( shell );
			} else {
				closeModal();
			}
		}
	}

	// ---------------------------------------------------------------------------
	// Modal lifecycle
	// ---------------------------------------------------------------------------

	function buildModal() {
		if ( overlay ) {
			return;
		}

		overlay = el( 'div', 'as-modal-overlay' );
		document.body.appendChild( overlay );

		modal = el( 'div', 'as-modal', overlay );
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', str( 'placeholder', defaults.placeholder ) );

		var head = el( 'div', 'as-modal-head', modal );
		modalShell = buildShell( head, false );

		// Results live in a dedicated scrollable body below the input.
		var body = el( 'div', 'as-modal-body', modal );
		body.appendChild( modalShell.results );
		body.appendChild( modalShell.live );

		var foot = el( 'div', 'as-modal-foot', modal );
		if ( Cfg.pageUrl ) {
			var all = el( 'a', 'as-see-all', foot );
			all.href = Cfg.pageUrl;
			all.target = '_top';
			all.textContent = str( 'seeAll', defaults.seeAll );
		}
		el( 'span', 'as-foot-hint', foot ).textContent = '↑↓ · Enter · Esc';

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Tab' && isModalOpen() ) {
				trapTab( e );
			}
		} );
	}

	function isModalOpen() {
		return overlay && overlay.style.display !== 'none';
	}

	function openModal( trigger ) {
		buildModal();
		openerEl = trigger || document.activeElement;
		overlay.style.display = 'flex';
		modal.style.display = 'flex';
		renderEmpty( modalShell );
		setTimeout( function () {
			modalShell.input.focus();
		}, 0 );
	}

	function closeModal() {
		if ( overlay ) {
			overlay.style.display = 'none';
		}
		if ( openerEl && openerEl.focus ) {
			openerEl.focus();
		}
	}

	function trapTab( e ) {
		e.preventDefault();
		var focusables = [ modalShell.input ];
		rowsOf( modalShell ).forEach( function ( r ) {
			focusables.push( r );
		} );
		var seeAll = overlay.querySelector( '.as-see-all' );
		if ( seeAll ) {
			focusables.push( seeAll );
		}
		var last = focusables[ focusables.length - 1 ];
		if ( last ) {
			last.focus();
		}
	}

	// ---------------------------------------------------------------------------
	// Global listeners + boot
	// ---------------------------------------------------------------------------

	function initPage() {
		var root = document.getElementById( 'as-page-root' );
		if ( ! root ) {
			return;
		}
		root.classList.add( 'as-wrap' );
		pageShell = buildShell( root, true );
		renderEmpty( pageShell );
	}

	function initWindow() {
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && ( e.key === 'k' || e.key === 'K' ) ) {
				e.preventDefault();
				openModal();
			}
		} );

		var node = document.getElementById( 'wp-admin-bar-as-admin-search' );
		if ( node ) {
			node.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openModal( node );
			} );
		}
	}

	function boot() {
		if ( ! window.ASAdminSearch || ! window.ASAdminSearch.restUrl ) {
			// Plugin not localized properly — do not throw, just stay inert.
			return;
		}
		initWindow();
		initPage();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();