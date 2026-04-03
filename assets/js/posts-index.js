(function (global) {
  "use strict";

  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function statusLabel(status) {
    if (!status) return "—";
    return String(status).charAt(0).toUpperCase() + String(status).slice(1);
  }

  function statusClass(status) {
    var s = String(status || "").toLowerCase();
    if (s === "draft") return "posts-index-badge--draft";
    if (s === "scheduled") return "posts-index-badge--scheduled";
    if (s === "published") return "posts-index-badge--published";
    if (s === "failed") return "posts-index-badge--failed";
    if (s === "queued" || s === "publishing") return "posts-index-badge--pending";
    return "posts-index-badge--muted";
  }

  function excerpt(text, max) {
    var t = (text || "").replace(/\s+/g, " ").trim();
    if (t.length <= max) return t;
    return t.slice(0, max - 1) + "…";
  }

  function formatWhen(iso) {
    if (!iso) return "—";
    var d = new Date(iso);
    if (isNaN(d.getTime())) return "—";
    return d.toLocaleString(undefined, {
      dateStyle: "medium",
      timeStyle: "short"
    });
  }

  function composerUrlForPost(post) {
    return "/composer?draft=" + encodeURIComponent(String(post.id));
  }

  function canContinueInComposer(post) {
    var s = String(post.status || "").toLowerCase();
    return s === "draft" || s === "scheduled" || s === "failed" || s === "publishing";
  }

  function buildQuery(state) {
    var q = new URLSearchParams();
    q.set("page", String(state.page));
    q.set("per_page", "15");
    if (state.q) q.set("q", state.q);
    if (state.status && state.status !== "all") q.set("status", state.status);
    if (state.sort === "scheduled") q.set("sort", "scheduled");
    return q.toString();
  }

  function renderList(root, items) {
    var ul = root.querySelector("[data-posts-index-list]");
    if (!ul) return;
    ul.innerHTML = "";
    items.forEach(function (post) {
      var li = document.createElement("li");
      li.className = "posts-index-item";

      var platforms = post.post_platforms || [];
      var platBits = platforms
        .map(function (p) {
          return p.platform;
        })
        .filter(Boolean);
      var platStr = platBits.length ? platBits.slice(0, 6).join(", ") + (platBits.length > 6 ? "…" : "") : "—";

      var actions = "";
      if (canContinueInComposer(post)) {
        actions +=
          '<a class="btn btn--primary btn--sm" href="' +
          esc(composerUrlForPost(post)) +
          '">Continue in composer</a>';
      } else {
        actions += '<span class="prose-muted posts-index-item__na">—</span>';
      }

      li.innerHTML =
        '<div class="posts-index-item__main">' +
        '<span class="posts-index-badge ' +
        statusClass(post.status) +
        '">' +
        esc(statusLabel(post.status)) +
        "</span>" +
        '<p class="posts-index-item__excerpt">' +
        esc(excerpt(post.content, 220)) +
        "</p>" +
        '<div class="posts-index-item__meta">' +
        '<span>Platforms: <strong>' +
        esc(platStr) +
        "</strong></span>" +
        '<span>Created: <strong>' +
        esc(formatWhen(post.created_at)) +
        "</strong></span>" +
        (post.scheduled_at
          ? '<span>Scheduled: <strong>' + esc(formatWhen(post.scheduled_at)) + "</strong></span>"
          : "") +
        "</div></div>" +
        '<div class="posts-index-item__actions">' +
        actions +
        "</div>";

      ul.appendChild(li);
    });
  }

  function renderPagination(nav, payload, state, onPage) {
    nav.innerHTML = "";
    var cur = payload.current_page || 1;
    var last = payload.last_page || 1;
    if (last <= 1) {
      nav.hidden = true;
      nav.setAttribute("hidden", "");
      return;
    }
    nav.hidden = false;
    nav.removeAttribute("hidden");

    function addBtn(label, page, disabled) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "btn btn--ghost btn--sm";
      b.textContent = label;
      b.disabled = !!disabled;
      if (!disabled) {
        b.addEventListener("click", function () {
          onPage(page);
        });
      }
      nav.appendChild(b);
    }

    addBtn("Previous", cur - 1, cur <= 1);
    var span = document.createElement("span");
    span.className = "posts-index-pagination__status prose-muted";
    span.textContent = "Page " + cur + " of " + last;
    nav.appendChild(span);
    addBtn("Next", cur + 1, cur >= last);
  }

  function initPostsIndex() {
    var root = document.querySelector("[data-posts-index-root]");
    if (!root || !global.App || typeof global.App.apiGet !== "function") return;

    var qEl = document.querySelector("[data-posts-index-q]");
    var statusEl = document.querySelector("[data-posts-index-status]");
    var sortEl = document.querySelector("[data-posts-index-sort]");
    var loadingEl = root.querySelector("[data-posts-index-loading]");
    var emptyEl = root.querySelector("[data-posts-index-empty]");
    var listEl = root.querySelector("[data-posts-index-list]");
    var pagEl = root.querySelector("[data-posts-index-pagination]");
    var countEl = root.querySelector("[data-posts-index-count]");

    var state = { page: 1, q: "", status: "all", sort: "newest" };
    var debounceTimer = null;

    function currentEmptyLabel() {
      var filtered = !!(state.q || (state.status && state.status !== "all") || state.sort === "scheduled");
      return filtered ? "No posts match your filters." : "No posts yet.";
    }

    function setLoading(on) {
      if (loadingEl) loadingEl.hidden = !on;
    }

    function fetchPage() {
      setLoading(true);
      if (emptyEl) emptyEl.hidden = true;
      if (listEl) {
        listEl.hidden = true;
        listEl.setAttribute("hidden", "");
      }
      if (pagEl) {
        pagEl.hidden = true;
        pagEl.setAttribute("hidden", "");
      }

      var qs = buildQuery(state);
      global.App.apiGet("/api/v1/posts?" + qs).then(function (res) {
        setLoading(false);
        if (!res._ok || !res.posts) {
          if (global.App.showFlash) global.App.showFlash(res.error || "Could not load posts.", "error");
          return;
        }
        var p = res.posts;
        var rows = p.data || [];
        var total = p.total != null ? p.total : rows.length;

        if (countEl) {
          countEl.textContent = total + " total";
          countEl.hidden = false;
          countEl.removeAttribute("hidden");
        }

        if (!rows.length) {
          if (emptyEl) {
            emptyEl.textContent = currentEmptyLabel();
            emptyEl.hidden = false;
            emptyEl.removeAttribute("hidden");
          }
          return;
        }

        if (listEl) {
          renderList(root, rows);
          listEl.hidden = false;
          listEl.removeAttribute("hidden");
        }
        if (pagEl) {
          renderPagination(pagEl, p, state, function (page) {
            state.page = page;
            fetchPage();
          });
        }
      }).catch(function () {
        setLoading(false);
        if (global.App.showFlash) global.App.showFlash("Could not load posts.", "error");
      });
    }

    function scheduleFetch() {
      state.page = 1;
      if (debounceTimer) global.clearTimeout(debounceTimer);
      debounceTimer = global.setTimeout(fetchPage, qEl === document.activeElement ? 280 : 0);
    }

    if (qEl) {
      qEl.addEventListener("input", function () {
        state.q = (qEl.value || "").trim();
        scheduleFetch();
      });
    }
    if (statusEl) {
      statusEl.addEventListener("change", function () {
        state.status = statusEl.value || "all";
        scheduleFetch();
      });
    }
    if (sortEl) {
      sortEl.addEventListener("change", function () {
        state.sort = sortEl.value || "newest";
        scheduleFetch();
      });
    }

    fetchPage();
  }

  document.addEventListener("DOMContentLoaded", initPostsIndex);
})(typeof window !== "undefined" ? window : this);
