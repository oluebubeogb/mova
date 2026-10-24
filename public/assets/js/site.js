/**
 * Mova public site chrome: header scroll state, smooth anchor offset.
 * Tiny, dependency-free, respects prefers-reduced-motion.
 */
(function () {
    'use strict';
    var header = document.querySelector('[data-mx-header]');
    if (header) {
        var ticking = false;
        var last = -1;
        var update = function () {
            ticking = false;
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            var s = y > 8;
            if (s !== last) {
                header.classList.toggle('is-scrolled', s);
                document.body.classList.toggle('mx-scrolled', s);
                last = s;
            }
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
        }, { passive: true });
        update();
    }
    // Mark the current page in any in-content nav too
    var here = location.pathname.replace(/\/+$/, '') || '/';
    document.querySelectorAll('.mx-footer a[href]').forEach(function (a) {
        var u = (a.getAttribute('href') || '').replace(/\/+$/, '') || '/';
        if (u === here && u !== '/') a.setAttribute('aria-current', 'page');
    });
})();
