/**
 * Mova public site — mobile / tablet navigation drawer
 */
(function () {
    'use strict';

    var toggle = document.querySelector('[data-nav-toggle]');
    var drawer = document.getElementById('nav-drawer');
    var backdrop = document.querySelector('[data-nav-backdrop]');
    if (!toggle || !drawer) return;

    function open() {
        drawer.hidden = false;
        if (backdrop) backdrop.hidden = false;
        document.body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close menu');
        // force reflow for transition
        drawer.offsetHeight;
        drawer.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-open');
        var first = drawer.querySelector('a, button');
        if (first) { try { first.focus({ preventScroll: true }); } catch (e) {} }
    }

    function close() {
        drawer.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
        window.setTimeout(function () {
            if (!drawer.classList.contains('is-open')) {
                drawer.hidden = true;
                if (backdrop) backdrop.hidden = true;
            }
        }, 220);
    }

    function isOpen() {
        return drawer.classList.contains('is-open');
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        if (isOpen()) close();
        else open();
    });

    if (backdrop) {
        backdrop.addEventListener('click', close);
    }
    drawer.querySelectorAll('[data-nav-close]').forEach(function (b) {
        b.addEventListener('click', function (e) { e.preventDefault(); close(); toggle.focus(); });
    });

    drawer.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) close();
    });

    // Close on resize to desktop
    var mq = window.matchMedia(document.querySelector('[data-mx-header]') ? '(min-width: 961px)' : '(min-width: 769px)');
    function onMq(e) {
        if (e.matches && isOpen()) close();
    }
    if (mq.addEventListener) mq.addEventListener('change', onMq);
    else if (mq.addListener) mq.addListener(onMq);
})();
