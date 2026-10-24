/*
 * Mova UI kit — mova-ui.js (core, all themes, ~4 KB, no dependencies, CSP-safe)
 * - Scroll reveals: tags below-the-fold cards / sections / prose blocks with the core
 *   [data-reveal] attribute (sections.css + sections.js do the animation). Content is
 *   never hidden without JS, and nothing animates under prefers-reduced-motion.
 * - Blog feeds rendered later (infinite scroll, embeds) are picked up automatically.
 * - Posts: reading progress, an automatic "On this page" list (3+ headings), copy-link.
 * - Autoplay videos pause off-screen and never autoplay under reduced motion.
 * Opt out per page with class "mx-static" on <body> or any wrapper.
 */
(function () {
  'use strict';
  var d = document, w = window, root = d.documentElement;
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || d).querySelectorAll(sel)); }
  function skip(el) {
    return el.hasAttribute('data-reveal') || el.closest('.mx-static, .presence-body, [data-presence-body], .presence-reveal, [data-mova-dev-fallback]') ||
      (el.closest('[id^="mova-dev-root"], .mova-dev-root') && !el.closest('.mx-enhance'));
  }
  function belowFold(el) { var r = el.getBoundingClientRect(); return r.top > (w.innerHeight || 800) * 0.92; }

  /* ---------- Reveal tagging ---------- */
  var GROUPS = [
    ['.mova-blog-grid', '.mova-blog-card', ''],
    ['.mx-related__grid', '.mx-related__card', ''],
    ['.mx-post-extras', ':scope > *', ''],
    ['.mova-prose', ':scope > h2, :scope > h3, :scope > figure, :scope > blockquote, :scope > table, :scope > .mova-blog, :scope > .mova-gallery', ''],
    ['.mx-enhance', ':scope > section, :scope > div > section, :scope > header + section', '']
  ];
  function tag(ctx) {
    var n = 0;
    GROUPS.forEach(function (g) {
      $all(g[0], ctx).concat(ctx && ctx.matches && ctx.matches(g[0]) ? [ctx] : []).forEach(function (wrap) {
        var i = 0;
        var kids; try { kids = $all(g[1], wrap); } catch (e) { kids = []; }
        kids.forEach(function (el) {
          if (skip(el) || !belowFold(el)) return;
          el.setAttribute('data-reveal', g[2]);
          el.style.setProperty('--mx-d', Math.min(i++, 5) * 70 + 'ms');
          n++;
        });
      });
    });
    return n;
  }
  function kick(ctx) { if (w.MovaSections && w.MovaSections.init) w.MovaSections.init(ctx); }

  /* ---------- Videos ---------- */
  function videos(ctx) {
    $all('video[autoplay]', ctx).forEach(function (v) {
      if (v.dataset.mxVid) return; v.dataset.mxVid = '1';
      if (reduce) { v.removeAttribute('autoplay'); try { v.pause(); } catch (e) {} v.setAttribute('controls', ''); return; }
      if (!('IntersectionObserver' in w)) return;
      new IntersectionObserver(function (es) {
        es.forEach(function (en) {
          var p;
          if (en.isIntersecting) { p = v.play && v.play(); if (p && p.catch) p.catch(function () {}); }
          else if (!v.paused) { v.pause(); }
        });
      }, { threshold: 0.15 }).observe(v);
    });
  }

  /* ---------- Post helpers ---------- */
  function slug(t) { return t.toLowerCase().replace(/<[^>]+>/g, '').replace(/[^a-z0-9\u00c0-\u024f]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'section'; }
  function toc() {
    var body = d.querySelector('[data-mx-toc]');
    if (!body || body.querySelector('[id^="mova-dev-root"], .mova-dev-fallback') || d.querySelector('.mx-toc')) return;
    var hs = $all('h2, h3', body).filter(function (h) { return !h.closest('.mova-blog, .mx-related, nav, aside, details') && h.textContent.trim(); });
    if (hs.length < 3) return;
    var used = {}, det = d.createElement('details'), sum = d.createElement('summary'), ol = d.createElement('ol'), links = [];
    det.className = 'mx-toc'; sum.textContent = 'On this page'; det.appendChild(sum); det.appendChild(ol);
    if ((w.innerWidth || 1024) >= 700) det.open = true;
    hs.forEach(function (h) {
      if (!h.id) { var s = slug(h.textContent), k = s, i = 2; while (used[k] || d.getElementById(k)) k = s + '-' + i++; h.id = k; }
      used[h.id] = 1;
      var li = d.createElement('li'), a = d.createElement('a');
      if (h.tagName === 'H3') li.className = 'is-sub';
      a.href = '#' + h.id; a.textContent = h.textContent.trim().replace(/\s+/g, ' ');
      li.appendChild(a); ol.appendChild(li); links.push([h, a]);
    });
    body.parentNode.insertBefore(det, body);
    if ('IntersectionObserver' in w) {
      var cur = null;
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (en) {
          if (!en.isIntersecting) return;
          links.forEach(function (p) { if (p[0] === en.target) { if (cur) cur.classList.remove('is-active'); cur = p[1]; cur.classList.add('is-active'); } });
        });
      }, { rootMargin: '0px 0px -70% 0px' });
      links.forEach(function (p) { io.observe(p[0]); });
    }
  }
  function progress() {
    var bar = d.querySelector('.mx-progress > span'), art = d.querySelector('.article--post');
    if (!bar || !art || reduce) return;
    var tick = false;
    function upd() {
      tick = false;
      var r = art.getBoundingClientRect(), h = r.height - (w.innerHeight || 1);
      var p = h > 0 ? Math.min(1, Math.max(0, -r.top / h)) : 1;
      bar.style.setProperty('--mx-read', p.toFixed(4));
    }
    w.addEventListener('scroll', function () { if (!tick) { tick = true; w.requestAnimationFrame(upd); } }, { passive: true });
    upd();
  }
  function copy() {
    $all('[data-mx-copy]').forEach(function (b) {
      if (!navigator.clipboard) return;
      b.hidden = false;
      b.addEventListener('click', function () {
        var lbl = b.querySelector('span');
        navigator.clipboard.writeText(b.getAttribute('data-mx-copy')).then(function () {
          b.classList.add('is-done'); if (lbl) lbl.textContent = 'Copied';
          setTimeout(function () { b.classList.remove('is-done'); if (lbl) lbl.textContent = 'Copy link'; }, 2000);
        }, function () {});
      });
    });
  }

  function start() {
    if (!root.classList.contains('mx-static') && !(d.body && d.body.classList.contains('mx-static'))) {
      toc();
      tag(d);
      if ('MutationObserver' in w) {
        var pending = null;
        new MutationObserver(function (ms) {
          ms.forEach(function (m) {
            Array.prototype.forEach.call(m.addedNodes, function (nd) {
              if (nd.nodeType !== 1) return;
              if (nd.matches('.mova-blog-card, .mova-blog, .mova-blog-grid, .mx-related') || nd.querySelector('.mova-blog-card, video[autoplay]')) {
                var wrap = nd.parentNode || d;
                if (tag(wrap)) { pending = pending || setTimeout(function () { pending = null; kick(); }, 30); }
                videos(wrap);
              }
            });
          });
        }).observe(d.body, { childList: true, subtree: true });
      }
    } else { toc(); }
    videos(d);
    progress();
    copy();
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
