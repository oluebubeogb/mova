/* Mova section library runtime — reveal, counters, FAQ animation, pricing toggle, newsletter.
   Tiny, dependency-free, idempotent (safe to load twice / re-run after DOM inserts). */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  root.classList.add('mx-js');
  if (/[?&]mx_static=1/.test(location.search)) root.classList.add('mx-static');

  function each(sel, ctx, fn) { Array.prototype.forEach.call((ctx || d).querySelectorAll(sel), fn); }

  function countUp(el) {
    if (el.dataset.mxDone) return; el.dataset.mxDone = '1';
    var target = parseFloat(el.dataset.count); if (isNaN(target)) return;
    var pre = el.dataset.prefix || '', suf = el.dataset.suffix || '';
    var dec = (String(el.dataset.count).split('.')[1] || '').length;
    var final = el.textContent;
    if (reduce || root.classList.contains('mx-static')) return;
    var t0 = null, dur = 1400;
    function fmt(v) { return pre + v.toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf; }
    function step(ts) {
      if (!t0) t0 = ts; var p = Math.min(1, (ts - t0) / dur); var e = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(target * e);
      if (p < 1) requestAnimationFrame(step); else el.textContent = final;
    }
    requestAnimationFrame(step);
  }

  var io = null;
  function reveal(ctx) {
    var els = (ctx || d).querySelectorAll('[data-reveal]:not(.is-in), [data-count]:not([data-mx-done])');
    if (!els.length) return;
    if (!('IntersectionObserver' in window) || reduce) {
      Array.prototype.forEach.call(els, function (el) { el.classList.add('is-in'); if (el.dataset.count) countUp(el); });
      return;
    }
    if (!io) io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('is-in');
        if (en.target.dataset.count) countUp(en.target);
        io.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    Array.prototype.forEach.call(els, function (el) { io.observe(el); });
  }

  function faq(ctx) {
    each('.mx-faq__item', ctx, function (det) {
      if (det.dataset.mxFaq) return; det.dataset.mxFaq = '1';
      var sum = det.querySelector('summary'), body = det.querySelector('.mx-faq__a');
      if (!sum || !body) return;
      sum.addEventListener('click', function (ev) {
        if (reduce) return;
        ev.preventDefault();
        body.classList.add('is-anim');
        if (det.open) {
          body.style.height = body.scrollHeight + 'px'; body.style.opacity = '1';
          requestAnimationFrame(function () { body.style.height = '0px'; body.style.opacity = '0'; });
          setTimeout(function () { det.open = false; body.style.height = ''; body.style.opacity = ''; }, 340);
        } else {
          det.open = true; var h = body.scrollHeight;
          body.style.height = '0px'; body.style.opacity = '0';
          requestAnimationFrame(function () { body.style.height = h + 'px'; body.style.opacity = '1'; });
          setTimeout(function () { body.style.height = ''; }, 360);
        }
      });
    });
  }

  function pricing(ctx) {
    each('.mx-pricing__switch', ctx, function (sw) {
      if (sw.dataset.mxSw) return; sw.dataset.mxSw = '1';
      var sec = sw.closest('.mx-pricing');
      sw.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-mx-period]'); if (!b) return;
        var p = b.dataset.mxPeriod;
        each('[data-mx-period]', sw, function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        each('.mx-plan__amt, .mx-plan__per', sec, function (el) { var v = el.getAttribute('data-' + p); if (v !== null) el.textContent = v; });
      });
    });
  }

  function newsletter(ctx) {
    each('form[data-mx-subscribe]', ctx, function (f) {
      if (f.dataset.mxNl) return; f.dataset.mxNl = '1';
      f.addEventListener('submit', function (ev) {
        if (!window.fetch) return;
        ev.preventDefault();
        var msg = f.querySelector('[data-mx-subscribe-msg]'), btn = f.querySelector('button');
        if (btn) btn.disabled = true;
        fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
          .then(function (j) {
            if (msg) { msg.textContent = j.message || (j.ok ? 'Thanks — you\u2019re subscribed!' : 'Something went wrong.'); msg.className = 'mx-newsletter__note ' + (j.ok ? 'is-ok' : 'is-err'); }
            if (j.ok) f.reset();
          })
          .catch(function () { if (msg) { msg.textContent = 'Network error — please try again.'; msg.className = 'mx-newsletter__note is-err'; } })
          .then(function () { if (btn) btn.disabled = false; });
      });
    });
  }

  function marquee(ctx) {
    each('.mx-logos__track', ctx, function (t) {
      if (t.dataset.mxMq) return; t.dataset.mxMq = '1';
      t.addEventListener('focusin', function () { t.classList.add('is-paused'); });
      t.addEventListener('focusout', function () { t.classList.remove('is-paused'); });
    });
  }

  function init(ctx) { reveal(ctx); faq(ctx); pricing(ctx); newsletter(ctx); marquee(ctx); }
  window.MovaSections = { init: init };
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { init(); }); else init();
})();
