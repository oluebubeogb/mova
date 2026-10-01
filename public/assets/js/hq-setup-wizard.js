/**
 * Quick Setup Wizard — multi-step HQ UI
 */
(function () {
  window.__swFullJs = true;
  var root = document.getElementById('setup-wizard');
  if (!root) return;

  var cfg = window.MOVA_SETUP_WIZARD || {};
  var form = document.getElementById('sw-form');
  var current = 1;
  var selectedPalette = null;

  function go(step) {
    current = step;
    root.querySelectorAll('.sw-panel').forEach(function (p) {
      var on = parseInt(p.getAttribute('data-panel'), 10) === step;
      p.classList.toggle('is-active', on);
      if (on) p.removeAttribute('hidden');
      else p.setAttribute('hidden', 'hidden');
    });
    root.querySelectorAll('.sw-step').forEach(function (s) {
      var n = parseInt(s.getAttribute('data-step'), 10);
      s.classList.toggle('is-active', n === step);
      s.classList.toggle('is-done', n < step);
    });
    if (step === 3) { renderKits(); refreshPageList(); }
    if (step === 4) refreshSummary();
  }


  function kitsForPack(pack) {
    var all = cfg.kits || [];
    var map = { school: 'school', organization: 'organization', generic: 'generic' };
    var want = map[pack] || pack;
    return all.filter(function (k) { return (k.pack || '') === want; });
  }

  function renderKits() {
    var wrap = document.getElementById('sw-kits');
    if (!wrap) return;
    var list = kitsForPack(packId());
    wrap.innerHTML = '';
    if (!list.length) {
      wrap.innerHTML = '<p class="sw-hint">No kits found for this pack. Place <code>mova-kits/</code> in the Mova root.</p>';
      var kid = document.getElementById('sw-kit-id');
      if (kid) kid.value = '';
      return;
    }
    list.forEach(function (k, idx) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sw-kit' + (idx === 0 ? ' is-selected' : '');
      btn.setAttribute('data-kit-id', k.id);
      btn.innerHTML = '<strong>' + escapeHtml(k.label || k.id) + '</strong><span>' + escapeHtml(k.description || '') + '</span>';
      btn.addEventListener('click', function () {
        wrap.querySelectorAll('.sw-kit').forEach(function (x) { x.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
        document.getElementById('sw-kit-id').value = k.id;
        refreshPageList();
      });
      wrap.appendChild(btn);
      if (idx === 0) document.getElementById('sw-kit-id').value = k.id;
    });
  }

  function packId() {
    var r = form.querySelector('input[name="pack_radio"]:checked');
    return r ? r.value : 'school';
  }

  function refreshPageList() {
    var id = packId();
    document.getElementById('sw-pack-id').value = id;
    var details = (cfg.packDetails && cfg.packDetails[id]) || { pages: [] };
    var ul = document.getElementById('sw-page-list');
    ul.innerHTML = details.pages.map(function (t) {
      return '<li>' + escapeHtml(t) + '</li>';
    }).join('') + '<li><em>Footer columns above Feed · llms.txt</em></li>';
  }

  function refreshSummary() {
    var name = (document.getElementById('sw-site-name').value || 'My Site').trim();
    var id = packId();
    var packLabel = id;
    (cfg.packs || []).forEach(function (p) {
      if (p.id === id) packLabel = p.label;
    });
    var pal = selectedPalette ? selectedPalette.label : 'Default (unchanged unless you pick one)';
    document.getElementById('sw-summary').innerHTML =
      '<strong>' + escapeHtml(name) + '</strong> · ' + escapeHtml(packLabel) +
      '<br>Palette: ' + escapeHtml(pal);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function syncColor(pickerId, hexId) {
    var picker = document.getElementById(pickerId);
    var hex = document.getElementById(hexId);
    if (!picker || !hex) return;
    picker.addEventListener('input', function () {
      hex.value = picker.value;
    });
    hex.addEventListener('change', function () {
      var v = hex.value.trim();
      if (/^#[0-9a-fA-F]{6}$/.test(v)) picker.value = v;
    });
  }
  syncColor('sw-color1', 'sw-color1-hex');
  syncColor('sw-color2', 'sw-color2-hex');
  syncColor('sw-color3', 'sw-color3-hex');

  root.querySelectorAll('[data-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = parseInt(btn.getAttribute('data-next'), 10);
      if (next === 2) {
        var name = (document.getElementById('sw-site-name').value || '').trim();
        if (!name) {
          document.getElementById('sw-site-name').focus();
          return;
        }
      }
      go(next);
    });
  });
  root.querySelectorAll('[data-prev]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      go(parseInt(btn.getAttribute('data-prev'), 10));
    });
  });
  root.querySelectorAll('.sw-step').forEach(function (s) {
    s.addEventListener('click', function () {
      var n = parseInt(s.getAttribute('data-step'), 10);
      if (n <= current || n === current + 1) go(n);
    });
  });

  form.querySelectorAll('input[name="pack_radio"]').forEach(function (r) {
    r.addEventListener('change', function () {
      form.querySelectorAll('.sw-pack').forEach(function (lab) {
        lab.classList.toggle('is-selected', lab.querySelector('input').checked);
      });
      document.getElementById('sw-pack-id').value = packId();
    });
  });

  document.getElementById('sw-gen-palettes').addEventListener('click', function () {
    var fd = new FormData();
    fd.append(cfg.csrfField || '_mova_csrf', cfg.csrf || '');
    fd.append('color1', document.getElementById('sw-color1-hex').value || document.getElementById('sw-color1').value);
    var c2 = (document.getElementById('sw-color2-hex').value || '').trim();
    var c3 = (document.getElementById('sw-color3-hex').value || '').trim();
    if (c2) fd.append('color2', c2);
    if (c3) fd.append('color3', c3);

    fetch('/hq/setup-wizard/palettes', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var wrap = document.getElementById('sw-palettes');
        wrap.hidden = false;
        wrap.innerHTML = '';
        if (!data.ok || !data.palettes) {
          wrap.textContent = data.error || 'Could not generate palettes.';
          return;
        }
        data.palettes.forEach(function (p, idx) {
          var el = document.createElement('button');
          el.type = 'button';
          el.className = 'sw-palette' + (idx === 0 ? ' is-selected' : '');
          var sw = '<div class="sw-palette-swatches">';
          ['primary', 'accent', 'background', 'text'].forEach(function (k) {
            sw += '<i style="background:' + (p.colors[k] || '#ccc') + '"></i>';
          });
          sw += '</div>';
          el.innerHTML = sw + '<strong>' + escapeHtml(p.label) + '</strong>';
          el.addEventListener('click', function () {
            wrap.querySelectorAll('.sw-palette').forEach(function (x) { x.classList.remove('is-selected'); });
            el.classList.add('is-selected');
            selectedPalette = p;
            document.getElementById('sw-palette-json').value = JSON.stringify(p);
          });
          wrap.appendChild(el);
          if (idx === 0) {
            selectedPalette = p;
            document.getElementById('sw-palette-json').value = JSON.stringify(p);
          }
        });
      })
      .catch(function () {
        var wrap = document.getElementById('sw-palettes');
        wrap.hidden = false;
        wrap.textContent = 'Network error generating palettes.';
      });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('sw-run');
    var result = document.getElementById('sw-result');
    btn.disabled = true;
    btn.textContent = 'Creating…';
    result.hidden = true;

    var fd = new FormData(form);
    fd.set('pack_id', packId());
    var kidEl = document.getElementById('sw-kit-id');
    if (kidEl && kidEl.value) fd.set('kit_id', kidEl.value);
    fd.set(cfg.csrfField || '_mova_csrf', cfg.csrf || '');
    if (!fd.get('palette_json') && selectedPalette) {
      fd.set('palette_json', JSON.stringify(selectedPalette));
    }
    var useAi = document.getElementById('sw-use-ai');
    if (useAi && useAi.checked) fd.set('use_ai', '1');

    fetch('/hq/setup-wizard/run', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.disabled = false;
        btn.textContent = 'Create site';
        result.hidden = false;
        if (data.ok) {
          result.className = 'sw-result is-ok';
          var list = (data.pages || []).map(function (p) {
            return '<li><a href="/hq/content/edit/' + p.id + '">' + escapeHtml(p.title) + '</a> <span style="opacity:.7">/' + escapeHtml(p.slug) + '</span></li>';
          }).join('');
          result.innerHTML =
            '<strong>' + escapeHtml(data.message || 'Done.') + '</strong>' +
            '<ul>' + list + '</ul>' +
            '<p style="margin:.75rem 0 0"><a href="/" target="_blank">View site</a> · <a href="/hq/design">Design</a> · <a href="/hq/content">Content</a></p>';
        } else {
          result.className = 'sw-result is-err';
          result.textContent = data.error || 'Something went wrong.';
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Create site';
        result.hidden = false;
        result.className = 'sw-result is-err';
        result.textContent = 'Network error.';
      });
  });

  go(1);
})();
