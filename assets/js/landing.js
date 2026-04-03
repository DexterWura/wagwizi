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

    function monthlyEquivFromYearly(yearlyTotal) {
      return Math.round(yearlyTotal / 12);
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

    function setMode(mode) {
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

    setMode(current);
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
  });
})();
