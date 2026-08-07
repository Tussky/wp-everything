/* SiteMap Redirects admin UI — vanilla JS + D3 from CDN */
(function () {
	'use strict';

	if (!window.SMR) { return; }
	var SMR = window.SMR;
	var I18N = SMR.i18n || {};
	var apiRoot = SMR.root; // e.g. .../sitemap-redirects/v1
	var nonce = SMR.nonce;

	var state = {
		tree: null,
		redirects: [],
		statusColors: {},
		selectedPath: null,
		svg: null,
		g: null,
		treeLayout: null,
		root: null,
		duration: 350,
	};

	// ----- helpers -----------------------------------------------------------

	function esc(s) {
		if (s === null || s === undefined) { return ''; }
		return String(s).replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}

	function statusColor(status) {
		var key = String(status);
		return state.statusColors[key] || state.statusColors.other || '#50575e';
	}

	function statusName(status) {
		var map = { 301: I18N.perm || 'Permanent', 302: I18N.temp || 'Temporary' };
		return map[status] || (I18N.temp || 'Redirect') + ' (' + status + ')';
	}

	function api(path, method) {
		method = method || 'GET';
		var opts = { method: method, headers: {} };
		if (nonce) { opts.headers['X-WP-Nonce'] = nonce; }
		if (method !== 'GET') {
			opts.headers['Content-Type'] = 'application/json';
		}
		return fetch(apiRoot + path, opts).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.json();
		});
	}

	// ----- render tree -------------------------------------------------------

	function initSvg() {
		var svgEl = document.getElementById('smr-tree');
		state.svg = d3.select(svgEl);
		state.g = state.svg.append('g');
		state.treeLayout = d3.tree().nodeSize([26, 220]); // [vertical spacing, horizontal depth]

		// Zoom/pan.
		state.svg.call(d3.zoom().scaleExtent([0.3, 3]).on('zoom', function (ev) {
			state.g.attr('transform', ev.transform);
		}));

		// Re-fit on resize.
		window.addEventListener('resize', function () {
			var panel = document.getElementById('smr-tree-panel');
			if (panel) { svgEl.setAttribute('height', Math.max(600, panel.clientHeight)); }
		});
	}

	function render(payload) {
		state.tree = payload.tree;
		state.redirects = payload.redirects || [];
		state.statusColors = payload.status_colors || {};
		document.querySelector('.smr-loading').style.display = 'none';

		// Counts.
		var countsEl = document.getElementById('smr-counts');
		if (countsEl && payload.counts) {
			countsEl.innerHTML =
				'<span>' + esc(payload.counts.nodes) + ' pages</span>' +
				'<span class="smr-count-sep">·</span>' +
				'<span>' + esc(payload.counts.redirects) + ' redirects</span>';
		}
		var lastEl = document.getElementById('smr-last-index');
		if (lastEl && payload.last_index) {
			lastEl.textContent = 'Last indexed: ' + payload.last_index;
		}

		renderLegend(payload);
		buildHierarchy();
		updateTree(state.root);
	}

	function buildHierarchy() {
		// D3 hierarchy from nested tree, with collapsed-by-default for deep trees.
		state.root = d3.hierarchy(state.tree, function (d) {
			return d.children && d.children.length ? d.children : null;
		});
		state.root.x0 = 0;
		state.root.y0 = 0;

		// Collapse nodes deeper than depth 1 for an uncluttered first view,
		// except keep containers expanded if they have few children.
		function collapse(d) {
			if (d.children && d.children.length) {
				d._children = d.children;
				if (d.depth > 1) { d.children = null; }
				d._children.forEach(collapse);
			}
		}
		if (state.root.children) {
			state.root.children.forEach(function (c) {
				if (c.children && c.children.length > 6) { collapse(c); }
			});
		}
	}

	function nodeClass(d) {
		var cls = 'smr-tree-node ';
		var data = d.data || {};
		cls += (data.type === 'container') ? 'container ' : ((data.type === 'redirect_source') ? 'redirect_source ' : 'leaf ');
		if (data.redirects && data.redirects.length) { cls += 'has-redirect '; }
		if (state.selectedPath && data.path === state.selectedPath) { cls += 'selected '; }
		return cls;
	}

	function updateTree(source) {
		var treeData = state.treeLayout(state.root);
		var nodes = treeData.descendants();
		var links = treeData.descendants().slice(1);

		// Normalize vertical positions (tree layout can produce negatives).
		nodes.forEach(function (d) { d.y = d.depth * 220; });

		// --- Nodes ---
		var node = state.g.selectAll('g.smr-tree-node')
			.data(nodes, function (d) { return d.data.path || (d.data.name + d.depth); });

		var nodeEnter = node.enter().append('g')
			.attr('class', nodeClass)
			.attr('transform', function (d) {
				return 'translate(' + source.y0 + ',' + source.x0 + ')';
			})
			.on('click', function (ev, d) {
				ev.stopPropagation();
				toggleChildren(d);
				selectNode(d);
				updateTree(d);
			});

		nodeEnter.append('circle')
			.attr('r', 1e-6)
			.attr('aria-label', function (d) { return esc((d.data.label || d.data.name) + (d.children || d._children ? ' — ' + I18N.expand + '/' + I18N.collapse : '')); });

		nodeEnter.append('text')
			.attr('dy', '.35em')
			.attr('x', function (d) { return d.children || d._children ? -12 : 12; })
			.attr('text-anchor', function (d) { return d.children || d._children ? 'end' : 'start'; })
			.text(function (d) {
				var label = d.data.label || d.data.name || '/';
				return label.length > 34 ? label.slice(0, 33) + '…' : label;
			})
			.append('title').text(function (d) { return esc(d.data.path || '/') + (d.data.url ? '\n' + esc(d.data.url) : ''); });

		// Merge enter + update.
		var nodeMerge = nodeEnter.merge(node);
		nodeMerge.transition().duration(state.duration)
			.attr('transform', function (d) { return 'translate(' + d.y + ',' + d.x + ')'; })
			.attr('class', nodeClass);
		nodeMerge.select('circle').transition().duration(state.duration)
			.attr('r', function (d) {
				var r = (d.data.type === 'container') ? 5 : 7;
				if (d.data.redirects && d.data.redirects.length) { r = 9; }
				return r;
			});

		// Exit.
		var nodeExit = node.exit().transition().duration(state.duration)
			.attr('transform', function (d) { return 'translate(' + source.y + ',' + source.x + ')'; })
			.remove();
		nodeExit.select('circle').attr('r', 1e-6);

		// --- Links ---
		var link = state.g.selectAll('path.smr-tree-link')
			.data(links, function (d) { return d.data.path; });

		var linkEnter = link.enter().insert('path', 'g')
			.attr('class', function (d) {
				return 'smr-tree-link' + (d.data.redirects && d.data.redirects.length ? ' has-redirect' : '');
			})
			.attr('d', function () {
				var o = { x: source.x0, y: source.y0 };
				return diagonal(o, o);
			});

		linkEnter.merge(link).transition().duration(state.duration)
			.attr('class', function (d) {
				return 'smr-tree-link' + (d.data.redirects && d.data.redirects.length ? ' has-redirect' : '');
			})
			.attr('d', function (d) { return diagonal(d, d.parent); });

		link.exit().transition().duration(state.duration)
			.attr('d', function () {
				var o = { x: source.x, y: source.y };
				return diagonal(o, o);
			})
			.remove();

		// Stash positions for next transition.
		nodes.forEach(function (d) { d.x0 = d.x; d.y0 = d.y; });
	}

	function diagonal(s, d) {
		return 'M ' + s.y + ' ' + s.x +
			' C ' + ((s.y + d.y) / 2) + ' ' + s.x + ',' +
			((s.y + d.y) / 2) + ' ' + d.x + ',' +
			d.y + ' ' + d.x;
	}

	function toggleChildren(d) {
		if (d.children) {
			d._children = d.children;
			d.children = null;
		} else if (d._children) {
			d.children = d._children;
			d._children = null;
		}
	}

	// ----- detail panel ------------------------------------------------------

	function selectNode(d) {
		state.selectedPath = d.data.path;
		renderDetail(d.data);
	}

	function renderDetail(data) {
		var panel = document.getElementById('smr-detail-panel');
		if (!panel) { return; }
		var rs = data.redirects || [];
		var html = '';

		html += '<h2 class="smr-node-title">' + esc(data.label || data.name || '/') + '</h2>';
		if (data.url) {
			html += '<div class="smr-node-url">' + esc(data.url) + '</div>';
		} else {
			html += '<div class="smr-node-url">' + esc(data.path || '/') + ' &mdash; <em>no page at this path</em></div>';
		}

		// Actions.
		html += '<div class="smr-node-actions">';
		if (data.url) {
			html += '<a class="button button-small" target="_blank" rel="noopener" href="' + esc(data.url) + '">🌐 ' + esc(I18N.open_page || 'Open') + '</a>';
		}
		if (data.editable && data.id && data.type && data.type !== 'container' && data.type !== 'home') {
			var editUrl = (typeof SMR_HOME_EDIT_BASE !== 'undefined' && SMR_HOME_EDIT_BASE) ? SMR_HOME_EDIT_BASE : '';
			// Build edit link client-side from wp-admin post.php?post=ID&action=edit
			if (typeof wp !== 'undefined' && wp.url && wp.url.addQueryArgs) {
				editUrl = wp.url.addQueryArgs(document.location.origin + '/wp-admin/post.php', { post: data.id, action: 'edit' });
			} else {
				editUrl = document.location.origin + '/wp-admin/post.php?post=' + encodeURIComponent(data.id) + '&action=edit';
			}
			html += '<a class="button button-small" href="' + esc(editUrl) + '">✎ ' + esc(I18N.edit_page || 'Edit') + '</a>';
		}
		html += '</div>';

		// Redirects.
		if (rs.length) {
			html += '<h3 class="smr-redirects-heading">Redirects on this page (priority order)</h3>';
			rs.forEach(function (r, i) {
				html += renderRedirect(r, i + 1);
			});
		} else if (data.type !== 'container') {
			html += '<div class="smr-redirects-heading">' + esc(I18N.no_redirects || 'No redirects') + '</div>';
		}

		// Core canonical note.
		var core = (state.redirects || []).filter(function (r) { return r.type === 'wp_canonical'; });
		if (core.length) {
			html += '<div class="smr-redirect-explain" style="margin-top:1em;border-top:1px solid #e0e3e7;padding-top:.6em">';
			html += '<strong>' + esc(I18N.source || 'Why does this redirect happen?') + '</strong><br>';
			html += esc(core[0].plain_english) + ' ' + esc(I18N.core || '');
			html += '</div>';
		}

		panel.innerHTML = html;
	}

	function renderRedirect(r, ordinal) {
		var color = statusColor(r.status);
		var html = '<div class="smr-redirect">';
		html += '<div class="smr-redirect-header">';
		html += '<span class="smr-redirect-priority">#' + esc(ordinal) + ' &middot; ' + esc(I18N.priority || 'Priority') + ' ' + esc(r.priority) + '</span>';
		html += '<span class="smr-redirect-status" style="background:' + esc(color) + '">' + esc(r.status) + ' ' + esc(statusName(r.status)) + '</span>';
		html += '</div>';
		html += '<div class="smr-redirect-plain">' + esc(r.plain_english) + '</div>';
		html += '<div class="smr-redirect-meta">';
		html += '<span class="smr-redirect-type-tag">' + esc(r.label) + '</span>';
		if (r.regex) { html += '<span class="smr-redirect-type-tag">pattern</span>'; }
		html += '</div>';
		html += '<div class="smr-redirect-explain"><strong>' + esc(I18N.source || 'Why does this redirect happen?') + '</strong><br>' + esc(r.explainer) + '</div>';
		html += '<div><strong>' + esc(I18N.destination || 'Destination') + ':</strong> <span class="smr-redirect-dest">' + esc(r.destination) + '</span></div>';
		html += '</div>';
		return html;
	}

	// ----- legend ------------------------------------------------------------

	function renderLegend(payload) {
		var el = document.getElementById('smr-legend');
		if (!el) { return; }
		var colors = payload.status_colors || {};
		var items = [
			{ k: '301', label: '301 Permanent' },
			{ k: '302', label: '302 Temporary' },
			{ k: '303', label: '303 See Other' },
			{ k: '307', label: '307 Temporary (keep method)' },
			{ k: '308', label: '308 Permanent (keep method)' },
		];
		var html = '<h3>' + esc(I18N.legend || 'Legend') + '</h3><div class="smr-legend-grid">';
		items.forEach(function (it) {
			html += '<span class="smr-legend-item"><span class="smr-legend-swatch" style="background:' + esc(colors[it.k] || colors.other) + '"></span>' + esc(it.label) + '</span>';
		});
		html += '<span class="smr-legend-item"><span class="smr-legend-swatch dashed"></span>Redirect source (old URL)</span>';
		html += '<span class="smr-legend-item"><span class="smr-legend-swatch" style="background:#fff;border:2px solid #d63638"></span>Page with a redirect</span>';
		html += '<span class="smr-legend-item"><span class="smr-legend-swatch" style="background:#fff;border:1.5px solid #2271b1"></span>Page (no redirect)</span>';
		html += '<span class="smr-legend-item"><span class="smr-legend-swatch" style="background:#e0e3e7"></span>Container (folder only)</span>';
		html += '</div>';
		html += '<p class="smr-legend-note">' + esc(I18N.core || '') + '</p>';
		el.innerHTML = html;
	}

	// --- reindex button ------------------------------------------------------

	function bindReindex() {
		var btn = document.getElementById('smr-reindex');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			btn.disabled = true;
			var label = btn.textContent;
			btn.innerHTML = '<span class="dashicons dashicons-update smr-spin"></span> ' + esc(I18N.reindexing || 'Re-indexing…');
			api('/reindex', 'POST').then(function (payload) {
				render(payload);
			}).catch(function (err) {
				alert('Re-index failed: ' + err.message);
			}).finally(function () {
				btn.disabled = false;
				btn.innerHTML = '<span class="dashicons dashicons-update"></span> ' + esc(I18N.reindex || 'Re-index site');
			});
		});
	}

	// --- boot ----------------------------------------------------------------

	document.addEventListener('DOMContentLoaded', function () {
		if (!window.d3) {
			var panel = document.getElementById('smr-tree-panel');
			if (panel) { panel.innerHTML = '<p style="padding:1em">D3 library failed to load from CDN. Check your network connection.</p>'; }
			return;
		}
		initSvg();
		bindReindex();
		api('/tree', 'GET').then(render).catch(function (err) {
			var panel = document.getElementById('smr-tree-panel');
			if (panel) { panel.innerHTML = '<p style="padding:1em;color:#d63638">Failed to load site map: ' + esc(err.message) + '</p>'; }
		});
	});
})();
