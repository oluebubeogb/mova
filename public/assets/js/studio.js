/**
 * Mova Studio — Phase 2
 * Elements-style Col2 panels, attribute editor, Monaco, managed CSS block, AJAX save
 */
(function () {
  'use strict';

  var app = document.getElementById('studio-app');
  if (!app) return;

  var contentId = app.getAttribute('data-content-id');
  var csrf = app.getAttribute('data-csrf');
  var saveUrl = app.getAttribute('data-save-url');
  var monacoCdn = app.getAttribute('data-monaco-cdn') || 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs';

  var siteCssVars = '';
  var varMap = {};
  (function loadSiteVars() {
    try {
      var styleEl = document.getElementById('studio-site-css-vars');
      if (styleEl) siteCssVars = styleEl.textContent || '';
    } catch (e) {}
    try {
      var mapEl = document.getElementById('studio-var-map');
      if (mapEl) varMap = JSON.parse(mapEl.textContent || '{}') || {};
    } catch (e) { varMap = {}; }
  })();


  var htmlArea = document.getElementById('studio-html');
  var cssArea = document.getElementById('studio-css');
  var jsArea = document.getElementById('studio-js');
  var titleInput = document.getElementById('studio-title');
  var colsRoot = document.getElementById('studio-cols');
  var col2Host = document.getElementById('studio-col2-host');
  var preview = document.getElementById('studio-preview');
  var previewWrap = document.getElementById('studio-preview-wrap');
  var toastEl = document.getElementById('studio-toast');
  var dirtyEl = document.getElementById('studio-dirty');
  var errorBadge = document.getElementById('studio-error-badge');
  var styleTpl = document.getElementById('studio-col2-style-template');
  var attrsTpl = document.getElementById('studio-col2-attrs-template');

  // Monaco editors (or null → textarea fallback)
  var editors = { html: null, css: null, js: null };
  var monacoReady = false;
  var useMonaco = false;

  // Style map: key = "kind:name" → { desktop: { base: {props, custom_css}, hover:… }, tablet:…, mobile:… }
  var styleMap = {};
  var MANAGED_START = '/* === studio-managed:start === */';
  var MANAGED_END = '/* === studio-managed:end === */';

  var history = [];
  var historyIdx = -1;
  var maxHistory = 50;
  var applyingHistory = false;
  var dirty = false;
  var histTimer = null;
  var previewTimer = null;

  // ── Property groups (from Elements editor) ───────────────────────────────
  var GROUPS = [
    { id: 'typography', label: 'Typography', icon: 'fa-font', fields: [
      { prop: 'font-family', label: 'Font', type: 'text', placeholder: 'Inter, system-ui, sans-serif' },
      { prop: 'font-size', label: 'Size', type: 'text', placeholder: '1.5rem' },
      { prop: 'font-weight', label: 'Weight', type: 'select', options: ['', '300', '400', '500', '600', '700', '800'] },
      { prop: 'line-height', label: 'Line height', type: 'text', placeholder: '1.5' },
      { prop: 'letter-spacing', label: 'Letter spacing', type: 'text', placeholder: '0.02em' },
      { prop: 'text-align', label: 'Alignment', type: 'select', options: ['', 'left', 'center', 'right', 'justify'] },
      { prop: 'text-decoration', label: 'Decoration', type: 'select', options: ['', 'none', 'underline', 'line-through'] },
      { prop: 'text-transform', label: 'Transform', type: 'select', options: ['', 'none', 'uppercase', 'lowercase', 'capitalize'] }
    ]},
    { id: 'colors', label: 'Colors', icon: 'fa-droplet', fields: [
      { prop: 'color', label: 'Text', type: 'colortext' },
      { prop: 'background-color', label: 'Background', type: 'colortext' },
      { prop: 'border-color', label: 'Border', type: 'colortext' }
    ]},
    { id: 'spacing', label: 'Spacing', icon: 'fa-up-down-left-right', fields: [
      { prop: 'margin', label: 'Margin', type: 'text', placeholder: '0 0 1rem' },
      { prop: 'padding', label: 'Padding', type: 'text', placeholder: '0.75rem 1rem' },
      { prop: 'gap', label: 'Gap', type: 'text', placeholder: '1rem' }
    ]},
    { id: 'size', label: 'Size', icon: 'fa-maximize', fields: [
      { prop: 'width', label: 'Width', type: 'text', placeholder: 'auto' },
      { prop: 'height', label: 'Height', type: 'text', placeholder: 'auto' },
      { prop: 'min-width', label: 'Min width', type: 'text' },
      { prop: 'max-width', label: 'Max width', type: 'text' },
      { prop: 'min-height', label: 'Min height', type: 'text' },
      { prop: 'max-height', label: 'Max height', type: 'text' },
      { prop: 'aspect-ratio', label: 'Aspect ratio', type: 'text', placeholder: '16 / 9' }
    ]},
    { id: 'border', label: 'Border', icon: 'fa-border-all', fields: [
      { prop: 'border', label: 'Border', type: 'text', placeholder: '1px solid #e5e7eb' },
      { prop: 'border-radius', label: 'Radius', type: 'text', placeholder: '8px' },
      { prop: 'border-width', label: 'Width', type: 'text' },
      { prop: 'border-style', label: 'Style', type: 'select', options: ['', 'none', 'solid', 'dashed', 'dotted', 'double'] }
    ]},
    { id: 'layout', label: 'Layout', icon: 'fa-table-cells', fields: [
      { prop: 'display', label: 'Display', type: 'select', options: ['', 'block', 'inline', 'inline-block', 'flex', 'grid', 'none'] },
      { prop: 'position', label: 'Position', type: 'select', options: ['', 'static', 'relative', 'absolute', 'fixed', 'sticky'] },
      { prop: 'top', label: 'Top', type: 'text' }, { prop: 'right', label: 'Right', type: 'text' },
      { prop: 'bottom', label: 'Bottom', type: 'text' }, { prop: 'left', label: 'Left', type: 'text' },
      { prop: 'z-index', label: 'Z-index', type: 'text' },
      { prop: 'overflow', label: 'Overflow', type: 'select', options: ['', 'visible', 'hidden', 'scroll', 'auto'] }
    ]},
    { id: 'flexbox', label: 'Flexbox', icon: 'fa-grip', fields: [
      { prop: 'flex-direction', label: 'Direction', type: 'select', options: ['', 'row', 'row-reverse', 'column', 'column-reverse'] },
      { prop: 'flex-wrap', label: 'Wrap', type: 'select', options: ['', 'nowrap', 'wrap', 'wrap-reverse'] },
      { prop: 'justify-content', label: 'Justify', type: 'select', options: ['', 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'] },
      { prop: 'align-items', label: 'Align items', type: 'select', options: ['', 'stretch', 'flex-start', 'center', 'flex-end', 'baseline'] },
      { prop: 'flex', label: 'Flex', type: 'text', placeholder: '1 1 auto' }
    ]},
    { id: 'grid', label: 'Grid', icon: 'fa-border-all', fields: [
      { prop: 'grid-template-columns', label: 'Columns', type: 'text', placeholder: '1fr 1fr' },
      { prop: 'grid-template-rows', label: 'Rows', type: 'text', placeholder: 'auto' },
      { prop: 'grid-gap', label: 'Gap', type: 'text', placeholder: '1rem' },
      { prop: 'place-items', label: 'Place items', type: 'text', placeholder: 'center' }
    ]},
    { id: 'background', label: 'Background', icon: 'fa-image', fields: [
      { prop: 'background-color', label: 'Color', type: 'colortext' },
      { prop: 'background-image', label: 'Image / gradient', type: 'text', placeholder: 'linear-gradient(135deg, #2563eb, #7c3aed)' },
      { prop: 'background-size', label: 'Size', type: 'select', options: ['', 'auto', 'cover', 'contain', '100% 100%'] },
      { prop: 'background-position', label: 'Position', type: 'text', placeholder: 'center center' },
      { prop: 'background-repeat', label: 'Repeat', type: 'select', options: ['', 'no-repeat', 'repeat', 'repeat-x', 'repeat-y'] }
    ]},
    { id: 'effects', label: 'Effects', icon: 'fa-wand-magic-sparkles', fields: [
      { prop: 'box-shadow', label: 'Shadow', type: 'text', placeholder: '0 4px 12px rgba(0,0,0,0.12)' },
      { prop: 'opacity', label: 'Opacity', type: 'text', placeholder: '1' },
      { prop: 'transform', label: 'Transform', type: 'text', placeholder: 'translateY(-2px)' },
      { prop: 'transition', label: 'Transition', type: 'text', placeholder: 'all 0.2s ease' },
      { prop: 'filter', label: 'Filter', type: 'text', placeholder: 'blur(4px)' },
      { prop: 'cursor', label: 'Cursor', type: 'select', options: ['', 'auto', 'pointer', 'default', 'text', 'move', 'not-allowed'] }
    ]}
  ];

  // ── Document getters/setters (Monaco or textarea) ────────────────────────
  function getHtml() {
    if (useMonaco && editors.html) return editors.html.getValue();
    return htmlArea ? htmlArea.value : '';
  }
  function getCss() {
    if (useMonaco && editors.css) return editors.css.getValue();
    return cssArea ? cssArea.value : '';
  }
  function getJs() {
    if (useMonaco && editors.js) return editors.js.getValue();
    return jsArea ? jsArea.value : '';
  }
  function setHtml(v) {
    if (useMonaco && editors.html) editors.html.setValue(v || '');
    else if (htmlArea) htmlArea.value = v || '';
  }
  function setCss(v) {
    if (useMonaco && editors.css) editors.css.setValue(v || '');
    else if (cssArea) cssArea.value = v || '';
  }
  function setJs(v) {
    if (useMonaco && editors.js) editors.js.setValue(v || '');
    else if (jsArea) jsArea.value = v || '';
  }

  // ── Style state helpers ──────────────────────────────────────────────────
  function styleKey(kind, name) {
    return kind + ':' + name;
  }

  function selectorFor(kind, name) {
    if (kind === 'id') return '#' + cssIdent(name);
    if (kind === 'class') return '.' + cssIdent(name);
    return name; // tag
  }

  function cssIdent(s) {
    // Escape special chars for CSS identifiers (basic)
    return String(s).replace(/([^\w-])/g, '\\$1');
  }

  function emptyBp() {
    return {
      base: { props: {}, custom_css: '' },
      hover: { props: {}, custom_css: '' },
      focus: { props: {}, custom_css: '' }
    };
  }

  function ensureStyle(kind, name) {
    var k = styleKey(kind, name);
    if (!styleMap[k]) {
      styleMap[k] = {
        kind: kind,
        name: name,
        desktop: emptyBp(),
        tablet: emptyBp(),
        mobile: emptyBp()
      };
    }
    return styleMap[k];
  }

  function linesFrom(slice) {
    if (!slice) return [];
    var lines = [];
    var props = slice.props || {};
    Object.keys(props).forEach(function (p) {
      if (props[p] !== '' && props[p] != null) lines.push(p + ': ' + props[p] + ';');
    });
    if (slice.custom_css) {
      slice.custom_css.split('\n').forEach(function (l) {
        l = l.trim();
        if (l) lines.push(l);
      });
    }
    return lines;
  }

  function compileManagedCss() {
    var parts = [];
    Object.keys(styleMap).forEach(function (k) {
      var entry = styleMap[k];
      var sel = selectorFor(entry.kind, entry.name);

      function block(bpName, media) {
        var bp = entry[bpName];
        if (!bp) return;
        var chunks = [];
        ['base', 'hover', 'focus'].forEach(function (pseudo) {
          var lines = linesFrom(bp[pseudo]);
          if (!lines.length) return;
          var s = pseudo === 'base' ? sel : sel + ':' + pseudo;
          chunks.push(s + ' {\n  ' + lines.join('\n  ') + '\n}');
        });
        if (!chunks.length) return;
        var body = chunks.join('\n');
        if (media) {
          parts.push('@media (max-width: ' + media + ') {\n' +
            body.split('\n').map(function (l) { return '  ' + l; }).join('\n') + '\n}');
        } else {
          parts.push(body);
        }
      }

      block('desktop', null);
      block('tablet', '768px');
      block('mobile', '480px');
    });
    return parts.join('\n\n');
  }

  function writeManagedCssToEditor() {
    var current = getCss();
    var managed = compileManagedCss();
    var start = current.indexOf(MANAGED_START);
    var end = current.indexOf(MANAGED_END);
    var block = MANAGED_START + '\n' + (managed ? managed + '\n' : '') + MANAGED_END;

    var next;
    if (start !== -1 && end !== -1 && end > start) {
      next = current.slice(0, start) + block + current.slice(end + MANAGED_END.length);
    } else {
      // append
      next = (current ? current.replace(/\s+$/, '') + '\n\n' : '') + block;
    }
    // Avoid feedback loops
    if (next !== current) {
      applyingHistory = true;
      setCss(next);
      applyingHistory = false;
    }
  }

  // ── Snapshot / history ───────────────────────────────────────────────────
  function snapshot() {
    return {
      title: titleInput ? titleInput.value : '',
      html: getHtml(),
      css: getCss(),
      js: getJs(),
      styles: JSON.parse(JSON.stringify(styleMap))
    };
  }

  function pushHistory() {
    if (applyingHistory) return;
    var snap = snapshot();
    if (historyIdx < history.length - 1) history = history.slice(0, historyIdx + 1);
    var last = history[history.length - 1];
    if (last && last.html === snap.html && last.css === snap.css && last.js === snap.js && last.title === snap.title &&
        JSON.stringify(last.styles) === JSON.stringify(snap.styles)) return;
    history.push(snap);
    if (history.length > maxHistory) history.shift();
    historyIdx = history.length - 1;
    updateHistoryButtons();
  }

  function applySnapshot(snap) {
    applyingHistory = true;
    if (titleInput) titleInput.value = snap.title || '';
    setHtml(snap.html || '');
    setCss(snap.css || '');
    setJs(snap.js || '');
    styleMap = snap.styles ? JSON.parse(JSON.stringify(snap.styles)) : {};
    applyingHistory = false;
    rebuildNav();
    refreshOpenStylePanels();
    updatePreview();
    setDirty(true);
    updateHistoryButtons();
  }

  function undo() {
    if (historyIdx <= 0) return;
    historyIdx--;
    applySnapshot(history[historyIdx]);
  }
  function redo() {
    if (historyIdx >= history.length - 1) return;
    historyIdx++;
    applySnapshot(history[historyIdx]);
  }
  function updateHistoryButtons() {
    var u = document.getElementById('studio-undo');
    var r = document.getElementById('studio-redo');
    if (u) u.disabled = historyIdx <= 0;
    if (r) r.disabled = historyIdx >= history.length - 1;
  }

  function setDirty(val) {
    dirty = !!val;
    if (dirtyEl) dirtyEl.hidden = !dirty;
  }

  function toast(msg, isError) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('is-error', !!isError);
    toastEl.hidden = false;
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.hidden = true; }, 2200);
  }

  function scheduleHistory() {
    clearTimeout(histTimer);
    histTimer = setTimeout(pushHistory, 400);
  }

  // ── Parse structure ──────────────────────────────────────────────────────
  function parseStructure(html) {
    var ids = [], classes = [], tags = [];
    var idSet = {}, classSet = {}, tagSet = {};
    try {
      var doc = new DOMParser().parseFromString('<div id="__root">' + (html || '') + '</div>', 'text/html');
      var root = doc.getElementById('__root') || doc.body;
      function walk(node) {
        if (!node || node.nodeType !== 1) return;
        var tag = node.tagName ? node.tagName.toLowerCase() : '';
        if (tag && tag !== 'html' && tag !== 'head' && tag !== 'body' && tag !== 'script' && tag !== 'style') {
          if (!(tag === 'div' && node.id === '__root')) {
            if (!tagSet[tag]) { tagSet[tag] = true; tags.push(tag); }
          }
        }
        if (node.id && node.id !== '__root' && !idSet[node.id]) {
          idSet[node.id] = true; ids.push(node.id);
        }
        if (node.classList) {
          Array.prototype.forEach.call(node.classList, function (c) {
            if (c && !classSet[c]) { classSet[c] = true; classes.push(c); }
          });
        }
        var ch = node.children;
        if (ch) for (var i = 0; i < ch.length; i++) walk(ch[i]);
      }
      walk(root);
    } catch (e) {
      var m, reId = /\bid\s*=\s*["']([^"']+)["']/gi;
      while ((m = reId.exec(html || ''))) if (!idSet[m[1]]) { idSet[m[1]] = true; ids.push(m[1]); }
      var reClass = /\bclass\s*=\s*["']([^"']+)["']/gi;
      while ((m = reClass.exec(html || ''))) {
        m[1].split(/\s+/).forEach(function (c) {
          if (c && !classSet[c]) { classSet[c] = true; classes.push(c); }
        });
      }
      var reTag = /<\s*([a-zA-Z][a-zA-Z0-9]*)\b/g;
      while ((m = reTag.exec(html || ''))) {
        var t = m[1].toLowerCase();
        if (t !== 'html' && t !== 'head' && t !== 'body' && !tagSet[t]) { tagSet[t] = true; tags.push(t); }
      }
    }
    ids.sort(); classes.sort();
    var preferred = ['h1','h2','h3','h4','h5','h6','p','a','button','img','ul','ol','li','table','tr','td','th','section','article','header','footer','nav','main','div','span'];
    tags.sort(function (a, b) {
      var ia = preferred.indexOf(a), ib = preferred.indexOf(b);
      if (ia === -1) ia = 999; if (ib === -1) ib = 999;
      return ia !== ib ? ia - ib : (a < b ? -1 : a > b ? 1 : 0);
    });
    return { ids: ids, classes: classes, tags: tags };
  }

  function iconFor(kind, name) {
    if (kind === 'id') return 'fa-hashtag';
    if (kind === 'class') return 'fa-tags';
    var map = {
      h1: 'fa-heading', h2: 'fa-heading', h3: 'fa-heading', p: 'fa-paragraph', a: 'fa-link',
      img: 'fa-image', button: 'fa-square', table: 'fa-table', tr: 'fa-table',
      ul: 'fa-list', ol: 'fa-list-ol', section: 'fa-puzzle-piece', div: 'fa-cube', span: 'fa-i-cursor'
    };
    return map[name] || 'fa-code';
  }

  function shortLabel(kind, name) {
    if (name.length > 10) return name.slice(0, 9) + '…';
    return name;
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function rebuildNav() {
    var struct = parseStructure(getHtml());
    renderNavList('studio-nav-ids', 'id', struct.ids);
    renderNavList('studio-nav-classes', 'class', struct.classes);
    renderNavList('studio-nav-tags', 'tag', struct.tags);
  }

  function renderNavList(listId, kind, items) {
    var list = document.getElementById(listId);
    if (!list) return;
    list.innerHTML = '';
    if (!items.length) {
      var empty = document.createElement('div');
      empty.className = 'studio-nav-empty';
      empty.textContent = 'None';
      list.appendChild(empty);
      return;
    }
    items.forEach(function (name) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'studio-nav-item';
      btn.setAttribute('data-kind', kind);
      btn.setAttribute('data-name', name);
      var prefix = kind === 'id' ? '#' : kind === 'class' ? '.' : '';
      btn.title = kind === 'tag' ? '<' + name + '>' : prefix + name;
      if (styleMap[styleKey(kind, name)]) btn.classList.add('has-styles');
      btn.innerHTML = '<i class="fa-solid ' + iconFor(kind, name) + '"></i><span class="studio-nav-short">' +
        escapeHtml(shortLabel(kind, name)) + '</span>';
      btn.addEventListener('click', function () {
        openStylePanel(kind, name);
        list.querySelectorAll('.studio-nav-item').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
      });
      list.appendChild(btn);
    });
  }

  // ── Col2 style panel ─────────────────────────────────────────────────────
  function openStylePanel(kind, name) {
    if (!styleTpl || !col2Host) return;
    var existing = col2Host.querySelector('[data-panel="style"][data-kind="' + kind + '"][data-name="' + (window.CSS && CSS.escape ? CSS.escape(name) : name.replace(/"/g, "")) + '"]');
    if (existing) {
      existing.scrollIntoView({ inline: 'nearest', behavior: 'smooth' });
      return;
    }

    ensureStyle(kind, name);
    var node = styleTpl.content.cloneNode(true);
    var col = node.querySelector('[data-col2]');
    col.setAttribute('data-kind', kind);
    col.setAttribute('data-name', name);

    var label = col.querySelector('.studio-col2-label');
    var display = kind === 'tag' ? '<' + name + '>' : (kind === 'id' ? '#' : '.') + name;
    if (label) label.textContent = display;

    col._studio = { kind: kind, name: name, bp: 'desktop', pseudo: 'base' };

    // Breakpoint buttons
    col.querySelectorAll('.studio-bp-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        col._studio.bp = btn.getAttribute('data-bp');
        col.querySelectorAll('.studio-bp-btn').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        fillStyleForm(col);
      });
    });

    // Pseudo buttons
    col.querySelectorAll('.studio-pseudo-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        col._studio.pseudo = btn.getAttribute('data-pseudo');
        col.querySelectorAll('.studio-pseudo-btn').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        fillStyleForm(col);
      });
    });

    // Custom CSS
    var customTa = col.querySelector('.studio-custom-css');
    if (customTa) {
      customTa.addEventListener('input', function () {
        var entry = ensureStyle(kind, name);
        var slice = entry[col._studio.bp][col._studio.pseudo];
        slice.custom_css = customTa.value;
        onStyleChange();
      });
    }

    buildStyleGroups(col);
    fillStyleForm(col);

    col.querySelector('.studio-col2-close').addEventListener('click', function () {
      var prev = col.previousElementSibling;
      if (prev && prev.classList.contains('studio-splitter')) prev.remove();
      col.remove();
      persistLayout();
    });

    if (col2Host.children.length > 0) {
      var sp = document.createElement('div');
      sp.className = 'studio-splitter';
      sp.setAttribute('role', 'separator');
      col2Host.appendChild(sp);
      wireSplitter(sp);
    }
    col2Host.appendChild(col);
    ensureSplitters();
    persistLayout();
  }

  function buildStyleGroups(col) {
    var host = col.querySelector('.studio-style-groups');
    if (!host) return;
    host.innerHTML = '';
    GROUPS.forEach(function (g) {
      var details = document.createElement('details');
      details.className = 'studio-group';
      details.open = (g.id === 'typography' || g.id === 'colors' || g.id === 'spacing');
      var sum = document.createElement('summary');
      sum.innerHTML = '<i class="fa-solid ' + g.icon + '"></i> ' + escapeHtml(g.label);
      details.appendChild(sum);
      var body = document.createElement('div');
      body.className = 'studio-group-body';
      g.fields.forEach(function (f) {
        var row = document.createElement('div');
        row.className = 'studio-field';
        var lab = document.createElement('label');
        lab.className = 'studio-field-label';
        lab.textContent = f.label;
        row.appendChild(lab);

        if (f.type === 'select') {
          var sel = document.createElement('select');
          sel.className = 'input studio-prop';
          sel.setAttribute('data-prop', f.prop);
          (f.options || ['']).forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o;
            opt.textContent = o || '—';
            sel.appendChild(opt);
          });
          sel.addEventListener('change', function () { propChanged(col, f.prop, sel.value); });
          row.appendChild(sel);
        } else if (f.type === 'colortext') {
          var wrap = document.createElement('div');
          wrap.className = 'studio-color-row';
          var color = document.createElement('input');
          color.type = 'color';
          color.value = '#000000';
          color.className = 'studio-prop-color';
          color.setAttribute('data-prop', f.prop);
          var text = document.createElement('input');
          text.type = 'text';
          text.className = 'input studio-prop';
          text.setAttribute('data-prop', f.prop);
          text.placeholder = '#000 or rgb()';
          color.addEventListener('input', function () {
            text.value = color.value;
            propChanged(col, f.prop, color.value);
          });
          text.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) color.value = text.value;
            propChanged(col, f.prop, text.value);
          });
          wrap.appendChild(color);
          wrap.appendChild(text);
          row.appendChild(wrap);
        } else {
          var input = document.createElement('input');
          input.type = 'text';
          input.className = 'input studio-prop';
          input.setAttribute('data-prop', f.prop);
          input.placeholder = f.placeholder || '';
          input.addEventListener('input', function () { propChanged(col, f.prop, input.value); });
          row.appendChild(input);
        }
        body.appendChild(row);
      });
      details.appendChild(body);
      host.appendChild(details);
    });
  }

  function propChanged(col, prop, value) {
    var st = col._studio;
    var entry = ensureStyle(st.kind, st.name);
    var slice = entry[st.bp][st.pseudo];
    if (!slice.props) slice.props = {};
    if (value === '' || value == null) delete slice.props[prop];
    else slice.props[prop] = value;
    onStyleChange();
  }

  function fillStyleForm(col) {
    var st = col._studio;
    var entry = ensureStyle(st.kind, st.name);
    var slice = entry[st.bp][st.pseudo] || { props: {}, custom_css: '' };
    var props = slice.props || {};

    col.querySelectorAll('.studio-prop').forEach(function (el) {
      var prop = el.getAttribute('data-prop');
      var val = props[prop] || '';
      el.value = val;
      if (el.classList.contains('studio-prop') && el.type !== 'color') {
        var colorSibling = el.parentElement && el.parentElement.querySelector('.studio-prop-color[data-prop="' + prop + '"]');
        if (colorSibling && /^#[0-9a-fA-F]{6}$/.test(val)) colorSibling.value = val;
      }
    });
    col.querySelectorAll('.studio-prop-color').forEach(function (el) {
      var prop = el.getAttribute('data-prop');
      var val = props[prop] || '';
      if (/^#[0-9a-fA-F]{6}$/.test(val)) el.value = val;
    });
    var customTa = col.querySelector('.studio-custom-css');
    if (customTa) customTa.value = slice.custom_css || '';
  }

  function onStyleChange() {
    writeManagedCssToEditor();
    setDirty(true);
    scheduleHistory();
    schedulePreview();
    rebuildNav(); // refresh has-styles dots
  }

  function refreshOpenStylePanels() {
    col2Host.querySelectorAll('[data-panel="style"]').forEach(function (col) {
      if (col._studio) fillStyleForm(col);
    });
  }

  // ── Attribute panel ──────────────────────────────────────────────────────
  function openAttrsPanel(prefill) {
    if (!attrsTpl || !col2Host) return;
    var existing = col2Host.querySelector('[data-panel="attrs"]');
    if (existing) {
      if (prefill) {
        var sel = existing.querySelector('.studio-attr-selector');
        if (sel) sel.value = prefill;
      }
      existing.scrollIntoView({ inline: 'nearest', behavior: 'smooth' });
      return;
    }

    var node = attrsTpl.content.cloneNode(true);
    var col = node.querySelector('[data-col2]');
    if (prefill) {
      var s = col.querySelector('.studio-attr-selector');
      if (s) s.value = prefill;
    }

    col.querySelector('.studio-attr-apply').addEventListener('click', function () {
      applyAttributes(col);
    });
    col.querySelector('.studio-col2-close').addEventListener('click', function () {
      var prev = col.previousElementSibling;
      if (prev && prev.classList.contains('studio-splitter')) prev.remove();
      col.remove();
      persistLayout();
    });

    // Load current attrs when selector blurs
    var selInput = col.querySelector('.studio-attr-selector');
    if (selInput) {
      selInput.addEventListener('change', function () { loadAttributes(col); });
      selInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); loadAttributes(col); }
      });
    }

    if (col2Host.children.length > 0) {
      var sp = document.createElement('div');
      sp.className = 'studio-splitter';
      col2Host.appendChild(sp);
      wireSplitter(sp);
    }
    col2Host.appendChild(col);
    if (prefill) loadAttributes(col);
    ensureSplitters();
    persistLayout();
  }

  function loadAttributes(col) {
    var selector = (col.querySelector('.studio-attr-selector') || {}).value || '';
    selector = selector.trim();
    var status = col.querySelector('.studio-attr-status');
    if (!selector) {
      if (status) { status.hidden = false; status.textContent = 'Enter a selector'; }
      return;
    }
    try {
      var doc = new DOMParser().parseFromString('<div id="__root">' + getHtml() + '</div>', 'text/html');
      var root = doc.getElementById('__root');
      var el = root ? root.querySelector(selector) : null;
      if (!el) {
        if (status) { status.hidden = false; status.textContent = 'No match for ' + selector; }
        return;
      }
      col.querySelector('.studio-attr-id').value = el.id || '';
      col.querySelector('.studio-attr-class').value = el.className || '';
      var data = {};
      Array.prototype.forEach.call(el.attributes || [], function (a) {
        if (a.name.indexOf('data-') === 0) data[a.name.slice(5)] = a.value;
      });
      col.querySelector('.studio-attr-data').value = Object.keys(data).length ? JSON.stringify(data, null, 2) : '';
      if (status) { status.hidden = false; status.textContent = 'Loaded from first match'; }
    } catch (e) {
      if (status) { status.hidden = false; status.textContent = 'Invalid selector'; }
    }
  }

  function applyAttributes(col) {
    var selector = ((col.querySelector('.studio-attr-selector') || {}).value || '').trim();
    var newId = ((col.querySelector('.studio-attr-id') || {}).value || '').trim();
    var newClass = ((col.querySelector('.studio-attr-class') || {}).value || '').trim();
    var dataRaw = ((col.querySelector('.studio-attr-data') || {}).value || '').trim();
    var status = col.querySelector('.studio-attr-status');
    if (!selector) {
      if (status) { status.hidden = false; status.textContent = 'Enter a selector'; }
      return;
    }
    var dataObj = {};
    if (dataRaw) {
      try { dataObj = JSON.parse(dataRaw); }
      catch (e) {
        if (status) { status.hidden = false; status.textContent = 'Invalid data JSON'; }
        return;
      }
    }
    try {
      var doc = new DOMParser().parseFromString('<div id="__root">' + getHtml() + '</div>', 'text/html');
      var root = doc.getElementById('__root');
      var el = root ? root.querySelector(selector) : null;
      if (!el) {
        if (status) { status.hidden = false; status.textContent = 'No match'; }
        return;
      }
      if (newId) el.id = newId; else el.removeAttribute('id');
      if (newClass) el.className = newClass; else el.removeAttribute('class');
      // Remove existing data-* then set
      Array.prototype.slice.call(el.attributes).forEach(function (a) {
        if (a.name.indexOf('data-') === 0) el.removeAttribute(a.name);
      });
      Object.keys(dataObj).forEach(function (k) {
        el.setAttribute('data-' + k, String(dataObj[k]));
      });
      setHtml(root.innerHTML);
      setDirty(true);
      scheduleHistory();
      rebuildNav();
      schedulePreview();
      if (status) { status.hidden = false; status.textContent = 'Applied'; }
      toast('Attributes applied');
    } catch (e) {
      if (status) { status.hidden = false; status.textContent = 'Apply failed'; }
    }
  }

  // ── Monaco ───────────────────────────────────────────────────────────────
  function loadMonaco() {
    if (window.monaco && window.require) {
      initMonacoEditors();
      return;
    }
    window.MonacoEnvironment = {
      getWorkerUrl: function () {
        return URL.createObjectURL(new Blob([
          "self.MonacoEnvironment={baseUrl:'" + monacoCdn + "/../'};",
          "importScripts('" + monacoCdn + "/base/worker/workerMain.js');"
        ], { type: 'text/javascript' }));
      }
    };
    var loader = document.createElement('script');
    loader.src = monacoCdn + '/loader.js';
    loader.onload = function () {
      window.require.config({ paths: { vs: monacoCdn } });
      window.require(['vs/editor/editor.main'], function () {
        initMonacoEditors();
      });
    };
    loader.onerror = function () {
      console.warn('[Studio] Monaco failed — using textareas');
      fallbackTextareas();
    };
    document.head.appendChild(loader);
  }

  function fallbackTextareas() {
    ['html', 'css', 'js'].forEach(function (lang) {
      var mount = document.getElementById('studio-monaco-' + lang);
      var ta = document.getElementById('studio-' + lang);
      if (mount) mount.hidden = true;
      if (ta) {
        ta.hidden = lang !== 'html';
        ta.classList.add('is-fallback');
      }
    });
    useMonaco = false;
    monacoReady = true;
    bindTextareaEvents();
  }

  function initMonacoEditors() {
    var dark = document.body.classList.contains('theme-dark') ||
      document.documentElement.getAttribute('data-theme') === 'dark';
    try {
      monaco.editor.defineTheme('studio-light', {
        base: 'vs', inherit: true,
        rules: [], colors: { 'editor.background': '#ffffff' }
      });
      monaco.editor.defineTheme('studio-dark', {
        base: 'vs-dark', inherit: true,
        rules: [], colors: { 'editor.background': '#1e293b' }
      });
    } catch (e) {}

    var common = {
      automaticLayout: true,
      minimap: { enabled: false },
      fontSize: 13,
      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
      lineNumbers: 'on',
      scrollBeyondLastLine: false,
      wordWrap: 'on',
      tabSize: 2,
      theme: dark ? 'studio-dark' : 'studio-light'
    };

    editors.html = monaco.editor.create(document.getElementById('studio-monaco-html'), Object.assign({}, common, {
      value: htmlArea ? htmlArea.value : '',
      language: 'html'
    }));
    editors.css = monaco.editor.create(document.getElementById('studio-monaco-css'), Object.assign({}, common, {
      value: cssArea ? cssArea.value : '',
      language: 'css'
    }));
    editors.js = monaco.editor.create(document.getElementById('studio-monaco-js'), Object.assign({}, common, {
      value: jsArea ? jsArea.value : '',
      language: 'javascript'
    }));

    // Hide textareas
    [htmlArea, cssArea, jsArea].forEach(function (ta) { if (ta) ta.hidden = true; });

    useMonaco = true;
    monacoReady = true;

    editors.html.onDidChangeModelContent(onContentChange);
    editors.css.onDidChangeModelContent(onContentChange);
    editors.js.onDidChangeModelContent(onContentChange);

    monaco.editor.onDidChangeMarkers(function () {
      updateErrorBadge();
    });

    // Theme sync
    var obs = new MutationObserver(function () {
      var d = document.body.classList.contains('theme-dark') ||
        document.documentElement.getAttribute('data-theme') === 'dark';
      monaco.editor.setTheme(d ? 'studio-dark' : 'studio-light');
    });
    obs.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });

    // Layout on tab switch
    window.addEventListener('resize', function () {
      Object.keys(editors).forEach(function (k) {
        if (editors[k]) editors[k].layout();
      });
    });
  }

  function bindTextareaEvents() {
    [htmlArea, cssArea, jsArea].forEach(function (el) {
      if (el) el.addEventListener('input', onContentChange);
    });
  }

  function updateErrorBadge() {
    if (!errorBadge || !window.monaco) return;
    var count = 0;
    try {
      ['html', 'css', 'js'].forEach(function (lang) {
        if (!editors[lang]) return;
        var model = editors[lang].getModel();
        if (!model) return;
        var markers = monaco.editor.getModelMarkers({ resource: model.uri });
        markers.forEach(function (m) {
          if (m.severity >= 8) count++;
        });
      });
    } catch (e) {}
    if (count > 0) {
      errorBadge.hidden = false;
      errorBadge.textContent = count + (count === 1 ? ' error' : ' errors');
    } else {
      errorBadge.hidden = true;
    }
  }

  // ── Code tabs ────────────────────────────────────────────────────────────
  function initCodeTabs() {
    var tabs = document.querySelectorAll('.studio-code-tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var name = tab.getAttribute('data-tab');
        tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
        ['html', 'css', 'js'].forEach(function (lang) {
          var mount = document.getElementById('studio-monaco-' + lang);
          var ta = document.getElementById('studio-' + lang);
          var show = lang === name;
          if (useMonaco) {
            if (mount) {
              mount.hidden = !show;
              if (show && editors[lang]) setTimeout(function () { editors[lang].layout(); }, 20);
            }
            if (ta) ta.hidden = true;
          } else {
            if (mount) mount.hidden = true;
            if (ta) ta.hidden = !show;
          }
        });
      });
    });
  }

  // ── Preview ──────────────────────────────────────────────────────────────
  function applyVarTokens(html) {
    if (!html) return '';
    // {{var:name}}
    html = html.replace(/\{\{\s*var:([a-zA-Z0-9_\-]+)\s*\}\}/g, function (_, key) {
      var v = varMap[key];
      return v != null ? String(v) : '';
    });
    // short forms
    html = html.replace(/\{\{\s*site_name\s*\}\}/g, varMap.site_name != null ? String(varMap.site_name) : 'Mova');
    html = html.replace(/\{\{\s*site_description\s*\}\}/g, varMap.site_description != null ? String(varMap.site_description) : '');
    html = html.replace(/\{\{\s*year\s*\}\}/g, String(new Date().getFullYear()));
    return html;
  }

  function updatePreview() {
    if (!preview) return;
    var html = applyVarTokens(getHtml());
    var css = getCss();
    var js = getJs();
    var theme = previewWrap ? previewWrap.getAttribute('data-theme') : 'light';
    var bg = theme === 'dark' ? '#0f172a' : '#ffffff';
    var fg = theme === 'dark' ? '#e2e8f0' : '#111827';
    var themeAttr = theme === 'dark' ? 'dark' : 'light';
    var doc =
      '<!DOCTYPE html><html data-theme="' + themeAttr + '"><head><meta charset="utf-8">' +
      '<meta name="viewport" content="width=device-width,initial-scale=1">' +
      '<style>' +
      (siteCssVars || '') + '\n' +
      'html,body{margin:0;padding:1rem;font-family:system-ui,sans-serif;background:' + bg + ';color:' + fg + ';}' +
      '\n' + css +
      '</style></head><body>' + html +
      '<script>window.MovaVars=' + JSON.stringify(varMap || {}) + ';' +
      '(function(){try{' + js + '}catch(e){console.error(e);}})();<\/script></body></html>';
    try {
      var blob = new Blob([doc], { type: 'text/html' });
      var url = URL.createObjectURL(blob);
      preview.onload = function () { try { URL.revokeObjectURL(url); } catch (e) {} };
      preview.src = url;
    } catch (e) {
      preview.srcdoc = doc;
    }
  }


  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(updatePreview, 280);
  }

  // ── Save ─────────────────────────────────────────────────────────────────
  function save() {
    var btn = document.getElementById('studio-save');
    if (btn) {
      btn.disabled = true;
      var span = btn.querySelector('span');
      if (span) span.textContent = 'Saving…';
    }
    // Sync managed CSS one more time
    writeManagedCssToEditor();

    var body = new URLSearchParams();
    body.set('_mova_csrf', csrf);
    body.set('title', titleInput ? titleInput.value : '');
    body.set('body', getHtml());
    body.set('raw_css', getCss());
    body.set('raw_js', getJs());

    fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
      .then(function (res) {
        if (res.json && res.json.ok) {
          setDirty(false);
          toast(res.json.message || 'Saved');
        } else {
          toast((res.json && res.json.message) || 'Save failed', true);
        }
      })
      .catch(function () { toast('Network error — could not save', true); })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          var span = btn.querySelector('span');
          if (span) span.textContent = 'Save';
        }
      });
  }

  // ── Columns chrome ───────────────────────────────────────────────────────
  function initColumnChrome() {
    document.querySelectorAll('[data-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var which = btn.getAttribute('data-close');
        var col = document.getElementById('studio-col-' + which);
        if (!col) return;
        col.classList.add('is-hidden');
        var restore = document.getElementById('studio-add-' + which);
        if (restore) restore.hidden = false;
        persistLayout();
      });
    });
    ['code', 'preview'].forEach(function (which) {
      var btn = document.getElementById('studio-add-' + which);
      if (!btn) return;
      btn.addEventListener('click', function () {
        var col = document.getElementById('studio-col-' + which);
        if (!col) return;
        col.classList.remove('is-hidden');
        btn.hidden = true;
        persistLayout();
        if (which === 'preview') updatePreview();
        if (which === 'code' && useMonaco) {
          setTimeout(function () {
            Object.keys(editors).forEach(function (k) { if (editors[k]) editors[k].layout(); });
          }, 30);
        }
      });
    });
    var collapseBtn = document.querySelector('[data-collapse="nav"]');
    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        var nav = document.getElementById('studio-col-nav');
        if (!nav) return;
        nav.classList.toggle('is-collapsed');
        var icon = collapseBtn.querySelector('i');
        if (icon) {
          icon.className = nav.classList.contains('is-collapsed')
            ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
        }
        persistLayout();
      });
    }
    var attrsBtn = document.getElementById('studio-open-attrs');
    if (attrsBtn) {
      attrsBtn.addEventListener('click', function () { openAttrsPanel(''); });
    }
  }

  // ── Resize ───────────────────────────────────────────────────────────────
  var drag = null;

  function minWidthFor(col) {
    if (!col) return 140;
    if (col.classList.contains('is-collapsed')) return 44;
    if (col.classList.contains('studio-col-nav')) return 120;
    // Nav, detail, code, preview — all resizable with the same floor
    return 140;
  }

  function nearestCol(el, dir) {
    // Walk siblings to find a visible .studio-col (including detail panels inside host)
    var cur = el;
    while (cur) {
      if (cur.classList && cur.classList.contains('studio-col') && !cur.classList.contains('is-hidden')) {
        return cur;
      }
      if (cur.classList && cur.classList.contains('studio-col2-host')) {
        var kids = cur.querySelectorAll(':scope > .studio-col, .studio-col');
        if (dir === 'left') {
          for (var i = kids.length - 1; i >= 0; i--) {
            if (!kids[i].classList.contains('is-hidden')) return kids[i];
          }
        } else {
          for (var j = 0; j < kids.length; j++) {
            if (!kids[j].classList.contains('is-hidden')) return kids[j];
          }
        }
        // empty host — skip past it
      }
      cur = dir === 'left' ? cur.previousElementSibling : cur.nextElementSibling;
    }
    return null;
  }

  function applyColWidth(col, px) {
    if (!col) return;
    var w = Math.round(px);
    col.style.flex = '0 0 ' + w + 'px';
    col.style.width = w + 'px';
    col.style.minWidth = w + 'px';
    col.style.maxWidth = 'none';
  }

  function wireSplitter(sp) {
    if (sp._studioWired) return;
    sp._studioWired = true;

    sp.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var left = nearestCol(sp.previousElementSibling, 'left');
      var right = nearestCol(sp.nextElementSibling, 'right');
      if (!left || !right) return;
      if (left.classList.contains('is-collapsed') || right.classList.contains('is-collapsed')) return;

      drag = {
        left: left,
        right: right,
        startX: e.clientX,
        wL: left.getBoundingClientRect().width,
        wR: right.getBoundingClientRect().width
      };
      try { sp.setPointerCapture(e.pointerId); } catch (err) {}
      document.body.classList.add('studio-resizing');
    });

    sp.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.startX;
      var minL = minWidthFor(drag.left);
      var minR = minWidthFor(drag.right);
      var wL = drag.wL + dx;
      var wR = drag.wR - dx;
      if (wL < minL) { wR -= (minL - wL); wL = minL; }
      if (wR < minR) { wL -= (minR - wR); wR = minR; }
      if (wL < minL || wR < minR) return;

      // Every column type (nav, detail, code, preview) grows and shrinks the same way
      applyColWidth(drag.left, wL);
      applyColWidth(drag.right, wR);
    });

    function endDrag() {
      if (!drag) return;
      drag = null;
      document.body.classList.remove('studio-resizing');
      persistLayout();
      if (useMonaco) {
        Object.keys(editors).forEach(function (k) { if (editors[k]) editors[k].layout(); });
      }
    }
    sp.addEventListener('pointerup', endDrag);
    sp.addEventListener('pointercancel', endDrag);
  }

  function initSplitters() {
    if (!colsRoot) return;
    colsRoot.querySelectorAll('.studio-splitter').forEach(wireSplitter);
  }

  /** Re-bind any new splitters created when Col2 panels open */
  function ensureSplitters() {
    initSplitters();
  }

  /** Right edge of Preview — drag to grow/shrink preview (takes space from Code) */
  function initPreviewEdge() {
    var edge = document.getElementById('studio-preview-edge');
    var previewCol = document.getElementById('studio-col-preview');
    var codeCol = document.getElementById('studio-col-code');
    if (!edge || !previewCol || !codeCol) return;
    if (edge._studioWired) return;
    edge._studioWired = true;

    var edgeDrag = null;
    edge.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (previewCol.classList.contains('is-hidden') || codeCol.classList.contains('is-hidden')) return;
      edgeDrag = {
        startX: e.clientX,
        wPreview: previewCol.getBoundingClientRect().width,
        wCode: codeCol.getBoundingClientRect().width
      };
      try { edge.setPointerCapture(e.pointerId); } catch (err) {}
      document.body.classList.add('studio-resizing');
    });
    edge.addEventListener('pointermove', function (e) {
      if (!edgeDrag) return;
      // Dragging the RIGHT edge to the right → grow preview, shrink code
      var dx = e.clientX - edgeDrag.startX;
      var minP = 140, minC = 140;
      var wP = edgeDrag.wPreview + dx;
      var wC = edgeDrag.wCode - dx;
      if (wP < minP) { wC -= (minP - wP); wP = minP; }
      if (wC < minC) { wP -= (minC - wC); wC = minC; }
      if (wP < minP || wC < minC) return;
      applyColWidth(previewCol, wP);
      applyColWidth(codeCol, wC);
    });
    function endEdge() {
      if (!edgeDrag) return;
      edgeDrag = null;
      document.body.classList.remove('studio-resizing');
      persistLayout();
      if (useMonaco) {
        Object.keys(editors).forEach(function (k) { if (editors[k]) editors[k].layout(); });
      }
    }
    edge.addEventListener('pointerup', endEdge);
    edge.addEventListener('pointercancel', endEdge);
  }




  function persistLayout() {
    try {
      localStorage.setItem('mova_studio_layout', JSON.stringify({
        navCollapsed: !!(document.getElementById('studio-col-nav') || {}).classList.contains('is-collapsed'),
        codeHidden: !!(document.getElementById('studio-col-code') || {}).classList.contains('is-hidden'),
        previewHidden: !!(document.getElementById('studio-col-preview') || {}).classList.contains('is-hidden')
      }));
    } catch (e) {}
  }

  function restoreLayout() {
    try {
      var state = JSON.parse(localStorage.getItem('mova_studio_layout') || 'null');
      if (!state) return;
      var nav = document.getElementById('studio-col-nav');
      if (nav && state.navCollapsed) {
        nav.classList.add('is-collapsed');
        var cb = document.querySelector('[data-collapse="nav"] i');
        if (cb) cb.className = 'fa-solid fa-chevron-right';
      }
      if (state.codeHidden) {
        var code = document.getElementById('studio-col-code');
        if (code) code.classList.add('is-hidden');
        var rc = document.getElementById('studio-add-code');
        if (rc) rc.hidden = false;
      }
      if (state.previewHidden) {
        var prev = document.getElementById('studio-col-preview');
        if (prev) prev.classList.add('is-hidden');
        var rp = document.getElementById('studio-add-preview');
        if (rp) rp.hidden = false;
      }
    } catch (e) {}
  }

  function initPreviewTools() {
    document.querySelectorAll('[data-vp]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var vp = btn.getAttribute('data-vp');
        if (previewWrap) previewWrap.setAttribute('data-vp', vp);
        document.querySelectorAll('[data-vp]').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
      });
    });
    var themeBtn = document.getElementById('studio-theme-toggle');
    if (themeBtn && previewWrap) {
      themeBtn.addEventListener('click', function () {
        var t = previewWrap.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        previewWrap.setAttribute('data-theme', t);
        updatePreview();
      });
    }
    var openBtn = document.getElementById('studio-open-tab');
    if (openBtn) {
      openBtn.addEventListener('click', function () {
        var doc = '<!DOCTYPE html><html data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
          '<style>' + (siteCssVars || '') + '\n' + getCss() + '</style></head><body>' + applyVarTokens(getHtml()) +
          '<script>window.MovaVars=' + JSON.stringify(varMap || {}) + ';' + getJs() + '<\/script></body></html>';
        var w = window.open('', '_blank');
        if (w) { w.document.open(); w.document.write(doc); w.document.close(); }
      });
    }
  }

  function onContentChange() {
    if (applyingHistory) return;
    setDirty(true);
    rebuildNav();
    schedulePreview();
    scheduleHistory();
  }

  function initInputs() {
    if (titleInput) {
      titleInput.addEventListener('input', function () {
        setDirty(true);
        scheduleHistory();
      });
    }
  }

  function initKeys() {
    document.addEventListener('keydown', function (e) {
      var mod = e.metaKey || e.ctrlKey;
      if (!mod) return;
      // Don't intercept when Monaco has focus for Z/Y — Monaco has its own undo.
      // Still handle Save always.
      var key = e.key.toLowerCase();
      if (key === 's') {
        e.preventDefault();
        save();
      } else if (!useMonaco) {
        if (key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
        else if (key === 'y' || (key === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
      } else if (key === 'z' && e.shiftKey) {
        // allow browser; our stack is secondary when Monaco is on
      }
    });
  }

  function initButtons() {
    var saveBtn = document.getElementById('studio-save');
    if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); save(); });
    var undoBtn = document.getElementById('studio-undo');
    if (undoBtn) undoBtn.addEventListener('click', function (e) { e.preventDefault(); undo(); });
    var redoBtn = document.getElementById('studio-redo');
    if (redoBtn) redoBtn.addEventListener('click', function (e) { e.preventDefault(); redo(); });

    var fsBtn = document.getElementById('studio-fullscreen');
    if (fsBtn) {
      fsBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var on = document.body.classList.toggle('studio-is-fullscreen');
        app.classList.toggle('is-fullscreen', on);
        var icon = fsBtn.querySelector('i');
        var label = fsBtn.querySelector('.studio-fs-label');
        if (icon) icon.className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
        if (label) label.textContent = on ? 'Exit' : 'Fullscreen';
        if (useMonaco) {
          setTimeout(function () {
            Object.keys(editors).forEach(function (k) { if (editors[k]) editors[k].layout(); });
          }, 50);
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('studio-is-fullscreen')) {
          fsBtn.click();
        }
      });
    }
  }

  // ── Parse existing managed CSS on load (best-effort empty — styleMap starts fresh) ──
  // Future: reverse-parse managed block. Phase 2 keeps styleMap session-local;
  // compiled CSS is the source of truth in the CSS document after first edit.

  function boot() {
    initCodeTabs();
    initColumnChrome();
    initSplitters();
    initPreviewEdge();
    initPreviewTools();
    initInputs();
    initKeys();
    initButtons();
    restoreLayout();
    loadMonaco();
    rebuildNav();
    updatePreview();
    pushHistory();
    setDirty(false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
