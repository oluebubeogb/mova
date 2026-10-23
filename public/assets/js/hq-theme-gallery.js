/**
 * Mova theme gallery — device-frame mockups, hover live preview, full-screen
 * "Preview on my site" (desktop / tablet / mobile) and one-click apply.
 * Used by HQ → Design → Themes and the Quick setup theme step.
 *
 *   MovaThemeGallery.mount(el, {
 *     themes: [{slug,name,description,tags,best_for,palette,fonts,shots:{card,desktop,mobile},active}],
 *     liveUrl: function (slug) { return '/?mova_theme=' + slug },   // what the live iframes show
 *     previewTitle: function (t) {},                                 // optional
 *     applyLabel: 'Apply theme', appliedLabel: 'Active',
 *     selected: 'presence',                                          // wizard: highlight a choice
 *     onApply: function (slug) { return Promise }                    // resolve → card becomes active
 *   })
 * Requires hq-section-picker.js (for the full-screen device preview).
 */
(function () {
  'use strict';
  var hoverOk = window.matchMedia && window.matchMedia('(hover: hover)').matches;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function toast(msg) {
    var t = document.querySelector('.mtg-toast');
    if (!t) { t = document.createElement('div'); t.className = 'mtg-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('is-on');
    clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('is-on'); }, 2600);
  }

  function mount(root, o) {
    o = o || {};
    var themes = o.themes || [];
    var applyLabel = o.applyLabel || 'Apply theme';
    var appliedLabel = o.appliedLabel || 'Active theme';
    var live = o.liveUrl || function (slug) { return '/?mova_theme=' + encodeURIComponent(slug); };
    root.classList.add('mtg-grid');
    root.innerHTML = themes.map(function (t) {
      var pal = t.palette || {};
      var sw = ['primary', 'accent', 'background', 'text'].map(function (k) { return pal[k] ? '<i style="background:' + esc(pal[k]) + '"></i>' : ''; }).join('');
      var fonts = t.fonts || {};
      var meta = (t.best_for || []).slice(0, 3).map(function (b) { return '<span>' + esc(b) + '</span>'; }).join('') +
        (fonts.heading ? '<span class="mtg-font" style="font-family:\'' + esc(fonts.heading) + '\',sans-serif">Aa ' + esc(fonts.heading) + '</span>' : '');
      return '<article class="mtg-card' + (t.active ? ' is-active' : '') + (o.selected === t.slug ? ' is-selected' : '') + '" data-slug="' + esc(t.slug) + '">' +
        (t.active ? '<span class="mtg-badge">✓ ' + esc(appliedLabel) + '</span>' : '') +
        (t.slug === 'presence' ? '<span class="mtg-badge mtg-badge--default">Mova default</span>' : '') +
        '<div class="mtg-stage" role="button" tabindex="0" aria-label="Preview ' + esc(t.name) + ' full screen">' +
          (t.shots && t.shots.card ? '<img src="' + esc(t.shots.card) + '" alt="' + esc(t.name) + ' theme on desktop and mobile" loading="lazy" decoding="async">' : '') +
          '<div class="mtg-live" aria-hidden="true"><div class="mtg-desk"><div class="mtg-dscreen"></div></div><div class="mtg-phone"><div class="mtg-pscreen"></div></div></div>' +
          '<span class="mtg-spin">Loading live preview…</span>' +
        '</div>' +
        '<div class="mtg-body">' +
          '<div class="mtg-title"><h3>' + esc(t.name) + '</h3><span class="mtg-swatches" aria-hidden="true">' + sw + '</span></div>' +
          '<p class="mtg-desc">' + esc(t.description || '') + '</p>' +
          '<div class="mtg-meta">' + meta + '</div>' +
          '<div class="mtg-actions">' +
            '<button type="button" class="msp-btn msp-btn--ghost" data-preview>Preview on my site</button>' +
            '<button type="button" class="msp-btn msp-btn--primary" data-apply' + (t.active && !o.selected ? ' disabled' : '') + '>' + esc(t.active && !o.selected ? appliedLabel : applyLabel) + '</button>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');

    function bySlug(s) { for (var i = 0; i < themes.length; i++) if (themes[i].slug === s) return themes[i]; return null; }

    /* live device frames: scale fixed-size iframes into the mock screens */
    function goLive(card) {
      if (card._live) { card.classList.add('is-live'); return; }
      card._live = true;
      var slug = card.getAttribute('data-slug');
      var url = live(slug);
      var desk = card.querySelector('.mtg-dscreen'), phone = card.querySelector('.mtg-pscreen');
      card.classList.add('is-loading');
      var pending = 2;
      function mk(box, w, h) {
        var f = document.createElement('iframe');
        f.className = 'mtg-frame'; f.tabIndex = -1; f.title = ''; f.setAttribute('aria-hidden', 'true');
        f.width = w; f.height = h; f.style.width = w + 'px'; f.style.height = h + 'px';
        f.src = url;
        f.addEventListener('load', function () { if (--pending <= 0) { card.classList.remove('is-loading'); if (card._want) card.classList.add('is-live'); } });
        box.appendChild(f);
        function fit() { var s = box.clientWidth / w; f.style.transform = 'scale(' + s + ')'; }
        fit(); window.addEventListener('resize', fit);
      }
      mk(desk, 1440, 1100);
      mk(phone, 390, 844);
    }
    root.querySelectorAll('.mtg-card').forEach(function (card) {
      var t = null;
      if (hoverOk) {
        card.addEventListener('mouseenter', function () { card._want = true; t = setTimeout(function () { goLive(card); if (!card.classList.contains('is-loading')) card.classList.add('is-live'); }, 380); });
        card.addEventListener('mouseleave', function () { card._want = false; clearTimeout(t); card.classList.remove('is-live'); card.classList.remove('is-loading'); });
      }
      var stage = card.querySelector('.mtg-stage');
      stage.addEventListener('click', function () { full(card.getAttribute('data-slug')); });
      stage.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); full(card.getAttribute('data-slug')); } });
      card.querySelector('[data-preview]').addEventListener('click', function () { full(card.getAttribute('data-slug')); });
      card.querySelector('[data-apply]').addEventListener('click', function () { apply(card.getAttribute('data-slug')); });
    });

    function markActive(slug) {
      root.querySelectorAll('.mtg-card').forEach(function (c) {
        var on = c.getAttribute('data-slug') === slug;
        c.classList.toggle(o.selected !== undefined ? 'is-selected' : 'is-active', on);
        var b = c.querySelector('[data-apply]');
        if (o.selected === undefined) {
          b.disabled = on; b.textContent = on ? appliedLabel : applyLabel;
          var badge = c.querySelector('.mtg-badge:not(.mtg-badge--default)');
          if (on && !badge) { badge = document.createElement('span'); badge.className = 'mtg-badge'; badge.textContent = '✓ ' + appliedLabel; c.insertBefore(badge, c.firstChild); }
          if (!on && badge) badge.remove();
        } else {
          b.textContent = on ? 'Selected ✓' : applyLabel;
        }
      });
    }

    function apply(slug, after) {
      var th = bySlug(slug);
      var p = o.onApply ? o.onApply(slug) : Promise.resolve();
      return Promise.resolve(p).then(function (ok) {
        if (ok === false) return;
        markActive(slug);
        toast((th ? th.name : slug) + (o.selected !== undefined ? ' selected' : ' is now live on your site'));
        if (after) after();
      }).catch(function (e) { toast(e && e.message ? e.message : 'Could not apply the theme'); });
    }

    function full(slug) {
      var th = bySlug(slug) || { name: slug };
      if (!window.MovaSectionPicker) { window.open(live(slug), '_blank'); return; }
      window.MovaSectionPicker.preview(live(slug), {
        title: (o.previewTitle ? o.previewTitle(th) : th.name + ' — preview on my site'),
        actionsHtml: '<button type="button" class="msp-btn msp-btn--primary" data-apply-full>' + esc(o.selected !== undefined ? 'Use ' + th.name : 'Apply ' + th.name) + '</button>',
        bind: function (r, close) {
          r.querySelector('[data-apply-full]').addEventListener('click', function () { apply(slug, close); });
        }
      });
    }
    return { apply: apply, preview: full, markActive: markActive };
  }
  window.MovaThemeGallery = { mount: mount, toast: toast };
})();
