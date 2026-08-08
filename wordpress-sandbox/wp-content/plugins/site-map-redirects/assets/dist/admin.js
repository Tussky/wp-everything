/**
 * SiteMap Redirects Admin Bundle — Interactive Tree with Badges and Detail Panel
 *
 * Features:
 * - D3.js-powered tree graph with zoom/pan
 * - Click-to-reveal redirect badges and detail panel
 * - Expand/collapse tree nodes
 * - REST API integration for live data
 * - Re-index button with loading state
 * - WordPress admin styling conventions
 * - WCAG 2.1 AA compliant colors
 * - Progressive disclosure and recognition over recall
 * - Doherty threshold optimization for interactions
 */

(function () {
    "use strict";

    var config = window.SiteMapRedirects || null;
    if (!config) return;

    window.SMR = {
        config: config,
        ready: true,
        init: function () {
            console.log("SiteMap Redirects UI initialized");
            SMR.tree = new SiteMapTree(config);
            SMR.reindex = new ReindexHandler(config);
        }
    };

    var ReindexHandler = function (config) {
        this.config = config;
        this.reindexButton = document.getElementById("smr-reindex");
        this.baseURL = config.restUrl + "reindex";
        this.nonce = config.nonce;

        this.init();
    };

    ReindexHandler.prototype.init = function () {
        var _this = this;

        if (this.reindexButton) {
            this.reindexButton.addEventListener("click", function () {
                _this.handleReindex();
            });
        }
    };

    ReindexHandler.prototype.handleReindex = function () {
        var _this = this;

        // Set loading state
        this.reindexButton.disabled = true;
        setButtonContent(this.reindexButton, "running");

        fetch(this.baseURL, {
            method: "POST",
            headers: {
                "X-WP-Nonce": this.nonce,
                "Content-Type": "application/json"
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Re-index failed");
                }
                return response.json();
            })
            .then(function (data) {
                console.log("Re-index completed:", data);
                setButtonContent(_this.reindexButton, "done");
                setTimeout(function () {
                    setButtonContent(_this.reindexButton, "default");
                    _this.reindexButton.disabled = false;
                }, 2000);

                // Refresh tree data
                if (window.SMR && window.SMR.tree) {
                    window.SMR.tree.fetchTreeData();
                }
            })
            .catch(function (error) {
                console.error("Re-index error:", error);
                setButtonContent(_this.reindexButton, "default");
                _this.reindexButton.disabled = false;

                // Show error to user
                if (window.SMR && window.SMR.tree) {
                    window.SMR.tree.showError("Re-index failed: " + error.message);
                }
            });
    };

    function setButtonContent(button, state) {
        while (button.firstChild) {
            button.removeChild(button.firstChild);
        }
        var icon = document.createElement("i");
        icon.textContent = "\u21BB"; // ↻
        button.appendChild(icon);
        var label;
        if (state === "running") {
            label = "Running\u2026";
        } else if (state === "done") {
            label = "Re-indexed";
        } else {
            label = (window.SiteMapRedirects && window.SiteMapRedirects.labels && window.SiteMapRedirects.labels.reindexButton)
                || "Re-index";
        }
        button.appendChild(document.createTextNode(" " + label));
    }

    var SiteMapTree = function (config) {
        this.config = config;
        this.container = document.querySelector(".smr-tree-container");
        this.detailPanel = document.querySelector(".smr-detail-panel");
        this.loadingElement = document.querySelector(".smr-loading");
        this.errorElement = document.querySelector(".smr-error");
        this.treeData = null;
        this.baseURL = config.restUrl + "tree";
        this.nonce = config.nonce;
        this.tooltip = null;
        this.selectedNodeId = null;

        this.init();
    };

    SiteMapTree.prototype.init = function () {
        var _this = this;

        // Initialize tooltip
        this.initTooltip();

        // Fetch initial data
        this.fetchTreeData();

        // Handle window resize for responsiveness
        window.addEventListener("resize", function () {
            if (_this.treeData) {
                _this.render(_this.treeData);
            }
        });
    };

    SiteMapTree.prototype.initTooltip = function () {
        var tooltip = document.createElement("div");
        tooltip.className = "node-tooltip";
        tooltip.style.cssText = `
            position: absolute;
            display: none;
            padding: 8px 12px;
            background: #3c434a;
            color: #fff;
            font-size: 12px;
            border-radius: 4px;
            pointer-events: none;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        `;
        document.body.appendChild(tooltip);
        this.tooltip = tooltip;

        document.addEventListener("mousemove", function (e) {
            if (tooltip.style.display === "block") {
                tooltip.style.left = (e.pageX + 15) + "px";
                tooltip.style.top = (e.pageY + 15) + "px";
            }
        });
    };

    SiteMapTree.prototype.showTooltip = function (content, x, y) {
        if (!this.tooltip) return;
        this.tooltip.textContent = content;
        this.tooltip.style.display = "block";
        this.tooltip.style.left = (x + 15) + "px";
        this.tooltip.style.top = (y + 15) + "px";
    };

    SiteMapTree.prototype.hideTooltip = function () {
        if (this.tooltip) {
            this.tooltip.style.display = "none";
        }
    };

    SiteMapTree.prototype.fetchTreeData = function () {
        var _this = this;

        this.loadingElement.style.display = "block";
        this.errorElement.style.display = "none";

        fetch(this.baseURL, {
            method: "GET",
            headers: {
                "X-WP-Nonce": this.nonce,
                "Content-Type": "application/json"
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Network response was not ok");
                }
                return response.json();
            })
            .then(function (data) {
                _this.treeData = data;
                _this.loadingElement.style.display = "none";
                _this.render(data);
            })
            .catch(function (error) {
                console.error("Error fetching tree data:", error);
                _this.loadingElement.style.display = "none";
                _this.showError(error.message);
            });
    };

    SiteMapTree.prototype.showError = function (message) {
        this.errorElement.style.display = "block";
        this.errorElement.textContent = "Error loading site map: " + message;
    };

    SiteMapTree.prototype.render = function (data) {
        var container = this.container;
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
        container.className = "smr-tree-container";

        var treeWrapper = document.createElement("div");
        treeWrapper.className = "smr-tree";
        treeWrapper.id = "smr-tree";

        var legend = this.renderLegend();

        var detailPanel = document.createElement("div");
        detailPanel.className = "smr-detail-panel";
        var empty = document.createElement("div");
        empty.className = "smr-empty-state";
        empty.textContent = "Click a node to view details";
        detailPanel.appendChild(empty);

        container.appendChild(treeWrapper);
        container.appendChild(legend);
        container.appendChild(detailPanel);

        // Render D3 tree
        this.renderD3Tree(treeWrapper, data);
    };

    SiteMapTree.prototype.renderLegend = function () {
        var legend = document.createElement("div");
        legend.className = "smr-legend";

        var h = document.createElement("h3");
        h.textContent = this.config.labels.legend || "Legend";
        legend.appendChild(h);

        var items = [
            { style: "background: #fff; border: 2px solid #72aee6;", text: "Node" },
            { style: "background: #2271b1; color: white; padding: 2px 6px; font-size: 10px; border-radius: 2px;", text: "301", label: "Permanent" },
            { style: "background: #dba624; color: white; padding: 2px 6px; font-size: 10px; border-radius: 2px;", text: "302", label: "Temporary" },
            { style: "background: #6f437e; color: white; padding: 2px 6px; font-size: 10px; border-radius: 2px;", text: "307", label: "Temporary (POST)" },
            { style: "background: #e04f5f; color: white; padding: 2px 6px; font-size: 10px; border-radius: 2px;", text: "308", label: "Permanent (POST)" },
            { style: "background: transparent; border: 2px dashed #dba624;", text: "", label: "Redirect Source" }
        ];

        items.forEach(function (item) {
            var row = document.createElement("div");
            row.className = "smr-legend-item";
            var swatch = document.createElement("div");
            swatch.className = "smr-legend-color";
            swatch.setAttribute("style", item.style);
            if (item.text) swatch.textContent = item.text;
            var caption = document.createElement("span");
            caption.textContent = item.label || item.text;
            row.appendChild(swatch);
            row.appendChild(caption);
            legend.appendChild(row);
        });

        return legend;
    };

    SiteMapTree.prototype.renderD3Tree = function (container, data) {
        var _this = this;

        var width = container.clientWidth;
        var height = 600;

        // Create zoom behavior
        var zoom = d3.zoom()
            .scaleExtent([0.1, 3])
            .on("zoom", function (event) {
                g.attr("transform", event.transform);
            });

        var svg = d3.select("#smr-tree")
            .append("svg")
            .attr("width", "100%")
            .attr("height", height)
            .call(zoom)
            .on("dblclick.zoom", null);

        var g = svg.append("g");

        // Convert data to tree layout
        var root = d3.hierarchy(data);

        var treeLayout = d3.tree().nodeSize([40, 180]);
        treeLayout(root);

        // Store node positions
        root.x0 = height / 2;
        root.y0 = 0;

        // Collapse all nodes by default (progressive disclosure)
        if (root.children) {
            root.children.forEach(function (d) {
                d._children = d.children;
                d.children = null;
            });
        }

        // Draw links
        var links = g.selectAll(".link")
            .data(root.links())
            .enter().append("path")
            .attr("class", "link")
            .attr("d", d3.linkHorizontal()
                .x(function (d) { return d.y; })
                .y(function (d) { return d.x; })
            );

        // Draw nodes
        var nodes = g.selectAll(".node")
            .data(root.descendants())
            .enter().append("g")
            .attr("class", function (d) {
                return "node" + (d.children ? " expanded" : "");
            })
            .attr("transform", function (d) {
                return "translate(" + d.y + "," + d.x + ")";
            })
            .on("click", function (event, d) {
                event.stopPropagation();
                _this.handleNodeClick(d);
            })
            .on("mouseover", function (event, d) {
                _this.handleNodeHover(event, d);
            })
            .on("mouseout", function (event, d) {
                _this.handleNodeLeave(event, d);
            });

        // Add circles
        nodes.append("circle")
            .attr("r", 8)
            .attr("cx", 0)
            .attr("cy", 0);

        // Add labels
        nodes.append("text")
            .attr("dy", ".35em")
            .attr("x", function (d) {
                return d.children || d._children ? -12 : 12;
            })
            .attr("text-anchor", function (d) {
                return d.children || d._children ? "end" : "start";
            })
            .text(function (d) {
                return d.data.label || d.data.title || "Page";
            });

        // Add redirect badges
        if (root.data.redirects && root.data.redirects.length > 0) {
            nodes.each(function (d) {
                if (d.data.redirects && d.data.redirects.length > 0) {
                    var badge = document.createElement("div");
                    badge.className = "node-badge node-badge-" + d.data.redirects[0].status;
                    badge.textContent = d.data.redirects[0].status;
                    badge.title = d.data.redirects[0].reason || "Redirect";
                    d.element.appendChild(badge);
                }
            });
        }

        // Update function for expand/collapse
        this.update = function (source) {
            // Compute the new tree layout.
            treeLayout(root);

            var duration = 400; // Doherty threshold goal

            // Normalize for fixed-depth.
            root.each(function (d) {
                d.y = d.depth * 180;
            });

            // Get the updated nodes.
            var nodes = g.selectAll(".node")
                .data(root.descendants(), function (d) {
                    return d.id || (d.id = ++i);
                });

            // Get the updated links.
            var links = g.selectAll(".link")
                .data(root.links(), function (d) {
                    return d.target.id;
                });

            // Exit any exiting nodes.
            var nodeExit = nodes.exit().transition()
                .duration(duration)
                .attr("transform", function (d) {
                    return "translate(" + source.y + "," + source.x + ")";
                })
                .remove();

            nodeExit.select("circle").attr("r", 1e-6);
            nodeExit.select("text").style("fill-opacity", 1e-6);

            // Update any + nodes for the entering new nodes.
            var nodeEnter = nodes.enter().append("g")
                .attr("class", "node")
                .attr("transform", function (d) {
                    return "translate(" + source.y + "," + source.x + ")";
                })
                .on("click", function (event, d) {
                    event.stopPropagation();
                    _this.handleNodeClick(d);
                })
                .on("mouseover", function (event, d) {
                    _this.handleNodeHover(event, d);
                })
                .on("mouseout", function (event, d) {
                    _this.handleNodeLeave(event, d);
                });

            nodeEnter.append("circle")
                .attr("r", 1e-6)
                .style("fill", function (d) {
                    return d._children ? "#2271b1" : "#fff";
                });

            nodeEnter.append("text")
                .attr("dy", ".35em")
                .attr("x", function (d) {
                    return d.children || d._children ? -12 : 12;
                })
                .attr("text-anchor", function (d) {
                    return d.children || d._children ? "end" : "start";
                })
                .text(function (d) {
                    return d.data.label || d.data.title || "Page";
                })
                .style("fill-opacity", 1e-6);

            // Update + nodes for the transition.
            var nodeUpdate = nodes.transition()
                .duration(duration)
                .attr("transform", function (d) {
                    return "translate(" + d.y + "," + d.x + ")";
                });

            nodeUpdate.select("circle")
                .attr("r", function (d) {
                    return d.children || d._children ? 8 : 6;
                })
                .style("fill", function (d) {
                    return d._children ? "#2271b1" : "#fff";
                });

            nodeUpdate.select("text")
                .style("fill-opacity", 1);

            // Transition exiting nodes to the parent's new position.
            nodeExit.select("circle")
                .attr("r", 1e-6)
                .style("fill", function (d) {
                    return d._children ? "#2271b1" : "#fff";
                });

            nodeExit.select("text")
                .style("fill-opacity", 1e-6);

            // Update the links.
            var linkUpdate = links.transition()
                .duration(duration)
                .attr("d", function (d) {
                    return d3.linkHorizontal()
                        .x(function (t) { return t.y; })
                        .y(function (t) { return t.x; });
                });

            // Any exiting links transition to the parent's new position.
            var linkExit = links.exit().transition()
                .duration(duration)
                .attr("d", function (d) {
                    var o = { x: source.x, y: source.y };
                    return d3.linkHorizontal()
                        .x(function (t) { return t.y; })
                        .y(function (t) { return t.x; })
                        ({ source: o, target: o });
                })
                .remove();

            // Create the new links for the entering node.
            var linkEnter = links.enter().insert("path", "g")
                .attr("class", "link")
                .attr("d", function (d) {
                    var o = { x: source.x, y: source.y };
                    return d3.linkHorizontal()
                        .x(function (t) { return t.y; })
                        .y(function (t) { return t.x; })
                        ({ source: o, target: o });
                });

            // Update + links for the new nodes.
            linkEnter.transition()
                .duration(duration)
                .attr("d", d3.linkHorizontal()
                    .x(function (t) { return t.y; })
                    .y(function (t) { return t.x; })
                );
        };

        var i = 0;
        this.update(root);
    };

    SiteMapTree.prototype.handleNodeClick = function (node) {
        var _this = this;

        // Expand/collapse children
        if (node._children) {
            node.children = node._children;
            node._children = null;
        } else if (node.children) {
            node._children = node.children;
            node.children = null;
        }

        // Update the tree
        this.update(node);

        // Update detail panel
        this.updateDetailPanel(node.data);
    };

    SiteMapTree.prototype.handleNodeHover = function (event, node) {
        if (node.data.redirects && node.data.redirects.length > 0) {
            var reason = node.data.redirects[0].reason || "";
            this.showTooltip(reason, event.pageX, event.pageY);
        }
    };

    SiteMapTree.prototype.handleNodeLeave = function (event, node) {
        this.hideTooltip();
    };

    SiteMapTree.prototype.updateDetailPanel = function (nodeData) {
        var panel = this.detailPanel;
        // Replace innerHTML="" with textContent reset for clarity and to keep
        // the XSS-safe pattern consistent across this file.
        while (panel.firstChild) {
            panel.removeChild(panel.firstChild);
        }

        // Node information section
        var infoSection = document.createElement("div");
        infoSection.className = "smr-detail-section";

        var titleLabel = document.createElement("div");
        titleLabel.className = "smr-detail-label";
        titleLabel.textContent = this.config.labels.title || "Title";

        var titleValue = document.createElement("div");
        titleValue.className = "smr-detail-value";
        var titleStrong = document.createElement("strong");
        titleStrong.textContent = nodeData.title || nodeData.label || "Homepage";
        titleValue.appendChild(titleStrong);

        var urlLabel = document.createElement("div");
        urlLabel.className = "smr-detail-label";
        urlLabel.textContent = "URL";

        var urlValue = document.createElement("div");
        urlValue.className = "smr-detail-value";
        var urlLink = document.createElement("a");
        // Use setAttribute so the browser will normalize the URL and reject
        // dangerous schemes like "javascript:" that a malicious server response
        // (or a future vulnerability in the redirect-discovery code) could slip in.
        var safeUrl = (typeof nodeData.url === "string" && /^(https?:|mailto:|\/|#)/i.test(nodeData.url))
            ? nodeData.url
            : "#";
        urlLink.setAttribute("href", safeUrl);
        urlLink.textContent = nodeData.url || "#";
        urlValue.appendChild(urlLink);

        var typeLabel = document.createElement("div");
        typeLabel.className = "smr-detail-label";
        typeLabel.textContent = "Type";

        var typeValue = document.createElement("div");
        typeValue.className = "smr-detail-value";
        typeValue.textContent = this.config.labels.nodeTypes[nodeData.nodeType] || nodeData.nodeType;

        infoSection.appendChild(titleLabel);
        infoSection.appendChild(titleValue);
        infoSection.appendChild(urlLabel);
        infoSection.appendChild(urlValue);
        infoSection.appendChild(typeLabel);
        infoSection.appendChild(typeValue);

        // Redirects section
        var redirectsSection = document.createElement("div");
        redirectsSection.className = "smr-detail-section smr-redirects";

        var redirectsTitle = document.createElement("div");
        redirectsTitle.className = "smr-redirect-title";
        redirectsTitle.textContent = this.config.labels.redirects || "Redirects";

        if (nodeData.redirects && nodeData.redirects.length > 0) {
            var redirectsContainer = document.createElement("div");
            nodeData.redirects.forEach(function (redirect) {
                var redirectItem = document.createElement("div");
                redirectItem.className = "smr-redirect-item smr-redirect-item-" + redirect.status;

                // Priority
                var priorityClass = "priority-" + redirect.priority;
                var priorityLabel = redirect.priority === "high" ? "High" : redirect.priority === "medium" ? "Medium" : "Low";

                var priorityBadge = document.createElement("span");
                priorityBadge.className = "smr-redirect-status " + priorityClass;
                priorityBadge.textContent = priorityLabel;

                var redirectStatus = document.createElement("span");
                redirectStatus.className = "smr-redirect-status smr-redirect-item-" + redirect.status;
                redirectStatus.textContent = _this.config.labels["status" + redirect.status] || redirect.status;

                var redirectTarget = document.createElement("span");
                redirectTarget.className = "smr-redirect-target";
                redirectTarget.textContent = redirect.target || "";

                var redirectSource = document.createElement("span");
                redirectSource.className = "smr-redirect-source";
                redirectSource.textContent = redirect.reason || "";

                var redirectRow = document.createElement("div");
                redirectRow.style.marginBottom = "8px";

                var redirectHeader = document.createElement("div");
                redirectHeader.style.display = "flex";
                redirectHeader.style.justifyContent = "space-between";
                redirectHeader.style.alignItems = "center";
                redirectHeader.style.marginBottom = "5px";

                redirectHeader.appendChild(priorityBadge);
                redirectHeader.appendChild(redirectStatus);

                var redirectBody = document.createElement("div");
                redirectBody.appendChild(redirectTarget);
                redirectBody.appendChild(redirectSource);

                redirectItem.appendChild(redirectHeader);
                redirectItem.appendChild(redirectBody);

                redirectsContainer.appendChild(redirectItem);
            });

            redirectsSection.appendChild(redirectsTitle);
            redirectsSection.appendChild(redirectsContainer);
        } else {
            var noRedirects = document.createElement("div");
            noRedirects.className = "smr-empty-state";
            noRedirects.textContent = this.config.labels.noData || "No redirects found";
            redirectsSection.appendChild(redirectsTitle);
            redirectsSection.appendChild(noRedirects);
        }

        panel.appendChild(infoSection);
        panel.appendChild(redirectsSection);
    };
})();

// Initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
        window.SMR.init();
    });
} else {
    window.SMR.init();
}