/**
 * Mova native theme toggle
 * Stores preference in localStorage key: mova-theme
 * Values: 'light' | 'dark' | null (system)
 * Public site follows system when unset; HQ defaults to dark when unset.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'mova-theme';
    var root = document.documentElement;
    var isHq = document.body && document.body.classList.contains('hq');

    function getStored() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function setStored(value) {
        try {
            if (value === null || value === undefined) {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, value);
            }
        } catch (e) { /* private mode */ }
    }

    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function resolveTheme(stored) {
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        // No stored preference
        if (isHq) {
            return 'dark'; // HQ defaults to dark
        }
        return systemPrefersDark() ? 'dark' : 'light';
    }

    function apply(theme) {
        root.setAttribute('data-theme', theme);
        // Sync meta theme-color for mobile chrome
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#0b0d12' : '#f8f9fb');
        }
    }

    function current() {
        return root.getAttribute('data-theme') || resolveTheme(getStored());
    }

    function toggle() {
        var next = current() === 'dark' ? 'light' : 'dark';
        setStored(next);
        apply(next);
        return next;
    }

    // Apply as early as possible (also run from inline head script)
    var stored = getStored();
    apply(resolveTheme(stored));

    // Wire up all toggles
    function bindToggles() {
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                toggle();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindToggles);
    } else {
        bindToggles();
    }

    // React to system changes only when user has no explicit preference
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (!getStored()) {
                apply(resolveTheme(null));
            }
        });
    }

    // Expose for debugging / future use
    window.MovaTheme = {
        toggle: toggle,
        apply: apply,
        current: current,
        getStored: getStored
    };
})();

/* Progressive images: mark already-complete (cached) imgs as loaded */
(function () {
  function mark(img) {
    if (img && img.classList && img.classList.contains('mova-img')) {
      if (img.complete && img.naturalWidth) img.classList.add('is-loaded');
    }
  }
  function scan(root) {
    (root || document).querySelectorAll('img.mova-img').forEach(mark);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }
  // Mutation observer for late-inserted content
  if (typeof MutationObserver !== 'undefined') {
    var mo = new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        m.addedNodes && m.addedNodes.forEach(function (n) {
          if (n.nodeType === 1) {
            if (n.tagName === 'IMG') mark(n);
            else if (n.querySelectorAll) scan(n);
          }
        });
      });
    });
    if (document.documentElement) {
      mo.observe(document.documentElement, { childList: true, subtree: true });
    }
  }
})();
