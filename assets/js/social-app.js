(function (global) {
  "use strict";

  var composerConstraintProbeToken = 0;

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

  function composerAssetUrl(path) {
    if (!path) return "";
    var p = String(path).replace(/^\//, "");
    return "/" + p;
  }

  function getComposerMediaMode() {
    var sel = document.getElementById("composer-media-type");
    return sel && sel.value ? sel.value : "image";
  }

  function getPlatformMediaCaps() {
    var c = global.__composerPlatformMediaCaps;
    return c && typeof c === "object" ? c : {};
  }

  /** @returns {'video'|'carousel'|'image'|null} */
  function effectivePreviewKind(mode, item) {
    if (!item || mode === "none") return null;
    if (item.type === "video" || mode === "video") return "video";
    if (mode === "carousel") return "carousel";
    return "image";
  }

  function platformShowsMediaKind(platformSlug, kind) {
    if (!kind) return false;
    var caps = getPlatformMediaCaps()[platformSlug] || {};
    if (kind === "video") return !!caps.supports_video;
    if (kind === "carousel") return !!(caps.supports_carousel && caps.supports_images);
    return !!caps.supports_images;
  }

  function anyCheckedPlatformSupports(kind) {
    if (!kind) return false;
    var seen = {};
    var checks = document.querySelectorAll('[name="platform_accounts[]"]:checked');
    if (!checks.length) return true;
    var any = false;
    checks.forEach(function (cb) {
      var p = cb.getAttribute("data-platform");
      if (!p || seen[p]) return;
      seen[p] = true;
      if (platformShowsMediaKind(p, kind)) any = true;
    });
    return any;
  }

  function getPlatformMediaRules() {
    var r = global.__composerPlatformMediaRules;
    return r && typeof r === "object" ? r : {};
  }

  function composerPlatformLabel(slug) {
    var lab = global.__composerPlatformLabels && global.__composerPlatformLabels[slug];
    return lab || slug;
  }

  /** @returns {'video'|'image'|null} */
  function rulesKindForComposer(mode, item) {
    if (!item || mode === "none") return null;
    if (item.type === "video") return "video";
    if (item.type === "image") return "image";
    if (mode === "video") return "video";
    return "image";
  }

  function mediaEdges(w, h) {
    var W = Number(w) || 0;
    var H = Number(h) || 0;
    return {
      w: W,
      h: H,
      long: Math.max(W, H),
      short: Math.min(W, H),
      ratio: H > 0 ? W / H : 0
    };
  }

  function constraintMessagesForBlock(block, probe, rulesKind) {
    var msgs = [];
    if (!block || !probe || probe.error) return msgs;
    var e = mediaEdges(probe.width, probe.height);
    if (!e.w || !e.h) return msgs;

    function num(key) {
      var v = block[key];
      return typeof v === "number" && !isNaN(v) ? v : null;
    }

    var x;
    if ((x = num("min_width")) != null && e.w < x) {
      msgs.push("width is " + e.w + "px (min " + x + "px)");
    }
    if ((x = num("max_width")) != null && e.w > x) {
      msgs.push("width is " + e.w + "px (max " + x + "px)");
    }
    if ((x = num("min_height")) != null && e.h < x) {
      msgs.push("height is " + e.h + "px (min " + x + "px)");
    }
    if ((x = num("max_height")) != null && e.h > x) {
      msgs.push("height is " + e.h + "px (max " + x + "px)");
    }
    if ((x = num("max_long_edge")) != null && e.long > x) {
      msgs.push("longest side is " + e.long + "px (max " + x + "px)");
    }
    if ((x = num("min_short_edge")) != null && e.short < x) {
      msgs.push("shortest side is " + e.short + "px (min " + x + "px)");
    }
    if ((x = num("aspect_ratio_min")) != null && e.ratio > 0 && e.ratio < x) {
      msgs.push("aspect ratio width÷height is too tall/narrow for this network");
    }
    if ((x = num("aspect_ratio_max")) != null && e.ratio > 0 && e.ratio > x) {
      msgs.push("aspect ratio width÷height is too wide/short for this network");
    }
    if ((x = num("max_file_mb")) != null && probe.size_bytes != null) {
      var maxB = x * 1024 * 1024;
      if (probe.size_bytes > maxB) {
        msgs.push("file is about " + (probe.size_bytes / (1024 * 1024)).toFixed(1) + "MB (max " + x + "MB)");
      }
    }
    if (rulesKind === "video") {
      var dur = probe.duration;
      if (typeof dur === "number" && !isNaN(dur) && isFinite(dur)) {
        if ((x = num("max_duration_sec")) != null && dur > x) {
          msgs.push("duration is " + Math.round(dur) + "s (max " + x + "s)");
        }
        if ((x = num("min_duration_sec")) != null && dur < x) {
          msgs.push("duration is " + Math.round(dur) + "s (min " + x + "s)");
        }
      }
    }
    return msgs;
  }

  function getConstraintIssuesForPlatform(slug, rulesKind, probe) {
    var rules = getPlatformMediaRules()[slug];
    if (!rules) return [];
    var block = rules[rulesKind];
    return constraintMessagesForBlock(block, probe, rulesKind);
  }

  function getCheckedPlatformSlugs() {
    var out = [];
    var seen = {};
    document.querySelectorAll('[name="platform_accounts[]"]:checked').forEach(function (cb) {
      var p = cb.getAttribute("data-platform");
      if (!p || seen[p]) return;
      seen[p] = true;
      out.push(p);
    });
    return out;
  }

  function probeComposerMedia(url, rulesKind, sizeBytes) {
    return new Promise(function (resolve) {
      if (!url) {
        resolve({ error: true });
        return;
      }
      if (rulesKind === "video") {
        var v = document.createElement("video");
        v.preload = "metadata";
        v.muted = true;
        v.setAttribute("playsinline", "");
        var settled = false;
        function finish(payload) {
          if (settled) return;
          settled = true;
          v.removeAttribute("src");
          v.load();
          resolve(payload);
        }
        var to = global.setTimeout(function () {
          finish({ error: true });
        }, 25000);
        v.addEventListener("loadedmetadata", function () {
          global.clearTimeout(to);
          finish({
            width: v.videoWidth,
            height: v.videoHeight,
            duration: v.duration,
            size_bytes: sizeBytes != null ? sizeBytes : null,
            error: false
          });
        });
        v.addEventListener("error", function () {
          global.clearTimeout(to);
          finish({ error: true });
        });
        v.src = url;
      } else {
        var img = new Image();
        var to2 = global.setTimeout(function () {
          img.onload = null;
          img.onerror = null;
          resolve({ error: true });
        }, 25000);
        img.onload = function () {
          global.clearTimeout(to2);
          resolve({
            width: img.naturalWidth,
            height: img.naturalHeight,
            duration: null,
            size_bytes: sizeBytes != null ? sizeBytes : null,
            error: false
          });
        };
        img.onerror = function () {
          global.clearTimeout(to2);
          resolve({ error: true });
        };
        img.src = url;
      }
    });
  }

  function clearComposerMediaConstraintUI() {
    global.__composerMediaProbe = null;
    document.querySelectorAll("[data-app-composer-platform-warnings]").forEach(function (ul) {
      ul.innerHTML = "";
      ul.hidden = true;
      ul.setAttribute("hidden", "");
    });
    var alertEl = document.querySelector("[data-app-composer-media-alert]");
    if (alertEl) {
      alertEl.hidden = true;
      alertEl.setAttribute("hidden", "");
    }
    var intro = document.querySelector("[data-app-composer-media-alert-intro]");
    var list = document.querySelector("[data-app-composer-media-alert-list]");
    if (intro) intro.textContent = "";
    if (list) list.innerHTML = "";
  }

  function showComposerMediaConstraintReadError() {
    global.__composerMediaProbe = null;
    document.querySelectorAll("[data-app-composer-platform-warnings]").forEach(function (ul) {
      ul.innerHTML = "";
      ul.hidden = true;
      ul.setAttribute("hidden", "");
    });
    var alertEl = document.querySelector("[data-app-composer-media-alert]");
    var intro = document.querySelector("[data-app-composer-media-alert-intro]");
    var list = document.querySelector("[data-app-composer-media-alert-list]");
    if (alertEl && intro && list) {
      intro.textContent =
        "We could not read this file's dimensions. Try another file or confirm dimensions before publishing.";
      list.innerHTML = "";
      alertEl.hidden = false;
      alertEl.removeAttribute("hidden");
    }
  }

  function applyComposerMediaConstraintUI(probe, rulesKind) {
    var item = global.__composerSelectedMedia || null;
    var mode = getComposerMediaMode();
    var previewKind = effectivePreviewKind(mode, item);
    if (!previewKind) {
      clearComposerMediaConstraintUI();
      return;
    }

    global.__composerMediaProbe = probe;

    var checkedSlugs = getCheckedPlatformSlugs();
    var slugIssues = {};
    var slugOk = {};
    checkedSlugs.forEach(function (slug) {
      if (!platformShowsMediaKind(slug, previewKind)) return;
      var issues = getConstraintIssuesForPlatform(slug, rulesKind, probe);
      if (issues.length) slugIssues[slug] = issues;
      else slugOk[slug] = true;
    });

    document.querySelectorAll("[data-app-composer-platform-warnings]").forEach(function (ul) {
      var slug = ul.getAttribute("data-app-composer-platform-warnings") || "";
      ul.innerHTML = "";
      if (checkedSlugs.indexOf(slug) === -1) {
        ul.hidden = true;
        ul.setAttribute("hidden", "");
        return;
      }
      if (!platformShowsMediaKind(slug, previewKind)) {
        ul.hidden = true;
        ul.setAttribute("hidden", "");
        return;
      }
      var issues = slugIssues[slug];
      if (!issues || !issues.length) {
        ul.hidden = true;
        ul.setAttribute("hidden", "");
        return;
      }
      issues.forEach(function (msg) {
        var li = document.createElement("li");
        li.textContent = msg;
        ul.appendChild(li);
      });
      ul.hidden = false;
      ul.removeAttribute("hidden");
    });

    var alertEl = document.querySelector("[data-app-composer-media-alert]");
    var intro = document.querySelector("[data-app-composer-media-alert-intro]");
    var list = document.querySelector("[data-app-composer-media-alert-list]");
    if (!alertEl || !intro || !list) return;

    var badSlugs = Object.keys(slugIssues);
    var goodCount = 0;
    checkedSlugs.forEach(function (slug) {
      if (!platformShowsMediaKind(slug, previewKind)) return;
      if (slugOk[slug]) goodCount += 1;
    });

    if (!badSlugs.length) {
      alertEl.hidden = true;
      alertEl.setAttribute("hidden", "");
      intro.textContent = "";
      list.innerHTML = "";
      return;
    }

    list.innerHTML = "";
    badSlugs.forEach(function (slug) {
      var li = document.createElement("li");
      var label = composerPlatformLabel(slug);
      li.textContent = label + ": " + slugIssues[slug].join("; ");
      list.appendChild(li);
    });

    if (goodCount > 0) {
      intro.textContent =
        "This media may not meet the guidelines for some selected networks. Others look fine—change the file or deselect channels to avoid rejections.";
    } else {
      intro.textContent =
        "This media may not meet the guidelines for every selected network that accepts this type of post.";
    }
    alertEl.hidden = false;
    alertEl.removeAttribute("hidden");
  }

  function refreshComposerMediaConstraints() {
    var item = global.__composerSelectedMedia || null;
    var mode = getComposerMediaMode();
    var rulesKind = rulesKindForComposer(mode, item);
    if (!item || !rulesKind || mode === "none") {
      clearComposerMediaConstraintUI();
      return;
    }
    var previewKind = effectivePreviewKind(mode, item);
    if (!previewKind || !item.path) {
      clearComposerMediaConstraintUI();
      return;
    }
    composerConstraintProbeToken += 1;
    var token = composerConstraintProbeToken;
    clearComposerMediaConstraintUI();
    var url = composerAssetUrl(item.path);
    var sizeBytes = item.size_bytes != null ? item.size_bytes : null;
    probeComposerMedia(url, rulesKind, sizeBytes).then(function (probe) {
      if (token !== composerConstraintProbeToken) return;
      if (probe.error || !probe.width || !probe.height) {
        showComposerMediaConstraintReadError();
        return;
      }
      applyComposerMediaConstraintUI(probe, rulesKind);
    });
  }

  function shouldWarnComposerMediaConstraints() {
    var item = global.__composerSelectedMedia || null;
    var mode = getComposerMediaMode();
    var rulesKind = rulesKindForComposer(mode, item);
    var probe = global.__composerMediaProbe;
    if (!item || !rulesKind || mode === "none" || !probe || probe.error) return null;
    if (!probe.width || !probe.height) return null;
    var previewKind = effectivePreviewKind(mode, item);
    if (!previewKind) return null;
    var rows = [];
    getCheckedPlatformSlugs().forEach(function (slug) {
      if (!platformShowsMediaKind(slug, previewKind)) return;
      var issues = getConstraintIssuesForPlatform(slug, rulesKind, probe);
      if (issues.length) {
        rows.push({ label: composerPlatformLabel(slug), messages: issues });
      }
    });
    return rows.length ? rows : null;
  }

  function confirmComposerMediaConstraintsIfNeeded() {
    var rows = shouldWarnComposerMediaConstraints();
    if (!rows) return true;
    var body = rows
      .map(function (r) {
        return r.label + " — " + r.messages.join("; ");
      })
      .join("\n");
    return global.confirm(
      "Media may not meet requirements for one or more selected networks:\n\n" + body + "\n\nContinue anyway?"
    );
  }

  function renderMediaPreview(container, url, displayKind) {
    if (!container) return;
    container.innerHTML = "";
    if (!url || !displayKind) {
      container.hidden = true;
      return;
    }
    container.hidden = false;
    if (displayKind === "video") {
      var v = document.createElement("video");
      v.className = "composer-preview-video";
      v.src = url;
      v.controls = true;
      v.setAttribute("playsinline", "");
      v.setAttribute("preload", "metadata");
      container.appendChild(v);
    } else {
      var img = document.createElement("img");
      img.className = "composer-preview-img";
      img.src = url;
      img.alt = "";
      img.loading = "lazy";
      container.appendChild(img);
    }
  }

  function syncComposerPreviewMedia() {
    var item = global.__composerSelectedMedia || null;
    var mode = getComposerMediaMode();
    var kind = effectivePreviewKind(mode, item);
    var url = item && item.path ? composerAssetUrl(item.path) : "";
    var displayKind = kind === "video" ? "video" : kind ? "image" : null;

    var feedMedia = document.querySelector("[data-app-composer-feed-media]");
    if (feedMedia) {
      if (item && kind && anyCheckedPlatformSupports(kind)) {
        renderMediaPreview(feedMedia, url, displayKind);
      } else {
        renderMediaPreview(feedMedia, "", null);
      }
    }

    document.querySelectorAll("[data-app-composer-preview]").forEach(function (card) {
      var slug = card.getAttribute("data-platform") || "";
      var slot = card.querySelector("[data-app-composer-preview-media]");
      if (!slot) return;
      if (item && kind && platformShowsMediaKind(slug, kind)) {
        renderMediaPreview(slot, url, displayKind);
      } else {
        renderMediaPreview(slot, "", null);
      }
    });

    refreshComposerMediaConstraints();
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
    var targets = document.querySelectorAll("[data-app-composer-preview-text]");
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
      syncComposerPreviewMedia();
    }
    function onInput() {
      if (raf) return;
      raf = global.requestAnimationFrame(flush);
    }
    master.addEventListener("input", onInput);
    document.querySelectorAll('[name="platform_accounts[]"]').forEach(function (cb) {
      cb.addEventListener("change", onInput);
    });
    var mediaTypeSel = document.getElementById("composer-media-type");
    if (mediaTypeSel) {
      mediaTypeSel.addEventListener("change", onInput);
    }
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

    var aiLocked = dock.getAttribute("data-composer-ai-locked") === "1";

    function setOpen(open) {
      dock.hidden = !open;
      dock.setAttribute("aria-hidden", open ? "false" : "true");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      if (open && aiInput && !aiLocked && !aiInput.disabled) {
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

  function initComposerAi() {
    var form = document.querySelector("[data-app-composer-ai]");
    if (!form) return;
    var dock = document.querySelector("[data-app-composer-ai-dock]");
    if (dock && dock.getAttribute("data-composer-ai-locked") === "1") {
      return;
    }
    var input = form.querySelector("input[type='text'], textarea");
    var messages = document.getElementById("composer-ai-messages");
    var master = document.getElementById("composer-master");
    var submitBtn = form.querySelector("button[type='submit']");
    if (!input || !messages || !global.App || typeof global.App.apiPost !== "function") return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (input.disabled) return;
      var text = (input.value || "").trim();
      if (!text) return;
      var draftBefore = master ? (master.value || "").trim() : "";
      var draftCurrent = draftBefore;
      appendMsg(messages, text, "user");
      input.value = "";
      input.disabled = true;
      if (submitBtn) submitBtn.disabled = true;
      global.App.apiPost("/composer/ai", {
        message: text,
        draft: draftCurrent
      })
        .then(function (res) {
          if (!res._ok || !res.success) {
            var msg = res.message || "Assistant request failed.";
            if (
              res.error_code === "platform_ai_quota_exhausted" ||
              res.error_code === "platform_ai_plan_no_tokens"
            ) {
              msg =
                res.message ||
                "You have reached your platform AI limit. Wait until your plan renews or add your own API key under Settings → AI.";
            }
            appendMsg(messages, msg, "assistant");
            if (global.App.showFlash) global.App.showFlash(msg, "error");
            return;
          }
          var reply = (res.reply || "").trim();
          if (!reply) {
            appendMsg(messages, "No reply from the model.", "assistant");
            return;
          }
          appendMsg(messages, reply, "assistant");
          showComposerFeedDiff(draftBefore, reply);
        })
        .catch(function () {
          appendMsg(messages, "Network error. Try again.", "assistant");
          if (global.App.showFlash) global.App.showFlash("Network error.", "error");
        })
        .then(function () {
          input.disabled = false;
          if (submitBtn) submitBtn.disabled = false;
          input.focus();
        });
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
    var mediaTypeSel = document.getElementById("composer-media-type");
    var modal = document.getElementById("modal-composer-media");
    if (!uploadBtn || !fileInput || !global.App) return;

    var stepSource = modal ? modal.querySelector('[data-composer-media-step="source"]') : null;
    var stepLibrary = modal ? modal.querySelector('[data-composer-media-step="library"]') : null;
    var hintEl = modal ? modal.querySelector("[data-composer-media-type-hint]") : null;
    var gridEl = modal ? modal.querySelector("[data-composer-media-grid]") : null;
    var emptyEl = modal ? modal.querySelector("[data-composer-media-empty]") : null;
    var libraryLoadingEl = modal ? modal.querySelector("[data-composer-media-library-loading]") : null;

    var pendingListQs = "";

    function getComposerMediaCounts() {
      var c = global.__composerMediaCounts;
      if (c && typeof c.image === "number" && typeof c.video === "number") {
        return c;
      }
      return null;
    }

    function composerLibraryHasRelevantItems(mt, counts) {
      if (!counts) return null;
      if (mt === "image") return counts.image > 0;
      if (mt === "video") return counts.video > 0;
      if (mt === "carousel") return counts.image + counts.video > 0;
      return false;
    }

    function composerBumpMediaCountsAfterUpload(res) {
      if (!res || !res.media || res.media.type == null) return;
      var c = global.__composerMediaCounts;
      if (!c || typeof c.image !== "number" || typeof c.video !== "number") return;
      if (res.media.type === "video") {
        c.video += 1;
      } else if (res.media.type === "image") {
        c.image += 1;
      }
    }

    function getMediaType() {
      return mediaTypeSel && mediaTypeSel.value ? mediaTypeSel.value : "image";
    }

    function setFileAccept(mt) {
      if (mt === "image") fileInput.setAttribute("accept", "image/*");
      else if (mt === "video") fileInput.setAttribute("accept", "video/*");
      else if (mt === "none") fileInput.setAttribute("accept", "image/*,video/*");
      else fileInput.setAttribute("accept", "image/*,video/*");
    }

    function mediaListQuery(mt) {
      if (mt === "image") return "type=image";
      if (mt === "video") return "type=video";
      return "";
    }

    function sourceHint(mt) {
      if (mt === "image") return "Choose where to get your image from.";
      if (mt === "video") return "Choose where to get your video from.";
      if (mt === "carousel") return "Pick from your library or upload images and videos from your device.";
      return "Choose where to get your file from.";
    }

    function resetModalSteps() {
      if (stepSource) stepSource.hidden = false;
      if (stepLibrary) stepLibrary.hidden = true;
      if (gridEl) gridEl.innerHTML = "";
      if (emptyEl) emptyEl.hidden = true;
      if (libraryLoadingEl) libraryLoadingEl.hidden = true;
    }

    function showLibraryStep() {
      if (stepSource) stepSource.hidden = true;
      if (stepLibrary) stepLibrary.hidden = false;
    }

    function showSourceStep() {
      if (stepSource) stepSource.hidden = false;
      if (stepLibrary) stepLibrary.hidden = true;
    }

    function openDevicePicker() {
      if (modal && global.App.closeModal) global.App.closeModal(modal);
      resetModalSteps();
      fileInput.click();
    }

    function mediaUrl(path) {
      if (!path) return "";
      var p = String(path).replace(/^\//, "");
      return "/" + p;
    }

    function renderLibraryItems(items) {
      if (!gridEl) return;
      gridEl.innerHTML = "";
      items.forEach(function (item) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "composer-media-picker__item";
        btn.setAttribute("role", "option");
        btn.setAttribute("data-media-id", String(item.id));
        var thumb = document.createElement("span");
        thumb.className = "composer-media-picker__thumb";
        var img = document.createElement("img");
        if (item.type === "video") {
          img.src = "/assets/images/video-thumb.svg";
          img.alt = "";
        } else {
          img.src = mediaUrl(item.path);
          img.alt = item.original_name || "";
        }
        img.width = 120;
        img.height = 80;
        img.loading = "lazy";
        thumb.appendChild(img);
        if (item.type === "video") {
          var play = document.createElement("span");
          play.className = "composer-media-picker__play";
          play.setAttribute("aria-hidden", "true");
          play.innerHTML = '<i class="fa-solid fa-play"></i>';
          thumb.appendChild(play);
        }
        var cap = document.createElement("span");
        cap.className = "composer-media-picker__caption";
        cap.textContent = item.original_name || "Untitled";
        btn.appendChild(thumb);
        btn.appendChild(cap);
        btn.addEventListener("click", function () {
          if (modal && global.App.closeModal) global.App.closeModal(modal);
          resetModalSteps();
          var sz = item.size_bytes;
          if (sz != null && typeof sz === "string") sz = parseInt(sz, 10);
          global.__composerSelectedMedia = {
            id: item.id,
            path: item.path,
            type: item.type,
            original_name: item.original_name,
            size_bytes: sz != null && !isNaN(sz) ? sz : null
          };
          syncComposerPreviewMedia();
          global.App.showFlash("Selected from library: " + (item.original_name || "Media"));
        });
        gridEl.appendChild(btn);
      });
    }

    function fetchAndShowLibrary(listQs) {
      var url = "/media?per_page=24";
      if (listQs) url += "&" + listQs;
      global.App.apiGet(url).then(function (res) {
        if (libraryLoadingEl) libraryLoadingEl.hidden = true;
        if (!res._ok || !res.media || !Array.isArray(res.media.data)) {
          global.App.showFlash("Could not load library.", "error");
          showSourceStep();
          return;
        }
        var data = res.media.data;
        if (!data.length) {
          if (emptyEl) emptyEl.hidden = false;
          return;
        }
        if (emptyEl) emptyEl.hidden = true;
        renderLibraryItems(data);
      }).catch(function () {
        if (libraryLoadingEl) libraryLoadingEl.hidden = true;
        global.App.showFlash("Could not load library.", "error");
        showSourceStep();
      });
    }

    if (mediaTypeSel) {
      mediaTypeSel.addEventListener("change", function () {
        setFileAccept(getMediaType());
      });
      setFileAccept(getMediaType());
    }

    uploadBtn.addEventListener("click", function () {
      var mt = getMediaType();
      setFileAccept(mt);

      if (mt === "none") {
        fileInput.click();
        return;
      }

      var listQs = mediaListQuery(mt);
      var checkUrl = "/media?per_page=1" + (listQs ? "&" + listQs : "");

      uploadBtn.classList.add("is-loading");
      global.App.apiGet(checkUrl).then(function (res) {
        uploadBtn.classList.remove("is-loading");
        var total = 0;
        if (res && res.media && res.media.total != null) {
          total = parseInt(String(res.media.total), 10) || 0;
        }
        if (!res._ok || res.success === false) {
          fileInput.click();
          return;
        }
        if (total < 1) {
          fileInput.click();
          return;
        }
        if (!modal || !stepSource) {
          fileInput.click();
          return;
        }
        pendingListQs = listQs;
        resetModalSteps();
        if (hintEl) hintEl.textContent = sourceHint(mt);
        global.App.openModal("modal-composer-media");
      }).catch(function () {
        uploadBtn.classList.remove("is-loading");
        fileInput.click();
      });
    });

    if (modal) {
      var devBtn = modal.querySelector("[data-composer-media-from-device]");
      var libBtn = modal.querySelector("[data-composer-media-from-library]");
      var backBtn = modal.querySelector("[data-composer-media-back]");
      if (devBtn) devBtn.addEventListener("click", openDevicePicker);
      if (libBtn) {
        libBtn.addEventListener("click", function () {
          showLibraryStep();
          if (gridEl) gridEl.innerHTML = "";
          if (emptyEl) emptyEl.hidden = true;
          if (libraryLoadingEl) libraryLoadingEl.hidden = false;
          fetchAndShowLibrary(pendingListQs);
        });
      }
      if (backBtn) {
        backBtn.addEventListener("click", function () {
          showSourceStep();
          if (libraryLoadingEl) libraryLoadingEl.hidden = true;
        });
      }
    }

    fileInput.addEventListener("change", function () {
      if (!fileInput.files || !fileInput.files[0]) return;
      var fd = new FormData();
      fd.append("file", fileInput.files[0]);
      if (global.App.apiUpload) {
        uploadBtn.classList.add("is-loading");
        global.App.apiUpload("/media", fd).then(function (res) {
          uploadBtn.classList.remove("is-loading");
          if (res._ok) {
            composerBumpMediaCountsAfterUpload(res);
            if (res.media && res.media.path) {
              var ub = res.media.size_bytes;
              if (ub != null && typeof ub === "string") ub = parseInt(ub, 10);
              global.__composerSelectedMedia = {
                id: res.media.id,
                path: res.media.path,
                type: res.media.type || "image",
                original_name: res.media.original_name,
                size_bytes: ub != null && !isNaN(ub) ? ub : null
              };
              syncComposerPreviewMedia();
            }
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

        if (!confirmComposerMediaConstraintsIfNeeded()) {
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
          var editingDraftId = global.__composerEditingPostId;
          var draftReq =
            editingDraftId && typeof global.App.apiPut === "function"
              ? global.App.apiPut("/api/v1/posts/" + editingDraftId, draftPayload)
              : global.App.apiPost("/api/v1/posts", draftPayload);
          draftReq.then(function (res) {
            btn.disabled = false;
            if (titleEl) titleEl.textContent = "Draft saved";
            if (res._ok && res.post && res.post.id) {
              global.__composerEditingPostId = res.post.id;
              try {
                var u = new URL(global.location.href);
                u.searchParams.set("draft", String(res.post.id));
                global.history.replaceState({}, "", u.pathname + u.search);
              } catch (e) {}
            }
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
          var editingPubId = global.__composerEditingPostId;

          function finishPublish(pubRes) {
            btn.disabled = false;
            if (titleEl) titleEl.textContent = pubRes._ok ? "Publishing" : "Publish failed";
            if (descEl) descEl.textContent = pubRes._ok
              ? (pubRes.message || "Your post is being published to all selected networks.")
              : (pubRes.error || "Publish failed.");
            if (iconEl) iconEl.className = "fa-solid fa-paper-plane";
            if (calLink) calLink.setAttribute("hidden", "");
            global.App.openModal("modal-composer-feedback");
          }

          if (editingPubId && typeof global.App.apiPut === "function") {
            global.App.apiPut("/api/v1/posts/" + editingPubId, publishPayload).then(function (putRes) {
              if (!putRes._ok || !putRes.post) {
                btn.disabled = false;
                global.App.showFlash(putRes.error || "Could not update post.", "error");
                return;
              }
              return global.App.apiPost("/api/v1/posts/" + editingPubId + "/publish").then(finishPublish);
            }).catch(function () {
              btn.disabled = false;
              global.App.showFlash("Network error.", "error");
            });
          } else {
            global.App.apiPost("/api/v1/posts", publishPayload).then(function (res) {
              if (!res._ok || !res.post) {
                btn.disabled = false;
                global.App.showFlash(res.error || "Could not create post.", "error");
                return;
              }
              var postId = res.post.id;
              return global.App.apiPost("/api/v1/posts/" + postId + "/publish").then(finishPublish);
            }).catch(function () {
              btn.disabled = false;
              global.App.showFlash("Network error.", "error");
            });
          }
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

          var editingSchedId = global.__composerEditingPostId;
          var scheduleUrl = editingSchedId
            ? "/api/v1/posts/" + editingSchedId + "/schedule"
            : "/api/v1/posts/schedule";

          global.App.apiPost(scheduleUrl, schedulePayload).then(function (schedRes) {
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

        if (global.App && global.App.apiPatch) {
          var scheduledAt = null;
          if (day && day !== "Queue") {
            var parsed = new Date(day + " 09:00:00");
            if (!isNaN(parsed.getTime())) {
              scheduledAt = parsed.toISOString().slice(0, 19).replace("T", "T");
            }
          }

          global.App.apiPatch("/api/v1/posts/" + id + "/reschedule", {
            scheduled_at: scheduledAt
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
    if (cfg.source === "byok") {
      el.textContent = cfg.hasApiKey
        ? "— Using your API key (requests billed by your provider)."
        : "— Add your API key under Settings → AI.";
    } else if (cfg.platformTokensApplies && cfg.platformTokensRemaining != null && cfg.platformTokensBudget != null) {
      el.textContent =
        "— Platform credits: " +
        cfg.platformTokensRemaining.toLocaleString() +
        " / " +
        cfg.platformTokensBudget.toLocaleString() +
        " tokens left this period.";
    } else {
      el.textContent = "— Using the platform API key (included with your subscription).";
    }
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

  function pad2(n) {
    return n < 10 ? "0" + n : String(n);
  }

  function applyScheduledAtToComposer(iso) {
    if (!iso) return;
    var d = new Date(iso);
    if (isNaN(d.getTime())) return;
    var dateEl = document.getElementById("composer-date");
    var timeEl = document.getElementById("composer-time");
    if (dateEl) {
      dateEl.value = d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
    }
    if (timeEl) {
      timeEl.value = pad2(d.getHours()) + ":" + pad2(d.getMinutes());
    }
  }

  function refreshComposerOverrideField() {
    var tabsWrap = document.querySelector("[data-app-tabs]");
    var overrideEl = document.getElementById("composer-override");
    if (!tabsWrap || !overrideEl) return;
    var active = tabsWrap.querySelector("[data-app-platform-tab][aria-selected=\"true\"]");
    var key = active ? active.getAttribute("data-app-platform-tab") : "master";
    var o = global.__composerPlatformOverrides || {};
    if (key === "master") {
      overrideEl.value = "";
      overrideEl.disabled = true;
    } else {
      overrideEl.disabled = false;
      overrideEl.value = o[key] || "";
    }
  }

  function initComposerDraftFromUrl() {
    global.__composerEditingPostId = null;
    var params = new URLSearchParams(global.location.search || "");
    var rawId = params.get("draft");
    if (!rawId || !global.App || typeof global.App.apiGet !== "function") return;
    var postId = parseInt(rawId, 10);
    if (!postId) return;

    global.App.apiGet("/api/v1/posts/" + postId).then(function (res) {
      if (!res._ok || !res.post) {
        if (global.App.showFlash) global.App.showFlash(res.error || "Could not open this post.", "error");
        return;
      }
      var post = res.post;
      var st = String(post.status || "").toLowerCase();
      if (st === "published" || st === "queued") {
        if (global.App.showFlash) global.App.showFlash("This post can't be edited in the composer.", "error");
        try {
          global.history.replaceState({}, "", global.location.pathname);
        } catch (e) {}
        return;
      }

      global.__composerEditingPostId = postId;

      var banner = document.querySelector("[data-app-composer-editing-banner]");
      var bannerLabel = document.querySelector("[data-app-composer-editing-label]");
      if (banner && bannerLabel) {
        var kind =
          st === "scheduled" ? "scheduled post" : st === "failed" ? "failed post" : "draft";
        bannerLabel.textContent = "Editing " + kind + " #" + postId;
        banner.hidden = false;
        banner.removeAttribute("hidden");
      }

      var master = document.getElementById("composer-master");
      if (master) {
        master.value = post.content || "";
        master.dispatchEvent(new Event("input", { bubbles: true }));
      }

      var accountIds = {};
      (post.post_platforms || []).forEach(function (pp) {
        if (pp.social_account_id != null) accountIds[String(pp.social_account_id)] = true;
      });
      document.querySelectorAll('[name="platform_accounts[]"]').forEach(function (cb) {
        cb.checked = !!accountIds[String(cb.value)];
      });

      if (!global.__composerPlatformOverrides) global.__composerPlatformOverrides = {};
      var ovr = global.__composerPlatformOverrides;
      (post.post_platforms || []).forEach(function (pp) {
        var pc = pp.platform_content;
        if (pc != null && String(pc).trim() !== "" && pp.platform) {
          ovr[pp.platform] = String(pc);
        }
      });

      var fcEl = document.getElementById("composer-first-comment");
      var rows = post.post_platforms || [];
      var firstCommentVal = null;
      var cd = null;
      rows.forEach(function (pp) {
        if (firstCommentVal == null && pp.first_comment) firstCommentVal = pp.first_comment;
        if (cd == null && pp.comment_delay_minutes != null && pp.comment_delay_minutes > 0) {
          cd = pp.comment_delay_minutes;
        }
      });
      if (fcEl && firstCommentVal) fcEl.value = firstCommentVal;

      if (cd != null && cd > 0) {
        var cval = document.getElementById("composer-comment-delay-value");
        var cunit = document.getElementById("composer-comment-delay-unit");
        if (cval && cunit) {
          if (cd % 60 === 0 && cd >= 60) {
            cval.value = String(cd / 60);
            cunit.value = "hours";
          } else {
            cval.value = String(cd);
            cunit.value = "minutes";
          }
        }
      }

      applyScheduledAtToComposer(post.scheduled_at);

      var media = post.media_files && post.media_files[0];
      if (media && media.path) {
        var sz = media.size_bytes;
        if (sz != null && typeof sz === "string") sz = parseInt(sz, 10);
        global.__composerSelectedMedia = {
          id: media.id,
          path: media.path,
          type: media.type === "video" ? "video" : "image",
          original_name: media.original_name,
          size_bytes: sz != null && !isNaN(sz) ? sz : null
        };
        var mt = document.getElementById("composer-media-type");
        if (mt) {
          mt.value = media.type === "video" ? "video" : "image";
          mt.dispatchEvent(new Event("change", { bubbles: true }));
        }
      } else {
        global.__composerSelectedMedia = null;
        var mtClear = document.getElementById("composer-media-type");
        if (mtClear) {
          mtClear.value = "image";
          mtClear.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }

      refreshComposerOverrideField();
      syncComposerPreviewMedia();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initComposerPreviewSync();
    initComposerToolbar();
    initComposerAi();
    initComposerAiSourceHint();
    initComposerPlatformOverrides();
    initComposerAudienceSlots();
    initComposerActions();
    initComposerUpload();
    initComposerDraftFromUrl();
    initMediaLibraryFilter();
    initCalendarDrag();
  });
})(typeof window !== "undefined" ? window : this);
