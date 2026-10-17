/**
 * Mova in-page gallery embeds — .mova-gallery[data-gallery]
 * Layouts: carousel, columns, grid, collage
 * Options: max_height, slides_visible, radius, hover_name, autoplay, …
 */
(function () {
  'use strict';

  function qs(sel, el) { return (el || document).querySelector(sel); }
  function qsa(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }

  function fetchPayload(id, layout, opts, preset) {
    var url = '/gallery/api/embed/' + encodeURIComponent(id);
    var params = [];
    if (preset) params.push('preset=' + encodeURIComponent(preset));
    if (layout) params.push('layout=' + encodeURIComponent(layout));
    if (opts && Object.keys(opts).length) params.push('opts=' + encodeURIComponent(JSON.stringify(opts)));
    if (params.length) url += '?' + params.join('&');
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('Gallery ' + id + ' unavailable');
      return r.json();
    });
  }

  function el(tag, cls, attrs) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    return n;
  }

  function applyShell(root, opts) {
    if (opts.max_height) root.style.setProperty('--mova-g-max-h', opts.max_height);
    if (opts.radius) root.style.setProperty('--mova-g-radius', opts.radius);
    if (opts.gap != null) root.style.setProperty('--mova-g-gap', opts.gap + 'px');
    if (opts.aspect && opts.aspect !== 'auto') root.style.setProperty('--mova-g-aspect', opts.aspect);
  }

  function validItems(items) {
    return (items || []).filter(function (it) {
      return it && it.url && !/\/mova-uploads\/?$/.test(it.url);
    });
  }

  function nameOverlay(it, opts) {
    if (!opts.hover_name) return null;
    var name = it.alt || it.caption || '';
    if (!name) return null;
    return el('div', 'mova-g-hover-name', { text: name });
  }

  function renderCarousel(root, data) {
    var opts = data.options || {};
    var items = validItems(data.items);
    if (!items.length) {
      root.innerHTML = '<p class="mova-g-empty">No images</p>';
      return;
    }
    applyShell(root, opts);
    var visible = Math.max(1, Math.min(6, parseInt(opts.slides_visible, 10) || 1));
    var wrap = el('div', 'mova-g-carousel' + (visible > 1 ? ' is-multi' : ''));
    wrap.style.setProperty('--mova-g-visible', String(visible));
    var track = el('div', 'mova-g-track');
    items.forEach(function (it, i) {
      var slide = el('div', 'mova-g-slide');
      slide.appendChild(el('img', '', { src: it.url, alt: it.alt || '', loading: i < visible ? 'eager' : 'lazy' }));
      var hover = nameOverlay(it, opts);
      if (hover) slide.appendChild(hover);
      if (it.caption && !opts.hover_name) slide.appendChild(el('div', 'mova-g-caption', { text: it.caption }));
      track.appendChild(slide);
    });
    wrap.appendChild(track);
    if (opts.arrows !== false && items.length > visible) {
      var prev = el('button', 'mova-g-nav mova-g-prev', { type: 'button', 'aria-label': 'Previous' });
      prev.textContent = '‹';
      var next = el('button', 'mova-g-nav mova-g-next', { type: 'button', 'aria-label': 'Next' });
      next.textContent = '›';
      wrap.appendChild(prev);
      wrap.appendChild(next);
    }
    var dotsWrap = null;
    var pages = Math.max(1, items.length - visible + 1);
    if (opts.dots !== false && pages > 1) {
      dotsWrap = el('div', 'mova-g-dots');
      for (var d = 0; d < pages; d++) {
        var dot = el('button', 'mova-g-dot' + (d === 0 ? ' is-active' : ''), { type: 'button', 'aria-label': 'Page ' + (d + 1) });
        dot.dataset.i = String(d);
        dotsWrap.appendChild(dot);
      }
      wrap.appendChild(dotsWrap);
    }
    root.appendChild(wrap);

    var idx = 0;
    function go(n) {
      idx = Math.max(0, Math.min(pages - 1, n));
      var slideW = 100 / visible;
      track.style.transform = 'translateX(' + (-idx * slideW) + '%)';
      if (dotsWrap) qsa('.mova-g-dot', dotsWrap).forEach(function (dot, i) {
        dot.classList.toggle('is-active', i === idx);
      });
    }
    go(0);
    var prevBtn = qs('.mova-g-prev', wrap);
    var nextBtn = qs('.mova-g-next', wrap);
    if (prevBtn) prevBtn.addEventListener('click', function () { go(idx - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(idx + 1); });
    if (dotsWrap) dotsWrap.addEventListener('click', function (e) {
      var t = e.target.closest('.mova-g-dot');
      if (t) go(parseInt(t.dataset.i, 10) || 0);
    });
    var timer = null;
    if (opts.autoplay && pages > 1) {
      var ms = opts.interval || 5000;
      timer = setInterval(function () { go(idx + 1 >= pages ? 0 : idx + 1); }, ms);
      wrap.addEventListener('mouseenter', function () { clearInterval(timer); });
      wrap.addEventListener('mouseleave', function () {
        timer = setInterval(function () { go(idx + 1 >= pages ? 0 : idx + 1); }, ms);
      });
    }
  }

  function renderGrid(root, data, mode) {
    var opts = data.options || {};
    var items = validItems(data.items);
    if (!items.length) {
      root.innerHTML = '<p class="mova-g-empty">No images</p>';
      return;
    }
    applyShell(root, opts);
    var cls = 'mova-g-grid';
    if (mode === 'columns') cls = 'mova-g-columns';
    if (mode === 'collage') cls = 'mova-g-collage';
    var grid = el('div', cls);
    if (opts.gap != null) grid.style.gap = opts.gap + 'px';
    if (mode === 'columns') grid.style.setProperty('--mova-g-cols', String(opts.columns || 3));
    if (mode === 'grid') grid.style.setProperty('--mova-g-min', (opts.min_width || 200) + 'px');
    items.forEach(function (it, i) {
      var cell = el('figure', 'mova-g-cell' + (mode === 'collage' ? ' mova-g-span-' + ((i % 5) + 1) : ''));
      cell.appendChild(el('img', '', { src: it.url, alt: it.alt || '', loading: 'lazy' }));
      var hover = nameOverlay(it, opts);
      if (hover) cell.appendChild(hover);
      if (it.caption && !opts.hover_name) cell.appendChild(el('figcaption', 'mova-g-caption', { text: it.caption }));
      grid.appendChild(cell);
    });
    root.appendChild(grid);
  }

  function mount(node) {
    if (node.dataset.movaMounted) return;
    node.dataset.movaMounted = '1';
    var id = node.getAttribute('data-gallery');
    if (!id) return;
    var layout = node.getAttribute('data-layout') || '';
    var preset = node.getAttribute('data-preset') || '';
    var opts = {};
    try {
      if (node.getAttribute('data-opts')) opts = JSON.parse(node.getAttribute('data-opts'));
    } catch (e) {}
    node.innerHTML = '<div class="mova-g-loading">Loading…</div>';
    fetchPayload(id, layout, opts, preset).then(function (data) {
      node.innerHTML = '';
      var lay = data.layout || layout || 'carousel';
      if (lay === 'carousel' || lay === 'slide') renderCarousel(node, data);
      else renderGrid(node, data, lay);
    }).catch(function (err) {
      node.innerHTML = '<p class="mova-g-empty">' + (err.message || 'Unavailable') + '</p>';
    });
  }

  function boot() {
    qsa('.mova-gallery[data-gallery]').forEach(mount);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.MovaGalleryEmbed = { boot: boot, mount: mount };
})();
