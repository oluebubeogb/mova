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
      input.classList.remove('mova-dev-fallback');
      input.style.width = '100%';
      input.style.height = '360px';
      input.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
      input.style.fontSize = '13px';
      input.style.padding = '0.75rem';
      input.style.border = '0';
      input.style.boxSizing = 'border-box';
      // Approximate line numbers via CSS counter for fallback
      input.style.backgroundImage =
        'linear-gradient(to right, #f1f5f9 3.5rem, transparent 3.5rem)';
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
      lineNumbers: 'on',              // always show line numbers
      lineNumbersMinChars: 3,
      glyphMargin: true,              // room for error icons in gutter
      folding: true,
      renderValidationDecorations: 'on',
      scrollBeyondLastLine: false,
      wordWrap: 'on',
      tabSize: 2,
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

    monacoReady = true;
    collectMarkers();
    syncHiddenInputs();
  }

  if (form) {
    form.addEventListener('submit', function () {
      const classic = document.getElementById('body-input');
      const devHtml = document.getElementById('mova-dev-html-input');
      const visual = document.getElementById('editor');

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
    });
  }

  setMode(cfg.mode === 'dev');
})();
