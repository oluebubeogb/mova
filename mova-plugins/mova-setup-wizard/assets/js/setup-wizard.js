/* Mova Quick setup — Type → Kit → Brand → Theme → Content → Review → Publish.
 * Every change autosaves to the server draft; the AI copy job is poll-driven
 * (one page per request) so it survives slow gateways, Docker and shared hosting. */
(function () {
  'use strict';
  var root = document.getElementById('qs');
  var bootEl = document.getElementById('qs-boot');
  if (!root || !bootEl) return;
  var B = JSON.parse(bootEl.textContent || '{}');
  var D = B.draft || {};
  var LAST = 7;
  var step = Math.min(Math.max(parseInt(D.step, 10) || 1, 1), 6);
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var kitById = function (id) { for (var i = 0; i < B.kits.length; i++) if (B.kits[i].id === id) return B.kits[i]; return null; };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- server ---------- */
  function post(url, data, isForm) {
    var body;
    if (isForm) { body = data; } else { body = new FormData(); Object.keys(data || {}).forEach(function (k) { body.append(k, data[k] == null ? '' : data[k]); }); }
    body.append('_mova_csrf', B.csrf);
    return fetch('/hq' + url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-Token': B.csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected server response (' + r.status + ').' }; }); });
  }
  var saveEl = $('#qs-save'), pending = {}, saveT = null, saving = null;
  function setSave(state, msg) {
    saveEl.className = 'qs-save is-' + state;
    var icon = { saving: 'fa-rotate fa-spin', saved: 'fa-check', error: 'fa-triangle-exclamation', idle: 'fa-cloud' }[state] || 'fa-cloud';
    saveEl.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i> <span>' + esc(msg) + '</span>';
  }
  function queue(fields, now) {
    Object.keys(fields).forEach(function (k) { pending[k] = fields[k]; D[k] = fields[k]; });
    setSave('saving', 'Saving…');
    clearTimeout(saveT);
    saveT = setTimeout(flush, now ? 0 : 500);
  }
  function flush() {
    clearTimeout(saveT);
    if (!Object.keys(pending).length) return saving || Promise.resolve();
    var send = pending; pending = {};
    var p = (saving || Promise.resolve()).then(function () { return post('/setup-wizard/draft', send); }).then(function (r) {
      if (r && r.ok) {
        if (r.draft) { D.ai = r.draft.ai || {}; D.copy_pages = r.draft.copy_pages || []; D.logo_url = r.draft.logo_url; }
        setSave('saved', 'Saved'); refreshLive();
      } else { setSave('error', (r && r.error) || 'Not saved'); Object.keys(send).forEach(function (k) { if (!(k in pending)) pending[k] = send[k]; }); }
    }).catch(function () { setSave('error', 'Offline — will retry'); Object.keys(send).forEach(function (k) { if (!(k in pending)) pending[k] = send[k]; }); setTimeout(flush, 4000); });
    saving = p;
    return p;
  }
  window.addEventListener('beforeunload', function () {
    if (!Object.keys(pending).length || !navigator.sendBeacon) return;
    var fd = new FormData(); Object.keys(pending).forEach(function (k) { fd.append(k, pending[k]); }); fd.append('_mova_csrf', B.csrf);
    navigator.sendBeacon('/hq/setup-wizard/draft', fd);
  });

  /* ---------- steps ---------- */
  var labels = ['', 'Type', 'Kit', 'Brand', 'Theme', 'Content', 'Review', 'Publish'];
  var nextLabels = ['', 'Choose a kit', 'Brand it', 'Pick a theme', 'Add content', 'Review pages', 'Continue to publish', ''];
  function canLeave(n) {
    if (n === 1 && !D.pack) return 'Choose what you are building.';
    if (n === 2 && !D.kit) return 'Choose a kit to continue.';
    if (n === 3 && !String(D.site_name || '').trim()) { $('#qs-name-err').hidden = false; $('#qs-name').setAttribute('aria-invalid', 'true'); $('#qs-name').focus(); return 'Add your site name.'; }
    return '';
  }
  function maxReach() { if (!D.pack) return 1; if (!D.kit) return 2; if (!String(D.site_name || '').trim()) return 3; return LAST; }
  function go(n, opts) {
    n = Math.max(1, Math.min(LAST, n));
    if (n > step) { for (var s = step; s < n; s++) { var why = canLeave(s); if (why) { hint(why, true); n = s; break; } } }
    step = n;
    root.setAttribute('data-step', String(n));
    $$('.qs-panel').forEach(function (p) { p.classList.toggle('is-on', +p.getAttribute('data-panel') === n); });
    var reach = maxReach();
    $$('.qs-dot').forEach(function (b) {
      var i = +b.getAttribute('data-go');
      b.classList.toggle('is-on', i === n); b.classList.toggle('is-done', i < n);
      b.disabled = i > reach; if (i === n) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
    $('#qs-bar').style.width = ((n - 1) / (LAST - 1) * 100) + '%';
    $('#qs-mstep').textContent = 'Step ' + n + ' of ' + LAST + ' · ' + labels[n];
    $('#qs-back').style.visibility = n === 1 ? 'hidden' : 'visible';
    var nx = $('#qs-next');
    nx.hidden = n === LAST;
    nx.querySelector('span').textContent = nextLabels[n] || 'Continue';
    hint('');
    if (n < LAST && String(D.step) !== String(n)) queue({ step: n });
    if (n === 3) refreshLive(true);
    if (n === 4) mountThemes();
    if (n === 5) renderAi();
    if (n === 6) renderReview();
    if (n === 7) renderSummary();
    if (!opts || !opts.noScroll) window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    var h = $('.qs-panel.is-on h2'); if (h && opts && opts.focus) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
  }
  function hint(msg, bad) { var h = $('#qs-hint'); h.textContent = msg || ''; h.classList.toggle('is-bad', !!bad); }
  $('#qs-next').addEventListener('click', function () { var why = canLeave(step); if (why) { hint(why, true); return; } flush(); go(step + 1, { focus: true }); });
  $('#qs-back').addEventListener('click', function () { go(step - 1, { focus: true }); });
  $$('.qs-dot').forEach(function (b) { b.addEventListener('click', function () { go(+b.getAttribute('data-go'), { focus: true }); }); });
  document.addEventListener('keydown', function (e) {
    if (document.querySelector('.msp-full, .msp')) return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); $('#qs-next').click(); }
    else if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); if (step > 1) go(step - 1, { focus: true }); }
    else if (e.key === 'Enter' && tag === 'input' && e.target.type !== 'file' && step < LAST) { e.preventDefault(); $('#qs-next').click(); }
  });
  $('#qs-reset').addEventListener('click', function () {
    if (!confirm('Start Quick setup again? Your current choices and AI copy will be cleared (published pages are not touched).')) return;
    post('/setup-wizard/draft', { reset: 1 }).then(function () { location.reload(); });
  });

  /* ---------- 1 · Type ---------- */
  $$('input[name="pack"]').forEach(function (r) {
    r.checked = r.value === D.pack;
    r.closest('.qs-pack').classList.toggle('is-on', r.checked);
    r.addEventListener('change', function () {
      $$('.qs-pack').forEach(function (l) { l.classList.toggle('is-on', l.contains(r)); });
      var changed = D.pack !== r.value;
      queue({ pack: r.value }, true);
      renderKits();
      if (changed) { var k = kitById(D.kit); if (k && k.pack !== r.value) { D.kit = ''; queue({ kit: '' }, true); renderKits(); } }
      setTimeout(function () { if (step === 1) go(2); }, reduce ? 0 : 260);
    });
  });

  /* ---------- 2 · Kit ---------- */
  function kitUrl(k, page) { return '/_preview/kit/' + encodeURIComponent(k.id) + '/' + (page || 'home') + '?font_pair=theme&theme=' + encodeURIComponent(D.theme || 'presence'); }
  function renderKits() {
    var box = $('#qs-kits');
    var list = B.kits.filter(function (k) { return !D.pack || k.pack === D.pack; });
    box.innerHTML = list.map(function (k) {
      var on = k.id === D.kit, pal = k.palette || {};
      return '<label class="qs-kit' + (on ? ' is-on' : '') + '" data-kit="' + esc(k.id) + '">' +
        '<input type="radio" name="kit" value="' + esc(k.id) + '"' + (on ? ' checked' : '') + '>' +
        '<span class="qs-kit__shot">' +
          (k.preview_url ? '<img src="' + esc(k.preview_url) + '" alt="" loading="lazy" decoding="async">' : '<span class="qs-kit__ph"></span>') +
          (k.preview_mobile_url ? '<img class="qs-kit__phone" src="' + esc(k.preview_mobile_url) + '" alt="" loading="lazy" decoding="async">' : '') +
        '</span>' +
        '<span class="qs-kit__body">' +
          '<span class="qs-kit__row"><strong>' + esc(k.label) + '</strong><span class="qs-sw">' + ['primary', 'accent', 'secondary'].map(function (c) { return pal[c] ? '<i style="background:' + esc(pal[c]) + '"></i>' : ''; }).join('') + '</span></span>' +
          '<span class="qs-kit__desc">' + esc(k.description) + '</span>' +
          '<span class="qs-kit__pages">' + (k.page_list || []).map(function (p) { return '<em>' + esc(p.title) + '</em>'; }).join('') + '</span>' +
          '<span class="qs-kit__act"><button type="button" class="msp-btn msp-btn--ghost" data-kit-preview="' + esc(k.id) + '"><i class="fa-regular fa-eye" aria-hidden="true"></i> Preview all pages</button>' +
          '<span class="qs-kit__pick">' + (on ? '<i class="fa-solid fa-circle-check"></i> Selected' : 'Use this kit') + '</span></span>' +
        '</span><i class="qs-check fa-solid fa-circle-check" aria-hidden="true"></i></label>';
    }).join('') || '<p class="qs-empty">No kits found for this site type.</p>';
    $$('input[name="kit"]', box).forEach(function (r) {
      r.addEventListener('change', function () { selectKit(r.value); });
    });
    $$('[data-kit-preview]', box).forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        var k = kitById(b.getAttribute('data-kit-preview'));
        if (!window.MovaSectionPicker) { window.open(kitUrl(k), '_blank'); return; }
        window.MovaSectionPicker.preview(kitUrl(k), {
          title: k.label + ' — demo content',
          actionsHtml: '<button type="button" class="msp-btn msp-btn--primary" data-use>Use this kit</button>',
          bind: function (r, close) { r.querySelector('[data-use]').addEventListener('click', function () { close(); selectKit(k.id, true); }); }
        });
      });
    });
  }
  function selectKit(id, advance) {
    var k = kitById(id); if (!k) return;
    var fresh = D.kit !== id;
    var f = { kit: id, pack: k.pack };
    // Kit colours become the starting palette unless the user already picked their own.
    if (fresh && (!D.primary || D._kitColors)) { f.primary = k.palette.primary; f.accent = k.palette.accent; f.secondary = k.palette.secondary; D._kitColors = true; }
    queue(f, true);
    renderKits(); syncBrand();
    if (advance || step === 2) setTimeout(function () { go(3); }, advance || reduce ? 0 : 260);
  }

  /* ---------- 3 · Brand ---------- */
  var nameEl = $('#qs-name'), tagEl = $('#qs-tagline');
  function packDefaults() { return { school: 'Curious minds, kind hearts, bright futures', organization: 'Together we build stronger communities', generic: 'Thoughtful work, beautifully delivered' }[D.pack] || ''; }
  function syncBrand() {
    nameEl.value = D.site_name || '';
    tagEl.value = D.tagline || '';
    tagEl.placeholder = packDefaults() ? 'e.g. ' + packDefaults() : '';
    var ab = $('#qs-about'); if (ab) ab.placeholder = ({ school: 'e.g. A friendly primary school for ages 4–11, known for outdoor learning and a strong music programme.', organization: 'e.g. We run food, skills and wellbeing programmes for families across the city, powered by 300 volunteers.', generic: 'e.g. Independent design studio helping small brands launch websites, packaging and campaigns.' })[D.pack] || '';
    var ci = $('#qs-city'); if (ci) ci.placeholder = 'e.g. Bristol';
    var em = $('#qs-email'); if (em) em.placeholder = 'hello@yourdomain.com';
    var k = kitById(D.kit), pal = (k && k.palette) || {};
    ['primary', 'accent', 'secondary'].forEach(function (c) {
      var v = D[c] || pal[c] || '#2563eb';
      $('#qs-' + c).value = v; $('#qs-' + c + '-v').textContent = v.toUpperCase();
    });
    renderPresets(); renderFonts(); renderLogo();
  }
  nameEl.addEventListener('input', function () { $('#qs-name-err').hidden = true; nameEl.removeAttribute('aria-invalid'); queue({ site_name: nameEl.value }); });
  tagEl.addEventListener('input', function () { queue({ tagline: tagEl.value }); });
  ['primary', 'accent', 'secondary'].forEach(function (c) {
    var i = $('#qs-' + c);
    i.addEventListener('input', function () { $('#qs-' + c + '-v').textContent = i.value.toUpperCase(); D._kitColors = false; queue({ primary: $('#qs-primary').value, accent: $('#qs-accent').value, secondary: $('#qs-secondary').value }); renderPresets(); });
  });
  function renderPresets() {
    var k = kitById(D.kit);
    var list = (k ? [{ id: 'kit', label: 'Kit colours', primary: k.palette.primary, accent: k.palette.accent, secondary: k.palette.secondary }] : []).concat(B.presets);
    var cur = [(D.primary || '').toLowerCase(), (D.accent || '').toLowerCase(), (D.secondary || '').toLowerCase()].join();
    $('#qs-presets').innerHTML = list.map(function (p) {
      var on = [p.primary, p.accent, p.secondary].join().toLowerCase() === cur;
      return '<button type="button" role="radio" aria-checked="' + on + '" class="qs-preset' + (on ? ' is-on' : '') + '" data-p="' + esc(p.primary + ',' + p.accent + ',' + p.secondary) + '">' +
        '<span><i style="background:' + esc(p.primary) + '"></i><i style="background:' + esc(p.accent) + '"></i><i style="background:' + esc(p.secondary) + '"></i></span>' + esc(p.label) + '</button>';
    }).join('');
    $$('.qs-preset').forEach(function (b) {
      b.addEventListener('click', function () {
        var c = b.getAttribute('data-p').split(',');
        D._kitColors = b === $('.qs-preset');
        queue({ primary: c[0], accent: c[1], secondary: c[2] });
        syncBrand();
      });
    });
  }
  function renderFonts() {
    var themeFont = '';
    B.themes.forEach(function (t) { if (t.slug === (D.theme || 'presence') && t.fonts) themeFont = t.fonts.heading || ''; });
    $('#qs-fonts').innerHTML = B.fontPairs.map(function (f) {
      var on = (D.font_pair || 'theme') === f.id;
      var head = f.id === 'theme' ? (themeFont || 'Inter') : f.sample;
      return '<button type="button" role="radio" aria-checked="' + on + '" class="qs-font' + (on ? ' is-on' : '') + '" data-f="' + esc(f.id) + '">' +
        '<span class="qs-font__aa" style="font-family:\'' + esc(head) + '\',system-ui">Aa</span>' +
        '<span class="qs-font__l"><strong>' + esc(f.label) + '</strong><small>' + esc(f.id === 'theme' ? head + ' · from your theme' : f.sample) + '</small></span></button>';
    }).join('');
    $$('.qs-font').forEach(function (b) { b.addEventListener('click', function () { queue({ font_pair: b.getAttribute('data-f') }); renderFonts(); }); });
  }
  function renderLogo() {
    var box = $('#qs-logo-img');
    box.innerHTML = D.logo_url ? '<img src="' + esc(D.logo_url) + '" alt="Your logo">' : '<i class="fa-regular fa-image" aria-hidden="true"></i>';
    $('#qs-logo-remove').hidden = !D.logo_url;
  }
  function uploadLogo(file) {
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { hint('Logos must be under 2 MB.', true); return; }
    var fd = new FormData(); fd.append('logo', file);
    $('#qs-logo').classList.add('is-busy'); setSave('saving', 'Uploading logo…');
    post('/setup-wizard/logo', fd, true).then(function (r) {
      $('#qs-logo').classList.remove('is-busy');
      if (r && r.ok) { D.logo_url = r.url; renderLogo(); setSave('saved', 'Logo saved'); refreshLive(true); }
      else { setSave('error', 'Upload failed'); hint((r && r.error) || 'Upload failed.', true); }
    }).catch(function () { $('#qs-logo').classList.remove('is-busy'); setSave('error', 'Upload failed'); });
  }
  $('#qs-logo-file').addEventListener('change', function (e) { uploadLogo(e.target.files[0]); e.target.value = ''; });
  $('#qs-logo-remove').addEventListener('click', function () { queue({ logo_url: '' }, true); renderLogo(); });
  var drop = $('#qs-logo');
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); }); });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files[0]) uploadLogo(e.dataTransfer.files[0]); });

  /* live preview (Brand step) — reloads after each save, keeps scroll position */
  var liveT = null, liveFrame = $('#qs-live');
  function wizUrl(page, extra) { return '/_preview/wizard/' + (page || 'home') + '?kit=' + encodeURIComponent(D.kit || '') + (extra || '') + '&t=' + Date.now(); }
  function refreshLive(force) {
    if (step !== 3 || !D.kit) return;
    clearTimeout(liveT);
    liveT = setTimeout(function () {
      var y = 0; try { y = liveFrame.contentWindow.scrollY || 0; } catch (e) {}
      liveFrame.parentNode.classList.add('is-busy');
      liveFrame.onload = function () { liveFrame.parentNode.classList.remove('is-busy'); try { liveFrame.contentWindow.scrollTo(0, y); } catch (e) {} fitLive(); };
      liveFrame.src = wizUrl('home');
    }, force ? 0 : 150);
  }
  function fitLive() { var s = liveFrame.parentNode.clientWidth / 1280; liveFrame.style.transform = 'scale(' + s + ')'; liveFrame.parentNode.style.height = Math.round(900 * s) + 'px'; }
  window.addEventListener('resize', function () { if (step === 3) fitLive(); });
  $$('[data-full]').forEach(function (b) { b.addEventListener('click', function () { fullPreview(b.getAttribute('data-full')); }); });
  function fullPreview(page) {
    if (!window.MovaSectionPicker) { window.open(wizUrl(page), '_blank'); return; }
    window.MovaSectionPicker.preview(wizUrl(page), { title: (D.site_name || 'Your site') + ' — preview' });
  }

  /* ---------- 4 · Theme ---------- */
  var themesMounted = false;
  function mountThemes() {
    if (themesMounted || !window.MovaThemeGallery) return;
    themesMounted = true;
    window.MovaThemeGallery.mount($('#qs-themes'), {
      themes: B.themes.map(function (t) { var c = {}; for (var k in t) c[k] = t[k]; c.active = false; return c; }),
      selected: D.theme || 'presence',
      applyLabel: 'Use this theme',
      liveUrl: function (slug) { return '/_preview/wizard/home?kit=' + encodeURIComponent(D.kit || '') + '&theme=' + encodeURIComponent(slug); },
      previewTitle: function (t) { return t.name + ' — on your new site'; },
      onApply: function (slug) { queue({ theme: slug }, true); return true; }
    });
  }

  /* ---------- 5 · Content & AI ---------- */
  ['about', 'city', 'phone', 'email', 'address'].forEach(function (f) {
    var i = $('#qs-' + f); if (!i) return;
    i.value = D[f] || '';
    i.addEventListener('input', function () { queue((function () { var o = {}; o[f] = i.value; return o; })()); });
  });
  var polling = false;
  function aiState() { return D.ai || {}; }
  function renderAi() {
    var box = $('#qs-ai-body'), j = aiState(), k = kitById(D.kit), total = j.total || (k ? (k.page_list || []).length : 0), done = (j.done || []).length;
    if (!B.ai) {
      box.innerHTML = '<p>Your pages start with polished copy written for ' + esc(({ school: 'schools', organization: 'organisations', generic: 'small businesses' })[D.pack] || 'your kind of site') +
        ', personalised with your name, tagline and the facts on the left.</p><p class="qs-muted">Want AI-written copy? Add an AI key in <a href="/hq/ai">AI settings</a> and come back — your draft is saved.</p>';
      return;
    }
    var st = j.status || '';
    if (st === 'running') {
      var pct = total ? Math.round(done / total * 100) : 0;
      box.innerHTML = '<p><strong>Writing your pages…</strong> ' + (j.current ? 'Finished “' + esc(j.current) + '”.' : 'Starting.') + '</p>' +
        '<div class="qs-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '"><i style="width:' + Math.max(pct, 6) + '%"></i></div>' +
        '<p class="qs-muted">' + done + ' of ' + total + ' pages · runs in the background — carry on, it keeps going while you review.</p>' +
        '<button type="button" class="qs-link" data-ai="stop">Stop and keep what’s done</button>';
      poll();
    } else if (st === 'done' || st === 'stopped') {
      var n = (D.copy_pages || []).length;
      box.innerHTML = '<p><i class="fa-solid fa-circle-check qs-ok" aria-hidden="true"></i> <strong>' + (n ? n + ' page' + (n === 1 ? '' : 's') + ' rewritten by AI.' : 'Using the default copy.') + '</strong></p>' +
        (j.errors ? '<p class="qs-muted">' + esc(j.last_error || 'Some pages kept the default copy.') + '</p>' : '') +
        (j.stale ? '<p class="qs-warn">You changed your facts since — rewrite to match.</p>' : '') +
        '<div class="qs-ai__btns"><button type="button" class="msp-btn msp-btn--ghost" data-ai="start">Rewrite again</button>' +
        (n ? '<button type="button" class="qs-link" data-ai="discard">Use default copy instead</button>' : '') + '</div>';
    } else {
      box.innerHTML = '<p>Pages start with polished default copy. Let AI rewrite it around your facts — one page at a time, in the background.</p>' +
        '<button type="button" class="qs-go qs-go--sm" data-ai="start"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Write my copy with AI</button>' +
        '<p class="qs-muted">Takes about ' + Math.max(1, Math.round(total * 0.3)) + '–' + Math.max(2, total) + ' minutes. Prices, numbers and contact details are never invented.</p>';
    }
    $$('[data-ai]', box).forEach(function (b) { b.addEventListener('click', function () { aiAction(b.getAttribute('data-ai')); }); });
    $('#qs-ai').classList.toggle('is-running', st === 'running');
  }
  function aiAction(a) {
    flush().then(function () {
      var url = a === 'start' ? '/setup-wizard/copy/start' : '/setup-wizard/copy/cancel';
      return post(url, a === 'discard' ? { discard: 1 } : {});
    }).then(function (r) {
      if (r && r.draft) { D.ai = r.draft.ai || {}; D.copy_pages = r.draft.copy_pages || []; }
      else if (r && r.error) hint(r.error, true);
      renderAi(); aiBadge();
    });
  }
  var fails = 0;
  function poll() {
    if (polling) return; polling = true;
    (function tick() {
      if ((aiState().status || '') !== 'running') { polling = false; return; }
      post('/setup-wizard/copy/step', {}).then(function (r) {
        fails = 0;
        if (r && r.draft) { D.ai = r.draft.ai || {}; D.copy_pages = r.draft.copy_pages || []; }
        if (step === 5) renderAi(); aiBadge();
        if (step === 6 && r && r.draft && (D.copy_pages || []).indexOf(curPage) >= 0) reloadReview();
        setTimeout(tick, r && r.busy ? 4000 : 400);
      }).catch(function () { fails++; setTimeout(tick, Math.min(30000, 3000 * fails)); });
    })();
  }
  function aiBadge() {
    var j = aiState(), dot = $('.qs-dot[data-go="5"]');
    if (dot) dot.classList.toggle('is-busy', j.status === 'running');
    var n = $('#qs-review-ai');
    if (n) { n.hidden = j.status !== 'running'; n.innerHTML = '<i class="fa-solid fa-feather-pointed fa-beat-fade" aria-hidden="true"></i> AI is still writing (' + (j.done || []).length + ' of ' + (j.total || '?') + ' pages). Pages refresh as they’re finished — you can publish at any time.'; }
  }

  /* ---------- 6 · Review ---------- */
  var curPage = 'home';
  function renderReview() {
    var k = kitById(D.kit); if (!k) return;
    var pages = k.page_list || [];
    if (!pages.some(function (p) { return p.slug === curPage; })) curPage = pages[0] ? pages[0].slug : 'home';
    $('#qs-pages').innerHTML = pages.map(function (p) {
      return '<button type="button" role="tab" aria-selected="' + (p.slug === curPage) + '" class="qs-tab' + (p.slug === curPage ? ' is-on' : '') + '" data-page="' + esc(p.slug) + '">' + esc(p.title) + '</button>';
    }).join('');
    $$('.qs-tab').forEach(function (b) { b.addEventListener('click', function () { curPage = b.getAttribute('data-page'); renderReview(); }); });
    reloadReview(); aiBadge();
  }
  function reloadReview() {
    flush().then(function () {
      var f = $('#qs-review');
      f.onload = function () {
        // keep the tabs in sync when the user clicks links inside the preview
        try { var m = f.contentWindow.location.pathname.match(/\/_preview\/wizard\/([^/?#]+)/); if (m && m[1] !== curPage) { curPage = m[1]; $$('.qs-tab').forEach(function (b) { var on = b.getAttribute('data-page') === curPage; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); }); } } catch (e) {}
      };
      f.src = wizUrl(curPage);
    });
  }
  $$('.qs-seg [data-dev]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('.qs-seg [data-dev]').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-pressed', x === b); });
      $('#qs-review-stage').setAttribute('data-dev', b.getAttribute('data-dev'));
    });
  });

  /* ---------- 7 · Publish ---------- */
  $$('input[name="status"]').forEach(function (r) {
    r.checked = r.value === (D.status || 'published');
    r.addEventListener('change', function () { queue({ status: r.value }); $('#qs-go span').textContent = r.value === 'draft' ? 'Create draft pages' : 'Publish my site'; });
  });
  function renderSummary() {
    var k = kitById(D.kit) || {}, th = '', fp = '';
    B.themes.forEach(function (t) { if (t.slug === (D.theme || 'presence')) th = t.name; });
    B.fontPairs.forEach(function (f) { if (f.id === (D.font_pair || 'theme')) fp = f.label; });
    var j = aiState(), n = (D.copy_pages || []).length;
    var row = function (dt, dd, go) { return '<div><dt>' + dt + '</dt><dd>' + dd + '</dd><button type="button" class="qs-link" data-go="' + go + '">Change</button></div>'; };
    $('#qs-summary').innerHTML =
      row('Site', '<strong>' + esc(D.site_name || '') + '</strong>' + (D.tagline ? ' · ' + esc(D.tagline) : ''), 3) +
      row('Kit', esc(k.label || '') + ' · ' + (k.page_list || []).length + ' pages: ' + (k.page_list || []).map(function (p) { return esc(p.title); }).join(', '), 2) +
      row('Theme', esc(th) + ' · ' + esc(fp) + ' fonts', 4) +
      row('Colours', ['primary', 'accent', 'secondary'].map(function (c) { return '<i class="qs-chip" style="background:' + esc(D[c] || (k.palette || {})[c] || '#ccc') + '"></i>'; }).join('') + (D.logo_url ? ' · logo added' : ' · no logo (name shown)'), 3) +
      row('Copy', j.status === 'running' ? 'AI is writing (' + (j.done || []).length + '/' + (j.total || '?') + ') — publishing now keeps the rest as default copy' : (n ? n + ' pages written by AI' : 'Default copy, personalised'), 5);
    $$('#qs-summary [data-go]').forEach(function (b) { b.addEventListener('click', function () { go(+b.getAttribute('data-go'), { focus: true }); }); });
  }
  $('#qs-go').addEventListener('click', function () {
    var btn = this, err = $('#qs-pub-err');
    for (var s = 1; s < LAST; s++) { var why = canLeave(s); if (why) { go(s); hint(why, true); return; } }
    btn.disabled = true; btn.classList.add('is-busy'); btn.querySelector('span').textContent = 'Building your site…'; err.hidden = true;
    flush().then(function () { return post('/setup-wizard/publish', {}); }).then(function (r) {
      if (!r || !r.ok) throw new Error((r && r.error) || 'Publishing failed.');
      D.ai = {}; // job is over
      $('#qs-publish').hidden = true; $('#qs-foot').hidden = true;
      var done = $('#qs-done'); done.hidden = false;
      var draft = (D.status || 'published') === 'draft';
      done.querySelector('h2').textContent = draft ? 'Your draft pages are ready' : 'Your site is live';
      $('#qs-done-msg').textContent = (r.message || '') + ' Menu, footer, homepage, brand and theme are set.';
      $('#qs-done-pages').innerHTML = (r.pages || []).map(function (p) {
        return '<li><a href="' + esc(p.url || ('/' + p.slug)) + '" target="_blank" rel="noopener">' + esc(p.title) + '</a><a class="qs-link" href="/hq/content/edit/' + esc(p.id) + '">Edit</a></li>';
      }).join('');
      $$('.qs-dot').forEach(function (b) { b.classList.add('is-done'); b.classList.remove('is-on'); b.removeAttribute('aria-current'); });
      $('#qs-bar').style.width = '100%';
      try { done.focus({ preventScroll: true }); } catch (e) {}
      window.scrollTo({ top: 0, behavior: 'auto' });
    }).catch(function (e) {
      err.textContent = e.message || 'Publishing failed.'; err.hidden = false;
    }).then(function () { btn.disabled = false; btn.classList.remove('is-busy'); btn.querySelector('span').textContent = (D.status === 'draft') ? 'Create draft pages' : 'Publish my site'; });
  });

  /* ---------- boot ---------- */
  renderKits(); syncBrand();
  if (step > maxReach()) step = maxReach();
  go(step, { noScroll: true });
  aiBadge();
  if ((aiState().status || '') === 'running') poll();
})();
