/* HQ → Backups → Export & import (site packs). No inline eval; CSP-safe. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  // ── tabs (Export / Import / History) ──
  var page = $('[data-sp-page]');
  if (page) {
    var show = function (tab) {
      $$('[data-sp-pane]', page).forEach(function (p) { p.hidden = p.getAttribute('data-sp-pane') !== tab; });
      $$('[data-sp-tab-link]', page).forEach(function (a) {
        var on = a.getAttribute('data-sp-tab-link') === tab;
        a.classList.toggle('is-active', on);
        a.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    };
    show(page.getAttribute('data-sp-tab') || 'export');
    $$('[data-sp-tab-link]', page).forEach(function (a) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        var t = a.getAttribute('data-sp-tab-link');
        show(t);
        try { history.replaceState(null, '', '?tab=' + t); } catch (e) {}
      });
    });
  }

  // ── auto-download after export ──
  var dl = $('[data-sp-autodl]');
  if (dl) {
    var key = 'sp-dl:' + dl.getAttribute('data-sp-autodl');
    var done = false;
    try { done = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch (e) {}
    if (!done) {
      var a = document.createElement('a');
      a.href = dl.getAttribute('data-sp-autodl');
      a.setAttribute('download', '');
      document.body.appendChild(a);
      a.click();
      a.remove();
    }
  }

  // ── export form: select all, content types ──
  var form = $('[data-sp-export]');
  if (form) {
    var all = $('[data-sp-all]', form);
    var groups = $$('[data-sp-group]', form);
    var types = $('[data-sp-types]', form);
    var sync = function () {
      var n = groups.filter(function (g) { return g.checked; }).length;
      all.checked = n === groups.length;
      all.indeterminate = n > 0 && n < groups.length;
      if (types) {
        var c = $('[data-sp-group="content"]', form);
        types.classList.toggle('is-off', !(c && c.checked));
        $$('input', types).forEach(function (i) { i.disabled = !(c && c.checked); });
      }
    };
    all.addEventListener('change', function () { groups.forEach(function (g) { g.checked = all.checked; }); sync(); });
    groups.forEach(function (g) { g.addEventListener('change', sync); });
    sync();
    form.addEventListener('submit', function () {
      var b = $('[data-sp-export-btn]', form);
      if (b) { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Building pack…'; }
    });
  }

  // ── upload: size check + file name ──
  var up = $('[data-sp-upload]');
  if (up) {
    var inp = $('input[type=file]', up);
    var max = parseInt(up.getAttribute('data-max') || '0', 10);
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      var lab = $('[data-sp-file-label]', up);
      if (f && lab) { lab.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)'; }
      var big = !!(f && max > 0 && f.size > max);
      $('[data-sp-toobig]', up).hidden = !big;
      $('button[type=submit]', up).disabled = big;
    });
    up.addEventListener('submit', function () {
      var b = $('button[type=submit]', up);
      b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Uploading & checking…';
    });
  }

  // ── preview: re-run dry run when options change, show unchanged ──
  var opts = $('[data-sp-options]');
  if (opts) {
    $$('[data-sp-autosubmit]', opts).forEach(function (i) {
      i.addEventListener('change', function () {
        if (i.name === 'remove_missing' && i.checked &&
            !window.confirm('Preview removing items that are not in the pack? Nothing is deleted until you confirm the import.')) {
          i.checked = false; return;
        }
        opts.submit();
      });
    });
  }
  var su = $('[data-sp-show-unchanged]');
  if (su) {
    su.addEventListener('change', function () {
      $$('[data-sp-unchanged]').forEach(function (r) { r.hidden = !su.checked; });
      if (su.checked) { $$('.sp-pgroup').forEach(function (d) { d.open = true; }); }
    });
  }
  var run = $('[data-sp-run]');
  if (run) {
    run.addEventListener('submit', function (ev) {
      if (run.getAttribute('data-remove') === '1' && !window.confirm('Really delete the items that are not in the pack? (A backup is made first and you can undo.)')) {
        ev.preventDefault(); return;
      }
      var b = $('[data-sp-start]', run);
      if (b) { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Starting…'; }
    });
  }

  // ── job progress: drive steps from the browser, poll status ──
  var job = $('[data-sp-job]');
  if (job && job.getAttribute('data-sp-live') === '1') {
    var id = job.getAttribute('data-sp-job');
    var bar = $('[data-sp-bar]', job);
    var msg = $('[data-sp-msg]', job);
    var log = $('[data-sp-log]', job);
    var fails = 0;
    var order = ['validate', 'plan', 'backup', 'stage', 'apply', 'swap', 'finalize'];
    var paint = function (s) {
      if (!s || !s.ok) { return; }
      bar.style.width = s.progress + '%';
      bar.parentNode.setAttribute('aria-valuenow', String(s.progress));
      msg.textContent = s.message || '';
      var ci = order.indexOf(s.phase);
      $$('[data-sp-phase]', job).forEach(function (li, i) {
        li.className = s.status === 'done' || i < ci ? 'is-done' : (i === ci ? (s.status === 'failed' ? 'is-bad' : 'is-cur') : '');
      });
      if (log && s.log) {
        log.innerHTML = '';
        s.log.forEach(function (l) {
          var li = document.createElement('li');
          var t = document.createElement('time'); t.textContent = l.t;
          li.appendChild(t); li.appendChild(document.createTextNode(' ' + l.m));
          log.appendChild(li);
        });
      }
    };
    var tick = function () {
      var body = new URLSearchParams(); body.set('id', id);
      fetch('/hq/backups/packs/job/step', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (r) { return r.json(); }).then(function (s) {
        fails = 0;
        paint(s);
        if (s.ok && (s.status === 'done' || s.status === 'failed')) {
          window.setTimeout(function () { window.location.reload(); }, 600);
        } else {
          window.setTimeout(tick, 400);
        }
      }).catch(function () {
        fails++;
        msg.textContent = 'Connection hiccup — retrying… (the background worker keeps going)';
        window.setTimeout(tick, Math.min(15000, 1500 * fails));
      });
    };
    tick();
  }
})();
