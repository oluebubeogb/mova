/* Mova HQ — Phase 5 interactions (vanilla, no build step)
 * nav groups + More overflow, mobile drawer, tooltips, help-text → tooltip,
 * keyboard-shortcuts modal, AI chips/badge/toast, icon-only a11y labels.
 */
(function () {
  'use strict';
  var d = document;
  function $(s, r) { return (r || d).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function on(el, ev, fn, o) { if (el) el.addEventListener(ev, fn, o || false); }
  var mqMobile = window.matchMedia('(max-width: 900px)');

  /* ---------------- Toasts ---------------- */
  function toast(msg, type, ms) {
    var box = $('.hq-toasts');
    if (!box) { box = d.createElement('div'); box.className = 'hq-toasts'; box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite'); d.body.appendChild(box); }
    var t = d.createElement('div');
    t.className = 'hq-toast' + (type ? ' is-' + type : '');
    var icon = type === 'success' ? 'fa-circle-check' : type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info';
    t.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i><div></div><button type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
    t.children[1].textContent = msg;
    box.appendChild(t);
    var kill = function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 220); };
    on(t.querySelector('button'), 'click', kill);
    setTimeout(kill, ms || 5000);
    return t;
  }
  window.HQToast = toast;

  /* ---------------- Tooltips ---------------- */
  var tipEl = null, tipFor = null, tipTimer = null;
  function showTip(el) {
    var text = el.getAttribute('data-tip');
    if (!text) return;
    if (!tipEl) { tipEl = d.createElement('div'); tipEl.className = 'hq-tip'; tipEl.setAttribute('role', 'tooltip'); tipEl.id = 'hq-tip'; d.body.appendChild(tipEl); }
    tipEl.textContent = text;
    tipFor = el;
    var r = el.getBoundingClientRect();
    tipEl.style.left = '0px'; tipEl.style.top = '0px';
    tipEl.classList.add('is-visible');
    var tw = tipEl.offsetWidth, th = tipEl.offsetHeight;
    var left = Math.max(8, Math.min(window.innerWidth - tw - 8, r.left + r.width / 2 - tw / 2));
    var top = r.bottom + 8;
    if (top + th > window.innerHeight - 8) top = r.top - th - 8;
    tipEl.style.left = left + 'px';
    tipEl.style.top = top + 'px';
    if (!el.hasAttribute('aria-describedby')) { el.setAttribute('aria-describedby', 'hq-tip'); el._hqTipDesc = true; }
  }
  function hideTip() {
    clearTimeout(tipTimer);
    if (tipEl) tipEl.classList.remove('is-visible');
    if (tipFor && tipFor._hqTipDesc) { tipFor.removeAttribute('aria-describedby'); tipFor._hqTipDesc = false; }
    tipFor = null;
  }
  on(d, 'mouseover', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (!el || el === tipFor) return;
    clearTimeout(tipTimer);
    tipTimer = setTimeout(function () { showTip(el); }, 280);
  });
  on(d, 'mouseout', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el && (!e.relatedTarget || !el.contains(e.relatedTarget))) hideTip();
  });
  on(d, 'focusin', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el && el.matches(':focus-visible')) showTip(el); });
  on(d, 'focusout', hideTip);
  on(window, 'scroll', hideTip, { passive: true, capture: true });
  // Mobile: tap the (i) help icon to toggle its tip
  on(d, 'click', function (e) {
    var h = e.target.closest && e.target.closest('.hq-help');
    if (!h) return;
    e.preventDefault();
    if (tipFor === h) hideTip(); else showTip(h);
  });

  /* Long help paragraphs under form fields → (i) tooltip next to the label */
  function convertHelpText(root) {
    $$('.hq-content .form-group > p, .hq-content .form-group > small, .hq-content .form-group > .form-hint, .hq-content .form-group > .help-text, .hq-content .form-group > .hint', root).forEach(function (p) {
      if (p.dataset.hqHelp) return;
      var txt = (p.textContent || '').replace(/\s+/g, ' ').trim();
      if (txt.length < 70 || p.querySelector('a,button,input,code,strong,img')) return;
      var small = p.tagName === 'SMALL' || /font-size:\s*0?\.(7|8)/.test(p.getAttribute('style') || '') || /hint|help|desc|muted/.test(p.className || '');
      if (!small) return;
      var group = p.parentElement;
      var label = group.querySelector(':scope > label');
      if (!label) return;
      p.dataset.hqHelp = '1';
      if (!p.id) p.id = 'hqh-' + Math.random().toString(36).slice(2, 8);
      var btn = d.createElement('button');
      btn.type = 'button';
      btn.className = 'hq-help';
      btn.setAttribute('data-tip', txt);
      btn.setAttribute('aria-label', 'Help: ' + txt);
      btn.innerHTML = '<i class="fa-regular fa-circle-question" aria-hidden="true"></i>';
      label.appendChild(btn);
      p.classList.add('hq-help-src');
      var field = group.querySelector('input,select,textarea');
      if (field && !field.hasAttribute('aria-describedby')) field.setAttribute('aria-describedby', p.id);
    });
  }

  /* ---------------- Icon-only buttons / unlabeled inputs ---------------- */
  function labelIcons(root) {
    $$('button, a.hq-icon-btn, a[role=button], [class*="icon-btn"]', root).forEach(function (b) {
      if (b.hasAttribute('aria-label') || b.hasAttribute('aria-labelledby')) return;
      if ((b.textContent || '').trim()) return;
      var t = b.getAttribute('title') || b.getAttribute('data-tip') || b.getAttribute('data-cmd') || b.getAttribute('data-action');
      if (t) b.setAttribute('aria-label', t.replace(/[-_]/g, ' '));
    });
    $$('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea', root).forEach(function (i) {
      if (i.hasAttribute('aria-label') || i.hasAttribute('aria-labelledby') || i.closest('label')) return;
      if (i.id && d.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(i.id) : i.id) + '"]')) return;
      var t = i.getAttribute('placeholder') || i.getAttribute('title') || i.getAttribute('name');
      if (!t) { var g = i.closest('.form-group'); var l = g && g.querySelector('label'); if (l) t = l.textContent.trim(); }
      if (t) i.setAttribute('aria-label', t.replace(/[\[\]_]/g, ' ').trim());
    });
  }

  /* ---------------- Line 2 group menus ---------------- */
  var openMenu = null;
  function closeMenus() {
    if (!openMenu) return;
    openMenu.menu.hidden = true;
    openMenu.trigger.setAttribute('aria-expanded', 'false');
    openMenu = null;
  }
  function placeMenu(trigger, menu) {
    var r = trigger.getBoundingClientRect();
    menu.style.top = Math.round(r.bottom + 6) + 'px';
    var w = menu.offsetWidth || 220;
    var left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8);
    menu.style.left = Math.round(left) + 'px';
  }
  function openGroup(trigger, focusFirst) {
    var menu = d.getElementById(trigger.getAttribute('aria-controls'));
    if (!menu) return;
    if (openMenu && openMenu.menu === menu) { closeMenus(); return; }
    closeMenus();
    if (menu.parentNode !== d.body) { menu._home = menu.parentNode; d.body.appendChild(menu); }  // escape overflow:hidden
    menu.hidden = false;
    placeMenu(trigger, menu);
    trigger.setAttribute('aria-expanded', 'true');
    openMenu = { trigger: trigger, menu: menu };
    if (focusFirst) { var f = menu.querySelector('a,button'); if (f) f.focus(); }
  }
  on(d, 'click', function (e) {
    var t = e.target.closest && e.target.closest('.hq-nav-grouptrigger, .hq-nav-moretrigger');
    if (t) { e.preventDefault(); openGroup(t, e.detail === 0); return; }
    if (openMenu && !openMenu.menu.contains(e.target)) closeMenus();
  });
  on(d, 'keydown', function (e) {
    if (!openMenu) {
      var t = e.target.closest && e.target.closest('.hq-nav-grouptrigger, .hq-nav-moretrigger');
      if (t && (e.key === 'ArrowDown')) { e.preventDefault(); openGroup(t, true); }
      return;
    }
    var items = $$('a,button', openMenu.menu);
    var i = items.indexOf(d.activeElement);
    if (e.key === 'Escape') { var tr = openMenu.trigger; closeMenus(); tr.focus(); e.preventDefault(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1]).focus(); }
    else if (e.key === 'Tab') { closeMenus(); }
  });
  on(window, 'resize', closeMenus);
  on(window, 'scroll', function () { if (openMenu) placeMenu(openMenu.trigger, openMenu.menu); }, { passive: true });

  function markActiveMenuItems() {
    var path = location.pathname.replace(/\/+$/, '') || '/hq';
    $$('.hq-nav-menuitem').forEach(function (a) {
      var href = (a.getAttribute('href') || '').split('?')[0].replace(/\/+$/, '');
      var act = href && (path === href || (path.indexOf(href + '/') === 0 && href.split('/').length > 2));
      a.classList.toggle('active', !!act);
      if (act) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    $$('.hq-nav-group').forEach(function (g) {
      var trig = g.querySelector('.hq-nav-grouptrigger');
      var menu = d.getElementById(trig && trig.getAttribute('aria-controls'));
      var act = menu && menu.querySelector('.hq-nav-menuitem.active');
      if (!trig) return;
      trig.classList.toggle('active', !!act);
      var cur = trig.querySelector('.hq-nav-groupcur');
      if (act && !cur) { cur = d.createElement('em'); cur.className = 'hq-nav-groupcur'; trig.insertBefore(cur, trig.querySelector('.hq-nav-caret')); }
      if (cur) { if (act) cur.textContent = act.textContent.trim(); else cur.remove(); }
    });
  }

  /* ---------------- More overflow (desktop/tablet) ---------------- */
  function buildMore() {
    var track = $('.hq-nav-track');
    var more = track && track.querySelector('.hq-nav-more');
    if (!track || !more) return;
    var menu = d.getElementById('hq-navgroup-more') || more.querySelector('.hq-nav-menu');
    var scroller = $('#hq-nav-scroller') || track.parentNode;
    $$('li.is-overflowed', track).forEach(function (li) { li.classList.remove('is-overflowed'); });
    more.hidden = true;
    if (!menu || window.innerWidth <= 640) return;
    var items = $$(':scope > li', track).filter(function (li) { return li !== more && !li.classList.contains('hq-nav-sep'); });
    if (scroller.scrollWidth <= scroller.clientWidth + 1) return;
    more.hidden = false;
    var hidden = [];
    for (var k = items.length - 1; k >= 0 && scroller.scrollWidth > scroller.clientWidth + 1; k--) {
      var li = items[k];
      if (li.querySelector('.active, [aria-current="page"]')) continue;   // never hide current page
      li.classList.add('is-overflowed');
      hidden.unshift(li);
    }
    if (!hidden.length) { more.hidden = true; return; }
    menu.innerHTML = '';
    hidden.forEach(function (li) {
      var links = li.querySelector('.hq-nav-menu') ? $$('.hq-nav-menuitem', li.querySelector('.hq-nav-menu')) : $$('a.hq-nav-item', li);
      var gm = li.querySelector('.hq-nav-grouptrigger');
      if (gm) { var m = d.getElementById(gm.getAttribute('aria-controls')); if (m) links = $$('.hq-nav-menuitem', m); }
      links.forEach(function (a) {
        var c = d.createElement('a');
        c.className = 'hq-nav-menuitem';
        c.setAttribute('role', 'menuitem');
        c.href = a.getAttribute('href');
        c.innerHTML = a.innerHTML;
        $$('.hq-nav-caret,.hq-nav-groupcur', c).forEach(function (x) { x.remove(); });
        menu.appendChild(c);
      });
    });
    markActiveMenuItems();
  }
  var moreTimer = null;
  function scheduleMore() { clearTimeout(moreTimer); moreTimer = setTimeout(buildMore, 120); }

  /* ---------------- Mobile drawer ---------------- */
  function drawer(open) {
    var dr = $('#hq-drawer'), bd = $('#hq-drawer-backdrop'), btn = $('#hq-menu-btn');
    if (!dr) return;
    dr.hidden = !open;
    if (bd) bd.hidden = !open;
    d.body.classList.toggle('hq-drawer-open', open);
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var path = location.pathname + location.search;
      $$('.hq-drawer-links a', dr).forEach(function (a) {
        var href = a.getAttribute('href');
        a.classList.toggle('active', href === path || href === location.pathname);
      });
      var f = dr.querySelector('.hq-drawer-links a.active') || $('#hq-drawer-close');
      if (f) f.focus();
    } else if (btn && dr.contains(d.activeElement)) { btn.focus(); }
  }
  on(d, 'click', function (e) {
    if (e.target.closest('#hq-menu-btn')) { e.preventDefault(); drawer(true); }
    else if (e.target.closest('#hq-drawer-close') || e.target.id === 'hq-drawer-backdrop') { drawer(false); }
  });
  on(d, 'keydown', function (e) {
    var dr = $('#hq-drawer');
    if (!dr || dr.hidden) return;
    if (e.key === 'Escape') { drawer(false); return; }
    if (e.key === 'Tab') {   // focus trap
      var f = $$('a,button,summary', dr).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });
  on(mqMobile, 'change', function (e) { if (!e.matches) drawer(false); });

  /* ---------------- Keyboard shortcuts modal ---------------- */
  var isMac = /Mac|iPhone|iPad/.test(navigator.platform || '');
  var mod = isMac ? '⌘' : 'Ctrl';
  function shortcutsModal() {
    if ($('#hq-shortcuts-modal')) return;
    var rows = [
      ['Search & jump anywhere', [mod, 'K']],
      ['Open Mova AI', [mod, 'J']],
      ['Save (in editors)', [mod, 'S']],
      ['Publish now (content editor)', [mod, 'Shift', 'P']],
      ['Show this help', ['?']],
      ['Close menus & dialogs', ['Esc']]
    ];
    var bd = d.createElement('div');
    bd.className = 'hq-modal-backdrop';
    bd.id = 'hq-shortcuts-modal';
    bd.innerHTML = '<div class="hq-modal" role="dialog" aria-modal="true" aria-labelledby="hq-shortcuts-title">' +
      '<div class="hq-modal-head"><i class="fa-regular fa-keyboard" aria-hidden="true"></i><h2 id="hq-shortcuts-title">Keyboard shortcuts</h2>' +
      '<button type="button" class="hq-icon-btn" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>' +
      '<div class="hq-modal-body"><dl class="hq-kbd-list">' +
      rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1].map(function (k) { return '<kbd>' + k + '</kbd>'; }).join('') + '</dd>'; }).join('') +
      '</dl></div></div>';
    var prev = d.activeElement;
    function close() { bd.remove(); d.removeEventListener('keydown', esc, true); if (prev && prev.focus) prev.focus(); }
    function esc(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
    on(bd, 'click', function (e) { if (e.target === bd || e.target.closest('[data-close]')) close(); });
    d.addEventListener('keydown', esc, true);
    d.body.appendChild(bd);
    bd.querySelector('[data-close]').focus();
  }
  on(d, 'click', function (e) {
    if (e.target.closest && e.target.closest('[data-hq-shortcuts]')) {
      e.preventDefault();
      var dd = $('#hq-user-dropdown'); if (dd) dd.hidden = true;
      shortcutsModal();
    }
  });
  on(d, 'keydown', function (e) {
    if (e.key !== '?' || e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.closest('.monaco-editor,.CodeMirror'))) return;
    e.preventDefault();
    shortcutsModal();
  });

  /* ---------------- Mova AI: chips, suggest panel, jobs badge + toast ---------------- */
  function initAi() {
    var input = $('#mova-ai-input');
    var suggest = $('#mova-ai-suggest');
    var msgs = $('#mova-ai-messages');
    on(d, 'click', function (e) {
      var c = e.target.closest && e.target.closest('[data-ai-suggest]');
      if (!c || !input) return;
      input.value = c.getAttribute('data-ai-suggest');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.focus();
      try { input.setSelectionRange(input.value.length, input.value.length); } catch (x) {}
    });
    if (suggest && msgs) {
      var sync = function () { suggest.hidden = msgs.children.length > 0; };
      sync();
      new MutationObserver(sync).observe(msgs, { childList: true });
    }
    var src = $('#mova-ai-jobs-badge');
    if (!src) return;
    var targets = ['#mova-ai-header-btn', '#mova-ai-fab'].map(function (s) {
      var host = $(s); if (!host) return null;
      var b = d.createElement('span');
      b.className = 'hq-jobs-badge is-running';
      b.hidden = true;
      b.setAttribute('aria-hidden', 'true');
      host.appendChild(b);
      return { host: host, badge: b, label: host.getAttribute('aria-label') || 'Open Mova AI' };
    }).filter(Boolean);
    var last = 0;
    function mirror() {
      var n = src.hidden ? 0 : (parseInt(src.textContent, 10) || 0);
      targets.forEach(function (t) {
        t.badge.hidden = n === 0;
        t.badge.textContent = src.textContent;
        t.host.setAttribute('aria-label', n ? t.label + ' — ' + n + ' background task' + (n === 1 ? '' : 's') + ' running' : t.label);
      });
      if (n < last) {
        var panelOpen = $('#mova-ai-panel') && $('#mova-ai-panel').classList.contains('open');
        toast(n === 0 ? 'Background AI task finished. Open Mova AI to see the result.' : 'A background AI task finished.', 'success');
        if (panelOpen) {/* user already sees it */}
      }
      last = n;
    }
    mirror();
    new MutationObserver(mirror).observe(src, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
  }

  /* ---------------- Boot ---------------- */
  function boot() {
    markActiveMenuItems();
    convertHelpText(d);
    labelIcons(d);
    initAi();
    buildMore();
    on(window, 'resize', scheduleMore);
    if (d.fonts && d.fonts.ready) d.fonts.ready.then(scheduleMore);
    // Late-rendered UI (modals, AJAX lists) also gets labels
    var t = null, pend = [];
    new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (n.nodeType === 1 && !(n.closest && n.closest('.monaco-editor,.CodeMirror,[contenteditable],#editor,.hq-tip,.hq-toasts'))) pend.push(n);
        });
      });
      if (!pend.length) return;
      clearTimeout(t);
      t = setTimeout(function () { var list = pend; pend = []; list.forEach(function (n) { if (n.isConnected) labelIcons(n.parentNode || n); }); }, 300);
    }).observe(d.body, { childList: true, subtree: true });
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})();
