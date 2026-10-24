/*!
 * Mova section picker — browse the section library with live previews and insert
 * ready-made, theme-aware sections into the visual editor, Assembly or anywhere else.
 *
 *   MovaSectionPicker.open({ title: 'Insert a section', onInsert: function (html, item) {} });
 *   MovaSectionPicker.load().then(function (data) {});   // { categories, items[], pages[] }
 *   MovaSectionPicker.preview(item | id)                  // full-screen device preview
 */
(function () {
  'use strict';
  var cache = {};
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  function load(withPages) {
    var key = withPages ? 'p' : 'n';
    if (cache[key]) return cache[key];
    cache[key] = fetch('/hq/templates/library.json' + (withPages ? '?pages=1' : ''), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .catch(function (e) { delete cache[key]; throw e; });
    return cache[key];
  }

  function el(html) {
    var d = document.createElement('div');
    d.innerHTML = html.trim();
    return d.firstChild;
  }

  var lastFocus = null;
  function trapClose(root, close) {
    lastFocus = document.activeElement;
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
      if (e.key === 'Tab') {
        var f = root.querySelectorAll('button:not([disabled]), [href], input, select, iframe, [tabindex]:not([tabindex="-1"])');
        f = Array.prototype.filter.call(f, function (x) { return x.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }
    root.addEventListener('keydown', onKey);
    document.documentElement.classList.add('msp-lock');
    return function () {
      root.removeEventListener('keydown', onKey);
      document.documentElement.classList.remove('msp-lock');
      if (lastFocus && lastFocus.focus) try { lastFocus.focus(); } catch (e) {}
    };
  }

  /* ---------- full-screen device preview ---------- */
  function preview(itemOrUrl, opts) {
    opts = opts || {};
    var url = typeof itemOrUrl === 'string' ? itemOrUrl : (itemOrUrl.live || ('/_preview/section/' + encodeURIComponent(itemOrUrl.id)));
    var title = opts.title || (itemOrUrl && itemOrUrl.name) || 'Preview';
    var root = el(
      '<div class="msp-full" role="dialog" aria-modal="true" aria-label="' + esc(title) + '">' +
        '<div class="msp-full__bar">' +
          '<strong class="msp-full__title">' + esc(title) + '</strong>' +
          '<div class="msp-seg" role="group" aria-label="Device">' +
            '<button type="button" data-dev="desktop" class="is-on" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg><span>Desktop</span></button>' +
            '<button type="button" data-dev="tablet" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/></svg><span>Tablet</span></button>' +
            '<button type="button" data-dev="mobile" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg><span>Mobile</span></button>' +
          '</div>' +
          '<div class="msp-full__actions">' + (opts.actionsHtml || '') +
            '<a class="msp-btn msp-btn--ghost" href="' + esc(url) + '" target="_blank" rel="noopener">Open in new tab</a>' +
            '<button type="button" class="msp-btn msp-btn--ghost msp-x" data-close aria-label="Close preview">✕</button>' +
          '</div>' +
        '</div>' +
        '<div class="msp-full__stage"><div class="msp-full__device" data-dev="desktop"><iframe title="' + esc(title) + '" src="' + esc(url) + '"></iframe></div></div>' +
      '</div>');
    document.body.appendChild(root);
    var dev = root.querySelector('.msp-full__device');
    root.querySelectorAll('[data-dev]').forEach(function (b) {
      if (b === dev) return;
      b.addEventListener('click', function () {
        root.querySelectorAll('.msp-seg [data-dev]').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        dev.setAttribute('data-dev', b.getAttribute('data-dev'));
      });
    });
    var release;
    function close() { release(); root.remove(); if (opts.onClose) opts.onClose(); }
    release = trapClose(root, close);
    root.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
    if (opts.bind) opts.bind(root, close);
    setTimeout(function () { var b = root.querySelector('.msp-seg .is-on'); if (b) b.focus(); }, 30);
    return { close: close, root: root };
  }

  /* ---------- picker modal ---------- */
  function open(opts) {
    opts = opts || {};
    var root = el(
      '<div class="msp" role="dialog" aria-modal="true" aria-label="' + esc(opts.title || 'Insert a section') + '">' +
        '<div class="msp__panel">' +
          '<header class="msp__head">' +
            '<div><h2>' + esc(opts.title || 'Insert a section') + '</h2><p>Ready-made, responsive sections that follow your brand colours, fonts and dark mode.</p></div>' +
            '<button type="button" class="msp-btn msp-btn--ghost msp-x" data-close aria-label="Close">✕</button>' +
          '</header>' +
          '<div class="msp__tools">' +
            '<input type="search" class="msp__q" placeholder="Search sections… (e.g. pricing, faq, team)" aria-label="Search sections">' +
            '<div class="msp__chips" role="tablist" aria-label="Categories"></div>' +
          '</div>' +
          '<div class="msp__body">' +
            '<div class="msp__grid" role="listbox" aria-label="Sections"><p class="msp__loading">Loading sections…</p></div>' +
            '<aside class="msp__side" aria-live="polite">' +
              '<div class="msp__live"><div class="msp__live-scale"><iframe title="Section preview" tabindex="-1" loading="lazy"></iframe></div><span class="msp__live-empty">Hover or select a section to preview it live</span></div>' +
              '<div class="msp__info"><h3></h3><p></p><div class="msp__tags"></div></div>' +
              '<div class="msp__act">' +
                '<button type="button" class="msp-btn msp-btn--ghost" data-full disabled>Full preview</button>' +
                '<button type="button" class="msp-btn msp-btn--primary" data-insert disabled>' + esc(opts.insertLabel || 'Insert section') + '</button>' +
              '</div>' +
            '</aside>' +
          '</div>' +
        '</div>' +
      '</div>');
    document.body.appendChild(root);
    var grid = root.querySelector('.msp__grid');
    var chips = root.querySelector('.msp__chips');
    var q = root.querySelector('.msp__q');
    var frame = root.querySelector('.msp__live iframe');
    var live = root.querySelector('.msp__live');
    var info = root.querySelector('.msp__info');
    var btnIns = root.querySelector('[data-insert]');
    var btnFull = root.querySelector('[data-full]');
    var items = [], cat = '', selected = null, hoverT = null, shownId = '';

    var release;
    function close() { release(); root.remove(); }
    release = trapClose(root, close);
    root.addEventListener('click', function (e) { if (e.target === root) close(); });
    root.querySelector('[data-close]').addEventListener('click', close);

    function show(it) {
      if (!it) return;
      info.querySelector('h3').textContent = it.name;
      info.querySelector('p').textContent = it.description || '';
      info.querySelector('.msp__tags').innerHTML = (it.tags || []).slice(0, 6).map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('');
      if (live.clientWidth) live.style.setProperty('--s', String(live.clientWidth / 1280));
      if (shownId !== it.id) {
        shownId = it.id;
        live.classList.add('is-loading');
        frame.onload = function () { live.classList.remove('is-loading'); live.classList.add('has-frame'); };
        frame.src = it.live;
      }
    }
    function select(it, card) {
      selected = it;
      grid.querySelectorAll('.msp-card').forEach(function (c) { c.setAttribute('aria-selected', c === card ? 'true' : 'false'); });
      btnIns.disabled = false; btnFull.disabled = false;
      show(it);
    }
    function insert(it) {
      if (!it) return;
      close();
      if (opts.onInsert) opts.onInsert(it.html, it);
    }
    btnIns.addEventListener('click', function () { insert(selected); });
    btnFull.addEventListener('click', function () {
      if (!selected) return;
      var it = selected;
      preview(it, {
        actionsHtml: '<button type="button" class="msp-btn msp-btn--primary" data-pv-insert>' + esc(opts.insertLabel || 'Insert section') + '</button>',
        bind: function (pv, closePv) {
          pv.querySelector('[data-pv-insert]').addEventListener('click', function () { closePv(); insert(it); });
        }
      });
    });

    function render() {
      var term = q.value.trim().toLowerCase();
      var list = items.filter(function (it) {
        if (cat && it.category !== cat) return false;
        if (!term) return true;
        return (it.name + ' ' + it.description + ' ' + (it.tags || []).join(' ') + ' ' + it.category).toLowerCase().indexOf(term) !== -1;
      });
      if (!list.length) { grid.innerHTML = '<p class="msp__loading">No sections match “' + esc(term) + '”.</p>'; return; }
      grid.innerHTML = '';
      list.forEach(function (it) {
        var card = el('<button type="button" class="msp-card" role="option" aria-selected="false">' +
          '<span class="msp-card__img"><img src="' + esc(it.thumb || it.preview) + '" alt="" loading="lazy" decoding="async" width="400" height="250"></span>' +
          '<span class="msp-card__name">' + esc(it.name) + '</span>' +
          '<span class="msp-card__cat">' + esc(labels[it.category] || it.category) + '</span></button>');
        card.addEventListener('mouseenter', function () { clearTimeout(hoverT); hoverT = setTimeout(function () { show(it); }, 180); });
        card.addEventListener('mouseleave', function () { clearTimeout(hoverT); if (selected) hoverT = setTimeout(function () { show(selected); }, 400); });
        card.addEventListener('focus', function () { show(it); });
        card.addEventListener('click', function () { select(it, card); });
        card.addEventListener('dblclick', function () { insert(it); });
        card.addEventListener('keydown', function (e) { if (e.key === 'Enter' && selected === it) { e.preventDefault(); insert(it); } });
        grid.appendChild(card);
      });
    }
    var labels = {};
    function chipsRender(cats) {
      var html = '<button type="button" role="tab" class="is-on" data-cat="">All</button>';
      Object.keys(cats).forEach(function (k) { html += '<button type="button" role="tab" data-cat="' + esc(k) + '">' + esc(cats[k]) + '</button>'; });
      chips.innerHTML = html;
      chips.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          cat = b.getAttribute('data-cat');
          chips.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
          render();
        });
      });
    }
    q.addEventListener('input', render);
    load(false).then(function (data) {
      items = data.items || [];
      labels = data.categories || {};
      var present = {};
      items.forEach(function (it) { present[it.category] = labels[it.category] || it.category; });
      var ordered = {};
      Object.keys(labels).forEach(function (k) { if (present[k]) ordered[k] = labels[k]; });
      Object.keys(present).forEach(function (k) { if (!ordered[k]) ordered[k] = present[k]; });
      chipsRender(ordered);
      if (opts.category && ordered[opts.category]) {
        var b = chips.querySelector('[data-cat="' + opts.category + '"]'); if (b) b.click(); else render();
      } else render();
      if (opts.select) {
        var it = items.filter(function (x) { return x.id === opts.select; })[0];
        if (it) select(it, null);
      }
    }).catch(function () {
      grid.innerHTML = '<p class="msp__loading">Could not load the section library. Please reload and try again.</p>';
    });
    setTimeout(function () { q.focus(); }, 30);
    return { close: close };
  }

  window.MovaSectionPicker = { open: open, load: load, preview: preview };
})();
