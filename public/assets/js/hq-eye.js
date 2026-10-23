/**
 * MovaEye — accessible show/hide toggle for every password / secret input.
 * - Auto-enhances input[type=password] and input[data-secret] (incl. ones added later).
 * - Real <button>, aria-pressed + aria-label, icon swap, one delegated click handler.
 * - Inputs flip back to password on form submit so browsers never store plain text.
 * Opt out per input with data-no-eye.
 */
(function () {
  'use strict';
  if (window.MovaEye) return;

  var SEL = 'input[type="password"]:not([data-no-eye]), input[data-secret]:not([data-no-eye])';
  var EYE = '<svg class="mova-eye-ico mova-eye-show" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
  var SLASH = '<svg class="mova-eye-ico mova-eye-hide" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A9.8 9.8 0 0112 5c6.4 0 10 7 10 7a17.6 17.6 0 01-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 005.4-1.6"/></svg>';
  var seq = 0;

  function label(input, shown) {
    var name = input.getAttribute('data-eye-label') || (input.getAttribute('data-secret') ? 'secret' : 'password');
    return (shown ? 'Hide ' : 'Show ') + name;
  }

  function enhance(input) {
    if (!input || input.dataset.movaEye === '1' || input.type === 'hidden') return;
    input.dataset.movaEye = '1';
    if (!input.id) input.id = 'mova-secret-' + (++seq);

    var wrap = input.parentElement;
    if (!wrap || !wrap.classList.contains('mova-eye')) {
      wrap = document.createElement('span');
      wrap.className = 'mova-eye';
      var cs = window.getComputedStyle(input);
      var parent = input.parentElement;
      if (parent && cs.width && parent.clientWidth && input.offsetWidth && input.offsetWidth < parent.clientWidth * 0.85) {
        wrap.classList.add('mova-eye--inline');
      }
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
    }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mova-eye-btn';
    btn.setAttribute('aria-controls', input.id);
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', label(input, false));
    btn.title = label(input, false);
    btn.innerHTML = EYE + SLASH;
    if (input.disabled) btn.disabled = true;
    wrap.appendChild(btn);
  }

  function toggle(btn, force) {
    var input = document.getElementById(btn.getAttribute('aria-controls'));
    if (!input) return;
    var show = typeof force === 'boolean' ? force : btn.getAttribute('aria-pressed') !== 'true';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.classList.toggle('is-visible', show);
    btn.setAttribute('aria-label', label(input, show));
    btn.title = label(input, show);
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    if (root.matches && root.matches(SEL)) enhance(root);
    root.querySelectorAll(SEL).forEach(enhance);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.mova-eye-btn');
    if (!btn) return;
    e.preventDefault();
    toggle(btn);
    var input = document.getElementById(btn.getAttribute('aria-controls'));
    if (input) { try { var n = input.value.length; input.focus(); input.setSelectionRange(n, n); } catch (_) {} }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('.mova-eye-btn[aria-pressed="true"]').forEach(function (b) { toggle(b, false); });
  }, true);

  function init() {
    scan(document);
    if ('MutationObserver' in window) {
      new MutationObserver(function (muts) {
        muts.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); });
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  window.MovaEye = { enhance: enhance, scan: scan, toggle: toggle };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
