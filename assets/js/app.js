(function (global) {
  "use strict";

  var STORAGE = {
    theme: "app-theme",
    legacyTheme: "creem-clone-theme",
    displayTimezone: "app-display-timezone",
    legacyDisplayCurrency: "app-display-currency",
    aiSource: "postai-ai-source",
    aiProvider: "postai-ai-provider",
    aiBaseUrl: "postai-ai-base-url",
    aiApiKey: "postai-ai-api-key",
    planHistory: "postai-plan-history"
  };

  function appendPlanChangeHistory(fromPlanId, toPlanId) {
    var ORDER = ["starter", "growth", "scale", "enterprise"];
    if (!fromPlanId || !toPlanId || fromPlanId === toPlanId) return;
    var i0 = ORDER.indexOf(fromPlanId);
    var i1 = ORDER.indexOf(toPlanId);
    if (i0 === -1 || i1 === -1) return;
    var kind = i1 > i0 ? "upgrade" : i1 < i0 ? "downgrade" : "change";
    var entry = {
      fromPlanId: fromPlanId,
      toPlanId: toPlanId,
      at: new Date().toISOString(),
      kind: kind
    };
    try {
      var raw = global.localStorage.getItem(STORAGE.planHistory);
      var list = [];
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) list = parsed;
      }
      list.unshift(entry);
      if (list.length > 100) list = list.slice(0, 100);
      global.localStorage.setItem(STORAGE.planHistory, JSON.stringify(list));
    } catch (e) {}
  }

  function readPlanHistory() {
    try {
      var raw = global.localStorage.getItem(STORAGE.planHistory);
      if (!raw) return [];
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function formatPlanHistoryWhen(iso) {
    try {
      var d = new Date(iso);
      return d.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
    } catch (e) {
      return iso || "—";
    }
  }

  function initPlanHistoryPage() {
    var root = document.querySelector("[data-app-plan-history]");
    if (!root) return;

    var PLAN_LABELS = {
      starter: "Starter",
      growth: "Growth",
      scale: "Scale",
      enterprise: "Enterprise"
    };

    function kindLabel(kind) {
      if (kind === "upgrade") return "Upgrade";
      if (kind === "downgrade") return "Downgrade";
      return "Change";
    }

    function kindClass(kind) {
      if (kind === "upgrade") return "plan-history-badge plan-history-badge--upgrade";
      if (kind === "downgrade") return "plan-history-badge plan-history-badge--downgrade";
      return "plan-history-badge plan-history-badge--neutral";
    }

    function render() {
      var list = readPlanHistory();
      var tbody = root.querySelector("[data-app-plan-history-body]");
      var emptyEl = root.querySelector("[data-app-plan-history-empty]");
      var tableScroll = root.querySelector("[data-app-plan-history-table-wrap]");
      if (!tbody || !emptyEl || !tableScroll) return;

      if (!list.length) {
        emptyEl.hidden = false;
        tableScroll.hidden = true;
        tbody.innerHTML = "";
        return;
      }

      emptyEl.hidden = true;
      tableScroll.hidden = false;
      tbody.innerHTML = "";

      list.forEach(function (row) {
        var tr = document.createElement("tr");
        var fromId = row.fromPlanId;
        var toId = row.toPlanId;
        var fromLabel = fromId ? PLAN_LABELS[fromId] || fromId : "—";
        var toLabel = PLAN_LABELS[toId] || toId || "—";
        var td0 = document.createElement("td");
        td0.textContent = formatPlanHistoryWhen(row.at);
        var td1 = document.createElement("td");
        td1.textContent = fromLabel;
        var td2 = document.createElement("td");
        td2.textContent = toLabel;
        var td3 = document.createElement("td");
        var span = document.createElement("span");
        span.className = kindClass(row.kind || "change");
        span.textContent = kindLabel(row.kind || "change");
        td3.appendChild(span);
        tr.appendChild(td0);
        tr.appendChild(td1);
        tr.appendChild(td2);
        tr.appendChild(td3);
        tbody.appendChild(tr);
      });
    }

    render();
  }

  var DISPLAY_TIMEZONES = (function () {
    var fromServer =
      typeof global.__appDisplayTimezones === "object" &&
      global.__appDisplayTimezones &&
      Object.keys(global.__appDisplayTimezones).length
        ? global.__appDisplayTimezones
        : null;
    if (fromServer) return fromServer;
    return { UTC: { code: "UTC", name: "Coordinated Universal Time", symbol: "" } };
  })();

  var LEGACY_DISPLAY_TIMEZONE_KEYS = { utc: "UTC", est: "America/New_York", cet: "Europe/Paris" };

  function readStoredTheme() {
    var t = localStorage.getItem(STORAGE.theme) || localStorage.getItem(STORAGE.legacyTheme);
    if (t === "light" || t === "dark") return t;
    if (global.matchMedia && global.matchMedia("(prefers-color-scheme: light)").matches) return "light";
    return "dark";
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    localStorage.setItem(STORAGE.theme, theme);
    var btn = document.querySelector("[data-app-theme-toggle]");
    if (!btn) return;
    btn.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");
    var label = btn.querySelector("[data-app-theme-label]");
    if (label) label.textContent = theme === "dark" ? "Dark" : "Light";
    var icon = btn.querySelector("[data-app-theme-icon]");
    if (icon) icon.className = theme === "dark" ? "fa-solid fa-moon" : "fa-solid fa-sun";
  }

  function initTopbarDate() {
    document.querySelectorAll("[data-app-topbar-date]").forEach(function (el) {
      var opts = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
      var d = new Date();
      el.textContent = d.toLocaleDateString(undefined, opts);
      el.setAttribute("datetime", d.toISOString().slice(0, 10));
    });
  }

  function initTheme() {
    applyTheme(readStoredTheme());
    var btn = document.querySelector("[data-app-theme-toggle]");
    if (btn) {
      btn.addEventListener("click", function () {
        var next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
        applyTheme(next);
      });
    }
    if (global.matchMedia) {
      global.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function (e) {
        if (!localStorage.getItem(STORAGE.theme) && !localStorage.getItem(STORAGE.legacyTheme)) {
          applyTheme(e.matches ? "dark" : "light");
        }
      });
    }
  }

  function syncBodyOverflow() {
    var drawerOpen = document.querySelector("[data-app-drawer-panel].is-open");
    var modalOpen = document.querySelector("[data-app-modal].is-open");
    document.body.style.overflow = drawerOpen || modalOpen ? "hidden" : "";
  }

  function closeModal(modal) {
    if (!modal || !modal.hasAttribute("data-app-modal")) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    syncBodyOverflow();
  }

  function openModal(id) {
    document.querySelectorAll("[data-app-modal].is-open").forEach(function (m) {
      if (m.id !== id) closeModal(m);
    });
    var modal = document.getElementById(id);
    if (!modal || !modal.hasAttribute("data-app-modal")) return;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    syncBodyOverflow();
    var focus = modal.querySelector(
      "input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([data-app-modal-close])"
    );
    if (focus) {
      global.setTimeout(function () {
        focus.focus();
      }, 40);
    }
  }

  function initModals() {
    document.querySelectorAll("[data-app-modal-open]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-app-modal-open");
        if (id) openModal(id);
      });
    });
    document.querySelectorAll("[data-app-modal-close]").forEach(function (el) {
      el.addEventListener("click", function () {
        var modal = el.closest("[data-app-modal]");
        closeModal(modal);
      });
    });
  }

  function initConnectAccountModal() {
    var modal = document.getElementById("modal-connect-account");
    if (!modal) return;
    document.querySelectorAll("[data-app-connect-open]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var name = btn.getAttribute("data-connect-name") || "Network";
        var iconClass = btn.getAttribute("data-connect-icon") || "fa-solid fa-plug";
        var desc = btn.getAttribute("data-connect-desc") || "";
        var titleEl = modal.querySelector("[data-connect-modal-title]");
        var iconEl = modal.querySelector("[data-connect-modal-icon]");
        var hintEl = modal.querySelector("[data-connect-modal-hint]");
        if (titleEl) titleEl.textContent = "Connect " + name;
        if (iconEl) {
          iconEl.className = iconClass + " modal-connect-hero__icon";
        }
        if (hintEl) {
          hintEl.textContent = desc || "PostAI will request only the permissions needed to publish and read analytics.";
        }
        openModal("modal-connect-account");
      });
    });
  }

  function initDrawer() {
    var panel = document.querySelector("[data-app-drawer-panel]");
    var overlay = document.querySelector("[data-app-drawer-overlay]");
    var openBtns = document.querySelectorAll("[data-app-drawer-open]");
    var closeBtns = document.querySelectorAll("[data-app-drawer-close]");

    function open() {
      if (panel) panel.classList.add("is-open");
      if (overlay) overlay.classList.add("is-open");
      syncBodyOverflow();
    }

    function close() {
      if (panel) panel.classList.remove("is-open");
      if (overlay) overlay.classList.remove("is-open");
      syncBodyOverflow();
    }

    openBtns.forEach(function (b) {
      b.addEventListener("click", open);
    });
    closeBtns.forEach(function (b) {
      b.addEventListener("click", close);
    });
    if (overlay) overlay.addEventListener("click", close);

    document.querySelectorAll(".nav-link").forEach(function (link) {
      link.addEventListener("click", function () {
        if (global.innerWidth <= 1024) close();
      });
    });
  }

  function closeStoreSwitcher(wrap) {
    if (!wrap || !wrap.hasAttribute("data-app-store-switcher")) return;
    var trigger = wrap.querySelector("[data-app-store-trigger]");
    var menu = wrap.querySelector(".workspace-menu");
    wrap.classList.remove("is-open");
    if (trigger) trigger.setAttribute("aria-expanded", "false");
    if (menu) menu.setAttribute("hidden", "");
  }

  function initStoreSwitcher() {
    document.querySelectorAll("[data-app-store-switcher]").forEach(function (wrap) {
      var trigger = wrap.querySelector("[data-app-store-trigger]");
      var menu = wrap.querySelector(".workspace-menu");
      var nameEl = wrap.querySelector("[data-app-current-store]");
      if (!trigger || !menu) return;

      function openMenu() {
        wrap.classList.add("is-open");
        trigger.setAttribute("aria-expanded", "true");
        menu.removeAttribute("hidden");
      }

      function closeMenu() {
        closeStoreSwitcher(wrap);
      }

      trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        if (wrap.classList.contains("is-open")) closeMenu();
        else openMenu();
      });

      wrap.querySelectorAll("[data-app-store-option]").forEach(function (opt) {
        opt.addEventListener("click", function () {
          var newName = opt.getAttribute("data-store-name") || "";
          wrap.querySelectorAll("[data-app-store-option]").forEach(function (b) {
            var on = b === opt;
            b.classList.toggle("is-active", on);
            b.setAttribute("aria-checked", on ? "true" : "false");
          });
          if (nameEl) nameEl.textContent = newName;
          closeMenu();
          var panel = document.querySelector("[data-app-drawer-panel]");
          var overlay = document.querySelector("[data-app-drawer-overlay]");
          if (global.innerWidth <= 1024 && panel && panel.classList.contains("is-open")) {
            panel.classList.remove("is-open");
            if (overlay) overlay.classList.remove("is-open");
            syncBodyOverflow();
          }
        });
      });
    });

    document.addEventListener("click", function (e) {
      if (e.target.closest("[data-app-store-switcher]")) return;
      document.querySelectorAll("[data-app-store-switcher].is-open").forEach(closeStoreSwitcher);
    });
  }

  function readStoredDisplayTimezone() {
    var def = global.__appDefaultDisplayTimezone || "UTC";
    var k = localStorage.getItem(STORAGE.displayTimezone);
    if (k && DISPLAY_TIMEZONES[k]) return k;
    if (k && LEGACY_DISPLAY_TIMEZONE_KEYS[k]) {
      var mapped = LEGACY_DISPLAY_TIMEZONE_KEYS[k];
      if (DISPLAY_TIMEZONES[mapped]) return mapped;
    }
    var legacy = localStorage.getItem(STORAGE.legacyDisplayCurrency);
    if (legacy === "eur" && DISPLAY_TIMEZONES["Europe/Paris"]) return "Europe/Paris";
    if ((legacy === "gbp" || legacy === "usd") && DISPLAY_TIMEZONES["UTC"]) return "UTC";
    return DISPLAY_TIMEZONES[def] ? def : Object.keys(DISPLAY_TIMEZONES)[0];
  }

  function applyDisplayTimezone(key) {
    var def = global.__appDefaultDisplayTimezone || "UTC";
    if (!DISPLAY_TIMEZONES[key]) {
      key = DISPLAY_TIMEZONES[def] ? def : Object.keys(DISPLAY_TIMEZONES)[0];
    }
    var meta = DISPLAY_TIMEZONES[key];
    document.documentElement.setAttribute("data-app-timezone", key);
    try {
      localStorage.setItem(STORAGE.displayTimezone, key);
    } catch (e) {}
    document.querySelectorAll("[data-app-timezone-label]").forEach(function (el) {
      el.textContent = meta.code;
    });
    document.querySelectorAll("[data-app-timezone-symbol]").forEach(function (el) {
      el.textContent = meta.symbol;
    });
    document.querySelectorAll("[data-app-timezone-option]").forEach(function (btn) {
      var v = btn.getAttribute("data-value");
      btn.setAttribute("aria-selected", v === key ? "true" : "false");
    });
  }

  function closeTimezoneMenu(wrap) {
    if (!wrap || !wrap.hasAttribute("data-app-timezone-wrap")) return;
    wrap.classList.remove("is-open");
    var trigger = wrap.querySelector("[data-app-timezone-trigger]");
    if (trigger) trigger.setAttribute("aria-expanded", "false");
    var menu = wrap.querySelector("[data-app-timezone-menu]");
    if (menu) menu.hidden = true;
  }

  function openTimezoneMenu(wrap) {
    if (!wrap) return;
    wrap.classList.add("is-open");
    var trigger = wrap.querySelector("[data-app-timezone-trigger]");
    if (trigger) trigger.setAttribute("aria-expanded", "true");
    var menu = wrap.querySelector("[data-app-timezone-menu]");
    if (menu) menu.hidden = false;
  }

  function closeAccountMenu(wrap) {
    if (!wrap || !wrap.hasAttribute("data-app-account-wrap")) return;
    wrap.classList.remove("is-open");
    var trigger = wrap.querySelector("[data-app-account-trigger]");
    if (trigger) trigger.setAttribute("aria-expanded", "false");
    var menu = wrap.querySelector("[data-app-account-menu]");
    if (menu) menu.hidden = true;
  }

  function openAccountMenu(wrap) {
    if (!wrap) return;
    wrap.classList.add("is-open");
    var trigger = wrap.querySelector("[data-app-account-trigger]");
    if (trigger) trigger.setAttribute("aria-expanded", "true");
    var menu = wrap.querySelector("[data-app-account-menu]");
    if (menu) menu.hidden = false;
  }

  function initDisplayTimezone() {
    var wraps = document.querySelectorAll("[data-app-timezone-wrap]");
    if (!wraps.length) return;

    applyDisplayTimezone(readStoredDisplayTimezone());

    wraps.forEach(function (wrap) {
      var trigger = wrap.querySelector("[data-app-timezone-trigger]");
      var menu = wrap.querySelector("[data-app-timezone-menu]");
      if (!trigger || !menu) return;

      trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        e.preventDefault();
        if (wrap.classList.contains("is-open")) closeTimezoneMenu(wrap);
        else {
          document.querySelectorAll("[data-app-timezone-wrap].is-open").forEach(function (w) {
            if (w !== wrap) closeTimezoneMenu(w);
          });
          document.querySelectorAll("[data-app-account-wrap].is-open").forEach(function (w) {
            closeAccountMenu(w);
          });
          openTimezoneMenu(wrap);
        }
      });

      menu.querySelectorAll("[data-app-timezone-option]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var v = btn.getAttribute("data-value");
          if (v) applyDisplayTimezone(v);
          closeTimezoneMenu(wrap);
        });
      });
    });

    document.addEventListener("click", function (e) {
      document.querySelectorAll("[data-app-timezone-wrap].is-open").forEach(function (wrap) {
        if (wrap.contains(e.target)) return;
        closeTimezoneMenu(wrap);
      });
    });
  }

  function initTopbarAccountMenu() {
    var wraps = document.querySelectorAll("[data-app-account-wrap]");
    if (!wraps.length) return;

    wraps.forEach(function (wrap) {
      var trigger = wrap.querySelector("[data-app-account-trigger]");
      var menu = wrap.querySelector("[data-app-account-menu]");
      if (!trigger || !menu) return;

      trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        e.preventDefault();
        if (wrap.classList.contains("is-open")) closeAccountMenu(wrap);
        else {
          document.querySelectorAll("[data-app-account-wrap].is-open").forEach(function (w) {
            if (w !== wrap) closeAccountMenu(w);
          });
          document.querySelectorAll("[data-app-timezone-wrap].is-open").forEach(function (w) {
            closeTimezoneMenu(w);
          });
          openAccountMenu(wrap);
        }
      });
    });

    document.addEventListener("click", function (e) {
      document.querySelectorAll("[data-app-account-wrap].is-open").forEach(function (accountWrap) {
        if (accountWrap.contains(e.target)) return;
        closeAccountMenu(accountWrap);
      });
    });
  }

  function initLogout() {
    var modalBuilt = false;

    function ensureLogoutModal() {
      if (modalBuilt) return document.getElementById("modal-logout");
      var modal = document.createElement("div");
      modal.id = "modal-logout";
      modal.className = "app-modal";
      modal.setAttribute("data-app-modal", "");
      modal.setAttribute("role", "dialog");
      modal.setAttribute("aria-modal", "true");
      modal.setAttribute("aria-labelledby", "modal-logout-title");
      modal.setAttribute("aria-hidden", "true");
      modal.innerHTML =
        '<div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>' +
        '<div class="app-modal__panel">' +
        '<div class="app-modal__head">' +
        '<div><h2 id="modal-logout-title">Log out?</h2>' +
        '<p class="app-modal__lede">You will need to sign in again to access this workspace.</p></div>' +
        '<button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">' +
        '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>' +
        '<div class="app-modal__foot">' +
        '<button type="button" class="btn btn--ghost" data-app-modal-close>Stay signed in</button>' +
        '<button type="button" class="btn btn--primary" data-app-logout-confirm>Log out</button>' +
        "</div></div>";

      document.body.appendChild(modal);

      modal.querySelectorAll("[data-app-modal-close]").forEach(function (el) {
        el.addEventListener("click", function () {
          closeModal(modal);
        });
      });
      var confirmBtn = modal.querySelector("[data-app-logout-confirm]");
      if (confirmBtn) {
        confirmBtn.addEventListener("click", function () {
          var logoutForm = document.querySelector('form[action$="/logout"]');
          if (logoutForm) {
            logoutForm.submit();
            return;
          }
          var form = document.createElement("form");
          form.method = "POST";
          form.action = "/logout";
          var csrf = document.querySelector('meta[name="csrf-token"]');
          if (csrf) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "_token";
            input.value = csrf.getAttribute("content");
            form.appendChild(input);
          }
          document.body.appendChild(form);
          form.submit();
        });
      }

      modalBuilt = true;
      return modal;
    }

    document.querySelectorAll("[data-app-logout]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        ensureLogoutModal();
        openModal("modal-logout");
      });
    });
  }

  function initPlansPage() {
    var root = document.querySelector("[data-app-plans]");
    if (!root) return;

    if (root.getAttribute("data-app-plans-server") === "1") {
      renderPlansFromServer(root);
      return;
    }

    var STORAGE_KEY = "postai-plan";
    var DEFAULT_PLAN = "growth";
    var PLAN_ORDER = ["starter", "growth", "scale", "enterprise"];
    var PLAN_LABELS = {
      starter: "Starter",
      growth: "Growth",
      scale: "Scale",
      enterprise: "Enterprise"
    };

    function planIndex(id) {
      return PLAN_ORDER.indexOf(id);
    }

    function readPlan() {
      try {
        var v = global.localStorage.getItem(STORAGE_KEY);
        if (v && planIndex(v) !== -1) return v;
      } catch (e) {}
      return DEFAULT_PLAN;
    }

    function writePlan(id) {
      try {
        global.localStorage.setItem(STORAGE_KEY, id);
      } catch (e) {}
    }

    function render() {
      var current = readPlan();
      var curIdx = planIndex(current);

      var labelEl = document.querySelector("[data-app-plan-label]");
      if (labelEl) labelEl.textContent = PLAN_LABELS[current] || current;

      root.querySelectorAll("[data-plan-id]").forEach(function (card) {
        var id = card.getAttribute("data-plan-id");
        if (!id) return;
        var idx = planIndex(id);
        var isCurrent = id === current;
        card.classList.toggle("plan-card--current", isCurrent);

        var btn = card.querySelector("[data-plan-select]");
        if (!btn) return;

        btn.classList.remove("btn--primary", "btn--ghost", "btn--outline");
        btn.disabled = false;

        if (id === "enterprise" && !isCurrent) {
          btn.textContent = "Contact sales";
          btn.classList.add("btn--outline");
          btn.setAttribute("aria-label", "Contact sales for Enterprise");
          return;
        }

        if (isCurrent) {
          btn.textContent = "Current plan";
          btn.disabled = true;
          btn.classList.add("btn--outline");
          btn.setAttribute("aria-label", "Current plan");
          return;
        }

        if (idx > curIdx) {
          btn.textContent = "Upgrade";
          btn.classList.add("btn--primary");
          btn.setAttribute("aria-label", "Upgrade to " + (PLAN_LABELS[id] || id));
        } else {
          btn.textContent = "Downgrade";
          btn.classList.add("btn--ghost");
          btn.setAttribute("aria-label", "Downgrade to " + (PLAN_LABELS[id] || id));
        }
      });
    }

    root.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-plan-select]");
      if (!btn || btn.disabled) return;
      var card = btn.closest("[data-plan-id]");
      if (!card) return;
      var id = card.getAttribute("data-plan-id");
      if (!id) return;

      var status = document.querySelector("[data-app-plan-status]");
      if (id === "enterprise") {
        if (status) {
          status.textContent =
            "Enterprise pricing is handled by sales. This demo does not send email or open a form.";
        }
        return;
      }

      var previous = readPlan();
      if (previous !== id) {
        appendPlanChangeHistory(previous, id);
      }
      writePlan(id);
      render();
      if (status) {
        status.textContent =
          "Your plan is now set to " +
          (PLAN_LABELS[id] || id) +
          ". Connect a payment backend to charge and sync.";
      }
    });

    render();
  }

  function renderPlansFromServer(root) {
    var current = root.getAttribute("data-current-plan-slug") || "";
    var labelEl = document.querySelector("[data-app-plan-label]");
    var nameEl = root.querySelector('.plan-card--current .plan-card__name');
    if (labelEl) {
      labelEl.textContent = nameEl ? nameEl.textContent.trim() : current || "No plan";
    }

    var order = [];
    root.querySelectorAll("[data-plan-id]").forEach(function (card) {
      var id = card.getAttribute("data-plan-id");
      if (!id) return;
      var sort = parseInt(card.getAttribute("data-plan-sort"), 10);
      order.push({ id: id, sort: isNaN(sort) ? 999 : sort });
    });
    order.sort(function (a, b) {
      return a.sort - b.sort;
    });
    var pos = {};
    order.forEach(function (row, i) {
      pos[row.id] = i;
    });

    root.querySelectorAll("[data-plan-id]").forEach(function (card) {
      var id = card.getAttribute("data-plan-id");
      if (!id) return;
      var isCurrent = id === current;
      card.classList.toggle("plan-card--current", isCurrent);

      var btn = card.querySelector("[data-plan-select]");
      if (!btn) return;

      btn.classList.remove("btn--primary", "btn--ghost", "btn--outline");
      btn.disabled = false;

      if (id === "enterprise" && !isCurrent) {
        btn.textContent = "Contact sales";
        btn.classList.add("btn--outline");
        btn.setAttribute("aria-label", "Contact sales for Enterprise");
        return;
      }

      if (isCurrent) {
        btn.textContent = "Current plan";
        btn.disabled = true;
        btn.classList.add("btn--outline");
        btn.setAttribute("aria-label", "Current plan");
        return;
      }

      var curIdx = pos[current] != null ? pos[current] : -1;
      var idx = pos[id] != null ? pos[id] : 0;
      if (idx > curIdx) {
        btn.textContent = "Upgrade";
        btn.classList.add("btn--primary");
        btn.setAttribute("aria-label", "Upgrade to plan");
      } else {
        btn.textContent = "Downgrade";
        btn.classList.add("btn--ghost");
        btn.setAttribute("aria-label", "Downgrade to plan");
      }
    });
  }

  function readAiConfig() {
    try {
      var source = global.localStorage.getItem(STORAGE.aiSource);
      if (source !== "byok") source = "platform";
      var provider = global.localStorage.getItem(STORAGE.aiProvider);
      if (provider !== "openai" && provider !== "anthropic" && provider !== "custom") {
        provider = "openai";
      }
      return {
        source: source,
        provider: provider,
        baseUrl: global.localStorage.getItem(STORAGE.aiBaseUrl) || "",
        hasApiKey: !!global.localStorage.getItem(STORAGE.aiApiKey)
      };
    } catch (e) {
      return { source: "platform", provider: "openai", baseUrl: "", hasApiKey: false };
    }
  }

  function initSettingsAi() {
    var root = document.querySelector("[data-app-settings-ai]");
    if (!root) return;

    var group = root.querySelector("[data-app-ai-source-group]");
    var byokPanel = root.querySelector("[data-app-ai-byok]");
    var providerEl = root.querySelector("[data-app-ai-provider]");
    var baseWrap = root.querySelector("[data-app-ai-base-url-wrap]");
    var baseInput = root.querySelector("[data-app-ai-base-url]");
    var keyInput = root.querySelector("[data-app-ai-key]");
    var keyHint = root.querySelector("[data-app-ai-key-hint]");
    var clearKeyBtn = root.querySelector("[data-app-ai-clear-key]");
    var statusEl = root.querySelector("[data-app-ai-status]");
    var saveBtn = root.querySelector("[data-app-ai-save]");

    function syncBaseUrlVisibility() {
      if (!providerEl || !baseWrap) return;
      var isCustom = providerEl.value === "custom";
      baseWrap.hidden = !isCustom;
      if (!isCustom && baseInput) baseInput.value = "";
    }

    function setSourceButtons(source) {
      if (!group) return;
      group.querySelectorAll("[data-ai-source]").forEach(function (btn) {
        var v = btn.getAttribute("data-ai-source");
        btn.setAttribute("aria-selected", v === source ? "true" : "false");
      });
    }

    function applyByokVisibility(source) {
      if (!byokPanel) return;
      byokPanel.hidden = source !== "byok";
    }

    function refreshKeyHint() {
      if (!keyHint) return;
      var cfg = readAiConfig();
      if (cfg.source !== "byok") {
        keyHint.textContent = "";
        return;
      }
      if (cfg.hasApiKey && keyInput && !(keyInput.value || "").trim()) {
        keyHint.textContent = "A key is saved for this browser. Paste a new key to replace it.";
      } else {
        keyHint.textContent = "";
      }
    }

    function refreshStatus() {
      var cfg = readAiConfig();
      if (statusEl) {
        if (cfg.source === "platform") {
          statusEl.textContent = "Composer and assistant use PostAI platform models (per your plan).";
        } else if (cfg.hasApiKey) {
          statusEl.textContent =
            "Using your API key from this browser. Route requests through your backend in production.";
        } else {
          statusEl.textContent = "Bring your own key is selected — add and save an API key to enable.";
        }
      }
      if (clearKeyBtn) {
        clearKeyBtn.hidden = cfg.source !== "byok" || !cfg.hasApiKey;
      }
    }

    function hydrate() {
      var cfg = readAiConfig();
      setSourceButtons(cfg.source);
      applyByokVisibility(cfg.source);
      if (providerEl) {
        providerEl.value = cfg.provider;
      }
      if (baseInput && cfg.baseUrl) {
        baseInput.value = cfg.baseUrl;
      }
      syncBaseUrlVisibility();
      if (keyInput) keyInput.value = "";
      refreshKeyHint();
      refreshStatus();
    }

    if (group) {
      group.querySelectorAll("[data-ai-source]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var v = btn.getAttribute("data-ai-source");
          if (v !== "platform" && v !== "byok") return;
          try {
            global.localStorage.setItem(STORAGE.aiSource, v);
          } catch (e) {}
          setSourceButtons(v);
          applyByokVisibility(v);
          refreshKeyHint();
          refreshStatus();
        });
      });
    }

    if (providerEl) {
      providerEl.addEventListener("change", syncBaseUrlVisibility);
    }

    if (clearKeyBtn) {
      clearKeyBtn.addEventListener("click", function () {
        try {
          global.localStorage.removeItem(STORAGE.aiApiKey);
        } catch (e) {}
        if (keyInput) keyInput.value = "";
        refreshKeyHint();
        refreshStatus();
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var provider = providerEl ? providerEl.value : "openai";
        if (provider !== "openai" && provider !== "anthropic" && provider !== "custom") {
          provider = "openai";
        }
        try {
          global.localStorage.setItem(STORAGE.aiProvider, provider);
          var baseVal = baseInput ? (baseInput.value || "").trim() : "";
          if (provider === "custom" && baseVal) {
            global.localStorage.setItem(STORAGE.aiBaseUrl, baseVal);
          } else {
            global.localStorage.removeItem(STORAGE.aiBaseUrl);
          }
          var keyVal = keyInput ? (keyInput.value || "").trim() : "";
          if (keyVal) {
            global.localStorage.setItem(STORAGE.aiApiKey, keyVal);
          }
          if (keyInput) keyInput.value = "";
        } catch (e) {}
        refreshKeyHint();
        refreshStatus();
        apiPost("/settings/ai", {
          ai_source: global.localStorage.getItem(STORAGE.aiSource) || "platform",
          ai_provider: provider,
          ai_base_url: provider === "custom" ? (baseInput ? (baseInput.value || "").trim() : "") : null
        });
        openModal("modal-settings-saved");
      });
    }

    hydrate();
  }

  function initNavAriaCurrent() {
    document.querySelectorAll(".nav-link--active").forEach(function (a) {
      a.setAttribute("aria-current", "page");
    });
  }

  function initSidebarCollapse() {
    var STORAGE_KEY = "app-sidebar-collapsed";
    var btn = document.querySelector("[data-app-sidebar-collapse]");
    if (!btn) return;

    function setCollapsed(collapsed) {
      if (collapsed) document.body.classList.add("app-sidebar-collapsed");
      else document.body.classList.remove("app-sidebar-collapsed");
      try {
        localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0");
      } catch (e) {}
      btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
      btn.setAttribute("aria-label", collapsed ? "Expand navigation" : "Collapse navigation");
      var icon = btn.querySelector("i");
      if (icon) icon.className = collapsed ? "fa-solid fa-angles-right" : "fa-solid fa-angles-left";
    }

    setCollapsed(localStorage.getItem(STORAGE_KEY) === "1");

    btn.addEventListener("click", function () {
      setCollapsed(!document.body.classList.contains("app-sidebar-collapsed"));
    });
  }

  function initEscapeKey() {
    global.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var openModalEl = document.querySelector("[data-app-modal].is-open");
      if (openModalEl) {
        e.preventDefault();
        closeModal(openModalEl);
        return;
      }
      var openTz = document.querySelector("[data-app-timezone-wrap].is-open");
      if (openTz) {
        e.preventDefault();
        closeTimezoneMenu(openTz);
        return;
      }
      var openAccount = document.querySelector("[data-app-account-wrap].is-open");
      if (openAccount) {
        e.preventDefault();
        closeAccountMenu(openAccount);
        return;
      }
      var openStore = document.querySelector("[data-app-store-switcher].is-open");
      if (openStore) {
        e.preventDefault();
        closeStoreSwitcher(openStore);
        return;
      }
      var panel = document.querySelector("[data-app-drawer-panel]");
      var overlay = document.querySelector("[data-app-drawer-overlay]");
      if (panel && panel.classList.contains("is-open")) {
        panel.classList.remove("is-open");
        if (overlay) overlay.classList.remove("is-open");
        syncBodyOverflow();
      }
    });
  }

  function initSwitches() {
    document.querySelectorAll("[data-app-switch]").forEach(function (el) {
      el.addEventListener("click", function () {
        var on = el.getAttribute("aria-checked") === "true";
        el.setAttribute("aria-checked", on ? "false" : "true");
      });
    });
  }

  function initSearchShortcut() {
    document.addEventListener("keydown", function (e) {
      var isK = e.key === "k" || e.key === "K";
      var mod = e.metaKey || e.ctrlKey;
      if (!mod || !isK) return;
      e.preventDefault();
      var input = document.querySelector("[data-app-search-input]");
      if (input) input.focus();
    });
  }

  function initSidebarNavSearch() {
    var wrap = document.querySelector("[data-app-sidebar-search]");
    var input = document.querySelector("[data-app-search-input]");
    var panel = document.querySelector("[data-app-sidebar-search-results]");
    var nav = document.querySelector("#app-sidebar .nav-scroll");
    if (!wrap || !input || !panel || !nav) return;

    function buildIndex() {
      var items = [];
      nav.querySelectorAll("a.nav-link[href]").forEach(function (a) {
        var href = a.getAttribute("href");
        if (!href || href === "#" || href.indexOf("javascript:") === 0) return;
        var label = (a.textContent || "").replace(/\s+/g, " ").trim();
        if (!label) return;
        var group = "Main";
        var groupEl = a.closest(".nav-group");
        if (groupEl) {
          var gl = groupEl.querySelector(".nav-group__label");
          if (gl) {
            group = (gl.textContent || "").replace(/\s+/g, " ").trim() || "Main";
          }
        }
        items.push({ href: href, label: label, group: group });
      });
      return items;
    }

    var INDEX = buildIndex();
    if (!INDEX.length) return;

    var activeIdx = -1;

    function norm(s) {
      return (s || "").toLowerCase();
    }

    function filter(q) {
      var nq = norm(q).trim();
      if (!nq) return [];
      return INDEX.filter(function (item) {
        return (
          norm(item.label).indexOf(nq) !== -1 ||
          norm(item.group).indexOf(nq) !== -1 ||
          norm(item.label + " " + item.group).indexOf(nq) !== -1
        );
      }).slice(0, 20);
    }

    function navigate(href) {
      try {
        global.sessionStorage.setItem("appNavPending", "1");
        document.documentElement.classList.add("app-nav-loading");
      } catch (err) {}
      global.location.href = href;
    }

    function closePanel() {
      panel.innerHTML = "";
      panel.hidden = true;
      input.setAttribute("aria-expanded", "false");
      activeIdx = -1;
    }

    function highlightActive() {
      var opts = panel.querySelectorAll(".app-sidebar-search-hit");
      opts.forEach(function (o, i) {
        o.classList.toggle("app-sidebar-search-hit--active", i === activeIdx);
        o.setAttribute("aria-selected", i === activeIdx ? "true" : "false");
      });
      if (opts[activeIdx]) {
        opts[activeIdx].scrollIntoView({ block: "nearest" });
      }
    }

    function render(list) {
      panel.innerHTML = "";
      activeIdx = -1;
      var qTrim = norm(input.value).trim();
      if (!qTrim) {
        closePanel();
        return;
      }
      if (!list.length) {
        var empty = document.createElement("div");
        empty.className = "app-sidebar-search-empty";
        empty.setAttribute("role", "status");
        empty.textContent = "No matching pages";
        panel.appendChild(empty);
        panel.hidden = false;
        input.setAttribute("aria-expanded", "true");
        return;
      }
      list.forEach(function (item, i) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "app-sidebar-search-hit";
        btn.setAttribute("role", "option");
        btn.setAttribute("data-href", item.href);
        btn.setAttribute("id", "app-sidebar-search-opt-" + i);
        btn.setAttribute("aria-selected", "false");
        var lab = document.createElement("span");
        lab.className = "app-sidebar-search-hit__label";
        lab.textContent = item.label;
        var gr = document.createElement("span");
        gr.className = "app-sidebar-search-hit__group";
        gr.textContent = item.group;
        btn.appendChild(lab);
        btn.appendChild(gr);
        btn.addEventListener("mousedown", function (e) {
          e.preventDefault();
          navigate(item.href);
        });
        panel.appendChild(btn);
      });
      panel.hidden = false;
      input.setAttribute("aria-expanded", "true");
    }

    input.addEventListener("input", function () {
      var q = input.value;
      if (!norm(q).trim()) {
        closePanel();
        return;
      }
      render(filter(q));
    });

    input.addEventListener("focus", function () {
      var q = input.value;
      if (norm(q).trim()) {
        render(filter(q));
      }
    });

    input.addEventListener("keydown", function (e) {
      var opts = panel.querySelectorAll(".app-sidebar-search-hit");
      if (e.key === "ArrowDown") {
        if (!opts.length) return;
        e.preventDefault();
        activeIdx = activeIdx < opts.length - 1 ? activeIdx + 1 : activeIdx;
        if (activeIdx < 0) activeIdx = 0;
        highlightActive();
        return;
      }
      if (e.key === "ArrowUp") {
        if (!opts.length) return;
        e.preventDefault();
        activeIdx = activeIdx > 0 ? activeIdx - 1 : 0;
        highlightActive();
        return;
      }
      if (e.key === "Enter") {
        if (!opts.length) return;
        e.preventDefault();
        var idx = activeIdx >= 0 ? activeIdx : 0;
        var href = opts[idx] && opts[idx].getAttribute("data-href");
        if (href) navigate(href);
        return;
      }
      if (e.key === "Escape") {
        if (!panel.hidden) {
          e.preventDefault();
          closePanel();
        }
      }
    });

    document.addEventListener("click", function (e) {
      if (!wrap.contains(e.target)) {
        closePanel();
      }
    });
  }

  function bindExclusiveButtons(container, selector) {
    var buttons = container.querySelectorAll(selector);
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        buttons.forEach(function (b) {
          b.setAttribute("aria-selected", "false");
        });
        btn.setAttribute("aria-selected", "true");
      });
    });
  }

  function initRadioGroups() {
    document.querySelectorAll("[data-app-radio-group]").forEach(function (group) {
      bindExclusiveButtons(group, "button");
    });
  }

  function initTabLists() {
    document.querySelectorAll("[data-app-tabs]").forEach(function (group) {
      bindExclusiveButtons(group, "button");
    });
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") : "";
  }

  function apiPost(url, data) {
    return fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": getCsrfToken(),
        "X-Requested-With": "XMLHttpRequest"
      },
      credentials: "same-origin",
      body: JSON.stringify(data || {})
    }).then(function (r) {
      return r.json().then(function (json) {
        json._status = r.status;
        json._ok = r.ok;
        return json;
      });
    });
  }

  function apiGet(url) {
    return fetch(url, {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
      credentials: "same-origin"
    }).then(function (r) {
      return r.json().then(function (json) {
        json._status = r.status;
        json._ok = r.ok;
        return json;
      });
    });
  }

  function apiUpload(url, formData) {
    return fetch(url, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-CSRF-TOKEN": getCsrfToken(),
        "X-Requested-With": "XMLHttpRequest"
      },
      credentials: "same-origin",
      body: formData
    }).then(function (r) {
      return r.json().then(function (json) {
        json._status = r.status;
        json._ok = r.ok;
        return json;
      });
    });
  }

  function showFlash(message, type) {
    var existing = document.querySelector(".app-flash");
    if (existing) existing.remove();
    var div = document.createElement("div");
    div.className = "app-flash app-flash--" + (type || "success");
    div.setAttribute("role", "alert");
    div.innerHTML =
      '<i class="fa-solid ' + (type === "error" ? "fa-circle-exclamation" : "fa-circle-check") + '" aria-hidden="true"></i> ' +
      '<span>' + message + '</span>' +
      '<button type="button" class="app-flash__close" aria-label="Dismiss">&times;</button>';
    var content = document.querySelector(".app-content") || document.body;
    content.insertBefore(div, content.firstChild);
    div.querySelector(".app-flash__close").addEventListener("click", function () {
      div.remove();
    });
    setTimeout(function () {
      if (div.parentNode) div.remove();
    }, 6000);
  }

  function initProfilePage() {
    var saveBtn = document.querySelector('[data-app-modal-open="modal-profile-saved"]');
    if (!saveBtn || !document.getElementById("profile-display-name")) return;

    saveBtn.removeAttribute("data-app-modal-open");
    saveBtn.addEventListener("click", function () {
      var data = {
        name: (document.getElementById("profile-display-name").value || "").trim(),
        email: (document.getElementById("profile-email").value || "").trim(),
        phone: (document.getElementById("profile-phone").value || "").trim() || null,
        bio: (document.getElementById("profile-bio").value || "").trim() || null,
        locale: document.getElementById("profile-locale") ? document.getElementById("profile-locale").value : "en"
      };
      saveBtn.disabled = true;
      apiPost("/profile", data)
        .then(function (res) {
          saveBtn.disabled = false;
          if (res._ok) {
            showFlash(res.message || "Profile updated.");
            openModal("modal-profile-saved");
          } else {
            var msg = res.message || res.error || "Failed to save profile.";
            if (res.errors) {
              var first = Object.values(res.errors)[0];
              if (Array.isArray(first)) msg = first[0];
            }
            showFlash(msg, "error");
          }
        })
        .catch(function () {
          saveBtn.disabled = false;
          showFlash("Network error. Please try again.", "error");
        });
    });

    var avatarBtn = document.querySelector(".profile-avatar-actions .btn");
    if (avatarBtn) {
      var fileInput = document.createElement("input");
      fileInput.type = "file";
      fileInput.accept = "image/jpeg,image/png";
      fileInput.style.display = "none";
      document.body.appendChild(fileInput);
      avatarBtn.addEventListener("click", function () {
        fileInput.click();
      });
      fileInput.addEventListener("change", function () {
        if (!fileInput.files || !fileInput.files[0]) return;
        var fd = new FormData();
        fd.append("avatar", fileInput.files[0]);
        avatarBtn.disabled = true;
        apiUpload("/profile/avatar", fd)
          .then(function (res) {
            avatarBtn.disabled = false;
            if (res._ok) {
              showFlash("Photo updated.");
              if (res.avatar_url) {
                var avatarEl = document.querySelector(".profile-avatar-lg");
                if (avatarEl) {
                  avatarEl.innerHTML = '<img src="' + res.avatar_url + '" alt="Avatar" />';
                }
              }
            } else {
              showFlash(res.message || "Upload failed.", "error");
            }
          })
          .catch(function () {
            avatarBtn.disabled = false;
            showFlash("Upload failed.", "error");
          });
        fileInput.value = "";
      });
    }

    var pwBtn = document.querySelector('.profile-card__body .btn[disabled]');
    var pwCurrent = document.getElementById("profile-password-current");
    var pwNew = document.getElementById("profile-password-new");
    var pwConfirm = document.getElementById("profile-password-confirm");
    if (pwBtn && pwCurrent && pwNew && pwConfirm) {
      [pwCurrent, pwNew, pwConfirm].forEach(function (el) {
        el.disabled = false;
        el.removeAttribute("aria-disabled");
      });
      pwBtn.disabled = false;
      pwBtn.removeAttribute("aria-disabled");
      pwBtn.addEventListener("click", function () {
        apiPost("/profile/password", {
          current_password: pwCurrent.value,
          password: pwNew.value,
          password_confirmation: pwConfirm.value
        }).then(function (res) {
          if (res._ok) {
            showFlash("Password updated.");
            pwCurrent.value = "";
            pwNew.value = "";
            pwConfirm.value = "";
          } else {
            var msg = res.message || "Failed to update password.";
            if (res.errors) {
              var first = Object.values(res.errors)[0];
              if (Array.isArray(first)) msg = first[0];
            }
            showFlash(msg, "error");
          }
        }).catch(function () {
          showFlash("Network error.", "error");
        });
      });
    }
  }

  function initSettingsPage() {
    var wsBtn = document.querySelector('.card--settings-workspace [data-app-modal-open="modal-settings-saved"]');
    if (!wsBtn) return;

    wsBtn.removeAttribute("data-app-modal-open");
    wsBtn.addEventListener("click", function () {
      var data = {
        workspace_name: (document.getElementById("ws-name").value || "").trim(),
        workspace_slug: (document.getElementById("ws-slug").value || "").trim()
      };
      wsBtn.disabled = true;
      apiPost("/settings/workspace", data).then(function (res) {
        wsBtn.disabled = false;
        if (res._ok) {
          showFlash(res.message || "Workspace saved.");
          openModal("modal-settings-saved");
        } else {
          showFlash(res.message || "Save failed.", "error");
        }
      }).catch(function () { wsBtn.disabled = false; showFlash("Network error.", "error"); });
    });

    var timeBtn = document.querySelector('.card--settings-time [data-app-modal-open="modal-settings-saved"]');
    if (timeBtn) {
      timeBtn.removeAttribute("data-app-modal-open");
      timeBtn.addEventListener("click", function () {
        var val = document.getElementById("default-time") ? document.getElementById("default-time").value : "09:00";
        timeBtn.disabled = true;
        apiPost("/settings/default-time", { default_posting_time: val }).then(function (res) {
          timeBtn.disabled = false;
          if (res._ok) {
            showFlash(res.message || "Default time saved.");
            openModal("modal-settings-saved");
          } else {
            showFlash(res.message || "Save failed.", "error");
          }
        }).catch(function () { timeBtn.disabled = false; showFlash("Network error.", "error"); });
      });
    }

    var notifChecks = document.querySelectorAll(".card .check-line input[type='checkbox']");
    notifChecks.forEach(function (cb) {
      cb.addEventListener("change", function () {
        var checks = document.querySelectorAll(".card .check-line input[type='checkbox']");
        apiPost("/settings/notifications", {
          email_on_failure: checks[0] ? checks[0].checked : true,
          weekly_digest: checks[1] ? checks[1].checked : true,
          product_updates: checks[2] ? checks[2].checked : false
        });
      });
    });
  }

  function initHelpTicketSubmit() {
    var modal = document.getElementById("modal-help-ticket");
    if (!modal) return;
    var submitBtn = modal.querySelector(".app-modal__foot .btn--primary");
    if (!submitBtn) return;

    submitBtn.removeAttribute("data-app-modal-close");
    submitBtn.addEventListener("click", function () {
      var subject = (document.getElementById("ticket-subject").value || "").trim();
      var category = document.getElementById("ticket-category") ? document.getElementById("ticket-category").value : "other";
      var message = (document.getElementById("ticket-message").value || "").trim();
      var context = document.querySelector('[name="ticket-context"]');

      if (!subject || !message) {
        showFlash("Please fill in the subject and details.", "error");
        return;
      }

      submitBtn.disabled = true;
      apiPost("/support-tickets", {
        subject: subject,
        category: category,
        message: message,
        include_context: context ? context.checked : false,
        page_url: window.location.href,
        user_agent: navigator.userAgent
      }).then(function (res) {
        submitBtn.disabled = false;
        if (res._ok) {
          showFlash(res.message || "Ticket submitted.");
          closeModal(modal);
          document.getElementById("ticket-subject").value = "";
          document.getElementById("ticket-message").value = "";
        } else {
          showFlash(res.message || "Could not submit ticket.", "error");
        }
      }).catch(function () {
        submitBtn.disabled = false;
        showFlash("Network error.", "error");
      });
    });
  }

  function initNotificationsLoad() {
    var modal = document.getElementById("modal-notifications");
    if (!modal) return;
    var list = modal.querySelector(".modal-notif-list");
    if (!list) return;
    var loaded = false;

    var observer = new MutationObserver(function () {
      if (modal.classList.contains("is-open") && !loaded) {
        loaded = true;
        apiGet("/notifications").then(function (res) {
          if (!res._ok || !res.notifications || !res.notifications.length) return;
          list.innerHTML = "";
          res.notifications.forEach(function (n) {
            var li = document.createElement("li");
            li.className = "modal-notif-item" + (n.read_at ? "" : " modal-notif-item--unread");
            li.innerHTML =
              '<span class="modal-notif-item__icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>' +
              '<div class="modal-notif-item__body"><strong>' + (n.title || "Notification") + '</strong>' +
              '<span>' + (n.body || "") + '</span></div>';
            list.appendChild(li);
          });
          apiPost("/notifications/read");
        });
      }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ["class"] });
  }

  function initNavPrefetch() {
    var conn = global.navigator.connection || global.navigator.mozConnection || global.navigator.webkitConnection;
    if (
      conn &&
      (conn.saveData === true ||
        conn.effectiveType === "slow-2g" ||
        conn.effectiveType === "2g" ||
        conn.effectiveType === "3g")
    ) {
      return;
    }

    var done = {};
    var timers = {};
    var delayMs = 320;

    document.querySelectorAll(".app-sidebar a[href]").forEach(function (a) {
      var href = a.getAttribute("href");
      if (!href || href.charAt(0) !== "/" || href.charAt(1) === "/") return;

      a.addEventListener(
        "pointerenter",
        function () {
          if (done[href]) return;
          global.clearTimeout(timers[href]);
          timers[href] = global.setTimeout(function () {
            if (done[href]) return;
            done[href] = true;
            var link = document.createElement("link");
            link.rel = "prefetch";
            link.href = href;
            document.head.appendChild(link);
          }, delayMs);
        },
        { passive: true }
      );

      a.addEventListener(
        "pointerleave",
        function () {
          global.clearTimeout(timers[href]);
        },
        { passive: true }
      );
    });
  }

  function initNavPreloader() {
    var preloader = document.getElementById("app-nav-preloader");
    var html = document.documentElement;

    function setPreloaderVisible(visible) {
      if (preloader) {
        preloader.setAttribute("aria-hidden", visible ? "false" : "true");
      }
    }

    function hideNavLoading() {
      html.classList.remove("app-nav-loading");
      setPreloaderVisible(false);
      try {
        global.sessionStorage.removeItem("appNavPending");
      } catch (e) {}
    }

    function showNavLoading() {
      html.classList.add("app-nav-loading");
      setPreloaderVisible(true);
      try {
        global.sessionStorage.setItem("appNavPending", "1");
      } catch (e) {}
    }

    var hadPending = false;
    try {
      hadPending = !!global.sessionStorage.getItem("appNavPending");
    } catch (e) {}

    if (hadPending) {
      setPreloaderVisible(true);
    }

    global.addEventListener(
      "pageshow",
      function (e) {
        if (e.persisted) {
          hideNavLoading();
        }
      },
      false
    );

    document.addEventListener(
      "click",
      function (e) {
        var a = e.target.closest("a[href]");
        if (!a) return;
        if (e.defaultPrevented) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.getAttribute("data-no-nav-loader") === "true") return;
        if (a.target === "_blank" || a.hasAttribute("download")) return;
        var href = a.getAttribute("href");
        if (!href || href.indexOf("javascript:") === 0) return;
        if (href.charAt(0) === "#") return;

        var url;
        try {
          url = new URL(href, global.location.href);
        } catch (err) {
          return;
        }

        if (url.origin !== global.location.origin) return;

        if (
          url.pathname === global.location.pathname &&
          url.search === global.location.search &&
          url.hash === global.location.hash
        ) {
          return;
        }

        showNavLoading();
      },
      true
    );

    if (hadPending) {
      global.requestAnimationFrame(function () {
        setTimeout(function () {
          hideNavLoading();
        }, 72);
      });
    }
  }

  function initPlansServerSync() {
    var root = document.querySelector("[data-app-plans]");
    if (!root || root.getAttribute("data-app-plans-server") !== "1") return;

    var paynowOn = root.getAttribute("data-paynow-checkout") === "1";
    var paidSlugs = [];
    try {
      if (global.__paidPlanSlugs && Array.isArray(global.__paidPlanSlugs)) {
        paidSlugs = global.__paidPlanSlugs;
      }
    } catch (err) {}

    function slugNeedsPaynow(slug) {
      return paidSlugs.indexOf(slug) !== -1;
    }

    root.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-plan-select]");
      if (!btn || btn.disabled) return;
      var card = btn.closest("[data-plan-id]");
      if (!card) return;
      var planSlug = card.getAttribute("data-plan-id");
      if (!planSlug || planSlug === "enterprise") return;

      var status = document.querySelector("[data-app-plan-status]");

      if (paynowOn && slugNeedsPaynow(planSlug)) {
        apiPost("/plans/paynow/start", { plan_slug: planSlug }).then(function (res) {
          if (res.success && res.redirect_url) {
            global.location.href = res.redirect_url;
            return;
          }
          if (status) status.textContent = res.message || "Could not start checkout.";
          if (!res.success && global.App && global.App.showFlash) {
            global.App.showFlash(res.message || "Checkout failed.", "error");
          }
        }).catch(function () {
          if (status) status.textContent = "Network error starting checkout.";
          if (global.App && global.App.showFlash) global.App.showFlash("Network error.", "error");
        });
        return;
      }

      apiPost("/plans/change", { plan_slug: planSlug }).then(function (res) {
        if (res._ok) {
          if (status) status.textContent = res.message || "Plan updated.";
          root.setAttribute("data-current-plan-slug", planSlug);
          renderPlansFromServer(root);
          if (global.App && global.App.showFlash) global.App.showFlash(res.message || "Plan updated.");
        } else {
          if (status) status.textContent = res.message || "Plan could not be updated.";
          if (res.checkout_required && global.App && global.App.showFlash) {
            global.App.showFlash(res.message || "Complete payment to choose this plan.", "error");
          }
        }
      });
    });
  }

  var App = {
    init: function () {
      initNavPreloader();
      initNavPrefetch();
      initTopbarDate();
      initTheme();
      initDrawer();
      initModals();
      initConnectAccountModal();
      initStoreSwitcher();
      initSidebarCollapse();
      initLogout();
      initNavAriaCurrent();
      initDisplayTimezone();
      initTopbarAccountMenu();
      initEscapeKey();
      initSwitches();
      initSearchShortcut();
      initSidebarNavSearch();
      initRadioGroups();
      initTabLists();
      initPlansPage();
      initPlanHistoryPage();
      initSettingsAi();
      initProfilePage();
      initSettingsPage();
      initHelpTicketSubmit();
      initNotificationsLoad();
      initPlansServerSync();
    },
    applyTheme: applyTheme,
    readStoredTheme: readStoredTheme,
    readAiConfig: readAiConfig,
    readPlanHistory: readPlanHistory,
    openModal: openModal,
    closeModal: closeModal,
    apiPost: apiPost,
    apiGet: apiGet,
    apiUpload: apiUpload,
    showFlash: showFlash,
    getCsrfToken: getCsrfToken
  };

  global.App = App;

  document.addEventListener("DOMContentLoaded", function () {
    App.init();
  });
})(typeof window !== "undefined" ? window : this);
