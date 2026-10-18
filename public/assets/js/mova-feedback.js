/**
 * Mova Feedback — mounts .mova-feedback[data-form]
 * Supports Intl Phone (flag + dial) and Country (searchable) fields.
 */
(function () {
  'use strict';

  function el(tag, cls, attrs) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'text') n.textContent = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    return n;
  }


  function countries() {
    var list = window.MOVA_FB_COUNTRIES;
    if (list && list.length) return list;
    // Minimal fallback if countries script failed to load
    return [
      {c:'US',n:'United States',d:'1',f:'🇺🇸'},
      {c:'GB',n:'United Kingdom',d:'44',f:'🇬🇧'},
      {c:'NG',n:'Nigeria',d:'234',f:'🇳🇬'},
      {c:'CA',n:'Canada',d:'1',f:'🇨🇦'},
      {c:'AU',n:'Australia',d:'61',f:'🇦🇺'},
      {c:'IN',n:'India',d:'91',f:'🇮🇳'},
      {c:'DE',n:'Germany',d:'49',f:'🇩🇪'},
      {c:'FR',n:'France',d:'33',f:'🇫🇷'},
      {c:'GH',n:'Ghana',d:'233',f:'🇬🇭'},
      {c:'KE',n:'Kenya',d:'254',f:'🇰🇪'},
      {c:'ZA',n:'South Africa',d:'27',f:'🇿🇦'},
      {c:'AE',n:'United Arab Emirates',d:'971',f:'🇦🇪'}
    ];
  }


  /** Real flag images — emoji flags often render as ISO letters (NG) on Windows */
  function flagImg(code, cls) {
    code = String(code || '').toLowerCase();
    if (!/^[a-z]{2}$/.test(code)) code = 'un';
    cls = cls || 'mova-fb-flag';
    return '<img class="' + cls + '" src="https://flagcdn.com/w40/' + code + '.png" ' +
      'srcset="https://flagcdn.com/w40/' + code + '.png 1x, https://flagcdn.com/w80/' + code + '.png 2x" ' +
      'width="20" height="15" alt="" loading="lazy" decoding="async">';
  }

  function findCountry(code) {
    code = String(code || '').toUpperCase();
    var list = countries();
    for (var i = 0; i < list.length; i++) {
      if (list[i].c === code) return list[i];
    }
    return list[0] || { c: 'US', n: 'United States', d: '1', f: '🇺🇸' };
  }

  function guessCountryCode() {
    try {
      var lang = (navigator.language || navigator.userLanguage || 'en-US').split(/[-_]/);
      if (lang.length > 1) {
        var c = lang[1].toUpperCase();
        if (findCountry(c).c === c) return c;
      }
      // timezone heuristic for a few regions
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
      if (/Lagos|Africa\//.test(tz)) return 'NG';
      if (/London/.test(tz)) return 'GB';
      if (/New_York|Chicago|Los_Angeles|Denver/.test(tz)) return 'US';
    } catch (e) {}
    return 'US';
  }

  function ensureCountriesScript(cb) {
    if (countries().length) {
      cb();
      return;
    }
    if (document.querySelector('script[src*="mova-feedback-countries"]')) {
      var tries = 0;
      var t = setInterval(function () {
        tries++;
        if (countries().length || tries > 40) {
          clearInterval(t);
          cb();
        }
      }, 50);
      return;
    }
    var s = document.createElement('script');
    s.src = '/assets/js/mova-feedback-countries.js?v=1&t=' + Date.now();
    s.onload = function () { cb(); };
    s.onerror = function () { cb(); };
    document.head.appendChild(s);
  }

  function ensureCss() {
    if (!document.querySelector('link[href*="mova-feedback.css"]')) {
      var l = document.createElement('link');
      l.rel = 'stylesheet';
      l.href = '/assets/css/mova-feedback.css?v=6';
      document.head.appendChild(l);
    }
  }

  function closeAllPanels(except) {
    document.querySelectorAll('.mova-fb-panel.is-open').forEach(function (p) {
      if (p !== except) p.classList.remove('is-open');
    });
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.mova-fb-intl') && !e.target.closest('.mova-fb-country')) {
      closeAllPanels();
    }
  });

  /**
   * Resolve typed text to a country (exact → starts-with → contains → fuzzy).
   * Used when users type a full name without clicking the list.
   */
  function resolveCountryMatch(text) {
    var q = String(text || '').toLowerCase().trim().replace(/\s+/g, ' ');
    if (!q) return null;
    var list = countries();
    var i, c, name, code;
    // Exact name or ISO
    for (i = 0; i < list.length; i++) {
      c = list[i];
      name = String(c.n || '').toLowerCase();
      code = String(c.c || '').toLowerCase();
      if (name === q || code === q) return c;
    }
    // Exact dial (+234 / 234)
    var dialQ = q.replace(/^\+/, '');
    if (/^\d{1,4}$/.test(dialQ)) {
      for (i = 0; i < list.length; i++) {
        if (String(list[i].d) === dialQ) return list[i];
      }
    }
    // Starts with name
    var starts = [];
    for (i = 0; i < list.length; i++) {
      c = list[i];
      name = String(c.n || '').toLowerCase();
      if (name.indexOf(q) === 0) starts.push(c);
    }
    if (starts.length === 1) return starts[0];
    if (starts.length > 1) {
      // Prefer shortest name (e.g. "Niger" vs "Nigeria" when typing "Niger ")
      starts.sort(function (a, b) { return a.n.length - b.n.length; });
      return starts[0];
    }
    // Contains
    var contains = [];
    for (i = 0; i < list.length; i++) {
      c = list[i];
      name = String(c.n || '').toLowerCase();
      code = String(c.c || '').toLowerCase();
      if (name.indexOf(q) !== -1 || code.indexOf(q) !== -1) contains.push(c);
    }
    if (contains.length === 1) return contains[0];
    if (contains.length > 1) {
      contains.sort(function (a, b) { return a.n.length - b.n.length; });
      return contains[0];
    }
    // Simple fuzzy: all query tokens appear in name
    var tokens = q.split(' ').filter(Boolean);
    if (tokens.length) {
      var fuzzy = [];
      for (i = 0; i < list.length; i++) {
        c = list[i];
        name = String(c.n || '').toLowerCase();
        var ok = true;
        for (var t = 0; t < tokens.length; t++) {
          if (name.indexOf(tokens[t]) === -1) { ok = false; break; }
        }
        if (ok) fuzzy.push(c);
      }
      if (fuzzy.length) {
        fuzzy.sort(function (a, b) { return a.n.length - b.n.length; });
        return fuzzy[0];
      }
    }
    return null;
  }

  function buildCountryList(panel, filter, onPick, mode) {
    panel.innerHTML = '';
    var q = String(filter || '').toLowerCase().trim();
    var list = countries().filter(function (c) {
      if (!q) return true;
      return (c.n + ' ' + c.c + ' ' + c.d).toLowerCase().indexOf(q) !== -1;
    });
    // Full list when not filtering — no slice so scroll reaches N, Z, etc.
    if (!list.length) {
      panel.appendChild(el('div', 'mova-fb-panel-empty', { text: 'No matches' }));
      return;
    }
    list.forEach(function (c) {
      var row = el('button', 'mova-fb-panel-item', { type: 'button' });
      if (mode === 'dial') {
        row.innerHTML = flagImg(c.c) +
          '<span class="mova-fb-panel-name">' + c.n + '</span>' +
          '<span class="mova-fb-panel-dial">+' + c.d + '</span>';
      } else {
        row.innerHTML = flagImg(c.c) +
          '<span class="mova-fb-panel-name">' + c.n + '</span>';
      }
      row.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        onPick(c);
        panel.classList.remove('is-open');
      });
      panel.appendChild(row);
    });
  }

  function intlPhoneField(f) {
    var wrap = el('div', 'mova-fb-field mova-fb-field-intl');
    var lab = el('label', '');
    lab.innerHTML = (f.label || f.key) + (f.required ? ' <span class="req">*</span>' : '');
    wrap.appendChild(lab);

    var widget = el('div', 'mova-fb-intl');
    var iso = guessCountryCode();
    var current = findCountry(iso);

    var trigger = el('button', 'mova-fb-intl-trigger', { type: 'button', 'aria-label': 'Country code' });
    function paintTrigger() {
      // PNG flag + dial (emoji flags show as ISO letters on many Windows systems)
      trigger.innerHTML = flagImg(current.c) +
        '<span class="mova-fb-dial">+' + current.d + '</span>' +
        '<span class="mova-fb-chevron" aria-hidden="true">▾</span>';
      trigger.setAttribute('title', (current.n || '') + ' +' + current.d);
    }
    paintTrigger();

    var num = el('input', 'mova-fb-intl-num', {
      type: 'tel',
      inputmode: 'tel',
      autocomplete: 'tel-national',
      placeholder: f.placeholder || 'Phone number'
    });
    if (f.required) num.required = true;

    var hidden = el('input', '', { type: 'hidden', name: f.key, value: '' });
    var hiddenIso = el('input', '', { type: 'hidden', name: f.key + '_country', value: current.c });

    var panel = el('div', 'mova-fb-panel mova-fb-intl-panel');
    var search = el('input', 'mova-fb-panel-search', {
      type: 'search',
      placeholder: 'Search country or code…',
      autocomplete: 'off'
    });
    panel.appendChild(search);
    var listBox = el('div', 'mova-fb-panel-list');
    panel.appendChild(listBox);

    function syncHidden() {
      var raw = String(num.value || '').replace(/[^\d+()\-\s]/g, '').trim();
      var digits = raw.replace(/\D/g, '');
      if (!digits) {
        hidden.value = '';
      } else {
        hidden.value = '+' + current.d + ' ' + raw.replace(/^\+?/, '').replace(new RegExp('^' + current.d), '').trim();
        if (hidden.value.indexOf('+' + current.d) !== 0) {
          hidden.value = '+' + current.d + ' ' + digits;
        }
      }
      hiddenIso.value = current.c;
    }

    function openPanel() {
      closeAllPanels(panel);
      panel.classList.add('is-open');
      buildCountryList(listBox, search.value, function (c) {
        current = c;
        paintTrigger();
        syncHidden();
      }, 'dial');
      setTimeout(function () { search.focus(); }, 10);
    }

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      if (panel.classList.contains('is-open')) panel.classList.remove('is-open');
      else openPanel();
    });
    search.addEventListener('input', function () {
      buildCountryList(listBox, search.value, function (c) {
        current = c;
        paintTrigger();
        syncHidden();
      }, 'dial');
    });
    num.addEventListener('input', syncHidden);
    num.addEventListener('change', syncHidden);

    widget.appendChild(trigger);
    widget.appendChild(num);
    widget.appendChild(hidden);
    widget.appendChild(hiddenIso);
    widget.appendChild(panel);
    wrap.appendChild(widget);
    if (f.help) wrap.appendChild(el('div', 'mova-fb-help', { text: f.help }));
    syncHidden();
    return wrap;
  }

  function countryField(f) {
    var wrap = el('div', 'mova-fb-field mova-fb-field-country');
    var lab = el('label', '');
    lab.innerHTML = (f.label || f.key) + (f.required ? ' <span class="req">*</span>' : '');
    wrap.appendChild(lab);

    var widget = el('div', 'mova-fb-country');
    var search = el('input', 'mova-fb-country-input', {
      type: 'text',
      autocomplete: 'country-name',
      placeholder: f.placeholder || 'Start typing a country…',
      role: 'combobox',
      'aria-autocomplete': 'list'
    });
    if (f.required) search.required = true;
    var hidden = el('input', '', { type: 'hidden', name: f.key, value: '' });
    var hiddenCode = el('input', '', { type: 'hidden', name: f.key + '_code', value: '' });

    var panel = el('div', 'mova-fb-panel mova-fb-country-panel');
    var listBox = el('div', 'mova-fb-panel-list');
    panel.appendChild(listBox);

    function pick(c) {
      if (!c) return;
      search.value = c.n;
      hidden.value = c.n;
      hiddenCode.value = c.c;
      panel.classList.remove('is-open');
    }

    function resolveTyped() {
      var typed = String(search.value || '').trim();
      if (!typed) {
        hidden.value = '';
        hiddenCode.value = '';
        return null;
      }
      // Already matched
      if (hidden.value && hidden.value.toLowerCase() === typed.toLowerCase()) {
        return { n: hidden.value, c: hiddenCode.value };
      }
      var match = resolveCountryMatch(typed);
      if (match) {
        pick(match);
        return match;
      }
      return null;
    }

    function openList() {
      closeAllPanels(panel);
      panel.classList.add('is-open');
      // Empty filter → full world list for scroll
      buildCountryList(listBox, search.value, pick, 'country');
    }

    search.addEventListener('focus', function () {
      openList();
    });
    search.addEventListener('input', function () {
      // Keep list filtered while typing; commit happens on blur/submit
      openList();
    });
    search.addEventListener('blur', function () {
      // Delay so list click can fire first
      setTimeout(function () {
        resolveTyped();
        panel.classList.remove('is-open');
      }, 180);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') panel.classList.remove('is-open');
      if (e.key === 'Enter') {
        e.preventDefault();
        resolveTyped();
        panel.classList.remove('is-open');
      }
    });
    // Expose for form submit
    widget._resolveCountry = resolveTyped;

    widget.appendChild(search);
    widget.appendChild(hidden);
    widget.appendChild(hiddenCode);
    widget.appendChild(panel);
    wrap.appendChild(widget);
    if (f.help) wrap.appendChild(el('div', 'mova-fb-help', { text: f.help }));
    return wrap;
  }


  function normType(t) {
    t = String(t || 'text').toLowerCase().trim().replace(/[-\s]+/g, '_');
    if (t === 'international_phone' || t === 'intlphone' || t === 'phone_intl') return 'intl_phone';
    return t;
  }

  function fieldHtml(f) {
    f = f || {};
    var type = normType(f.type);
    f = Object.assign({}, f, { type: type });

    if (type === 'hidden') {
      var wrapH = el('div', 'mova-fb-field');
      wrapH.appendChild(el('input', '', { type: 'hidden', name: f.key, value: f.placeholder || '' }));
      return wrapH;
    }
    if (type === 'intl_phone') return intlPhoneField(f);
    if (type === 'country') return countryField(f);

    var wrap = el('div', 'mova-fb-field');
    var lab = el('label', '');
    lab.innerHTML = (f.label || f.key) + (f.required ? ' <span class="req">*</span>' : '');
    wrap.appendChild(lab);

    if (type === 'textarea') {
      var ta = el('textarea', '', { name: f.key, placeholder: f.placeholder || '' });
      if (f.required) ta.required = true;
      wrap.appendChild(ta);
    } else if (type === 'select') {
      var sel = el('select', '', { name: f.key });
      sel.appendChild(el('option', '', { value: '', text: '—' }));
      (f.options || []).forEach(function (o) {
        sel.appendChild(el('option', '', { value: o, text: o }));
      });
      if (f.required) sel.required = true;
      wrap.appendChild(sel);
    } else if (type === 'radio' || type === 'checkbox') {
      var box = el('div', 'mova-fb-options');
      (f.options || []).forEach(function (o, i) {
        var id = f.key + '_' + i;
        var row = el('label', '');
        var inp = el('input', '', {
          type: type,
          name: type === 'checkbox' ? f.key + '[]' : f.key,
          value: o,
          id: id
        });
        if (f.required && i === 0 && type === 'radio') inp.required = true;
        row.appendChild(inp);
        row.appendChild(document.createTextNode(' ' + o));
        box.appendChild(row);
      });
      wrap.appendChild(box);
    } else {
      var inputType = type === 'phone' ? 'tel'
        : (type === 'date' ? 'date'
        : (type === 'email' ? 'email'
        : (type === 'number' ? 'number' : 'text')));
      var input = el('input', '', { type: inputType, name: f.key, placeholder: f.placeholder || '' });
      if (f.required) input.required = true;
      wrap.appendChild(input);
    }
    if (f.help) wrap.appendChild(el('div', 'mova-fb-help', { text: f.help }));
    return wrap;
  }

  function mount(node) {
    if (node.dataset.movaMounted) return;
    node.dataset.movaMounted = '1';
    ensureCss();
    var slug = node.getAttribute('data-form') || 'contact';
    node.innerHTML = '<div class="mova-fb-card"><p class="mova-fb-desc">Loading form…</p></div>';

    function build(formDef) {
      var card = el('div', 'mova-fb-card');
      card.appendChild(el('h3', 'mova-fb-title', { text: formDef.title || 'Feedback' }));
      if (formDef.description) card.appendChild(el('p', 'mova-fb-desc', { text: formDef.description }));
      var form = el('form', 'mova-fb-form', { novalidate: 'novalidate' });
      (formDef.fields || []).forEach(function (f) { form.appendChild(fieldHtml(f)); });
      var hp = el('div', 'mova-fb-hp');
      hp.appendChild(el('input', '', { type: 'text', name: '_mova_hp', tabindex: '-1', autocomplete: 'off' }));
      form.appendChild(hp);
      var btn = el('button', 'mova-fb-submit', { type: 'submit', text: 'Send' });
      form.appendChild(btn);
      var msg = el('div', 'mova-fb-msg');
      form.appendChild(msg);
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        msg.textContent = '';
        msg.className = 'mova-fb-msg';
        // Resolve type-to-search fields before validate/submit
        var invalid = false;
        form.querySelectorAll('.mova-fb-country').forEach(function (w) {
          if (typeof w._resolveCountry === 'function') w._resolveCountry();
        });
        form.querySelectorAll('.mova-fb-field-country').forEach(function (w) {
          var req = w.querySelector('input.mova-fb-country-input');
          var hid = w.querySelector('input[type="hidden"]');
          // Try match once more from visible input
          if (req && (!hid || !hid.value)) {
            var m = resolveCountryMatch(req.value);
            if (m) {
              req.value = m.n;
              if (hid) hid.value = m.n;
              var hc = w.querySelector('input[name$="_code"]');
              if (hc) hc.value = m.c;
            }
          }
          if (req && req.required && (!hid || !hid.value)) {
            invalid = true;
            msg.className = 'mova-fb-msg err';
            msg.textContent = 'Please enter or select a valid country.';
          }
        });
        form.querySelectorAll('.mova-fb-field-intl').forEach(function (w) {
          var num = w.querySelector('.mova-fb-intl-num');
          var hid = w.querySelector('input[type="hidden"]');
          // sync phone hidden
          if (num) num.dispatchEvent(new Event('input'));
          if (num && num.required && (!hid || !String(hid.value).replace(/\D/g, ''))) {
            invalid = true;
            msg.className = 'mova-fb-msg err';
            msg.textContent = 'Please enter a phone number.';
          }
        });
        if (invalid) return;
        btn.disabled = true;
        var fd = new FormData(form);
        var payload = {};
        fd.forEach(function (v, k) {
          if (k === '_mova_hp') return;
          if (k.slice(-2) === '[]') {
            var key = k.slice(0, -2);
            if (!payload[key]) payload[key] = [];
            payload[key].push(v);
          } else {
            payload[k] = v;
          }
        });
        fetch('/feedback/api/submit/' + encodeURIComponent(slug), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ values: payload, page_url: location.href })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            btn.disabled = false;
            if (!res.ok || !res.j.success) {
              msg.className = 'mova-fb-msg err';
              msg.textContent = (res.j && res.j.error) || 'Could not send. Try again.';
              return;
            }
            msg.className = 'mova-fb-msg ok';
            msg.textContent = (formDef.success_message || 'Thank you — we received your message.');
            form.reset();
            form.querySelectorAll('.mova-fb-intl input[type="hidden"], .mova-fb-country input[type="hidden"]').forEach(function (h) {
              h.value = '';
            });
          }).catch(function () {
            btn.disabled = false;
            msg.className = 'mova-fb-msg err';
            msg.textContent = 'Network error. Try again.';
          });
      });
      card.appendChild(form);
      node.innerHTML = '';
      node.appendChild(card);
    }

    ensureCountriesScript(function () {
      fetch('/feedback/api/form/' + encodeURIComponent(slug), { credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) throw new Error('Form not found');
          return r.json();
        })
        .then(function (data) { build(data.form); })
        .catch(function () {
          node.innerHTML = '<div class="mova-fb-card"><p class="mova-fb-msg err">Form unavailable.</p></div>';
        });
    });
  }

  function boot() {
    ensureCss();
    document.querySelectorAll('.mova-feedback[data-form]').forEach(mount);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.MovaFeedback = { boot: boot };
})();
