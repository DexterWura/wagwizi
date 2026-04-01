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

    var buttons = toggleRoot.querySelectorAll("[data-lp-billing]");
    var cards = document.querySelectorAll("[data-lp-pricing-card][data-monthly]");

    function monthlyEquivFromYearly(yearlyTotal) {
      return Math.round(yearlyTotal / 12);
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
        billingEl.textContent = "Billed annually ($" + yearlyTotal + "/yr)";
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
    initMockupParallax();
    initFaq();
    initPricingBilling();
  });
})();
