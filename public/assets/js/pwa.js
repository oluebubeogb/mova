/* Mova PWA — service worker registration + install banner. Config: window.MovaPWA (from Pwa::headHtml). */
(function () {
  'use strict';
  var C = window.MovaPWA;
  if (!C) return;
  var LS = window.localStorage, K_DISMISS = 'mova_pwa_dismissed', K_PV = 'mova_pwa_pv', K_INST = 'mova_pwa_installed';
  var get = function (k) { try { return LS.getItem(k); } catch (e) { return null; } };
  var set = function (k, v) { try { LS.setItem(k, v); } catch (e) {} };
  var inFrame = (function () { try { return window.self !== window.top; } catch (e) { return true; } })();
  var params = new URLSearchParams(location.search);
  var preview = params.has('mova_theme') || params.has('mx_static');

  // ---- service worker ----
  if ('serviceWorker' in navigator && !inFrame && !preview && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(C.sw, { scope: '/' }).then(function (reg) {
        set('mova_pwa_sw', '1');
        C.registration = reg;
      }).catch(function (err) { if (window.console) console.warn('[Mova PWA] service worker not registered:', err && err.message); });
    });
  }

  // ---- install banner ----
  function standalone() {
    try {
      if (window.navigator.standalone === true) return true;
      return ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'].some(function (m) { return window.matchMedia('(display-mode: ' + m + ')').matches; });
    } catch (e) { return false; }
  }
  var ua = navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isIOSSafari = isIOS && /safari/i.test(ua) && !/crios|fxios|edgios|opios|gsa\//i.test(ua);
  var deferred = null, el = null, shown = false, ready = false;

  var pv = (parseInt(get(K_PV) || '0', 10) || 0) + 1;
  set(K_PV, String(pv));

  function snoozed() {
    var t = parseInt(get(K_DISMISS) || '0', 10) || 0;
    if (!t) return false;
    return Date.now() - t < (C.snooze || 0) * 86400000;
  }
  function eligible() { return C.banner && !inFrame && !preview && !standalone() && get(K_INST) !== '1' && !snoozed(); }

  var SHARE = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>';
  var ADD = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8.5v7M8.5 12h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function build(mode) {
    var d = document.createElement('div');
    d.className = 'mova-pwa-banner';
    d.id = 'mova-pwa-banner';
    d.setAttribute('role', 'dialog');
    d.setAttribute('aria-modal', 'false');
    d.setAttribute('aria-labelledby', 'mova-pwa-title');
    d.setAttribute('aria-describedby', 'mova-pwa-msg');
    var body = mode === 'ios'
      ? '<p class="mova-pwa-msg" id="mova-pwa-msg">Install this app: tap <span class="mova-pwa-ico" aria-label="Share">' + SHARE + '</span> <strong>Share</strong>, then <span class="mova-pwa-ico">' + ADD + '</span> <strong>Add to Home Screen</strong>.</p>'
      : '<p class="mova-pwa-msg" id="mova-pwa-msg">' + esc(C.msg) + '</p>';
    var actions = mode === 'ios'
      ? '<div class="mova-pwa-actions"><button type="button" class="mova-pwa-btn mova-pwa-btn--primary" data-pwa="dismiss">Got it</button></div>'
      : '<div class="mova-pwa-actions"><button type="button" class="mova-pwa-btn mova-pwa-btn--ghost" data-pwa="dismiss">Not now</button><button type="button" class="mova-pwa-btn mova-pwa-btn--primary" data-pwa="install">Install</button></div>';
    d.innerHTML = '<img class="mova-pwa-icon" src="' + esc(C.icon) + '" alt="" width="48" height="48">'
      + '<div class="mova-pwa-body"><p class="mova-pwa-title" id="mova-pwa-title">' + esc(C.name) + '</p>' + body + actions + '</div>'
      + '<button type="button" class="mova-pwa-close" data-pwa="dismiss" aria-label="Dismiss install banner"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>';
    d.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-pwa]');
      if (!b) return;
      if (b.getAttribute('data-pwa') === 'install') install(); else dismiss();
    });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') dismiss(); });
    return d;
  }

  function show(mode) {
    if (shown || !document.body) return;
    shown = true;
    el = build(mode || (deferred ? 'prompt' : 'ios'));
    document.body.appendChild(el);
    el.offsetWidth; // reflow → transition
    el.classList.add('is-visible');
    document.documentElement.classList.add('mova-pwa-banner-open');
  }
  function hide() {
    if (!el) return;
    var node = el; el = null;
    node.classList.remove('is-visible');
    document.documentElement.classList.remove('mova-pwa-banner-open');
    var rm = function () { if (node.parentNode) node.parentNode.removeChild(node); };
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) rm(); else setTimeout(rm, 320);
  }
  function dismiss() { set(K_DISMISS, String(Date.now())); hide(); }
  function install() {
    if (!deferred) { hide(); return; }
    var p = deferred; deferred = null;
    p.prompt();
    (p.userChoice || Promise.resolve({})).then(function (r) {
      if (r && r.outcome === 'accepted') set(K_INST, '1'); else set(K_DISMISS, String(Date.now()));
      hide();
    });
  }
  function trigger(mode) {
    if (!eligible()) return;
    if (C.trigger === 'pageviews') { if (pv >= (C.pageviews || 1)) show(mode); return; }
    setTimeout(function () { if (eligible()) show(mode); }, Math.max(0, (C.delay || 0) * 1000));
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (!ready) { ready = true; trigger('prompt'); }
  });
  window.addEventListener('appinstalled', function () { set(K_INST, '1'); deferred = null; hide(); });
  try {
    window.matchMedia('(display-mode: standalone)').addEventListener('change', function (e) { if (e.matches) hide(); });
  } catch (e) {}

  if (isIOSSafari) {
    var go = function () { if (!ready) { ready = true; trigger('ios'); } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
  }

  // Small API (used by tests and the HQ preview).
  C.show = function (mode) { shown = false; show(mode || (deferred ? 'prompt' : 'ios')); };
  C.hide = hide;
  C.isStandalone = standalone;
  C.eligible = eligible;
})();
