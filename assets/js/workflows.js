(function (global) {
  "use strict";

  function bySel(sel, root) {
    return (root || document).querySelector(sel);
  }

  function all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function readTemplates() {
    var el = bySel("#workflows-templates-json");
    if (!el) return [];
    try {
      var parsed = JSON.parse(el.textContent || "[]");
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function initWorkflowsPage() {
    var root = bySel("[data-app-workflows]");
    if (!root || !global.App) return;
    var templates = readTemplates();
    var canvas = bySel("[data-workflow-canvas]", root);
    var edgesSvg = bySel("[data-workflow-edges]", root);
    var nodeLayer = bySel("[data-workflow-node-layer]", root);
    var emptyState = bySel("[data-workflow-empty-state]", root);
    var palette = bySel("[data-workflow-node-palette]", root);
    var nameEl = bySel("#workflow-name", root);
    var triggerEl = bySel("[data-workflow-trigger]", root);
    var activeEl = bySel("[data-workflow-active]", root);
    var templateEl = bySel("[data-workflow-template-select]", root);
    var listEl = bySel("[data-workflow-list]", root);
    var runsEl = bySel("[data-workflow-runs]", root);
    var cfgEmpty = bySel("[data-workflow-inspector-empty]", root);
    var cfgEditor = bySel("[data-workflow-node-config]", root);
    var cfgSave = bySel("[data-workflow-node-config-save]", root);

    var currentWorkflowId = null;
    var selectedNodeId = null;
    var connectFromNodeId = null;
    var connectMode = false;
    var canvasScale = 1;
    var dragNodeId = null;
    var dragOffsetX = 0;
    var dragOffsetY = 0;
    var graph = { nodes: [], edges: [] };

    var nodeCatalog = [
      { type: "trigger.manual", label: "Manual trigger" },
      { type: "trigger.schedule", label: "Schedule trigger" },
      { type: "trigger.event", label: "Event trigger" },
      { type: "utility.set_variables", label: "Set variables" },
      { type: "utility.delay", label: "Delay" },
      { type: "utility.condition", label: "Condition" },
      { type: "ai.generate_caption", label: "AI generate caption" },
      { type: "action.compose_post", label: "Compose post" },
      { type: "action.publish_post", label: "Publish post" }
    ];

    function selectedAccountIds() {
      return all("[data-workflow-account]:checked", root).map(function (el) {
        return parseInt(el.value, 10);
      }).filter(function (v) { return !isNaN(v) && v > 0; });
    }

    function defaultConfigForType(type) {
      if (type === "action.compose_post") {
        return { platform_account_ids: selectedAccountIds(), audience: "everyone" };
      }
      if (type === "ai.generate_caption") return { instruction: "Improve this post for social media." };
      if (type === "utility.delay") return { seconds: 5 };
      if (type === "utility.condition") return { left: "draft", operator: "contains", right: "#" };
      if (type === "utility.set_variables") return { variables: { draft: "Your draft text here" } };
      if (type === "trigger.event") return { event_key: "campaign.ready" };
      if (type === "trigger.schedule") return { interval_minutes: 60 };
      return {};
    }

    function renderPalette() {
      if (!palette) return;
      palette.innerHTML = "";
      nodeCatalog.forEach(function (n) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn--ghost btn--compact";
        btn.textContent = n.label;
        btn.dataset.nodeType = n.type;
        btn.draggable = true;
        btn.addEventListener("dragstart", function (e) {
          if (!e.dataTransfer) return;
          e.dataTransfer.setData("text/workflow-node-type", n.type);
        });
        btn.addEventListener("click", function () {
          addNode(n.type);
        });
        palette.appendChild(btn);
      });
    }

    function nodeById(id) {
      return graph.nodes.find(function (n) { return n.id === id; });
    }

    function edgeExists(fromId, toId) {
      return graph.edges.some(function (e) { return e.from === fromId && e.to === toId; });
    }

    function addEdge(fromId, toId) {
      if (!fromId || !toId || fromId === toId) return;
      if (!nodeById(fromId) || !nodeById(toId)) return;
      if (edgeExists(fromId, toId)) return;
      graph.edges.push({ from: fromId, to: toId });
    }

    function removeEdgesFromNode(nodeId) {
      graph.edges = graph.edges.filter(function (e) {
        return e.from !== nodeId && e.to !== nodeId;
      });
    }

    function addNode(type, x, y) {
      var id = "node_" + Date.now() + "_" + Math.floor(Math.random() * 1000);
      var cx = typeof x === "number" ? x : 120 + (graph.nodes.length * 40);
      var cy = typeof y === "number" ? y : 120 + (graph.nodes.length * 22);
      graph.nodes.push({
        id: id,
        type: type,
        config: defaultConfigForType(type),
        x: cx,
        y: cy
      });
      if (graph.nodes.length > 1) {
        var prev = graph.nodes[graph.nodes.length - 2];
        addEdge(prev.id, id);
      }
      renderCanvas();
    }

    function renderCanvas() {
      if (!canvas || !nodeLayer || !edgesSvg) return;
      nodeLayer.innerHTML = "";
      edgesSvg.innerHTML = "";
      if (emptyState) emptyState.hidden = graph.nodes.length > 0;

      var canvasRect = canvas.getBoundingClientRect();
      edgesSvg.setAttribute("viewBox", "0 0 " + Math.max(400, canvasRect.width) + " " + Math.max(300, canvasRect.height));

      graph.nodes.forEach(function (node) {
        if (typeof node.x !== "number") node.x = 80;
        if (typeof node.y !== "number") node.y = 80;
      });

      graph.edges.forEach(function (edge) {
        var from = nodeById(edge.from);
        var to = nodeById(edge.to);
        if (!from || !to) return;
        var sx = from.x + 212;
        var sy = from.y + 38;
        var ex = to.x;
        var ey = to.y + 38;
        var c1x = sx + 80;
        var c2x = ex - 80;
        var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.setAttribute("d", "M " + sx + " " + sy + " C " + c1x + " " + sy + ", " + c2x + " " + ey + ", " + ex + " " + ey);
        path.setAttribute("class", "workflow-edge-path");
        edgesSvg.appendChild(path);
      });

      if (!graph.nodes.length) {
        return;
      }

      graph.nodes.forEach(function (node, idx) {
        var card = document.createElement("div");
        card.className = "workflow-node-card" + (selectedNodeId === node.id ? " is-active" : "");
        card.dataset.nodeId = node.id;
        card.style.left = (node.x || 0) + "px";
        card.style.top = (node.y || 0) + "px";
        card.innerHTML =
          '<div class="workflow-node-card__head">' +
            '<strong>' + node.type + '</strong>' +
            '<small>#' + String(idx + 1) + '</small>' +
          '</div>' +
          '<div class="workflow-node-card__sub">Double-click to edit config</div>' +
          '<button type="button" class="workflow-node-card__in" title="Input"></button>' +
          '<button type="button" class="workflow-node-card__out" title="Output"></button>' +
          '<button type="button" class="workflow-node-card__plus" title="Add next node"><i class="fa-solid fa-plus"></i></button>';

        var outBtn = card.querySelector(".workflow-node-card__out");
        var inBtn = card.querySelector(".workflow-node-card__in");
        var plusBtn = card.querySelector(".workflow-node-card__plus");

        if (outBtn) {
          outBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            connectFromNodeId = node.id;
            connectMode = true;
            if (global.App.showFlash) global.App.showFlash("Select target node input to connect.");
            renderCanvas();
          });
        }

        if (inBtn) {
          inBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!connectMode || !connectFromNodeId) return;
            addEdge(connectFromNodeId, node.id);
            connectFromNodeId = null;
            connectMode = false;
            renderCanvas();
          });
        }

        if (plusBtn) {
          plusBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            addNode("action.compose_post", (node.x || 0) + 260, (node.y || 0));
            var next = graph.nodes[graph.nodes.length - 1];
            if (next) addEdge(node.id, next.id);
            renderCanvas();
          });
        }

        card.addEventListener("click", function () {
          selectedNodeId = node.id;
          openInspector(node);
          renderCanvas();
        });
        card.addEventListener("dblclick", function () {
          selectedNodeId = node.id;
          openInspector(node);
        });

        card.addEventListener("mousedown", function (e) {
          var target = e.target;
          if (target && (target.closest(".workflow-node-card__in") || target.closest(".workflow-node-card__out") || target.closest(".workflow-node-card__plus"))) {
            return;
          }
          dragNodeId = node.id;
          var rect = card.getBoundingClientRect();
          dragOffsetX = e.clientX - rect.left;
          dragOffsetY = e.clientY - rect.top;
          e.preventDefault();
        });

        nodeLayer.appendChild(card);
      });

      if (nodeLayer) {
        nodeLayer.style.transform = "scale(" + canvasScale + ")";
        nodeLayer.style.transformOrigin = "0 0";
      }
      if (edgesSvg) {
        edgesSvg.style.transform = "scale(" + canvasScale + ")";
        edgesSvg.style.transformOrigin = "0 0";
      }
    }

    function openInspector(node) {
      if (!cfgEditor || !cfgSave || !cfgEmpty) return;
      cfgEmpty.hidden = true;
      cfgEditor.hidden = false;
      cfgSave.hidden = false;
      cfgEditor.value = JSON.stringify(node.config || {}, null, 2);
    }

    if (cfgSave && cfgEditor) {
      cfgSave.addEventListener("click", function () {
        if (!selectedNodeId) return;
        var node = graph.nodes.find(function (n) { return n.id === selectedNodeId; });
        if (!node) return;
        try {
          var parsed = JSON.parse(cfgEditor.value || "{}");
          node.config = parsed;
          if (global.App.showFlash) global.App.showFlash("Node config updated.", "success");
        } catch (e) {
          if (global.App.showFlash) global.App.showFlash("Invalid JSON in node config.", "error");
        }
      });
    }

    if (canvas) {
      canvas.addEventListener("dragover", function (e) {
        e.preventDefault();
      });
      canvas.addEventListener("drop", function (e) {
        e.preventDefault();
        if (!e.dataTransfer) return;
        var type = e.dataTransfer.getData("text/workflow-node-type");
        if (!type) return;
        var rect = canvas.getBoundingClientRect();
        var x = (e.clientX - rect.left) / canvasScale;
        var y = (e.clientY - rect.top) / canvasScale;
        addNode(type, x - 100, y - 32);
      });
    }

    document.addEventListener("mousemove", function (e) {
      if (!dragNodeId || !canvas) return;
      var node = nodeById(dragNodeId);
      if (!node) return;
      var rect = canvas.getBoundingClientRect();
      node.x = (e.clientX - rect.left - dragOffsetX) / canvasScale;
      node.y = (e.clientY - rect.top - dragOffsetY) / canvasScale;
      renderCanvas();
    });

    document.addEventListener("mouseup", function () {
      dragNodeId = null;
    });

    function loadTemplateByKey(key) {
      var t = templates.find(function (x) { return x.key === key; });
      if (!t) return;
      nameEl.value = t.name || "";
      if (triggerEl) triggerEl.value = t.trigger_type || "manual";
      graph = JSON.parse(JSON.stringify(t.graph || { nodes: [], edges: [] }));
      graph.nodes.forEach(function (n, i) {
        if (typeof n.x !== "number") n.x = 120 + (i * 230);
        if (typeof n.y !== "number") n.y = 220;
      });
      selectedNodeId = null;
      if (global.App.showFlash) global.App.showFlash("Template loaded.");
      renderCanvas();
    }

    if (templateEl) {
      templateEl.addEventListener("change", function () {
        var key = templateEl.value || "";
        if (!key) return;
        loadTemplateByKey(key);
      });
    }

    function refreshList() {
      if (!global.App.apiGet || !listEl) return;
      global.App.apiGet("/api/v1/workflows").then(function (res) {
        if (!res._ok) return;
        var list = Array.isArray(res.workflows) ? res.workflows : [];
        listEl.innerHTML = "";
        list.forEach(function (wf) {
          var row = document.createElement("div");
          row.className = "workflows-list__item";
          var title = document.createElement("button");
          title.type = "button";
          title.className = "btn btn--ghost btn--compact";
          title.textContent = (wf.name || "Untitled") + " [" + (wf.status || "draft") + "]";
          title.addEventListener("click", function () {
            currentWorkflowId = wf.id;
            nameEl.value = wf.name || "";
            if (triggerEl) triggerEl.value = wf.trigger_type || "manual";
            graph = wf.graph && typeof wf.graph === "object" ? wf.graph : { nodes: [], edges: [] };
            graph.nodes.forEach(function (n, i) {
              if (typeof n.x !== "number") n.x = 120 + (i * 230);
              if (typeof n.y !== "number") n.y = 220;
            });
            selectedNodeId = null;
            renderCanvas();
            refreshRuns();
          });
          var del = document.createElement("button");
          del.type = "button";
          del.className = "btn btn--ghost btn--compact";
          del.textContent = "Delete";
          del.addEventListener("click", function () {
            if (!global.confirm("Delete this workflow?")) return;
            fetch("/api/v1/workflows/" + wf.id, {
              method: "DELETE",
              headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": global.App.getCsrfToken ? global.App.getCsrfToken() : "",
                "X-Requested-With": "XMLHttpRequest"
              },
              credentials: "same-origin"
            }).then(function (r) {
              return r.json().then(function (dres) {
                dres._ok = r.ok;
                return dres;
              });
            }).then(function (dres) {
              if (!dres._ok) {
                if (global.App.showFlash) global.App.showFlash(dres.message || "Delete failed.", "error");
                return;
              }
              if (currentWorkflowId === wf.id) {
                currentWorkflowId = null;
                graph = { nodes: [], edges: [] };
                nameEl.value = "";
                renderCanvas();
              }
              refreshList();
            });
          });
          row.appendChild(title);
          row.appendChild(del);
          listEl.appendChild(row);
        });
      });
    }

    function refreshRuns() {
      if (!runsEl) return;
      if (!currentWorkflowId) {
        runsEl.innerHTML = '<div class="muted">Select a workflow to view runs.</div>';
        return;
      }
      global.App.apiGet("/api/v1/workflows/" + currentWorkflowId + "/runs").then(function (res) {
        if (!res._ok) return;
        var runs = Array.isArray(res.runs) ? res.runs : [];
        runsEl.innerHTML = "";
        if (!runs.length) {
          runsEl.innerHTML = '<div class="muted">No runs yet.</div>';
          return;
        }
        runs.forEach(function (run) {
          var row = document.createElement("div");
          row.className = "workflows-list__item";
          row.innerHTML =
            "<strong>Run #" + run.id + "</strong>" +
            "<span>Status: " + (run.status || "unknown") + "</span>" +
            "<span>Trigger: " + (run.trigger_type || "manual") + "</span>";
          runsEl.appendChild(row);
        });
      });
    }

    function saveCurrentWorkflow() {
      var name = (nameEl && nameEl.value ? nameEl.value : "").trim();
      if (!name) {
        if (global.App.showFlash) global.App.showFlash("Workflow name is required.", "error");
        return;
      }
      var payload = {
        name: name,
        trigger_type: triggerEl ? triggerEl.value : "manual",
        status: activeEl && activeEl.checked ? "active" : "draft",
        graph: graph,
        trigger_config: {}
      };
      if (payload.trigger_type === "event") {
        payload.trigger_config.event_key = "campaign.ready";
      }
      if (payload.trigger_type === "schedule") {
        payload.trigger_config.interval_minutes = 60;
      }
      var req = currentWorkflowId
        ? global.App.apiPut("/api/v1/workflows/" + currentWorkflowId, payload)
        : global.App.apiPost("/api/v1/workflows", payload);

      req.then(function (res) {
        if (!res._ok || !res.workflow) {
          if (global.App.showFlash) global.App.showFlash(res.message || "Could not save workflow.", "error");
          return;
        }
        currentWorkflowId = res.workflow.id;
        if (global.App.showFlash) global.App.showFlash("Workflow saved.");
        refreshList();
      }).catch(function () {
        if (global.App.showFlash) global.App.showFlash("Network error while saving workflow.", "error");
      });
    }

    function runCurrentWorkflow() {
      if (!currentWorkflowId) {
        if (global.App.showFlash) global.App.showFlash("Save workflow first.", "error");
        return;
      }
      global.App.apiPost("/api/v1/workflows/" + currentWorkflowId + "/run", {
        context: {
          draft: "Workflow draft seed",
          vars: {}
        }
      }).then(function (res) {
        if (!res._ok) {
          if (global.App.showFlash) global.App.showFlash(res.message || "Workflow run failed.", "error");
          return;
        }
        if (global.App.showFlash) global.App.showFlash("Workflow run started/completed.");
        refreshRuns();
      }).catch(function () {
        if (global.App.showFlash) global.App.showFlash("Network error while running workflow.", "error");
      });
    }

    var btnSave = bySel("[data-workflows-save]", root);
    var btnRun = bySel("[data-workflows-run]", root);
    var btnNew = bySel("[data-workflows-new]", root);
    var btnZoomOut = bySel("[data-workflow-zoom-out]", root);
    var btnZoomIn = bySel("[data-workflow-zoom-in]", root);
    var btnResetView = bySel("[data-workflow-reset-view]", root);
    var btnUnselect = bySel("[data-workflow-unselect]", root);
    var btnAutoLayout = bySel("[data-workflow-auto-layout]", root);
    var btnConnectMode = bySel("[data-workflow-connect-mode]", root);
    var btnClearConnections = bySel("[data-workflow-clear-connections]", root);
    if (btnSave) btnSave.addEventListener("click", saveCurrentWorkflow);
    if (btnRun) btnRun.addEventListener("click", runCurrentWorkflow);
    if (btnZoomOut) btnZoomOut.addEventListener("click", function () {
      canvasScale = Math.max(0.6, +(canvasScale - 0.1).toFixed(2));
      renderCanvas();
    });
    if (btnZoomIn) btnZoomIn.addEventListener("click", function () {
      canvasScale = Math.min(1.6, +(canvasScale + 0.1).toFixed(2));
      renderCanvas();
    });
    if (btnResetView) btnResetView.addEventListener("click", function () {
      canvasScale = 1;
      renderCanvas();
    });
    if (btnUnselect) btnUnselect.addEventListener("click", function () {
      selectedNodeId = null;
      if (cfgEmpty) cfgEmpty.hidden = false;
      if (cfgEditor) cfgEditor.hidden = true;
      if (cfgSave) cfgSave.hidden = true;
      renderCanvas();
    });
    if (btnAutoLayout) btnAutoLayout.addEventListener("click", function () {
      graph.nodes.forEach(function (node, i) {
        var row = Math.floor(i / 4);
        var col = i % 4;
        node.x = 110 + (col * 245);
        node.y = 120 + (row * 130);
      });
      renderCanvas();
    });
    if (btnConnectMode) btnConnectMode.addEventListener("click", function () {
      connectMode = !connectMode;
      connectFromNodeId = null;
      if (global.App.showFlash) global.App.showFlash(connectMode ? "Connect mode on. Click output then input." : "Connect mode off.");
    });
    if (btnClearConnections) btnClearConnections.addEventListener("click", function () {
      if (!global.confirm("Remove all node connections?")) return;
      graph.edges = [];
      renderCanvas();
    });
    if (btnNew) {
      btnNew.addEventListener("click", function () {
        currentWorkflowId = null;
        selectedNodeId = null;
        connectFromNodeId = null;
        connectMode = false;
        graph = { nodes: [], edges: [] };
        if (nameEl) nameEl.value = "";
        if (triggerEl) triggerEl.value = "manual";
        if (activeEl) activeEl.checked = false;
        if (cfgEmpty) cfgEmpty.hidden = false;
        if (cfgEditor) cfgEditor.hidden = true;
        if (cfgSave) cfgSave.hidden = true;
        renderCanvas();
      });
    }

    renderPalette();
    renderCanvas();
    refreshList();
    refreshRuns();
  }

  document.addEventListener("DOMContentLoaded", initWorkflowsPage);
})(window);

