/**
 * Mova HQ — icon+text layer tabs
 * Markup:
 *   .hq-layers
 *     .hq-layer-tabs > button.hq-layer-tab[data-layer="id"]
 *     hr.hq-layer-rule
 *     .hq-layer-panels > .hq-layer-panel[data-layer-panel="id"]
 */
(function () {
    'use strict';

    function initRoot(root) {
        var tabs = root.querySelectorAll('.hq-layer-tab');
        var panels = root.querySelectorAll('.hq-layer-panel');
        if (!tabs.length || !panels.length) return;

        var storageKey = root.getAttribute('data-layer-key') || '';

        function activate(id, persist) {
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-layer') === id;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var on = panel.getAttribute('data-layer-panel') === id;
                panel.hidden = !on;
                panel.classList.toggle('is-active', on);
            });
            if (persist && storageKey) {
                try { localStorage.setItem(storageKey, id); } catch (e) {}
            }
        }

        var initial = null;
        // URL ?layer= wins (e.g. /hq/settings?layer=ai)
        try {
            var q = new URLSearchParams(window.location.search).get('layer');
            if (q) initial = q;
        } catch (e) {}
        if (!initial && storageKey) {
            try { initial = localStorage.getItem(storageKey); } catch (e) {}
        }
        // Drop obsolete layers (e.g. smtp removed from Settings)
        if (initial && !root.querySelector('.hq-layer-panel[data-layer-panel="' + initial + '"]')
            && !root.querySelector('.hq-layer-tab[data-layer="' + initial + '"]')) {
            initial = null;
        }
        if (!initial) {
            var active = root.querySelector('.hq-layer-tab.is-active')
                || root.querySelector('.hq-layer-panel.is-active');
            if (active) {
                initial = active.getAttribute('data-layer') || active.getAttribute('data-layer-panel');
            }
            if (!initial && tabs[0]) initial = tabs[0].getAttribute('data-layer');
            if (!initial) {
                var firstPanel = root.querySelector('.hq-layer-panel');
                if (firstPanel) initial = firstPanel.getAttribute('data-layer-panel');
            }
        }
        activate(initial, false);

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                activate(tab.getAttribute('data-layer'), true);
            });
        });
    }

    function boot() {
        document.querySelectorAll('.hq-layers').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
