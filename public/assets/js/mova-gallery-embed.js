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
    if (opts.aspect && opts.aspect !== 'auto') {
      root.style.setProperty('--mova-g-aspect', opts.aspect);
      root.classList.add('has-aspect');
    } else {
      root.classList.remove('has-aspect');
    }
    if (opts.overlay_color) root.style.setProperty('--mova-g-overlay', opts.overlay_color);
    if (opts.name_padding) root.style.setProperty('--mova-g-name-pad', opts.name_padding);
  }

  function validItems(items) {
    return (items || []).filter(function (it) {
      return it && it.url && !/\/mova-uploads\/?$/.test(it.url);
    });
  }

  function nameLabel(it) {
    return (it && (it.alt || it.caption)) || '';
  }
  function nameOverlay(it, opts, mode) {
    mode = mode || opts.name_mode || (opts.hover_name ? 'hover' : 'hide');
    if (mode === 'hide' || mode === 'caption') return null;
    var name = nameLabel(it);
    if (!name) return null;
    var cls = mode === 'always' ? 'mova-g-name is-always' : 'mova-g-name is-hover';
    return el('div', cls, { text: name });
  }
  function nameCaption(it, opts) {
    var mode = opts.name_mode || (opts.hover_name ? 'hover' : 'hide');
    if (mode !== 'caption') return null;
    var name = nameLabel(it);
    if (!name) return null;
    var node = el('div', 'mova-g-name-caption', { text: name });
    if (opts.name_padding) node.style.padding = opts.name_padding;
    return node;
  }

  function renderCarousel(root, data) {
    var opts = data.options || {};
    var items = validItems(data.items);
    if (!items.length) {
      root.innerHTML = '<div class="mova-g-empty mova-gallery-empty" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/></svg><strong>Gallery coming soon</strong><span>Photos will appear here once they are added.</span></div>';
      return;
    }
    applyShell(root, opts);
    var visible = Math.max(1, Math.min(6, parseInt(opts.slides_visible, 10) || 1));
    var wrap = el('div', 'mova-g-carousel' + (visible > 1 ? ' is-multi' : ''));
    wrap.style.setProperty('--mova-g-visible', String(visible));
    var track = el('div', 'mova-g-track');
    var mode = opts.name_mode || (opts.hover_name ? 'hover' : 'hide');
    if (mode === 'caption') wrap.classList.add('has-captions');
    items.forEach(function (it, i) {
      var slide = el('div', 'mova-g-slide');
      slide.appendChild(el('img', '', { src: it.url, alt: it.alt || '', loading: i < visible ? 'eager' : 'lazy' }));
      if (opts.overlay_color) slide.style.setProperty('--mova-g-overlay', opts.overlay_color);
      if (opts.name_padding) slide.style.setProperty('--mova-g-name-pad', opts.name_padding);
      var hover = nameOverlay(it, opts);
      if (hover) {
        if (opts.name_padding) hover.style.padding = opts.name_padding;
        slide.appendChild(hover);
      }
      var unit = el('div', 'mova-g-slide-unit');
      unit.appendChild(slide);
      var cap = nameCaption(it, opts);
      if (cap) {
        unit.appendChild(cap);
      } else if (it.caption && mode === 'hide') {
        /* gallery item caption only when names are hidden */
        unit.appendChild(el('div', 'mova-g-caption', { text: it.caption }));
      }
      track.appendChild(unit);
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
      root.innerHTML = '<div class="mova-g-empty mova-gallery-empty" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/></svg><strong>Gallery coming soon</strong><span>Photos will appear here once they are added.</span></div>';
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
    var nameMode = opts.name_mode || (opts.hover_name ? 'hover' : 'hide');
    items.forEach(function (it, i) {
      var cell = el('figure', 'mova-g-cell' + (mode === 'collage' ? ' mova-g-span-' + ((i % 5) + 1) : ''));
      cell.appendChild(el('img', '', { src: it.url, alt: it.alt || '', loading: 'lazy' }));
      if (opts.overlay_color) cell.style.setProperty('--mova-g-overlay', opts.overlay_color);
      if (opts.name_padding) cell.style.setProperty('--mova-g-name-pad', opts.name_padding);
      var hover = nameOverlay(it, opts);
      if (hover) {
        if (opts.name_padding) hover.style.padding = opts.name_padding;
        cell.appendChild(hover);
      }
      grid.appendChild(cell);
      var cap = nameCaption(it, opts);
      if (cap) {
        /* caption sits under the cell */
        var wrapCell = el('div', 'mova-g-cell-wrap');
        // re-parent: replace last child
        grid.removeChild(cell);
        wrapCell.appendChild(cell);
        wrapCell.appendChild(cap);
        grid.appendChild(wrapCell);
      } else if (it.caption && nameMode === 'hide') {
        cell.appendChild(el('figcaption', 'mova-g-caption', { text: it.caption }));
      }
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
