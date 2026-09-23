(function () {
  'use strict';

  var root = document.querySelector('.mova-gallery');
  if (!root) return;

  var lightbox = document.getElementById('mova-lightbox');
  var lbImg = document.getElementById('mova-lightbox-img');
  var lbCaption = document.getElementById('mova-lightbox-caption');
  var lbDate = document.getElementById('mova-lightbox-date');
  var slideControls = document.getElementById('mova-lightbox-slide-controls');

  var items = [];
  var index = 0;
  var slideTimer = null;
  var slidePlaying = false;
  var SLIDE_MS = 4000;

  function collectItems(scope) {
    var tiles = (scope || root).querySelectorAll('[data-lightbox-index]');
    var list = [];
    tiles.forEach(function (tile) {
      list.push({
        el: tile,
        url: tile.getAttribute('data-url') || '',
        alt: tile.getAttribute('data-alt') || '',
        caption: tile.getAttribute('data-caption') || '',
        date: tile.getAttribute('data-date') || '',
      });
    });
    // sort by index attribute within continuous stream
    list.sort(function (a, b) {
      return (
        parseInt(a.el.getAttribute('data-lightbox-index'), 10) -
        parseInt(b.el.getAttribute('data-lightbox-index'), 10)
      );
    });
    return list;
  }

  function refreshItems() {
    items = collectItems(root);
  }

  function openAt(i) {
    refreshItems();
    if (!items.length) return;
    index = ((i % items.length) + items.length) % items.length;
    showCurrent();
    lightbox.hidden = false;
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLb() {
    stopSlideshow();
    lightbox.hidden = true;
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function showCurrent() {
    var it = items[index];
    if (!it) return;
    lbImg.src = it.url;
    lbImg.alt = it.alt || '';
    lbCaption.textContent = it.caption || it.alt || '';
    lbDate.textContent = it.date || '';
  }

  function next() {
    if (!items.length) return;
    index = (index + 1) % items.length;
    showCurrent();
  }

  function prev() {
    if (!items.length) return;
    index = (index - 1 + items.length) % items.length;
    showCurrent();
  }

  function startSlideshow() {
    refreshItems();
    if (!items.length) return;
    slidePlaying = true;
    if (slideControls) slideControls.hidden = false;
    openAt(index || 0);
    stopSlideshow(true);
    slideTimer = setInterval(function () {
      next();
    }, SLIDE_MS);
    updateSlideBtn();
  }

  function stopSlideshow(keepPlayingFlag) {
    if (slideTimer) {
      clearInterval(slideTimer);
      slideTimer = null;
    }
    if (!keepPlayingFlag) {
      slidePlaying = false;
      updateSlideBtn();
    }
  }

  function toggleSlideshow() {
    if (slidePlaying && slideTimer) {
      stopSlideshow();
    } else {
      startSlideshow();
    }
  }

  function updateSlideBtn() {
    var btn = slideControls && slideControls.querySelector('[data-lb-slide-toggle]');
    if (!btn) return;
    btn.innerHTML = slidePlaying
      ? '<i class="fa-solid fa-pause"></i>'
      : '<i class="fa-solid fa-play"></i>';
    btn.setAttribute('aria-label', slidePlaying ? 'Pause slideshow' : 'Play slideshow');
  }

  // Tile clicks
  root.addEventListener('click', function (e) {
    var tile = e.target.closest('[data-lightbox-index]');
    if (tile && root.contains(tile)) {
      e.preventDefault();
      var i = parseInt(tile.getAttribute('data-lightbox-index'), 10) || 0;
      openAt(i);
    }
  });

  // Lightbox controls
  if (lightbox) {
    lightbox.querySelector('[data-lb-close]')?.addEventListener('click', closeLb);
    lightbox.querySelector('[data-lb-prev]')?.addEventListener('click', function () {
      stopSlideshow();
      prev();
    });
    lightbox.querySelector('[data-lb-next]')?.addEventListener('click', function () {
      stopSlideshow();
      next();
    });
    lightbox.querySelector('[data-lb-slide-toggle]')?.addEventListener('click', toggleSlideshow);
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLb();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (lightbox && !lightbox.hidden) {
      if (e.key === 'Escape') closeLb();
      if (e.key === 'ArrowRight') {
        stopSlideshow();
        next();
      }
      if (e.key === 'ArrowLeft') {
        stopSlideshow();
        prev();
      }
    }
  });

  // Slideshow button
  root.querySelector('[data-slideshow-start]')?.addEventListener('click', function () {
    index = 0;
    startSlideshow();
  });

  // Sidebar mobile
  root.querySelector('[data-sidebar-open]')?.addEventListener('click', function () {
    root.classList.add('is-sidebar-open');
  });
  root.querySelector('[data-sidebar-close]')?.addEventListener('click', function () {
    root.classList.remove('is-sidebar-open');
  });

  // See more (stream)
  var moreBtn = root.querySelector('[data-load-more]');
  var nextBefore = root.getAttribute('data-next-before') || '';
  var hasMore = root.getAttribute('data-has-more') === '1';

  function monthLabel(ym) {
    if (!ym || ym.length < 7) return 'Undated';
    var d = new Date(ym + '-01T00:00:00');
    if (isNaN(d.getTime())) return ym;
    return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
  }

  function formatDate(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function appendStream(payload) {
    var streamEl = document.getElementById('mova-gallery-stream');
    if (!streamEl || !payload || !payload.items) return;

    var existing = streamEl.querySelectorAll('[data-lightbox-index]').length;
    var groups = {};
    payload.items.forEach(function (img) {
      var key = (img.created_at || '').substring(0, 7) || 'unknown';
      if (!groups[key]) groups[key] = [];
      groups[key].push(img);
    });

    Object.keys(groups).forEach(function (ym) {
      var titleText = monthLabel(ym);
      var headings = streamEl.querySelectorAll('.mova-gallery-section-title');
      var grid = null;
      for (var h = 0; h < headings.length; h++) {
        if (headings[h].textContent.trim() === titleText) {
          grid = headings[h].nextElementSibling;
          break;
        }
      }
      if (!grid || !grid.classList.contains('mova-gallery-grid')) {
        var h2 = document.createElement('h2');
        h2.className = 'mova-gallery-section-title';
        h2.textContent = titleText;
        grid = document.createElement('div');
        grid.className = 'mova-gallery-grid';
        grid.setAttribute('data-gallery-grid', '');
        grid.setAttribute('data-context', 'stream');
        streamEl.appendChild(h2);
        streamEl.appendChild(grid);
      }
      groups[ym].forEach(function (img) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mova-gallery-tile';
        btn.setAttribute('data-lightbox-index', String(existing));
        btn.setAttribute('data-url', img.url || '');
        btn.setAttribute('data-thumb', img.thumb_url || '');
        btn.setAttribute('data-alt', img.alt || '');
        btn.setAttribute('data-caption', img.alt || '');
        btn.setAttribute('data-date', formatDate(img.created_at));
        var im = document.createElement('img');
        im.src = img.thumb_url || img.url || '';
        im.alt = img.alt || '';
        im.loading = 'lazy';
        btn.appendChild(im);
        grid.appendChild(btn);
        existing++;
      });
    });

    nextBefore = payload.next_before || '';
    hasMore = !!payload.has_more;
    root.setAttribute('data-next-before', nextBefore || '');
    root.setAttribute('data-has-more', hasMore ? '1' : '0');
    if (!hasMore && moreBtn) {
      moreBtn.parentElement && moreBtn.parentElement.remove();
    }
  }

  if (moreBtn) {
    moreBtn.addEventListener('click', function () {
      if (!hasMore || !nextBefore) return;
      moreBtn.disabled = true;
      moreBtn.textContent = 'Loading…';
      fetch('/gallery/api/stream?before=' + encodeURIComponent(nextBefore))
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          appendStream(data);
          moreBtn.disabled = false;
          moreBtn.textContent = 'See more';
          if (!hasMore) {
            moreBtn.parentElement && moreBtn.parentElement.remove();
          }
        })
        .catch(function () {
          moreBtn.disabled = false;
          moreBtn.textContent = 'See more';
        });
    });
  }

  refreshItems();
})();
