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
    var palette = bySel("[data-workflow-node-palette]", root);
    var nameEl = bySel("#workflow-name", root);
    var triggerEl = bySel("[data-workflow-trigger]", root);
    var templateEl = bySel("[data-workflow-template-select]", root);
    var listEl = bySel("[data-workflow-list]", root);
    var runsEl = bySel("[data-workflow-runs]", root);
    var cfgEmpty = bySel("[data-workflow-inspector-empty]", root);
    var cfgEditor = bySel("[data-workflow-node-config]", root);
    var cfgSave = bySel("[data-workflow-node-config-save]", root);

    var currentWorkflowId = null;
    var selectedNodeId = null;
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

    function addNode(type) {
      var id = "node_" + Date.now() + "_" + Math.floor(Math.random() * 1000);
      graph.nodes.push({
        id: id,
        type: type,
        config: defaultConfigForType(type)
      });
      if (graph.nodes.length > 1) {
        var prev = graph.nodes[graph.nodes.length - 2];
        graph.edges.push({ from: prev.id, to: id });
      }
      renderCanvas();
    }

    function renderCanvas() {
      if (!canvas) return;
      canvas.innerHTML = "";
      if (!graph.nodes.length) {
        var empty = document.createElement("p");
        empty.className = "muted";
        empty.textContent = "Drag nodes here (double-click node to edit config JSON).";
        canvas.appendChild(empty);
        return;
      }

      graph.nodes.forEach(function (node, idx) {
        var card = document.createElement("button");
        card.type = "button";
        card.className = "workflow-node-card" + (selectedNodeId === node.id ? " is-active" : "");
        card.dataset.nodeId = node.id;
        card.innerHTML = "<strong>" + node.type + "</strong><small>#"+ String(idx + 1) + "</small>";
        card.addEventListener("click", function () {
          selectedNodeId = node.id;
          openInspector(node);
          renderCanvas();
        });
        card.addEventListener("dblclick", function () {
          selectedNodeId = node.id;
          openInspector(node);
        });
        canvas.appendChild(card);
      });
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
        addNode(type);
      });
    }

    function loadTemplateByKey(key) {
      var t = templates.find(function (x) { return x.key === key; });
      if (!t) return;
      nameEl.value = t.name || "";
      if (triggerEl) triggerEl.value = t.trigger_type || "manual";
      graph = JSON.parse(JSON.stringify(t.graph || { nodes: [], edges: [] }));
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
        status: "active",
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
    if (btnSave) btnSave.addEventListener("click", saveCurrentWorkflow);
    if (btnRun) btnRun.addEventListener("click", runCurrentWorkflow);
    if (btnNew) {
      btnNew.addEventListener("click", function () {
        currentWorkflowId = null;
        selectedNodeId = null;
        graph = { nodes: [], edges: [] };
        if (nameEl) nameEl.value = "";
        if (triggerEl) triggerEl.value = "manual";
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

