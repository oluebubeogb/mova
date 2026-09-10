/**
 * Presence — scroll-triggered “come alive” animations
 * - Elements with [data-presence] animate once when they enter the viewport
 * - Article body paragraphs are grouped every 5 and animate as groups
 * - Respects prefers-reduced-motion
 */
(function () {
  'use strict';

  // Enable CSS rules that hide elements until they enter view
  document.documentElement.classList.add('presence-anim');

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.presence-reveal, .presence-p-group').forEach(function (el) {
      el.classList.add('is-inview');
    });
    return;
  }

  // Start a bit earlier so the full rise is visible while scrolling
  var ROOT_MARGIN = '0px 0px -4% 0px';
  var THRESHOLD = 0.06;

  function markInView(el) {
    el.classList.add('is-inview');
  }

  function observeAll(selector, options) {
    var nodes = document.querySelectorAll(selector);
    if (!nodes.length) return;

    if (!('IntersectionObserver' in window)) {
      nodes.forEach(markInView);
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          markInView(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, options || { root: null, rootMargin: ROOT_MARGIN, threshold: THRESHOLD });

    nodes.forEach(function (el) {
      io.observe(el);
    });
  }

  /**
   * Wrap consecutive paragraphs in .article-body into groups of 5
   * so long articles animate in calm batches rather than one-by-one.
   */
  function groupParagraphs() {
    var bodies = document.querySelectorAll('[data-presence-body], .article-body.presence-body, .presence-body');
    bodies.forEach(function (body) {
      if (body.getAttribute('data-presence-grouped') === '1') return;

      var children = Array.prototype.slice.call(body.children);
      var buffer = [];
      var groupIndex = 0;

      function flush() {
        if (!buffer.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'presence-p-group';
        wrap.style.setProperty('--presence-g', String(groupIndex));
        buffer[0].parentNode.insertBefore(wrap, buffer[0]);
        buffer.forEach(function (node) {
          wrap.appendChild(node);
        });
        buffer = [];
        groupIndex += 1;
      }

      children.forEach(function (child) {
        var tag = (child.tagName || '').toLowerCase();
        if (tag === 'p') {
          buffer.push(child);
          if (buffer.length >= 5) flush();
        } else {
          flush();
          // Headings, lists, blockquotes, tables, figures get their own reveal
          if (!child.classList.contains('presence-reveal') && !child.hasAttribute('data-presence')) {
            child.classList.add('presence-reveal');
            child.setAttribute('data-presence', '');
          }
        }
      });
      flush();

      body.setAttribute('data-presence-grouped', '1');
    });
  }

  function init() {
    groupParagraphs();
    observeAll('.presence-reveal[data-presence], .presence-reveal', {
      root: null,
      rootMargin: ROOT_MARGIN,
      threshold: THRESHOLD
    });
    observeAll('.presence-p-group', {
      root: null,
      rootMargin: '0px 0px -3% 0px',
      threshold: 0.05
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
