(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function initReveal() {
    var sel = "[data-lp-reveal]";
    var els = document.querySelectorAll(sel);
    if (!els.length) return;
    if (reduceMotion || !("IntersectionObserver" in window)) {
      els.forEach(function (el) {
        el.classList.add("is-visible");
      });
      return;
    }
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        });
      },
      { rootMargin: "0px 0px -6% 0px", threshold: 0.06 }
    );
    els.forEach(function (el) {
      io.observe(el);
    });
  }

  function initScrollChrome() {
    var header = document.getElementById("lp-header");
    var bg = document.querySelector(".lp-hero__bg");
    var parallaxBg = bg && !reduceMotion;
    if (!header && !parallaxBg) return;
    var ticking = false;
    function update() {
      ticking = false;
      var y = window.scrollY || 0;
      if (header) header.classList.toggle("is-scrolled", y > 24);
      if (parallaxBg) bg.style.transform = "translate3d(0, " + (y * 0.12).toFixed(1) + "px, 0)";
    }
    function onScroll() {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    update();
  }

  function initMobileNav() {
    var btn = document.querySelector("[data-lp-nav-toggle]");
    var panel = document.getElementById("lp-nav-panel");
    if (!btn || !panel) return;
    function setOpen(open) {
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      panel.classList.toggle("is-open", open);
      document.body.classList.toggle("lp-nav-open", open);
    }
    btn.addEventListener("click", function () {
      setOpen(!panel.classList.contains("is-open"));
    });
    panel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        setOpen(false);
      });
    });
  }

  function initSmoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener("click", function (e) {
        var id = a.getAttribute("href");
        if (!id || id.length < 2) return;
        var target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
      });
    });
  }

  function initMockupDate() {
    var el = document.querySelector("[data-lp-mockup-date]");
    if (!el) return;
    try {
      var fmt = new Intl.DateTimeFormat("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric"
      });
      el.textContent = fmt.format(new Date());
      el.setAttribute("datetime", new Date().toISOString());
    } catch (e) {
      el.textContent = "";
    }
  }

  function initMockupDrawer() {
    var root = document.querySelector("[data-lp-mockup-demo-root]");
    if (!root) return;

    var openBtn = root.querySelector("[data-lp-mockup-drawer-open]");
    var backdrop = root.querySelector("[data-lp-mockup-drawer-backdrop]");
    var closeBtn = root.querySelector("[data-lp-mockup-drawer-close]");
    if (!openBtn || !backdrop) return;

    var mq = window.matchMedia("(max-width: 720px)");

    function setOpen(open) {
      if (!mq.matches) {
        root.classList.remove("lp-mockup-app--nav-open");
        openBtn.setAttribute("aria-expanded", "false");
        return;
      }
      root.classList.toggle("lp-mockup-app--nav-open", open);
      openBtn.setAttribute("aria-expanded", open ? "true" : "false");
    }

    openBtn.addEventListener("click", function () {
      setOpen(!root.classList.contains("lp-mockup-app--nav-open"));
    });

    backdrop.addEventListener("click", function () {
      setOpen(false);
    });

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        setOpen(false);
      });
    }

    var nav = root.querySelector(".lp-mockup-app__nav");
    if (nav) {
      nav.addEventListener("click", function () {
        if (mq.matches) setOpen(false);
      });
    }

    if (mq.addEventListener) {
      mq.addEventListener("change", function () {
        if (!mq.matches) root.classList.remove("lp-mockup-app--nav-open");
        openBtn.setAttribute("aria-expanded", "false");
      });
    } else if (mq.addListener) {
      mq.addListener(function () {
        if (!mq.matches) root.classList.remove("lp-mockup-app--nav-open");
        openBtn.setAttribute("aria-expanded", "false");
      });
    }

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!root.classList.contains("lp-mockup-app--nav-open")) return;
      setOpen(false);
    });
  }

  function initMockupComposerDemo() {
    var root = document.querySelector("[data-lp-mockup-demo-root]");
    var mock = document.querySelector("[data-lp-mockup]");
    if (!root || !mock) return;

    var motionReduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var draft = root.querySelector("[data-lp-mockup-draft]");
    var preview = root.querySelector("[data-lp-mockup-preview-text]");
    var homeNav = root.querySelector("[data-lp-mockup-nav-home]");
    var compNav = root.querySelector("[data-lp-mockup-nav-composer]");
    var fullText = "Launch week starts Monday — here's the rundown 🚀";

    var loopTimer = null;
    var typeTimer = null;
    var busy = false;

    function clearDemoState() {
      root.classList.remove(
        "lp-mockup-demo--typing-done",
        "lp-mockup-demo--platforms",
        "lp-mockup-demo--schedule-pulse",
        "lp-mockup-demo--sent"
      );
      if (draft) draft.textContent = "";
      if (preview) preview.textContent = "";
      if (homeNav) homeNav.classList.add("is-active");
      if (compNav) compNav.classList.remove("is-active");
    }

    function runOnce() {
      if (busy) return;
      busy = true;
      if (typeTimer) {
        window.clearInterval(typeTimer);
        typeTimer = null;
      }
      clearDemoState();

      window.setTimeout(function () {
        if (homeNav) homeNav.classList.remove("is-active");
        if (compNav) compNav.classList.add("is-active");
      }, 450);

      function afterType() {
        root.classList.add("lp-mockup-demo--typing-done");
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--platforms");
        }, 280);
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--schedule-pulse");
        }, 900);
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--sent");
        }, 2000);
        window.setTimeout(function () {
          busy = false;
        }, 2400);
        window.setTimeout(function () {
          clearDemoState();
        }, 5600);
      }

      if (motionReduce) {
        if (draft) draft.textContent = fullText;
        if (preview) preview.textContent = fullText;
        root.classList.add("lp-mockup-demo--typing-done");
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--platforms");
        }, 200);
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--schedule-pulse");
        }, 500);
        window.setTimeout(function () {
          root.classList.add("lp-mockup-demo--sent");
        }, 800);
        window.setTimeout(function () {
          busy = false;
        }, 1500);
        window.setTimeout(function () {
          clearDemoState();
        }, 4200);
        return;
      }

      var i = 0;
      typeTimer = window.setInterval(function () {
        i += 1;
        var slice = fullText.slice(0, i);
        if (draft) draft.textContent = slice;
        if (preview) preview.textContent = slice;
        if (i >= fullText.length) {
          window.clearInterval(typeTimer);
          typeTimer = null;
          afterType();
        }
      }, 40);
    }

    function startLoop() {
      runOnce();
      if (motionReduce) return;
      if (loopTimer) window.clearInterval(loopTimer);
      loopTimer = window.setInterval(runOnce, 8800);
    }

    function kickoff() {
      if (mock.classList.contains("is-visible")) {
        startLoop();
        return;
      }
      if (!("IntersectionObserver" in window)) {
        startLoop();
        return;
      }
      var io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            startLoop();
            io.disconnect();
          });
        },
        { rootMargin: "0px 0px -5% 0px", threshold: 0.08 }
      );
      io.observe(mock);
    }

    kickoff();
  }

  function initMockupParallax() {
    if (reduceMotion) return;
    var mock = document.querySelector("[data-lp-mockup]");
    if (!mock) return;
    var frame = mock.querySelector(".lp-mockup__tilt") || mock;
    var raf = null;
    var pending = null;
    function apply() {
      raf = null;
      if (!pending) return;
      var e = pending;
      pending = null;
      var rect = frame.getBoundingClientRect();
      var x = (e.clientX - rect.left) / rect.width - 0.5;
      var y = (e.clientY - rect.top) / rect.height - 0.5;
      var rx = (-y * 10).toFixed(2);
      var ry = (x * 12).toFixed(2);
      frame.style.transform =
        "perspective(1200px) rotateX(" + rx + "deg) rotateY(" + ry + "deg) translateZ(0)";
    }
    function onMove(e) {
      pending = e;
      if (!raf) raf = requestAnimationFrame(apply);
    }
    function onLeave() {
      pending = null;
      if (raf) cancelAnimationFrame(raf);
      raf = null;
      frame.style.transform = "perspective(1200px) rotateX(0deg) rotateY(0deg)";
    }
    mock.addEventListener("mousemove", onMove, { passive: true });
    mock.addEventListener("mouseleave", onLeave);
  }

  function initFaq() {
    document.querySelectorAll("[data-lp-faq]").forEach(function (root) {
      root.querySelectorAll(".lp-faq__question").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var item = btn.closest(".lp-faq__item");
          if (!item) return;
          var wasOpen = item.classList.contains("is-open");
          root.querySelectorAll(".lp-faq__item").forEach(function (i) {
            i.classList.remove("is-open");
            var b = i.querySelector(".lp-faq__question");
            if (b) b.setAttribute("aria-expanded", "false");
          });
          if (!wasOpen) {
            item.classList.add("is-open");
            btn.setAttribute("aria-expanded", "true");
          }
        });
      });
    });
  }

  function initPricingBilling() {
    var toggleRoot = document.querySelector("[data-lp-billing-toggle]");
    if (!toggleRoot) return;

    var wrap = toggleRoot.closest("[data-lp-currency-symbol]") || toggleRoot.parentElement;
    var currencySym = wrap && wrap.getAttribute ? wrap.getAttribute("data-lp-currency-symbol") || "$" : "$";

    var buttons = toggleRoot.querySelectorAll("[data-lp-billing]");
    var cards = document.querySelectorAll("[data-lp-pricing-card][data-monthly]");
    var saveBadge = toggleRoot.querySelector(".lp-billing-toggle__save");

    function monthlyEquivFromYearly(yearlyTotal) {
      return Math.round(yearlyTotal / 12);
    }

    function savePercent(monthly, yearlyTotal) {
      var annualMonthly = monthly * 12;
      if (!isFinite(annualMonthly) || annualMonthly <= 0) return null;
      if (!isFinite(yearlyTotal) || yearlyTotal <= 0) return null;
      var pct = ((annualMonthly - yearlyTotal) / annualMonthly) * 100;
      if (!isFinite(pct) || pct <= 0) return null;
      return Math.round(pct);
    }

    function annualBillingLabel(total) {
      var sym = currencySym;
      if (sym.length === 1) {
        return "Billed annually (" + sym + total + "/yr)";
      }
      return "Billed annually (" + sym + " " + total + "/yr)";
    }

    function applyBillingMode(mode) {
      cards.forEach(function (card) {
        var monthly = parseFloat(card.getAttribute("data-monthly"), 10);
        var yearlyTotal = parseFloat(card.getAttribute("data-yearly-total"), 10);
        var amountEl = card.querySelector("[data-lp-price-amount]");
        var suffixEl = card.querySelector("[data-lp-price-suffix]");
        var billingEl = card.querySelector("[data-lp-price-billing]");
        if (!amountEl || !suffixEl || !billingEl) return;

        if (card.getAttribute("data-lp-lifetime") === "1") {
          var ot = parseFloat(card.getAttribute("data-lp-onetime"), 10);
          if (!isFinite(ot)) ot = monthly;
          amountEl.textContent = String(Math.round(ot));
          suffixEl.textContent = " one time payment";
          billingEl.hidden = true;
          billingEl.textContent = "";
          return;
        }

        if (mode === "monthly") {
          amountEl.textContent = String(Math.round(monthly));
          suffixEl.textContent = "/ month";
          billingEl.hidden = true;
          billingEl.textContent = "";
          return;
        }

        if (monthly === 0 && yearlyTotal === 0) {
          amountEl.textContent = "0";
          suffixEl.textContent = "/ month";
          billingEl.hidden = true;
          billingEl.textContent = "";
          return;
        }

        var eq = monthlyEquivFromYearly(yearlyTotal);
        amountEl.textContent = String(eq);
        suffixEl.textContent = "/ month";
        billingEl.hidden = false;
        billingEl.textContent = annualBillingLabel(yearlyTotal);
      });
    }

    function applySaveBadge() {
      if (!saveBadge) return;
      var best = null;
      cards.forEach(function (card) {
        if (card.getAttribute("data-lp-lifetime") === "1") return;
        var monthly = parseFloat(card.getAttribute("data-monthly"), 10);
        var yearlyTotal = parseFloat(card.getAttribute("data-yearly-total"), 10);
        var pct = savePercent(monthly, yearlyTotal);
        if (pct === null) return;
        if (best === null || pct > best) best = pct;
      });

      if (best === null) {
        saveBadge.textContent = "";
        saveBadge.setAttribute("hidden", "");
        return;
      }

      saveBadge.removeAttribute("hidden");
      saveBadge.textContent = "Save " + best + "%";
    }

    function setMode(mode) {
      toggleRoot.setAttribute("data-lp-billing-active", mode);
      buttons.forEach(function (btn) {
        var isSel = btn.getAttribute("data-lp-billing") === mode;
        btn.classList.toggle("is-active", isSel);
        btn.setAttribute("aria-pressed", isSel ? "true" : "false");
      });
      applyBillingMode(mode);
    }

    var current = "monthly";
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var m = btn.getAttribute("data-lp-billing");
        if (!m || m === current) return;
        current = m;
        setMode(m);
      });
    });

    applySaveBadge();
    setMode(current);
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") || "" : "";
  }

  function lpApiPost(url, data) {
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

  function readLandingCheckoutConfig() {
    var el = document.getElementById("lp-checkout-config");
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || "null");
    } catch (e) {
      return null;
    }
  }

  function slugNeedsLandingCheckout(cfg, slug) {
    return cfg && Array.isArray(cfg.paid_plan_slugs) && cfg.paid_plan_slugs.indexOf(slug) !== -1;
  }

  function billingIntervalForSubscribe(card) {
    if (!card) return "monthly";
    if (card.getAttribute("data-lp-lifetime") === "1") return "monthly";
    var toggle = document.querySelector("[data-lp-billing-toggle]");
    var mode = toggle ? toggle.getAttribute("data-lp-billing-active") || "monthly" : "monthly";
    if (mode === "yearly") {
      if (card.getAttribute("data-offers-yearly") !== "1") return null;
      return "yearly";
    }
    return "monthly";
  }

  function resolveLandingGateway(cfg) {
    if (!cfg) return "paynow";
    if (cfg.checkout_mode === "choose") {
      return null;
    }
    var d = (cfg.default_gateway || "paynow").toLowerCase();
    if (d === "none") return "paynow";
    return d;
  }

  function initLandingCheckoutLoggedIn() {
    var cfg = readLandingCheckoutConfig();
    var section = document.querySelector(".lp-pricing");
    var statusEl = document.querySelector("[data-lp-checkout-status]");
    var gatewayModal = document.getElementById("lp-modal-gateway-picker");
    if (!cfg || !section) return;

    var busy = false;
    var pendingCheckout = null;

    function showStatus(msg, anim) {
      if (!statusEl) return;
      statusEl.hidden = false;
      statusEl.textContent = msg;
      statusEl.classList.toggle("is-animating", !!anim);
    }

    function clearStatus() {
      if (!statusEl) return;
      statusEl.hidden = true;
      statusEl.textContent = "";
      statusEl.classList.remove("is-animating");
    }

    function setCheckoutBusy(on) {
      busy = on;
      section.classList.toggle("lp-pricing--checkout-busy", on);
      section.setAttribute("aria-busy", on ? "true" : "false");
      document.querySelectorAll("[data-lp-subscribe]").forEach(function (b) {
        b.disabled = !!on;
      });
      document.querySelectorAll("[data-lp-billing]").forEach(function (b) {
        b.disabled = !!on;
      });
    }

    function setModalOpen(open) {
      if (!gatewayModal) return;
      gatewayModal.classList.toggle("is-open", !!open);
      gatewayModal.setAttribute("aria-hidden", open ? "false" : "true");
      document.body.style.overflow = open ? "hidden" : "";
    }

    function selectedGatewayFromModal() {
      if (!gatewayModal) return null;
      var sel = gatewayModal.querySelector('input[name="lp_checkout_gateway_modal"]:checked');
      return sel ? sel.value : null;
    }

    function runHostedCheckoutWithGateway(planSlug, billing, gw) {
      setCheckoutBusy(true);
      showStatus("Connecting you to our secure payment provider — you’ll finish checkout in a new step…", true);
      return lpApiPost("/plans/checkout/start", {
        plan_slug: planSlug,
        gateway: gw,
        billing_interval: billing
      }).then(function (r2) {
        if (r2.success && r2.redirect_url) {
          window.location.href = r2.redirect_url;
          return;
        }
        var m = r2.message || "Could not start checkout.";
        if (r2.paypal_error_name) m += " (" + r2.paypal_error_name + ")";
        showStatus(m, false);
        setCheckoutBusy(false);
      });
    }

    if (gatewayModal) {
      gatewayModal.querySelectorAll("[data-lp-modal-close]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          pendingCheckout = null;
          setModalOpen(false);
          clearStatus();
        });
      });

      var modalConfirm = gatewayModal.querySelector("[data-lp-gateway-confirm]");
      if (modalConfirm) {
        modalConfirm.addEventListener("click", function () {
          if (!pendingCheckout) return;
          var gw = selectedGatewayFromModal();
          if (!gw) {
            showStatus("Choose a payment method to continue checkout.", false);
            return;
          }
          var data = pendingCheckout;
          pendingCheckout = null;
          setModalOpen(false);
          runHostedCheckoutWithGateway(data.planSlug, data.billing, gw).catch(function () {
            showStatus("Network error. Check your connection and try again.", false);
            setCheckoutBusy(false);
          });
        });
      }

      document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        if (!gatewayModal.classList.contains("is-open")) return;
        pendingCheckout = null;
        setModalOpen(false);
      });
    }

    section.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-lp-subscribe]");
      if (!btn || btn.disabled) return;
      var planSlug = btn.getAttribute("data-plan-slug");
      if (!planSlug || planSlug === "enterprise") return;

      var card = btn.closest("[data-lp-pricing-card]");
      var billing = billingIntervalForSubscribe(card);
      if (billing === null) {
        showStatus("Yearly billing isn’t available for this plan. Choose Monthly above, or pick another tier.", false);
        return;
      }

      if (cfg.free_plan_slug && planSlug === cfg.free_plan_slug && cfg.current_plan_slug && cfg.current_plan_slug !== cfg.free_plan_slug) {
        if (!window.confirm("Switch to the free plan? You may lose paid features. You can upgrade again anytime.")) {
          return;
        }
      }

      setCheckoutBusy(true);
      showStatus("Connecting you to our secure payment provider — you’ll finish checkout in a new step…", true);

      function fail(msg) {
        showStatus(msg || "Something went wrong. Please try again or open Plans from your dashboard.", false);
        setCheckoutBusy(false);
      }

      lpApiPost("/plans/change", { plan_slug: planSlug })
        .then(function (res) {
          if (res.success && res.trial) {
            window.location.reload();
            return;
          }
          if (res._ok && res.success) {
            window.location.reload();
            return;
          }
          if (res.checkout_required && res._status === 402 && cfg.hosted_available && slugNeedsLandingCheckout(cfg, planSlug)) {
            var gw = resolveLandingGateway(cfg);
            if (!gw && gatewayModal) {
              setCheckoutBusy(false);
              pendingCheckout = { planSlug: planSlug, billing: billing };
              showStatus("Choose a payment method to continue checkout.", false);
              setModalOpen(true);
              return;
            }
            if (!gw) {
              fail("Choose a payment method to continue checkout.");
              return;
            }
            return runHostedCheckoutWithGateway(planSlug, billing, gw);
          }
          fail(res.message || "This plan can’t be selected right now.");
        })
        .catch(function () {
          fail("Network error. Check your connection and try again.");
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initReveal();
    initScrollChrome();
    initMobileNav();
    initSmoothAnchors();
    initMockupDate();
    initMockupDrawer();
    initMockupComposerDemo();
    initMockupParallax();
    initFaq();
    initPricingBilling();
    initLandingCheckoutLoggedIn();
  });
})();
