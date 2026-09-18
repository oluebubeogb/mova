/**
 * Mova Dev Editor — Monaco boot + mode toggle
 * Line numbers always on; errors show on the line (red underline + gutter)
 * and in a clickable list: "Line 12: …"
 */
(function () {
  'use strict';

  const cfg = window.MOVA_DEV_EDITOR || {};
  const modeInput = document.getElementById('mova-editor-mode');
  const toggle = document.getElementById('mova-dev-mode-toggle');
  const panels = document.getElementById('mova-dev-panels');
  const badge = document.getElementById('mova-dev-error-badge');
  const errorList = document.getElementById('mova-dev-error-list');
  const form = document.getElementById('content-form');

  if (!toggle || !panels) return;

  let editors = { html: null, css: null, js: null };
  let monacoReady = false;

  /** Strip tags that would style or run on the HQ document */
  function sanitizeForHqDom(html) {
    var wrap = document.createElement('div');
    wrap.innerHTML = html || '';
    wrap.querySelectorAll('style, script, link[rel="stylesheet"]').forEach(function (el) {
      el.remove();
    });
    return wrap.innerHTML;
  }

  let markerCount = 0;
  let lastMarkers = []; // { lang, line, col, message, severity, editor }

  function setMode(dev) {
    modeInput.value = dev ? 'dev' : 'visual';
    panels.hidden = !dev;
    document.body.classList.toggle('mova-dev-mode-on', dev);
    var chromeWrap = document.getElementById('mova-chrome-toggle-wrap');
    if (chromeWrap) chromeWrap.hidden = !dev;
    if (dev && !monacoReady) {
      loadMonaco();
    }
    if (!dev && editors.html) {
      const html = editors.html.getValue();
      const visual = document.getElementById('editor');
      const bodyInput = document.getElementById('body-input');
      // Never inject <style>/<script> into HQ contenteditable (it styles the whole admin UI)
      if (visual) visual.innerHTML = sanitizeForHqDom(html);
      if (bodyInput) bodyInput.value = html;
    }
  }

  toggle.addEventListener('change', function () {
    setMode(toggle.checked);
  });

  // Tabs
  document.querySelectorAll('.mova-dev-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      const name = tab.getAttribute('data-tab');
      document.querySelectorAll('.mova-dev-tab').forEach(t => t.classList.remove('is-active'));
      document.querySelectorAll('.mova-dev-pane').forEach(p => p.classList.remove('is-active'));
      tab.classList.add('is-active');
      const pane = document.querySelector('.mova-dev-pane[data-pane="' + name + '"]');
      if (pane) pane.classList.add('is-active');
      if (editors[name]) {
        setTimeout(() => editors[name].layout(), 10);
      }
    });
  });

  function switchToTab(lang) {
    const tab = document.querySelector('.mova-dev-tab[data-tab="' + lang + '"]');
    if (tab) tab.click();
  }

  function updateBadgeAndList() {
    if (badge) {
      if (markerCount > 0) {
        badge.hidden = false;
        badge.textContent = markerCount + (markerCount === 1 ? ' error' : ' errors');
        badge.classList.remove('is-ok');
      } else if (monacoReady) {
        badge.hidden = false;
        badge.textContent = 'No errors';
        badge.classList.add('is-ok');
      } else {
        badge.hidden = true;
      }
    }

    if (!errorList) return;

    if (!lastMarkers.length) {
      errorList.hidden = true;
      errorList.innerHTML = '';
      return;
    }

    errorList.hidden = false;
    errorList.innerHTML = '';
    const title = document.createElement('div');
    title.className = 'mova-dev-error-list-title';
    title.textContent = 'Problems';
    errorList.appendChild(title);

    lastMarkers.forEach(function (m) {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'mova-dev-error-row' + (m.severity >= 8 ? ' is-error' : ' is-warning');
      const label = (m.lang || '').toUpperCase();
      row.innerHTML =
        '<span class="mova-dev-error-loc">' + label + ' · Line ' + m.line +
        (m.col ? ':' + m.col : '') + '</span>' +
        '<span class="mova-dev-error-msg"></span>';
      row.querySelector('.mova-dev-error-msg').textContent = m.message;
      row.title = 'Go to line ' + m.line;
      row.addEventListener('click', function () {
        switchToTab(m.lang);
        if (m.editor) {
          m.editor.revealLineInCenter(m.line);
          m.editor.setPosition({ lineNumber: m.line, column: m.col || 1 });
          m.editor.focus();
        }
      });
      errorList.appendChild(row);
    });
  }

  function collectMarkers() {
    let total = 0;
    const collected = [];
    Object.keys(editors).forEach(function (lang) {
      const ed = editors[lang];
      if (!ed) return;
      const model = ed.getModel();
      if (!model) return;
      const markers = monaco.editor.getModelMarkers({ resource: model.uri });
      markers.forEach(function (mk) {
        // 8 = Error, 4 = Warning (Monaco MarkerSeverity)
        if (mk.severity < 4) return;
        total += mk.severity >= 8 ? 1 : 0;
        collected.push({
          lang: lang,
          line: mk.startLineNumber || 1,
          col: mk.startColumn || 1,
          message: mk.message || 'Syntax error',
          severity: mk.severity,
          editor: ed
        });
      });
    });
    // Sort by language then line
    collected.sort(function (a, b) {
      if (a.lang !== b.lang) return a.lang.localeCompare(b.lang);
      return a.line - b.line;
    });
    markerCount = total || collected.filter(m => m.severity >= 8).length;
    if (!markerCount && collected.length) {
      markerCount = collected.length; // count warnings if no pure errors
    }
    lastMarkers = collected;
    updateBadgeAndList();
  }

  function syncHiddenInputs() {
    if (editors.html) {
      const el = document.getElementById('mova-dev-html-input');
      if (el) el.value = editors.html.getValue();
      const bodyInput = document.getElementById('body-input');
      if (bodyInput) bodyInput.value = editors.html.getValue();
    }
    if (editors.css) {
      const el = document.getElementById('mova-dev-css-input');
      if (el) el.value = editors.css.getValue();
    }
    if (editors.js) {
      const el = document.getElementById('mova-dev-js-input');
      if (el) el.value = editors.js.getValue();
    }
  }

  function loadMonaco() {
    if (window.require && window.monaco) {
      initEditors();
      return;
    }
    const cdn = cfg.monacoCdn || 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs';
    window.MonacoEnvironment = {
      getWorkerUrl: function () {
        return URL.createObjectURL(new Blob([
          "self.MonacoEnvironment={baseUrl:'" + cdn + "/../'};",
          "importScripts('" + cdn + "/base/worker/workerMain.js');"
        ], { type: 'text/javascript' }));
      }
    };
    const loader = document.createElement('script');
    loader.src = cdn + '/loader.js';
    loader.onload = function () {
      window.require.config({ paths: { vs: cdn } });
      window.require(['vs/editor/editor.main'], function () {
        initEditors();
      });
    };
    loader.onerror = function () {
      console.warn('[Mova Dev Editor] Monaco failed to load — using plain textareas.');
      fallbackTextareas();
    };
    document.head.appendChild(loader);
  }

  function fallbackTextareas() {
    ['html', 'css', 'js'].forEach(function (lang) {
      const mount = document.getElementById('mova-monaco-' + lang);
      const input = document.getElementById('mova-dev-' + lang + '-input');
      if (!mount || !input) return;
      input.hidden = false;
      input.classList.add('mova-dev-fallback');
      input.style.width = '100%';
      input.style.height = '360px';
      input.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
      input.style.fontSize = '13px';
      input.style.padding = '0.75rem';
      input.style.border = '0';
      input.style.boxSizing = 'border-box';
      input.style.resize = 'vertical';
      // Approximate line numbers via left gutter
      input.style.backgroundImage =
        'linear-gradient(to right, var(--hq-muted-bg, #f1f5f9) 3.5rem, transparent 3.5rem)';
      input.style.backgroundAttachment = 'local';
      input.style.paddingLeft = '4rem';
      input.style.lineHeight = '1.5';
      mount.style.display = 'none';
    });
    monacoReady = true;
  }

  function initEditors() {
    const initial = cfg.initial || {};
    const common = {
      automaticLayout: true,
      minimap: { enabled: false },
      fontSize: 13,
      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
      lineNumbers: 'on',              // always show line numbers
      lineNumbersMinChars: 3,
      lineDecorationsWidth: 10,
      glyphMargin: true,              // room for error icons in gutter
      folding: true,
      renderLineHighlight: 'line',
      renderValidationDecorations: 'on',
      scrollBeyondLastLine: false,
      wordWrap: 'on',
      tabSize: 2,
      padding: { top: 8, bottom: 8 },
      theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs'
    };

    editors.html = monaco.editor.create(document.getElementById('mova-monaco-html'), Object.assign({}, common, {
      value: initial.html || '',
      language: 'html'
    }));
    editors.css = monaco.editor.create(document.getElementById('mova-monaco-css'), Object.assign({}, common, {
      value: initial.css || '',
      language: 'css'
    }));
    editors.js = monaco.editor.create(document.getElementById('mova-monaco-js'), Object.assign({}, common, {
      value: initial.js || '',
      language: 'javascript'
    }));

    // Red underlines + gutter markers update this list
    monaco.editor.onDidChangeMarkers(function () {
      collectMarkers();
    });

    Object.keys(editors).forEach(function (k) {
      editors[k].onDidChangeModelContent(function () {
        syncHiddenInputs();
      });
    });

    // High-contrast line numbers (avoid blending with editor bg)
    try {
      monaco.editor.defineTheme('mova-light', {
        base: 'vs',
        inherit: true,
        rules: [],
        colors: {
          'editorLineNumber.foreground': '#64748b',
          'editorLineNumber.activeForeground': '#0f172a',
          'editorGutter.background': '#f1f5f9'
        }
      });
      monaco.editor.defineTheme('mova-dark', {
        base: 'vs-dark',
        inherit: true,
        rules: [],
        colors: {
          'editorLineNumber.foreground': '#94a3b8',
          'editorLineNumber.activeForeground': '#f8fafc',
          'editorGutter.background': '#1e293b'
        }
      });
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      monaco.editor.setTheme(dark ? 'mova-dark' : 'mova-light');
    } catch (e) {}

    // Force layout so line-number gutter is painted (can be 0-width until first layout)
    Object.keys(editors).forEach(function (k) {
      try {
        editors[k].updateOptions({ lineNumbers: 'on', lineNumbersMinChars: 3, glyphMargin: true });
        editors[k].layout();
      } catch (e) {}
    });

    monacoReady = true;
    collectMarkers();
    syncHiddenInputs();
  }

  if (form) {
    form.addEventListener('submit', function () {
      const classic = document.getElementById('body-input');
      const devHtml = document.getElementById('mova-dev-html-input');
      const visual = document.getElementById('editor');

      // Body HTML may contain its own <form required> (e.g. contact page) inside
      // the visual contenteditable. Those controls are descendants of #content-form
      // and can block HQ save when hidden. Only neutralize #editor — never
      // #mova-dev-panels (raw_css / raw_js / html textareas must keep their names).
      function neutralizeBodyControls(root) {
        if (!root) return;
        root.querySelectorAll('input, select, textarea, button').forEach(function (el) {
          el.removeAttribute('required');
          el.removeAttribute('aria-required');
          if (el.getAttribute('name')) {
            el.setAttribute('data-mova-name', el.getAttribute('name'));
            el.removeAttribute('name');
          }
        });
        root.querySelectorAll('form').forEach(function (nested) {
          nested.setAttribute('novalidate', 'novalidate');
        });
      }
      neutralizeBodyControls(visual);

      if (modeInput && modeInput.value === 'dev') {
        // Dev Mode: Monaco/dev textarea is the source of truth for body
        syncHiddenInputs();
        if (devHtml) {
          devHtml.setAttribute('name', 'body');
          if (editors.html) {
            devHtml.value = editors.html.getValue();
          }
        }
        // Prevent duplicate name="body" — visual field must not post
        if (classic) {
          classic.removeAttribute('name');
        }
      } else {
        // Visual Mode: contenteditable → #body-input is the only body field
        if (classic) {
          classic.setAttribute('name', 'body');
          if (visual) {
            classic.value = visual.innerHTML;
          }
        }
        // Ensure Dev textarea never collides with visual body
        if (devHtml) {
          devHtml.removeAttribute('name');
        }
      }
    }, true);
  }

  /* ── Import / Export ─────────────────────────────────────────────── */

  function getContentName() {
    var slugEl = document.querySelector('input[name="slug"]');
    var titleEl = document.querySelector('input[name="title"]');
    var raw = (slugEl && slugEl.value.trim()) || (titleEl && titleEl.value.trim()) || 'content';
    return raw
      .toLowerCase()
      .replace(/[^a-z0-9._-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 64) || 'content';
  }

  function getEditorValues() {
    return {
      html: editors.html ? editors.html.getValue() : (document.getElementById('mova-dev-html-input') || {}).value || '',
      css: editors.css ? editors.css.getValue() : (document.getElementById('mova-dev-css-input') || {}).value || '',
      js: editors.js ? editors.js.getValue() : (document.getElementById('mova-dev-js-input') || {}).value || ''
    };
  }

  function setEditorValue(lang, value) {
    if (editors[lang]) {
      editors[lang].setValue(value == null ? '' : String(value));
    }
    var el = document.getElementById('mova-dev-' + lang + '-input');
    if (el) el.value = value == null ? '' : String(value);
    if (lang === 'html') {
      var bodyInput = document.getElementById('body-input');
      if (bodyInput) bodyInput.value = value == null ? '' : String(value);
    }
  }

  function triggerAutoSave() {
    syncHiddenInputs();
    // Ensure form is in dev mode so body/css/js post correctly
    if (modeInput) modeInput.value = 'dev';
    if (toggle && !toggle.checked) {
      toggle.checked = true;
      setMode(true);
    }
    var saveBtn = document.getElementById('btn-publish-save');
    if (saveBtn) {
      try { saveBtn.click(); } catch (e) {}
      return;
    }
    if (form) {
      try { form.requestSubmit ? form.requestSubmit() : form.submit(); } catch (e) {}
    }
  }

  /* Minimal ZIP writer (store only, no compression) — no external dependency */
  function crc32(str) {
    var table = crc32._t;
    if (!table) {
      table = crc32._t = new Uint32Array(256);
      for (var n = 0; n < 256; n++) {
        var c = n;
        for (var k = 0; k < 8; k++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
        table[n] = c;
      }
    }
    var crc = 0 ^ (-1);
    for (var i = 0; i < str.length; i++) {
      crc = (crc >>> 8) ^ table[(crc ^ str.charCodeAt(i)) & 0xff];
    }
    return (crc ^ (-1)) >>> 0;
  }

  function strToU8(str) {
    if (typeof TextEncoder !== 'undefined') return new TextEncoder().encode(str);
    var arr = new Uint8Array(str.length);
    for (var i = 0; i < str.length; i++) arr[i] = str.charCodeAt(i) & 0xff;
    return arr;
  }

  function u32(n) {
    return new Uint8Array([n & 0xff, (n >>> 8) & 0xff, (n >>> 16) & 0xff, (n >>> 24) & 0xff]);
  }
  function u16(n) {
    return new Uint8Array([n & 0xff, (n >>> 8) & 0xff]);
  }

  function buildZip(files) {
    // files: [{ name, data: string }]
    var localParts = [];
    var centralParts = [];
    var offset = 0;
    files.forEach(function (f) {
      var nameBytes = strToU8(f.name);
      var dataBytes = strToU8(f.data);
      var crc = crc32(f.data);
      var size = dataBytes.length;

      // Local file header
      var local = [];
      local.push(u32(0x04034b50));
      local.push(u16(20)); // version needed
      local.push(u16(0));  // flags
      local.push(u16(0));  // method store
      local.push(u16(0));  // time
      local.push(u16(0));  // date
      local.push(u32(crc));
      local.push(u32(size));
      local.push(u32(size));
      local.push(u16(nameBytes.length));
      local.push(u16(0)); // extra
      local.push(nameBytes);
      local.push(dataBytes);

      var localLen = 30 + nameBytes.length + size;
      localParts.push({ chunks: local, len: localLen });

      // Central directory header
      var central = [];
      central.push(u32(0x02014b50));
      central.push(u16(20)); // version made by
      central.push(u16(20)); // version needed
      central.push(u16(0));
      central.push(u16(0));
      central.push(u16(0));
      central.push(u16(0));
      central.push(u32(crc));
      central.push(u32(size));
      central.push(u32(size));
      central.push(u16(nameBytes.length));
      central.push(u16(0)); // extra
      central.push(u16(0)); // comment
      central.push(u16(0)); // disk
      central.push(u16(0)); // int attr
      central.push(u32(0)); // ext attr
      central.push(u32(offset));
      central.push(nameBytes);

      centralParts.push({ chunks: central, len: 46 + nameBytes.length });
      offset += localLen;
    });

    var centralSize = 0;
    centralParts.forEach(function (p) { centralSize += p.len; });
    var centralOffset = offset;

    var end = [];
    end.push(u32(0x06054b50));
    end.push(u16(0));
    end.push(u16(0));
    end.push(u16(files.length));
    end.push(u16(files.length));
    end.push(u32(centralSize));
    end.push(u32(centralOffset));
    end.push(u16(0));

    var total = offset + centralSize + 22;
    var out = new Uint8Array(total);
    var pos = 0;
    function writeChunks(parts) {
      parts.forEach(function (p) {
        p.chunks.forEach(function (c) {
          out.set(c, pos);
          pos += c.length;
        });
      });
    }
    writeChunks(localParts);
    writeChunks(centralParts);
    end.forEach(function (c) {
      out.set(c, pos);
      pos += c.length;
    });
    return out;
  }

  function downloadBlob(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
      URL.revokeObjectURL(url);
      a.remove();
    }, 1500);
  }

  function exportZip() {
    var name = getContentName();
    var vals = getEditorValues();
    var files = [
      { name: name + '.html', data: vals.html || '' },
      { name: name + '.css', data: vals.css || '' },
      { name: name + '.js', data: vals.js || '' }
    ];
    var zipBytes = buildZip(files);
    var blob = new Blob([zipBytes], { type: 'application/zip' });
    downloadBlob(blob, name + '.zip');
  }

  /* ── Mixed .txt parser ─────────────────────────────────────────────
     Keeps consecutive runs of the same language together.
     Detects HTML / CSS / JS via strong signals; respects comments
     (HTML comments, block comments, and line comments) so comment
     text does not flip language. */

  function stripCommentsForDetect(line, state) {
    // Returns { text, state } where state tracks open block comments
    var out = '';
    var i = 0;
    var s = state || { inBlock: false, blockType: null }; // blockType: 'cssjs' | 'html'
    while (i < line.length) {
      if (s.inBlock) {
        if (s.blockType === 'html') {
          var endH = line.indexOf('-->', i);
          if (endH === -1) { i = line.length; }
          else { i = endH + 3; s.inBlock = false; s.blockType = null; }
        } else {
          var endC = line.indexOf('*/', i);
          if (endC === -1) { i = line.length; }
          else { i = endC + 2; s.inBlock = false; s.blockType = null; }
        }
        continue;
      }
      // HTML comment
      if (line.substr(i, 4) === '<!--') {
        s.inBlock = true; s.blockType = 'html'; i += 4; continue;
      }
      // CSS/JS block comment
      if (line.substr(i, 2) === '/*') {
        s.inBlock = true; s.blockType = 'cssjs'; i += 2; continue;
      }
      // JS line comment
      if (line.substr(i, 2) === '//') {
        break; // rest of line is comment
      }
      out += line[i];
      i++;
    }
    return { text: out, state: s };
  }

  function detectLineLang(rawLine, prevLang, commentState) {
    var stripped = stripCommentsForDetect(rawLine, commentState);
    var line = stripped.text.trim();
    commentState = stripped.state;

    if (!line) return { lang: prevLang || null, commentState: commentState };

    // Strong HTML signals
    if (
      /^<!doctype\b/i.test(line) ||
      /^<\/?[a-z][\w:-]*\b/i.test(line) ||
      /<\/[a-z][\w:-]*>/i.test(line) ||
      /^<[a-z][\w:-]*(\s|>|\/|$)/i.test(line)
    ) {
      return { lang: 'html', commentState: commentState };
    }

    // Strong CSS signals (selector + brace, at-rule, property declaration)
    if (
      /^@(media|keyframes|import|charset|font-face|supports|layer|container)\b/i.test(line) ||
      /^[.#]?[a-zA-Z_\-][\w\-]*\s*[,{>]/.test(line) ||
      /^[a-zA-Z\-]+\s*:\s*[^;]+;?\s*$/.test(line) ||
      /^\s*[{}]+\s*$/.test(line) && prevLang === 'css' ||
      /\{[^}]*$/.test(line) && /[a-zA-Z.#\[]/.test(line)
    ) {
      // Avoid classifying pure JS object-like lines as CSS when prev is JS
      if (prevLang === 'js' && /^(const|let|var|function|if|for|while|return|class|export|import)\b/.test(line)) {
        return { lang: 'js', commentState: commentState };
      }
      if (prevLang === 'js' && /[{}();=]/.test(line) && !/:\s*[^;]+;/.test(line) && !/^[.#@]/.test(line)) {
        return { lang: 'js', commentState: commentState };
      }
      return { lang: 'css', commentState: commentState };
    }

    // Strong JS signals
    if (
      /^(const|let|var|function|class|export|import|async|await|return|if|else|for|while|switch|try|catch|throw|new|typeof|instanceof)\b/.test(line) ||
      /=>/.test(line) ||
      /\b(console|document|window|Math|JSON|Promise|Array|Object)\b/.test(line) ||
      /;\s*$/.test(line) && /[=(){}[\]]/.test(line)
    ) {
      return { lang: 'js', commentState: commentState };
    }

    // Stay in previous language for weak / ambiguous lines (keeps runs together)
    if (prevLang) return { lang: prevLang, commentState: commentState };

    // First non-empty line with no clear signal — guess from content
    if (/[<>]/.test(line)) return { lang: 'html', commentState: commentState };
    if (/[{}:;]/.test(line) && !/[=()]/.test(line)) return { lang: 'css', commentState: commentState };
    return { lang: 'js', commentState: commentState };
  }

  function parseMixedTxt(text) {
    var lines = String(text || '').split(/\r?\n/);
    var buckets = { html: [], css: [], js: [] };
    var current = null;
    var commentState = { inBlock: false, blockType: null };

    lines.forEach(function (rawLine) {
      var det = detectLineLang(rawLine, current, commentState);
      commentState = det.commentState;
      if (det.lang) current = det.lang;
      if (!current) {
        // still unknown — hold in a temporary buffer attached later; treat as html fallback
        buckets.html.push(rawLine);
        return;
      }
      buckets[current].push(rawLine);
    });

    function join(arr) {
      // trim leading/trailing blank lines only
      while (arr.length && !arr[0].trim()) arr.shift();
      while (arr.length && !arr[arr.length - 1].trim()) arr.pop();
      return arr.join('\n');
    }

    return {
      html: join(buckets.html),
      css: join(buckets.css),
      js: join(buckets.js)
    };
  }

  function applyImport(parts) {
    // parts: { html?, css?, js? } — only set keys that are present (non-null)
    var changed = false;
    ['html', 'css', 'js'].forEach(function (lang) {
      if (parts[lang] != null) {
        setEditorValue(lang, parts[lang]);
        changed = true;
      }
    });
    if (changed) {
      syncHiddenInputs();
      if (monacoReady) collectMarkers();
      // Prefer showing the first non-empty imported language
      if (parts.html) switchToTab('html');
      else if (parts.css) switchToTab('css');
      else if (parts.js) switchToTab('js');
      triggerAutoSave();
    }
  }

  function handleFiles(fileList) {
    var files = Array.prototype.slice.call(fileList || []);
    if (!files.length) return;

    var allowed = { html: true, css: true, js: true, txt: true };
    var byExt = { html: null, css: null, js: null };
    var txtFiles = [];
    var pending = 0;
    var done = false;

    function finish() {
      if (done) return;
      done = true;
      var parts = {};
      // discrete extension files win over txt for the same type
      if (byExt.html != null) parts.html = byExt.html;
      if (byExt.css != null) parts.css = byExt.css;
      if (byExt.js != null) parts.js = byExt.js;

      // merge txt parses for any type not already set by discrete files
      txtFiles.forEach(function (parsed) {
        ['html', 'css', 'js'].forEach(function (lang) {
          if (parts[lang] == null && parsed[lang]) {
            parts[lang] = parsed[lang];
          } else if (parts[lang] == null && parsed[lang] === '') {
            // keep empty only if explicitly the only content — skip
          }
        });
      });

      // If only txt and it produced content, use it
      if (Object.keys(parts).length === 0 && txtFiles.length) {
        var merged = { html: '', css: '', js: '' };
        txtFiles.forEach(function (p) {
          if (p.html) merged.html += (merged.html ? '\n' : '') + p.html;
          if (p.css) merged.css += (merged.css ? '\n' : '') + p.css;
          if (p.js) merged.js += (merged.js ? '\n' : '') + p.js;
        });
        parts = merged;
      }

      // Only override languages that were actually present in the upload
      var toApply = {};
      if (byExt.html != null || (txtFiles.length && parts.html)) toApply.html = parts.html != null ? parts.html : '';
      if (byExt.css != null || (txtFiles.length && parts.css)) toApply.css = parts.css != null ? parts.css : '';
      if (byExt.js != null || (txtFiles.length && parts.js)) toApply.js = parts.js != null ? parts.js : '';

      // If discrete files only of one type, only override that type
      if (!txtFiles.length) {
        toApply = {};
        if (byExt.html != null) toApply.html = byExt.html;
        if (byExt.css != null) toApply.css = byExt.css;
        if (byExt.js != null) toApply.js = byExt.js;
      }

      applyImport(toApply);
    }

    files.forEach(function (file) {
      var name = (file.name || '').toLowerCase();
      var ext = name.split('.').pop();
      if (!allowed[ext]) return;
      pending++;
      var reader = new FileReader();
      reader.onload = function (ev) {
        var text = ev.target.result || '';
        if (ext === 'txt') {
          txtFiles.push(parseMixedTxt(text));
        } else if (ext === 'html') {
          byExt.html = text;
        } else if (ext === 'css') {
          byExt.css = text;
        } else if (ext === 'js') {
          byExt.js = text;
        }
        pending--;
        if (pending === 0) finish();
      };
      reader.onerror = function () {
        pending--;
        if (pending === 0) finish();
      };
      reader.readAsText(file);
    });

    if (pending === 0) {
      // no valid files
      return;
    }
  }

  var importBtn = document.getElementById('mova-dev-import-btn');
  var exportBtn = document.getElementById('mova-dev-export-btn');
  var importInput = document.getElementById('mova-dev-import-input');

  if (importBtn && importInput) {
    importBtn.addEventListener('click', function () {
      importInput.value = '';
      importInput.click();
    });
    importInput.addEventListener('change', function () {
      handleFiles(importInput.files);
    });
  }
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      exportZip();
    });
  }

  setMode(cfg.mode === 'dev');
})();
