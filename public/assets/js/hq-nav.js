/**
 * Mova HQ — dynamic Line 2 navigation (registry-driven).
 *
 * The markup is server-rendered by hqLine2Html() from Mova\Hq\RouteRegistry, so the nav is
 * correct before JS runs. This script only enhances it — no innerHTML re-renders:
 *  - live active state for sub-items (query filters, layers, editor tools)
 *  - sliding active indicator + scroll-into-view for the active item
 *  - edge fades + wheel/drag-free horizontal scrolling on small screens
 *  - session-scoped back/forward history with "Back to …" tooltips
 *  - roving arrow-key focus (Left/Right/Home/End) across the track
 *  - user menu toggle
 * Public API: window.MovaHqNav = { activateLayer, refresh, history }
 */
(function () {
    'use strict';

    var NAV = window.MOVA_HQ_NAV || null;
    var HIST_KEY = 'movaHqHist';
    var HIST_CAP = 50;

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ------------------------------------------------------------------ layers */

    function activateLayer(id) {
        var root = $('.hq-layers');
        if (!root || !id) return false;
        var found = false;
        $all('.hq-layer-tab', root).forEach(function (tab) {
            var on = tab.getAttribute('data-layer') === id;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) found = true;
        });
        $all('.hq-layer-panel', root).forEach(function (panel) {
            var on = panel.getAttribute('data-layer-panel') === id;
            panel.hidden = !on;
            // Server-rendered panels may carry an inline display:none for the initial layer;
            // clear it so a client-side layer switch never leaves a blank panel.
            panel.style.display = on ? '' : 'none';
            panel.classList.toggle('is-active', on);
            if (on) found = true;
        });
        if (found) {
            document.body.dataset.activeLayer = id;
            var key = root.getAttribute('data-layer-key');
            if (key) { try { localStorage.setItem(key, id); } catch (e) { /* ignore */ } }
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('layer', id);
                history.replaceState(history.state, '', u.pathname + u.search + u.hash);
            } catch (e) { /* ignore */ }
            syncActive();
        }
        return found;
    }

    function currentLayer() {
        var tab = $('.hq-layers .hq-layer-tab.is-active');
        if (tab) return tab.getAttribute('data-layer');
        var panel = $('.hq-layers .hq-layer-panel.is-active');
        if (panel) return panel.getAttribute('data-layer-panel');
        if (document.body.dataset.activeLayer) return document.body.dataset.activeLayer;
        try { return new URLSearchParams(location.search).get('layer'); } catch (e) { return null; }
    }

    /* ------------------------------------------------------------ active state */

    var IGNORE_PARAMS = { layer: 1, focus: 1, page: 1, q: 1, sort: 1 };
    /** Same path and the same meaningful query params (exact set, so ?tab=inbox ≠ ?tab=inbox&folder=Sent). */
    function hrefMatchesLocation(href) {
        try {
            var u = new URL(href, location.origin);
            if (u.pathname.replace(/\/+$/, '') !== location.pathname.replace(/\/+$/, '')) return false;
            var a = {}, b = {};
            u.searchParams.forEach(function (v, k) { if (!IGNORE_PARAMS[k]) a[k] = v; });
            new URLSearchParams(location.search).forEach(function (v, k) { if (!IGNORE_PARAMS[k] && v !== '') b[k] = v; });
            var ka = Object.keys(a), kb = Object.keys(b);
            if (ka.length !== kb.length) return false;
            return ka.every(function (k) { return b[k] === a[k]; });
        } catch (e) { return false; }
    }

    function subIsActive(el) {
        var action = el.getAttribute('data-hq-action');
        if (action) return document.body.dataset.activeTool === action;
        var layer = el.getAttribute('data-hq-layer');
        if (layer) {
            var href = el.getAttribute('href') || '';
            var samePage = !href || new URL(href, location.origin).pathname === location.pathname;
            return samePage && currentLayer() === layer;
        }
        var match = el.getAttribute('data-match');
        if (match) {
            try {
                var m = JSON.parse(match), q = new URLSearchParams(location.search), ok = true;
                Object.keys(m).forEach(function (k) {
                    var v = m[k], have = q.get(k);
                    if (v === null ? (have !== null && have !== '') : have !== String(v)) ok = false;
                });
                return ok && new URL(el.getAttribute('href'), location.origin).pathname === location.pathname;
            } catch (e) { return false; }
        }
        var h = el.getAttribute('href');
        return h ? hrefMatchesLocation(h) : false;
    }

    function syncActive() {
        var nav = document.getElementById('hq-contextual-nav');
        if (!nav) return;
        $all('.hq-nav-item.is-sub', nav).forEach(function (el) {
            var on = subIsActive(el);
            el.classList.toggle('active', on);
            if (el.tagName === 'BUTTON') el.setAttribute('aria-pressed', on ? 'true' : 'false');
            else if (on) el.setAttribute('aria-current', 'true'); else el.removeAttribute('aria-current');
        });
        placeIndicator(true);
    }

    /* -------------------------------------------------------- indicator/fades */

    var indicatorRaf = 0;
    function placeIndicator(scrollToActive) {
        cancelAnimationFrame(indicatorRaf);
        indicatorRaf = requestAnimationFrame(function () {
            var scroller = document.getElementById('hq-nav-scroller');
            var ind = $('#hq-contextual-nav .hq-nav-indicator');
            if (!scroller || !ind) return;
            var active = $('.hq-nav-item.active:not(.is-sub)', scroller) || $('.hq-nav-item.active', scroller);
            if (!active) { ind.classList.remove('is-on'); updateFades(); return; }
            // Indicator lives inside the (position:relative) scroller, so it scrolls with the items.
            ind.style.width = active.offsetWidth + 'px';
            ind.style.transform = 'translateX(' + active.offsetLeft + 'px)';
            ind.classList.add('is-on');
            if (scrollToActive) {
                var sl = scroller.scrollLeft, w = scroller.clientWidth;
                if (active.offsetLeft < sl + 24 || active.offsetLeft + active.offsetWidth > sl + w - 24) {
                    scroller.scrollTo({ left: Math.max(0, active.offsetLeft - w / 2 + active.offsetWidth / 2), behavior: 'smooth' });
                }
            }
            updateFades();
        });
    }

    function updateFades() {
        var vp = document.getElementById('hq-nav-viewport');
        var sc = document.getElementById('hq-nav-scroller');
        if (!vp || !sc) return;
        var max = sc.scrollWidth - sc.clientWidth;
        vp.classList.toggle('has-left', sc.scrollLeft > 2);
        vp.classList.toggle('has-right', sc.scrollLeft < max - 2);
    }

    /* ---------------------------------------------------------------- history */

    function readHist() {
        try {
            var h = JSON.parse(sessionStorage.getItem(HIST_KEY) || 'null');
            if (h && Array.isArray(h.stack) && typeof h.idx === 'number') return h;
        } catch (e) { /* ignore */ }
        return { stack: [], idx: -1 };
    }
    function writeHist(h) { try { sessionStorage.setItem(HIST_KEY, JSON.stringify(h)); } catch (e) { /* ignore */ } }

    function pageTitle() {
        var r = NAV && NAV.route;
        var t = (r && r.label) || document.title.replace(/\s*[—|-]\s*Mova.*$/i, '');
        return String(t || 'Page').slice(0, 60);
    }

    function recordVisit() {
        var url = location.pathname + location.search;
        var h = readHist();
        var type = 'navigate';
        try {
            var e = performance.getEntriesByType('navigation')[0];
            if (e && e.type) type = e.type;
        } catch (err) { /* ignore */ }
        var entry = { url: url, title: pageTitle() };
        var cur = h.stack[h.idx];
        if (cur && cur.url === url) {
            cur.title = entry.title; // reload or same page
        } else if (type === 'back_forward') {
            var back = h.stack[h.idx - 1], fwd = h.stack[h.idx + 1];
            if (back && back.url === url) h.idx -= 1;
            else if (fwd && fwd.url === url) h.idx += 1;
            else { h.stack = h.stack.slice(0, h.idx + 1); h.stack.push(entry); h.idx = h.stack.length - 1; }
        } else {
            h.stack = h.stack.slice(0, h.idx + 1);
            h.stack.push(entry);
            if (h.stack.length > HIST_CAP) h.stack = h.stack.slice(h.stack.length - HIST_CAP);
            h.idx = h.stack.length - 1;
        }
        writeHist(h);
        return h;
    }

    function initHistory() {
        var h = recordVisit();
        var back = document.getElementById('hq-nav-back');
        var fwd = document.getElementById('hq-nav-forward');
        var prev = h.stack[h.idx - 1], next = h.stack[h.idx + 1];
        if (back) {
            back.disabled = !prev && window.history.length <= 1;
            var bl = prev ? 'Back to ' + prev.title : 'Go back';
            back.title = bl; back.setAttribute('aria-label', bl);
            back.addEventListener('click', function () { window.history.back(); });
        }
        if (fwd) {
            fwd.disabled = !next;
            var fl = next ? 'Forward to ' + next.title : 'Go forward';
            fwd.title = fl; fwd.setAttribute('aria-label', fl);
            fwd.addEventListener('click', function () { window.history.forward(); });
        }
        document.addEventListener('keydown', function (e) {
            if (!e.altKey || e.ctrlKey || e.metaKey) return;
            var t = e.target;
            if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
            if (e.key === 'ArrowLeft' && back && !back.disabled) { e.preventDefault(); window.history.back(); }
            if (e.key === 'ArrowRight' && fwd && !fwd.disabled) { e.preventDefault(); window.history.forward(); }
        });
    }

    /* ------------------------------------------------------------ interactions */

    function initNav() {
        var nav = document.getElementById('hq-contextual-nav');
        if (!nav) return;
        var scroller = document.getElementById('hq-nav-scroller');

        nav.addEventListener('click', function (e) {
            var el = e.target.closest('.hq-nav-item');
            if (!el || !nav.contains(el)) return;
            var action = el.getAttribute('data-hq-action');
            if (action) {
                e.preventDefault();
                document.dispatchEvent(new CustomEvent('hq:tool', { detail: { action: action } }));
                if (!document.body.dataset.activeTool) document.body.dataset.activeTool = action;
                syncActive();
                return;
            }
            var layer = el.getAttribute('data-hq-layer');
            if (layer) {
                var href = el.getAttribute('href');
                var samePage = !href || new URL(href, location.origin).pathname === location.pathname;
                if (samePage && $('.hq-layers') && activateLayer(layer)) {
                    e.preventDefault();
                }
            }
        });

        // Roving arrow keys inside the track
        nav.addEventListener('keydown', function (e) {
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) === -1 || e.altKey) return;
            var items = $all('.hq-nav-item', nav);
            var i = items.indexOf(document.activeElement);
            if (i === -1) return;
            e.preventDefault();
            var n = e.key === 'Home' ? 0 : e.key === 'End' ? items.length - 1
                : (i + (e.key === 'ArrowRight' ? 1 : -1) + items.length) % items.length;
            items[n].focus();
            items[n].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });

        if (scroller) {
            scroller.addEventListener('scroll', updateFades, { passive: true });
            // Vertical wheel scrolls the nav horizontally when it overflows
            scroller.addEventListener('wheel', function (e) {
                if (Math.abs(e.deltaY) > Math.abs(e.deltaX) && scroller.scrollWidth > scroller.clientWidth) {
                    scroller.scrollLeft += e.deltaY;
                    e.preventDefault();
                }
            }, { passive: false });
        }
        window.addEventListener('resize', function () { placeIndicator(false); });

        // Editor tools set body[data-active-tool]; layer tabs toggle .is-active.
        try {
            new MutationObserver(function () { syncActive(); })
                .observe(document.body, { attributes: true, attributeFilter: ['data-active-tool', 'data-active-layer'] });
            var layers = $('.hq-layers .hq-layer-tabs') || $('.hq-layers');
            if (layers) {
                new MutationObserver(function () { syncActive(); })
                    .observe(layers, { attributes: true, subtree: true, attributeFilter: ['class'] });
            }
        } catch (e) { /* ignore */ }
        document.addEventListener('hq:tool', function () { setTimeout(syncActive, 0); });

        syncActive();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { placeIndicator(true); });
    }

    function initUserMenu() {
        var btn = document.getElementById('hq-user-btn');
        var dropdown = document.getElementById('hq-user-dropdown');
        if (!btn || !dropdown) return;
        btn.setAttribute('aria-haspopup', 'true');
        btn.setAttribute('aria-expanded', 'false');
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.hidden = !dropdown.hidden;
            btn.setAttribute('aria-expanded', dropdown.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function () { dropdown.hidden = true; btn.setAttribute('aria-expanded', 'false'); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !dropdown.hidden) { dropdown.hidden = true; btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
        });
    }

    window.MovaHqNav = {
        activateLayer: activateLayer,
        refresh: syncActive,
        history: function () { return readHist(); }
    };

    function boot() {
        initNav();
        initHistory();
        initUserMenu();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
