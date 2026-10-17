/**
 * Mova in-page gallery embeds — mounts .mova-gallery[data-gallery]
 * Layouts: carousel (default), columns, grid, collage
 */
(function () {
  'use strict';

  function qs(sel, el) { return (el || document).querySelector(sel); }
  function qsa(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }

  function fetchPayload(id, layout, opts) {
    var url = '/gallery/api/embed/' + encodeURIComponent(id);
    var params = [];
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

  function renderCarousel(root, data) {
    var opts = data.options || {};
    var items = data.items || [];
    if (!items.length) {
      root.innerHTML = '<p class="mova-g-empty">No images</p>';
      return;
    }
    var wrap = el('div', 'mova-g-carousel');
    var track = el('div', 'mova-g-track');
    items = items.filter(function (it) { return it && it.url && !/\/mova-uploads\/?$/.test(it.url); });
    if (!items.length) {
      root.innerHTML = '<p class="mova-g-empty">No images</p>';
      return;
    }
    items.forEach(function (it, i) {
      var slide = el('div', 'mova-g-slide' + (i === 0 ? ' is-active' : ''));
      slide.appendChild(el('img', '', { src: it.url, alt: it.alt || '', loading: i ? 'lazy' : 'eager' }));
      if (it.caption) slide.appendChild(el('div', 'mova-g-caption', { text: it.caption }));
      track.appendChild(slide);
    });
    wrap.appendChild(track);
    if (opts.arrows !== false && items.length > 1) {
      var prev = el('button', 'mova-g-nav mova-g-prev', { type: 'button', 'aria-label': 'Previous' });
      prev.innerHTML = '‹';
      var next = el('button', 'mova-g-nav mova-g-next', { type: 'button', 'aria-label': 'Next' });
      next.innerHTML = '›';
      wrap.appendChild(prev);
      wrap.appendChild(next);
    }
    var dotsWrap = null;
    if (opts.dots !== false && items.length > 1) {
      dotsWrap = el('div', 'mova-g-dots');
      items.forEach(function (_, i) {
        var d = el('button', 'mova-g-dot' + (i === 0 ? ' is-active' : ''), { type: 'button', 'aria-label': 'Slide ' + (i + 1) });
        d.dataset.i = String(i);
        dotsWrap.appendChild(d);
      });
      wrap.appendChild(dotsWrap);
    }
    root.appendChild(wrap);
    if (opts.aspect && opts.aspect !== 'auto') {
      wrap.style.setProperty('--mova-g-aspect', opts.aspect);
    }

    var idx = 0;
    function go(n) {
      idx = (n + items.length) % items.length;
      qsa('.mova-g-slide', wrap).forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
      if (dotsWrap) qsa('.mova-g-dot', dotsWrap).forEach(function (d, i) { d.classList.toggle('is-active', i === idx); });
    }
    var prevBtn = qs('.mova-g-prev', wrap);
    var nextBtn = qs('.mova-g-next', wrap);
    if (prevBtn) prevBtn.addEventListener('click', function () { go(idx - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(idx + 1); });
    if (dotsWrap) dotsWrap.addEventListener('click', function (e) {
      var t = e.target.closest('.mova-g-dot');
      if (t) go(parseInt(t.dataset.i, 10) || 0);
    });
    var timer = null;
    if (opts.autoplay && items.length > 1) {
      var ms = opts.interval || 5000;
      timer = setInterval(function () { go(idx + 1); }, ms);
      wrap.addEventListener('mouseenter', function () { clearInterval(timer); });
      wrap.addEventListener('mouseleave', function () { timer = setInterval(function () { go(idx + 1); }, ms); });
    }
  }

  function renderGrid(root, data, mode) {
    var opts = data.options || {};
    var items = data.items || [];
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
      if (it.caption) cell.appendChild(el('figcaption', 'mova-g-caption', { text: it.caption }));
      grid.appendChild(cell);
    });
    root.appendChild(grid);
  }

  function mount(node) {
    if (node.dataset.movaMounted) return;
    node.dataset.movaMounted = '1';
    var id = node.getAttribute('data-gallery');
    if (!id) return;
    var layout = node.getAttribute('data-layout') || 'carousel';
    var opts = {};
    try {
      if (node.getAttribute('data-opts')) opts = JSON.parse(node.getAttribute('data-opts'));
    } catch (e) {}
    node.innerHTML = '<div class="mova-g-loading">Loading…</div>';
    fetchPayload(id, layout, opts).then(function (data) {
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
