/* Documentation theme: TOC scroll-spy + copy buttons on code blocks. No dependencies. */
(function () {
  'use strict';
  var toc = document.querySelector('.doc-toc');
  if (toc) {
    var links = Array.prototype.slice.call(toc.querySelectorAll('a[href^="#"]:not(.doc-toc__top)'));
    var map = {};
    links.forEach(function (a) { map[decodeURIComponent(a.getAttribute('href').slice(1))] = a; });
    var heads = Object.keys(map).map(function (id) { return document.getElementById(id); }).filter(Boolean);
    if (window.matchMedia && window.matchMedia('(max-width: 1024px)').matches) {
      var box = toc.querySelector('details'); if (box) box.removeAttribute('open');
    }
    if ('IntersectionObserver' in window && heads.length) {
      var current = null;
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) current = e.target.id; });
        links.forEach(function (a) { a.classList.remove('is-active'); a.removeAttribute('aria-current'); });
        if (current && map[current]) { map[current].classList.add('is-active'); map[current].setAttribute('aria-current', 'location'); }
      }, { rootMargin: '-80px 0px -65% 0px' });
      heads.forEach(function (h) { io.observe(h); });
    }
  }
  document.querySelectorAll('.article-body pre').forEach(function (pre) {
    if (pre.querySelector('.doc-copy')) return;
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'doc-copy'; b.textContent = 'Copy'; b.setAttribute('aria-label', 'Copy code');
    b.addEventListener('click', function () {
      var code = pre.querySelector('code') || pre;
      var txt = code.innerText;
      var done = function () { b.textContent = 'Copied'; b.classList.add('is-done'); setTimeout(function () { b.textContent = 'Copy'; b.classList.remove('is-done'); }, 1600); };
      if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, function () {}); }
    });
    pre.classList.add('has-copy');
    pre.appendChild(b);
  });
})();
