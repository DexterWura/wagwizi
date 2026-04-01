(function (global) {
  "use strict";

  function insertAtCursor(textarea, text) {
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var val = textarea.value || "";
    textarea.value = val.slice(0, start) + text + val.slice(end);
    var pos = start + text.length;
    textarea.selectionStart = pos;
    textarea.selectionEnd = pos;
    textarea.focus();
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
  }

  function getSelectedAccountIds() {
    var ids = [];
    document.querySelectorAll('[name="platform_accounts[]"]:checked').forEach(function (cb) {
      ids.push(parseInt(cb.value, 10));
    });
    return ids;
  }

  function collectPlatformContentByAccount() {
    var overrides = global.__composerPlatformOverrides || {};
    var contentMap = {};

    document.querySelectorAll('[name="platform_accounts[]"]:checked').forEach(function (cb) {
      var accountId = parseInt(cb.value, 10);
      var platform = cb.getAttribute("data-platform");
      if (!accountId || !platform) return;
      var text = (overrides[platform] || "").trim();
      if (text) contentMap[accountId] = text;
    });

    return contentMap;
  }

  function buildCommentDelayMinutesPayload() {
    var firstCommentEl = document.getElementById("composer-first-comment");
    var firstComment = firstCommentEl ? (firstCommentEl.value || "").trim() : "";

    if (!firstComment) return {};

    var payload = { first_comment: firstComment };
    var valueEl = document.getElementById("composer-comment-delay-value");
    var unitEl = document.getElementById("composer-comment-delay-unit");
    var delayValue = valueEl ? parseInt(valueEl.value || "", 10) : NaN;
    var delayUnit = unitEl ? unitEl.value : "";

    if (!isNaN(delayValue) && delayValue > 0 && (delayUnit === "minutes" || delayUnit === "hours")) {
      payload.comment_delay_value = delayValue;
      payload.comment_delay_unit = delayUnit;
    }

    return payload;
  }

  function initComposerPreviewSync() {
    var master = document.getElementById("composer-master");
    if (!master) return;
    var targets = document.querySelectorAll("[data-app-composer-preview]");
    var live = document.querySelector("[data-app-composer-feed-live]");
    var diffWrap = document.querySelector("[data-app-composer-feed-diff]");
    var delEl = diffWrap ? diffWrap.querySelector(".diff-inline-del") : null;
    var insEl = diffWrap ? diffWrap.querySelector(".diff-inline-ins") : null;
    var fallback = "Your post will appear here.";
    var raf = null;
    function clearFeedDiff() {
      if (!diffWrap || !live || diffWrap.hasAttribute("hidden")) return;
      diffWrap.hidden = true;
      diffWrap.setAttribute("hidden", "");
      live.hidden = false;
      if (delEl) delEl.textContent = "";
      if (insEl) insEl.textContent = "";
    }
    function flush() {
      raf = null;
      clearFeedDiff();
      var text = (master.value || "").trim();
      if (!text) text = fallback;
      targets.forEach(function (el) {
        el.textContent = text;
      });
      if (live) live.textContent = text;
    }
    function onInput() {
      if (raf) return;
      raf = global.requestAnimationFrame(flush);
    }
    master.addEventListener("input", onInput);
    flush();
  }

  function showComposerFeedDiff(oldText, newText) {
    var diffWrap = document.querySelector("[data-app-composer-feed-diff]");
    var live = document.querySelector("[data-app-composer-feed-live]");
    var delEl = diffWrap && diffWrap.querySelector(".diff-inline-del");
    var insEl = diffWrap && diffWrap.querySelector(".diff-inline-ins");
    if (!diffWrap || !live || !delEl || !insEl) return;
    delEl.textContent = oldText || "";
    insEl.textContent = newText || "";
    live.hidden = true;
    diffWrap.hidden = false;
    diffWrap.removeAttribute("hidden");
  }

  function initComposerAiDock() {
    var dock = document.querySelector("[data-app-composer-ai-dock]");
    var btn = document.querySelector("[data-app-composer-ai-focus]");
    var closeBtn = document.querySelector("[data-app-composer-ai-close]");
    var aiInput = document.getElementById("composer-ai-input");
    if (!dock || !btn) return;

    function setOpen(open) {
      dock.hidden = !open;
      dock.setAttribute("aria-hidden", open ? "false" : "true");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      if (open && aiInput) {
        global.setTimeout(function () {
          aiInput.focus();
        }, 16);
      }
    }

    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      setOpen(dock.hidden);
    });
    if (closeBtn) {
      closeBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        setOpen(false);
      });
    }
    document.addEventListener("click", function (e) {
      if (dock.hidden) return;
      if (dock.contains(e.target) || btn.contains(e.target)) return;
      setOpen(false);
    });
    document.addEventListener(
      "keydown",
      function (e) {
        if (e.key !== "Escape" || dock.hidden) return;
        e.preventDefault();
        e.stopPropagation();
        setOpen(false);
      },
      true
    );
  }

  function initComposerToolbar() {
    var master = document.getElementById("composer-master");
    var hashBtn = document.querySelector("[data-app-composer-hashtag]");
    var emojiBtn = document.querySelector("[data-app-composer-emoji]");

    if (hashBtn && master) {
      hashBtn.addEventListener("click", function () {
        insertAtCursor(master, " #");
      });
    }
    if (emojiBtn && master) {
      emojiBtn.addEventListener("click", function () {
        insertAtCursor(master, " ☕ ");
      });
    }
    initComposerAiDock();
  }

  function initComposerPlatformOverrides() {
    var tabsWrap = document.querySelector("[data-app-tabs]");
    var overrideEl = document.getElementById("composer-override");
    if (!tabsWrap || !overrideEl) return;

    var tabs = tabsWrap.querySelectorAll("[data-app-platform-tab]");
    if (!tabs.length) return;

    var active = "master";
    var overrides = {};
    global.__composerPlatformOverrides = overrides;

    function renderTabState() {
      tabs.forEach(function (tab) {
        var key = tab.getAttribute("data-app-platform-tab");
        var isActive = key === active;
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
      });
      overrideEl.value = active === "master" ? "" : (overrides[active] || "");
      overrideEl.placeholder = active === "master"
        ? "Select a platform tab to add an override…"
        : "Write override for " + active + "…";
      overrideEl.disabled = active === "master";
    }

    function persistCurrent() {
      if (active === "master") return;
      var text = (overrideEl.value || "").trim();
      overrides[active] = text;
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        persistCurrent();
        active = tab.getAttribute("data-app-platform-tab") || "master";
        renderTabState();
      });
    });

    overrideEl.addEventListener("input", function () {
      if (active === "master") return;
      overrides[active] = overrideEl.value || "";
    });

    renderTabState();
  }

  function initComposerAiMock() {
    var form = document.querySelector("[data-app-composer-ai]");
    if (!form) return;
    var input = form.querySelector("input[type='text'], textarea");
    var messages = document.getElementById("composer-ai-messages");
    var master = document.getElementById("composer-master");
    if (!input || !messages) return;
    var suggestedCopy =
      "One composer. Every channel. Clear previews — calmer social planning.";
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var text = (input.value || "").trim();
      if (!text) return;
      var draftBefore = master ? (master.value || "").trim() : "";
      appendMsg(messages, text, "user");
      input.value = "";
      global.setTimeout(function () {
        appendMsg(
          messages,
          "Suggested revision is in the feed preview. Edit the master draft to dismiss it.",
          "assistant"
        );
        showComposerFeedDiff(draftBefore, suggestedCopy);
      }, 400);
    });
  }

  function appendMsg(container, text, role) {
    var div = document.createElement("div");
    div.className = "ai-msg ai-msg--" + role;
    div.textContent = text;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
  }

  function initComposerUpload() {
    var uploadBtn = document.querySelector("[data-app-composer-upload]");
    var fileInput = document.getElementById("composer-media-input");
    if (!uploadBtn || !fileInput) return;

    uploadBtn.addEventListener("click", function () {
      fileInput.click();
    });

    fileInput.addEventListener("change", function () {
      if (!fileInput.files || !fileInput.files[0]) return;
      var fd = new FormData();
      fd.append("file", fileInput.files[0]);
      if (global.App && global.App.apiUpload) {
        uploadBtn.classList.add("is-loading");
        global.App.apiUpload("/media", fd).then(function (res) {
          uploadBtn.classList.remove("is-loading");
          if (res._ok) {
            global.App.showFlash("Media uploaded.");
          } else {
            global.App.showFlash(res.message || "Upload failed.", "error");
          }
        }).catch(function () {
          uploadBtn.classList.remove("is-loading");
          global.App.showFlash("Upload failed.", "error");
        });
      }
      fileInput.value = "";
    });
  }

  function initComposerActions() {
    var modal = document.getElementById("modal-composer-feedback");
    if (!modal || !global.App) return;
    var titleEl = modal.querySelector("[data-feedback-title]");
    var descEl = modal.querySelector("[data-feedback-desc]");
    var iconEl = modal.querySelector("[data-feedback-icon]");
    var calLink = modal.querySelector("[data-feedback-calendar-link]");

    document.querySelectorAll("[data-app-composer-action]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var action = btn.getAttribute("data-app-composer-action");
        var content = (document.getElementById("composer-master").value || "").trim();
        var accounts = getSelectedAccountIds();
        var platformContent = collectPlatformContentByAccount();
        var commentPayload = buildCommentDelayMinutesPayload();

        if (!content) {
          global.App.showFlash("Write some content first.", "error");
          return;
        }

        btn.disabled = true;

        if (action === "draft") {
          var draftPayload = {
            content: content,
            platform_accounts: accounts,
            platform_content: platformContent
          };
          Object.assign(draftPayload, commentPayload);
          global.App.apiPost("/api/v1/posts", draftPayload).then(function (res) {
            btn.disabled = false;
            if (titleEl) titleEl.textContent = "Draft saved";
            if (descEl) descEl.textContent = res._ok
              ? "Draft #" + (res.post ? res.post.id : "") + " saved to your account."
              : (res.error || "Could not save draft.");
            if (iconEl) iconEl.className = "fa-solid fa-floppy-disk";
            if (calLink) calLink.setAttribute("hidden", "");
            global.App.openModal("modal-composer-feedback");
          }).catch(function () {
            btn.disabled = false;
            global.App.showFlash("Network error.", "error");
          });
          return;
        }

        if (action === "publish") {
          var publishPayload = {
            content: content,
            platform_accounts: accounts,
            platform_content: platformContent
          };
          Object.assign(publishPayload, commentPayload);
          global.App.apiPost("/api/v1/posts", publishPayload).then(function (res) {
            if (!res._ok || !res.post) {
              btn.disabled = false;
              global.App.showFlash(res.error || "Could not create post.", "error");
              return;
            }
            var postId = res.post.id;
            return global.App.apiPost("/api/v1/posts/" + postId + "/publish").then(function (pubRes) {
              btn.disabled = false;
              if (titleEl) titleEl.textContent = pubRes._ok ? "Publishing" : "Publish failed";
              if (descEl) descEl.textContent = pubRes._ok
                ? (pubRes.message || "Your post is being published to all selected networks.")
                : (pubRes.error || "Publish failed.");
              if (iconEl) iconEl.className = "fa-solid fa-paper-plane";
              if (calLink) calLink.setAttribute("hidden", "");
              global.App.openModal("modal-composer-feedback");
            });
          }).catch(function () {
            btn.disabled = false;
            global.App.showFlash("Network error.", "error");
          });
          return;
        }

        if (action === "schedule") {
          var dateVal = document.getElementById("composer-date") ? document.getElementById("composer-date").value : "";
          var timeVal = document.getElementById("composer-time") ? document.getElementById("composer-time").value : "";
          var delayValueEl = document.getElementById("composer-delay-value");
          var delayUnitEl = document.getElementById("composer-delay-unit");
          var delayValue = delayValueEl ? parseInt(delayValueEl.value || "", 10) : NaN;
          var delayUnit = delayUnitEl ? delayUnitEl.value : "";

          var useDelay = !isNaN(delayValue) && delayValue > 0 && (delayUnit === "minutes" || delayUnit === "hours");

          if ((!dateVal || !timeVal) && !useDelay) {
            btn.disabled = false;
            global.App.showFlash("Pick date/time or set a delay in minutes/hours.", "error");
            return;
          }

          var scheduledAt = dateVal && timeVal ? (dateVal + "T" + timeVal + ":00") : null;

          var schedulePayload = {
            content: content,
            platform_accounts: accounts,
            platform_content: platformContent
          };
          Object.assign(schedulePayload, commentPayload);
          if (scheduledAt) schedulePayload.scheduled_at = scheduledAt;
          if (useDelay) {
            schedulePayload.delay_value = delayValue;
            schedulePayload.delay_unit = delayUnit;
          }

          global.App.apiPost("/api/v1/posts/schedule", schedulePayload).then(function (schedRes) {
            btn.disabled = false;
            if (titleEl) titleEl.textContent = schedRes._ok ? "Scheduled" : "Schedule failed";
            if (descEl) descEl.textContent = schedRes._ok
              ? (useDelay
                ? ("Your post is scheduled in " + delayValue + " " + delayUnit + ".")
                : ("Your post is scheduled for " + dateVal + " at " + timeVal + "."))
              : (schedRes.error || "Could not schedule.");
            if (iconEl) iconEl.className = "fa-solid fa-calendar-days";
            if (calLink) {
              if (schedRes._ok) calLink.removeAttribute("hidden");
              else calLink.setAttribute("hidden", "");
            }
            global.App.openModal("modal-composer-feedback");
          }).catch(function () {
            btn.disabled = false;
            global.App.showFlash("Network error.", "error");
          });
        }
      });
    });
  }

  function initCalendarDrag() {
    var root = document.querySelector("[data-app-calendar]");
    if (!root) return;

    root.querySelectorAll(".calendar-post-pill").forEach(function (pill) {
      pill.setAttribute("draggable", "true");
      pill.addEventListener("dragstart", function (e) {
        var id = pill.getAttribute("data-post-id");
        if (id) e.dataTransfer.setData("text/plain", id);
        e.dataTransfer.effectAllowed = "move";
      });
    });

    var dropTargets = root.querySelectorAll(".calendar-cell[data-calendar-day], .calendar-queue__list");
    dropTargets.forEach(function (cell) {
      cell.addEventListener("dragover", function (e) {
        e.preventDefault();
        cell.classList.add("calendar-cell--drop");
      });
      cell.addEventListener("dragleave", function () {
        cell.classList.remove("calendar-cell--drop");
      });
      cell.addEventListener("drop", function (e) {
        e.preventDefault();
        cell.classList.remove("calendar-cell--drop");
        var id = e.dataTransfer.getData("text/plain");
        if (!id) return;
        var pill = root.querySelector('.calendar-post-pill[data-post-id="' + id.replace(/"/g, "") + '"]');
        if (!pill) return;
        cell.appendChild(pill);
        var hint = pill.querySelector(".calendar-post-pill__when");
        var day = cell.getAttribute("data-calendar-day");
        if (hint && day) {
          if (day === "Queue") {
            hint.textContent = "— draft";
          } else {
            hint.textContent = "— " + day;
          }
        }

        if (global.App && global.App.apiPost) {
          var scheduledAt = null;
          if (day && day !== "Queue") {
            var parsed = new Date(day + " 09:00:00");
            if (!isNaN(parsed.getTime())) {
              scheduledAt = parsed.toISOString().slice(0, 19).replace("T", "T");
            }
          }

          global.App.apiPost("/api/v1/posts/" + id + "/reschedule", {
            scheduled_at: scheduledAt,
            _method: "PATCH"
          }).then(function (res) {
            if (!res._ok) {
              global.App.showFlash(res.error || "Could not reschedule.", "error");
            }
          }).catch(function () {});
        }
      });
    });
  }

  function initMediaLibraryFilter() {
    var root = document.querySelector("[data-app-media-library]");
    if (!root) return;
    var buttons = root.querySelectorAll("[data-media-filter]");
    var grid = document.querySelector(".media-lib-grid");
    if (!grid) return;
    var items = grid.querySelectorAll("[data-media-type]");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var f = btn.getAttribute("data-media-filter");
        buttons.forEach(function (b) {
          b.setAttribute("aria-selected", b === btn ? "true" : "false");
        });
        items.forEach(function (el) {
          var t = el.getAttribute("data-media-type");
          var show = f === "all" || f === t;
          if (show) el.removeAttribute("hidden");
          else el.setAttribute("hidden", "");
        });
      });
    });
  }

  function initComposerAiSourceHint() {
    var el = document.querySelector("[data-app-composer-ai-source-hint]");
    if (!el || !global.App || typeof global.App.readAiConfig !== "function") return;
    var cfg = global.App.readAiConfig();
    el.textContent = cfg.source === "byok" ? "— Your API key" : "— PostAI platform";
  }

  function initComposerAudienceSlots() {
    var root = document.querySelector("[data-app-audience-hint]");
    if (!root) return;
    root.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-app-apply-slot]");
      if (!btn) return;
      var hour = parseInt(btn.getAttribute("data-app-apply-slot"), 10);
      if (isNaN(hour)) return;
      var timeInput = document.getElementById("composer-time");
      if (!timeInput) return;
      var h = ((hour % 24) + 24) % 24;
      timeInput.value = (h < 10 ? "0" : "") + h + ":00";
      timeInput.dispatchEvent(new Event("change", { bubbles: true }));
      timeInput.focus();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initComposerPreviewSync();
    initComposerToolbar();
    initComposerAiMock();
    initComposerAiSourceHint();
    initComposerPlatformOverrides();
    initComposerAudienceSlots();
    initComposerActions();
    initComposerUpload();
    initMediaLibraryFilter();
    initCalendarDrag();
  });
})(typeof window !== "undefined" ? window : this);
