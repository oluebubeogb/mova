/**
 * Mova AI panel — HQ (sessions, reactions, soft actions, friendly empty state)
 * Shortcut: Ctrl+J / Cmd+J
 */
(function () {
  'use strict';

  var STORAGE_OPEN = 'mova_ai_panel_open';
  var STORAGE_SESSION = 'mova_ai_session_id';
  var STORAGE_OFFLINE_Q = 'mova_ai_offline_queue';
  var STORAGE_BG_MODE = 'mova_ai_bg_mode';
  var lastUserMessage = '';
  var jobPollTimer = null;
  var knownJobIds = {};

  var thinkingPhrases = [
    'Reading your request…',
    'Checking HQ screens…',
    'Talking to Mova AI…',
    'Preparing a response…',
    'Almost there…'
  ];

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var input = document.querySelector('input[name="_mova_csrf"]');
    return input ? input.value : '';
  }

  function pageContext() {
    var ctx = window.MovaAiContext || {};
    var entityId = ctx.entityId || 0;
    if (!entityId) {
      var m = (window.location.pathname || '').match(/\/hq\/content\/edit\/(\d+)/);
      if (m) entityId = parseInt(m[1], 10) || 0;
    }
    var out = {
      route: ctx.route || window.location.pathname + window.location.search,
      area: ctx.area || (document.body && document.body.getAttribute('data-workspace')) || '',
      layer: ctx.layer || new URLSearchParams(window.location.search).get('layer') || '',
      entity_id: entityId
    };
    if (state.templateId) out.template_id = state.templateId;
    return out;
  }

  function el(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function cleanReply(text) {
    text = String(text || '').trim();
    if (!text) return text;
    if (text.charAt(0) === '{' && text.indexOf('"reply"') !== -1) {
      try {
        var data = JSON.parse(text);
        if (data && typeof data.reply === 'string') return data.reply;
      } catch (e) {}
    }
    var idx = text.indexOf('{"reply"');
    if (idx > 0) return text.slice(0, idx).trim();
    return text.replace(/```json[\s\S]*?```/gi, '').trim();
  }

  function timeGreeting() {
    var h = new Date().getHours();
    var name = (window.MovaAiContext && window.MovaAiContext.userName) || '';
    var who = name ? ', ' + name : '';
    var options;
    if (h < 5) {
      options = [
        'Burning the midnight oil' + who + '?',
        'Still here' + who + '? I have HQ covered.',
        'Late shift' + who + ' — what are we fixing?'
      ];
    } else if (h < 12) {
      options = [
        'Good morning' + who + '.',
        'Morning' + who + ' — ready when you are.',
        'Hey' + who + '. What shall we ship today?'
      ];
    } else if (h < 17) {
      options = [
        'Good afternoon' + who + '.',
        'Hey' + who + ' — how can I help in HQ?',
        'Afternoon' + who + '. Content, design, or settings?'
      ];
    } else if (h < 21) {
      options = [
        'Good evening' + who + '.',
        'Evening' + who + ' — still building?',
        'Hey' + who + '. Let us tidy up HQ.'
      ];
    } else {
      options = [
        'Evening' + who + '.',
        'Wrapping up' + who + '?',
        'Hey' + who + ' — one more thing?'
      ];
    }
    return options[Math.floor(Math.random() * options.length)];
  }

  var state = {
    open: false,
    sessionId: null,
    busy: false,
    view: 'chat',
    bgMode: true,
    activeJobs: {},
    templateId: null
  };

  function setOpen(open) {
    state.open = !!open;
    var panel = el('mova-ai-panel');
    var fab = el('mova-ai-fab');
    if (!panel) return;
    panel.classList.toggle('is-open', state.open);
    document.body.classList.toggle('mova-ai-open', state.open);
    if (fab) fab.hidden = state.open;
    try {
      localStorage.setItem(STORAGE_OPEN, state.open ? '1' : '0');
    } catch (e) {}
    if (state.open) {
      var ta = el('mova-ai-input');
      if (ta) setTimeout(function () { ta.focus(); }, 120);
    }
  }

  function toggle() {
    setOpen(!state.open);
  }

  function showSessionsView(show) {
    if (show) showJobsView(false);
    state.view = show ? 'sessions' : 'chat';
    var list = el('mova-ai-sessions');
    var messages = el('mova-ai-messages');
    var composer = document.querySelector('.mova-ai-composer');
    var jobs = el('mova-ai-jobs');
    if (list) list.hidden = !show;
    if (jobs && show) jobs.hidden = true;
    if (messages) messages.hidden = !!show;
    if (composer) composer.hidden = !!show;
    var title = document.querySelector('.mova-ai-panel-header h2');
    if (title) {
      title.innerHTML = show
        ? 'Sessions <span class="mova-ai-badge">HQ</span>'
        : 'Mova AI <span class="mova-ai-badge">HQ</span>';
    }
  }

  function showJobsView(show) {
    if (show) showSessionsView(false);
    state.view = show ? 'jobs' : 'chat';
    var jobs = el('mova-ai-jobs');
    var messages = el('mova-ai-messages');
    var composer = document.querySelector('.mova-ai-composer');
    var sessions = el('mova-ai-sessions');
    if (jobs) jobs.hidden = !show;
    if (sessions && show) sessions.hidden = true;
    if (messages) messages.hidden = !!show;
    if (composer) composer.hidden = !!show;
    var title = document.querySelector('.mova-ai-panel-header h2');
    if (title) {
      title.innerHTML = show
        ? 'Jobs <span class="mova-ai-badge">BG</span>'
        : 'Mova AI <span class="mova-ai-badge">HQ</span>';
    }
    if (show) refreshJobsList();
  }

  function offlineQueue() {
    try {
      var raw = localStorage.getItem(STORAGE_OFFLINE_Q);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
  }

  function saveOfflineQueue(q) {
    try { localStorage.setItem(STORAGE_OFFLINE_Q, JSON.stringify(q || [])); } catch (e) {}
  }

  function enqueueOffline(prompt, ctx) {
    var q = offlineQueue();
    q.push({
      id: 'off_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
      prompt: prompt,
      session_id: state.sessionId,
      context: ctx,
      created_at: new Date().toISOString()
    });
    saveOfflineQueue(q);
    updateJobsBadge();
    return q[q.length - 1];
  }

  function updateJobsBadge() {
    var badge = el('mova-ai-jobs-badge');
    if (!badge) return;
    var active = 0;
    Object.keys(state.activeJobs).forEach(function (k) {
      var j = state.activeJobs[k];
      if (j && (j.status === 'queued' || j.status === 'running')) active++;
    });
    active += offlineQueue().length;
    if (active > 0) {
      badge.hidden = false;
      badge.textContent = String(active > 9 ? '9+' : active);
    } else {
      badge.hidden = true;
    }
  }

  function refreshJobsList() {
    var list = el('mova-ai-jobs-list');
    if (!list) return;
    list.innerHTML = '<p class="mova-ai-jobs-loading">Loading…</p>';

    var offline = offlineQueue();
    var html = '';
    if (offline.length) {
      html += '<div class="mova-ai-jobs-section">Offline queue</div>';
      offline.forEach(function (item) {
        html += '<div class="mova-ai-job-row is-offline">' +
          '<div class="mova-ai-job-title">' + escapeHtml((item.prompt || '').slice(0, 80)) + '</div>' +
          '<div class="mova-ai-job-meta">Waiting for network</div></div>';
      });
    }

    fetch('/hq/ai/jobs?active=0', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!list) return;
        var jobs = (data && data.jobs) || [];
        jobs.forEach(function (j) {
          if (j.status === 'queued' || j.status === 'running') {
            state.activeJobs[j.id] = j;
          }
        });
        updateJobsBadge();
        if (!jobs.length && !offline.length) {
          list.innerHTML = '<p class="mova-ai-jobs-empty">No background jobs yet. Enable Background and send a prompt — coding tasks run longer without blocking the panel.</p>';
          return;
        }
        html += '<div class="mova-ai-jobs-section">Recent</div>';
        jobs.slice(0, 20).forEach(function (j) {
          var st = j.status || '';
          var prog = j.progress || st;
          html += '<div class="mova-ai-job-row is-' + escapeHtml(st) + '" data-job="' + j.id + '">' +
            '<div class="mova-ai-job-title">' + escapeHtml((j.prompt || '').slice(0, 80)) + '</div>' +
            '<div class="mova-ai-job-meta">' +
            '<span class="mova-ai-job-status">' + escapeHtml(st) + '</span> · ' +
            escapeHtml(prog) +
            (j.provider ? ' · ' + escapeHtml(j.provider) : '') +
            '</div></div>';
        });
        list.innerHTML = html;
        list.querySelectorAll('[data-job]').forEach(function (row) {
          row.addEventListener('click', function () {
            var id = parseInt(row.getAttribute('data-job'), 10);
            openJobResult(id);
          });
        });
      })
      .catch(function () {
        if (!offline.length) {
          list.innerHTML = '<p class="mova-ai-jobs-empty">Could not load jobs.</p>';
        } else {
          list.innerHTML = html;
        }
      });
  }

  function openJobResult(id) {
    fetch('/hq/ai/jobs/' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.job) return;
        var j = data.job;
        showJobsView(false);
        if (j.session_id) {
          state.sessionId = j.session_id;
          try { localStorage.setItem(STORAGE_SESSION, String(j.session_id)); } catch (e) {}
        }
        if (j.status === 'done' && j.reply) {
          appendMessage('user', j.prompt, null, null);
          appendMessage('assistant', j.reply, j.actions || [], j.provider || null);
        } else if (j.status === 'failed') {
          appendMessage('assistant', j.error || 'Job failed.', null, 'error');
        } else {
          appendMessage('assistant', 'Job is still ' + j.status + ': ' + (j.progress || ''), null, null);
        }
      })
      .catch(function () {});
  }

  function startJobPolling() {
    if (jobPollTimer) return;
    jobPollTimer = setInterval(function () {
      pollActiveJobs();
      flushOfflineQueue();
    }, 2500);
  }

  function pollActiveJobs() {
    var ids = Object.keys(state.activeJobs);
    if (!ids.length) {
      updateJobsBadge();
      return;
    }
    ids.forEach(function (id) {
      fetch('/hq/ai/jobs/' + id, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.job) return;
          var j = data.job;
          state.activeJobs[j.id] = j;
          // Kick process if still queued
          if (j.status === 'queued') {
            kickProcess(j.id);
          }
          if (j.status === 'done' || j.status === 'failed' || j.status === 'cancelled') {
            onJobFinished(j);
            delete state.activeJobs[j.id];
          }
          updateJobsBadge();
          if (state.view === 'jobs') refreshJobsList();
        })
        .catch(function () {});
    });
  }

  function kickProcess(jobId) {
    var body = new URLSearchParams();
    body.set('_mova_csrf', csrfToken());
    body.set('job_id', String(jobId));
    fetch('/hq/ai/jobs/process', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: body.toString()
    }).catch(function () {});
  }

  function onJobFinished(j) {
    if (knownJobIds[j.id]) return;
    knownJobIds[j.id] = true;
    if (j.session_id && !state.sessionId) {
      state.sessionId = j.session_id;
      try { localStorage.setItem(STORAGE_SESSION, String(j.session_id)); } catch (e) {}
    }
    // Only surface if user is on that session or no session filter
    if (j.session_id && state.sessionId && j.session_id !== state.sessionId) {
      updateJobsBadge();
      return;
    }
    removeThinking();
    state.busy = false;
    var sendBtn = el('mova-ai-send');
    if (sendBtn) sendBtn.disabled = false;
    if (j.status === 'done') {
      appendMessage('assistant', j.reply || 'Done.', j.actions || [], j.provider || 'Mova AI');
      (j.actions || []).forEach(function (a) {
        if (a && a.type === 'navigate' && a.soft) softFillEditor(a);
        if (a && a.type === 'insert_code') applyInsertCode(a);
      });
    } else if (j.status === 'failed') {
      appendMessage('assistant', j.error || 'Background job failed. Try again.', null, 'error');
    }
    updateJobsBadge();
  }

  function decodeEntities(s) {
    s = String(s || '');
    if (s.indexOf('&lt;') === -1 && s.indexOf('&amp;') === -1 && s.indexOf('&quot;') === -1) {
      return s;
    }
    var ta = document.createElement('textarea');
    ta.innerHTML = s;
    return ta.value;
  }

  /** Strip YAML/front-matter wrappers the model sometimes emits; keep pure code. */
  function cleanCodeSnippet(raw, langHint) {
    var s = String(raw || '').replace(/^\uFEFF/, '');
    s = s.replace(/\r\n/g, '\n');
    // If whole reply was copied with fences still inside, pull first fence
    var fence = s.match(/```[\w]*\n?([\s\S]*?)```/);
    if (fence) s = fence[1];
    s = decodeEntities(s);
    // YAML-ish: type: page / body: |
    if (/^\s*type\s*:/m.test(s) || /^\s*body\s*:\s*\|/m.test(s)) {
      var bodyMatch = s.match(/^\s*body\s*:\s*\|\s*\n([\s\S]*)/m);
      if (bodyMatch) {
        s = bodyMatch[1];
        // Un-indent common leading spaces from YAML block scalar
        var lines = s.split('\n');
        var minIndent = null;
        lines.forEach(function (line) {
          if (!line.trim()) return;
          var m = line.match(/^( +)/);
          var n = m ? m[1].length : 0;
          if (minIndent === null || n < minIndent) minIndent = n;
        });
        if (minIndent && minIndent > 0) {
          s = lines.map(function (line) {
            return line.indexOf(Array(minIndent + 1).join(' ')) === 0
              ? line.slice(minIndent)
              : line;
          }).join('\n');
        }
      } else {
        // Drop pure yaml key lines
        s = s.split('\n').filter(function (line) {
          return !/^\s*(type|title|status|slug|body)\s*:/.test(line);
        }).join('\n');
      }
    }
    // Leading language label alone on first line
    s = s.replace(/^(html|css|javascript|js|json|xml|text)\s*\n/i, '');
    return s.replace(/^\n+/, '').replace(/\n+$/, '') + (s.trim() ? '\n' : '');
  }

  function detectLang(code, hint) {
    var h = (hint || '').toLowerCase();
    if (h === 'js' || h === 'javascript') return 'js';
    if (h === 'css' || h === 'scss') return 'css';
    if (h === 'html' || h === 'xml' || h === 'markup') return 'html';
    var c = String(code || '');
    if (/^\s*[\.\#\@\w-]+\s*\{/.test(c) || /:\s*[^;]+;/.test(c) && c.indexOf('<') === -1) return 'css';
    if (/^\s*(function|const|let|var|document\.|window\.|=>)/m.test(c) && c.indexOf('<') === -1) return 'js';
    if (/<[a-zA-Z]/.test(c)) return 'html';
    return h || 'html';
  }

  /** Lightweight syntax highlight for AI code blocks (no external lib). */
  function highlightCode(code, lang) {
    var s = escapeHtml(String(code || ''));
    var L = (lang || '').toLowerCase();
    if (L === 'html' || L === 'xml' || L === 'markup') {
      // comments
      s = s.replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span class="tok-comment">$1</span>');
      // tags + attributes
      s = s.replace(/(&lt;\/?[a-zA-Z][\w:-]*)/g, '<span class="tok-tag">$1</span>');
      s = s.replace(/\s([a-zA-Z_:][\w:.-]*)(=)/g, ' <span class="tok-attr">$1</span>$2');
      s = s.replace(/(=)(&quot;[^&]*&quot;|&#39;[^&]*&#39;)/g, '$1<span class="tok-str">$2</span>');
      return s;
    }
    if (L === 'css' || L === 'scss') {
      s = s.replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="tok-comment">$1</span>');
      s = s.replace(/(#[0-9a-fA-F]{3,8}|\d+\.?\d*(?:px|rem|em|%|vh|vw|s|ms)?)/g, '<span class="tok-number">$1</span>');
      s = s.replace(/([a-zA-Z-]+)(\s*:)/g, '<span class="tok-property">$1</span>$2');
      s = s.replace(/(^|\n)([^{}\/]+)(\{)/gm, function (_, a, sel, b) {
        return a + '<span class="tok-selector">' + sel + '</span>' + b;
      });
      return s;
    }
    if (L === 'js' || L === 'javascript') {
      s = s.replace(/(\/\*[\s\S]*?\*\/|\/\/[^\n]*)/g, '<span class="tok-comment">$1</span>');
      s = s.replace(/\b(const|let|var|function|return|if|else|for|while|class|new|this|typeof|async|await|import|export|from|default)\b/g, '<span class="tok-keyword">$1</span>');
      s = s.replace(/(&quot;[^&]*&quot;|&#39;[^&]*&#39;|`[^`]*`)/g, '<span class="tok-str">$1</span>');
      s = s.replace(/\b(\d+\.?\d*)\b/g, '<span class="tok-number">$1</span>');
      return s;
    }
    return s;
  }


  function writeTextarea(el, code, mode) {
    if (!el) return false;
    var next = mode === 'replace' ? code : ((el.value ? el.value.replace(/\s*$/, '') + '\n\n' : '') + code);
    el.value = next;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }

  function insertIntoStudio(code, lang, mode) {
    if (!document.getElementById('studio-app') && !document.getElementById('studio-html')) {
      return false;
    }
    var api = window.MovaStudio;
    if (api) {
      try {
        if (lang === 'css') {
          if (mode === 'replace' && api.setCss) api.setCss(code);
          else if (api.appendCss) api.appendCss(code);
          else if (api.setCss && api.getCss) api.setCss((api.getCss() || '') + '\n' + code);
        } else if (lang === 'js') {
          if (mode === 'replace' && api.setJs) api.setJs(code);
          else if (api.appendJs) api.appendJs(code);
          else if (api.setJs && api.getJs) api.setJs((api.getJs() || '') + '\n' + code);
        } else {
          if (mode === 'replace' && api.setHtml) api.setHtml(code);
          else if (api.appendHtml) api.appendHtml(code);
          else if (api.setHtml && api.getHtml) api.setHtml((api.getHtml() || '') + code);
        }
        return true;
      } catch (e) {}
    }
    var id = lang === 'css' ? 'studio-css' : (lang === 'js' ? 'studio-js' : 'studio-html');
    return writeTextarea(document.getElementById(id), code, mode);
  }

  function insertIntoContent(code, mode) {
    // Prefer visible/dev body editors, then hidden body-input
    var candidates = [
      document.querySelector('#mova-dev-panels textarea[name="body"]'),
      document.querySelector('textarea[name="body"]:not([hidden])'),
      document.getElementById('body-input'),
      document.querySelector('#content-form textarea[name="body"]'),
      document.querySelector('textarea[name="body"]')
    ];
    var el = null;
    for (var i = 0; i < candidates.length; i++) {
      if (candidates[i]) { el = candidates[i]; break; }
    }
    if (!el) {
      if (window.movaStudioSetBody && typeof window.movaStudioSetBody === 'function') {
        try {
          if (mode === 'replace') window.movaStudioSetBody(code);
          else window.movaStudioSetBody(code);
          return true;
        } catch (e) {}
      }
      return false;
    }
    var ok = writeTextarea(el, code, mode);
    if (window.movaStudioSetBody && typeof window.movaStudioSetBody === 'function') {
      try { window.movaStudioSetBody(el.value); } catch (e2) {}
    }
    // Visual editor bridge if present
    if (window.movaVisualEditor && typeof window.movaVisualEditor.setHtml === 'function') {
      try { window.movaVisualEditor.setHtml(el.value); } catch (e3) {}
    }
    return ok;
  }

  /** Convert Markdown → HTML only for visual/body inserts (never for CSS/JS/code). */
  function prepareBodyInsert(code, lang) {
    var L = (lang || '').toLowerCase();
    if (L === 'css' || L === 'js' || L === 'javascript' || L === 'typescript' || L === 'json') {
      return code;
    }
    var md = window.MovaMarkdown;
    if (!md || typeof md.maybeToHtml !== 'function') return code;
    // Structured HTML or explicit html language → leave as-is
    if (L === 'html' || L === 'xml' || (md.looksLikeHtml && md.looksLikeHtml(code))) {
      return code;
    }
    if (md.looksLikeMarkdown && md.looksLikeMarkdown(code)) {
      return md.toHtml(code);
    }
    return code;
  }

  function insertIntoFocused(code, mode) {
    var ae = document.activeElement;
    if (ae && (ae.tagName === 'TEXTAREA' || (ae.tagName === 'INPUT' && ae.type === 'text'))) {
      if (ae.closest && ae.closest('.mova-ai-panel')) return false;
      return writeTextarea(ae, code, mode);
    }
    if (ae && ae.isContentEditable) {
      var html = prepareBodyInsert(code, '');
      if (mode === 'replace') ae.innerHTML = html;
      else ae.insertAdjacentHTML('beforeend', html);
      if (window.movaVisualEditor && ae === window.movaVisualEditor.el) {
        try {
          var bi = document.getElementById('body-input');
          if (bi) bi.value = ae.innerHTML;
        } catch (e) {}
      }
      return true;
    }
    return false;
  }

  function applyInsertCode(action) {
    if (!action || !action.payload) return false;
    var lang = detectLang(action.payload.code || '', action.payload.language || action.payload.lang || '');
    var code = cleanCodeSnippet(action.payload.code || '', lang);
    var mode = action.payload.mode || 'append';
    if (!code.trim()) return false;

    // Body-oriented inserts: convert MD → HTML when appropriate
    var bodyCode = prepareBodyInsert(code, lang);

    // 1) Focused field outside AI panel
    if (insertIntoFocused(bodyCode, mode)) return true;
    // 2) Studio (always raw code — never MD-convert CSS/JS/HTML source)
    if (insertIntoStudio(code, lang, mode)) return true;
    // 3) Content edit (visual body may need MD → HTML)
    if (insertIntoContent(bodyCode, mode)) return true;
    // 4) Any obvious code area on page
    var fallback = document.querySelector('textarea.studio-code-area, textarea[name="raw_css"], textarea[name="raw_js"], textarea[name="custom_css"]');
    if (fallback) return writeTextarea(fallback, code, mode);
    return false;
  }

  function flushOfflineQueue() {
    if (!navigator.onLine) return;
    var q = offlineQueue();
    if (!q.length) return;
    var item = q.shift();
    saveOfflineQueue(q);
    submitBackgroundJob(item.prompt, item.session_id, item.context, item.id);
  }

  function submitBackgroundJob(text, sessionId, ctx, clientKey) {
    ctx = ctx || pageContext();
    var body = new URLSearchParams();
    body.set('_mova_csrf', csrfToken());
    body.set('message', text);
    body.set('background', '1');
    if (sessionId) body.set('session_id', String(sessionId));
    if (clientKey) body.set('client_key', clientKey);
    body.set('route', ctx.route || '');
    body.set('area', ctx.area || '');
    body.set('layer', ctx.layer || '');
    if (ctx.entity_id) body.set('entity_id', String(ctx.entity_id));
    if (ctx.template_id) body.set('template_id', String(ctx.template_id));

    return fetch('/hq/ai/jobs', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.job) {
          state.activeJobs[data.job.id] = data.job;
          if (data.job.status === 'queued') kickProcess(data.job.id);
          updateJobsBadge();
          startJobPolling();
          return data.job;
        }
        throw new Error((data && data.error) || 'Job failed');
      });
  }

  function applyCssVars(vars) {
    if (!vars || typeof vars !== 'object') return;
    var root = document.documentElement;
    Object.keys(vars).forEach(function (k) {
      try { root.style.setProperty(k, vars[k]); } catch (e) {}
    });
  }

  function softFillEditor(action) {
    if (!action || !action.soft) return;
    var m = (action.path || '').match(/\/hq\/content\/edit\/(\d+)/);
    if (!m) return;
    var here = (window.location.pathname || '').match(/\/hq\/content\/edit\/(\d+)/);
    if (!here || here[1] !== m[1]) return;
    var form = document.getElementById('content-form');
    if (!form) return;
    if (action.title_text) {
      var title = form.querySelector('input[name="title"]');
      if (title) title.value = action.title_text;
    }
    if (action.excerpt) {
      var ex = form.querySelector('textarea[name="excerpt"], input[name="excerpt"]');
      if (ex) ex.value = action.excerpt;
    }
    if (action.body) {
      var body = form.querySelector('textarea[name="body"]');
      if (body) {
        body.value = action.body;
        body.dispatchEvent(new Event('input', { bubbles: true }));
        body.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (window.movaStudioSetBody && typeof window.movaStudioSetBody === 'function') {
        try { window.movaStudioSetBody(action.body); } catch (e) {}
      }
    }
  }

  function formatElapsed(sec) {
    sec = Math.max(0, Math.floor(sec));
    if (sec < 60) return sec + 's';
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
  }

  function showThinking() {
    var box = el('mova-ai-messages');
    if (!box) return;
    var empty = box.querySelector('.mova-ai-empty');
    if (empty) empty.remove();
    removeThinking();

    var div = document.createElement('div');
    div.className = 'mova-ai-msg thinking';
    div.id = 'mova-ai-thinking';
    div.innerHTML =
      '<div class="mova-ai-thinking-row">' +
      '<div class="mova-ai-dots"><span></span><span></span><span></span></div>' +
      '<span class="mova-ai-thinking-label">Working for <strong id="mova-ai-elapsed">0s</strong></span>' +
      '</div>' +
      '<span class="mova-ai-thinking-status" id="mova-ai-thinking-status">' +
      escapeHtml(thinkingPhrases[0]) +
      '</span>';
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;

    var started = Date.now();
    var i = 0;
    div._movaTimer = setInterval(function () {
      i = (i + 1) % thinkingPhrases.length;
      var st = el('mova-ai-thinking-status');
      if (st) st.textContent = thinkingPhrases[i];
      var elap = el('mova-ai-elapsed');
      if (elap) elap.textContent = formatElapsed((Date.now() - started) / 1000);
    }, 1000);
  }

  function removeThinking() {
    var t = el('mova-ai-thinking');
    if (t) {
      if (t._movaTimer) clearInterval(t._movaTimer);
      t.remove();
    }
  }

  function applyDesignAction(action, btn) {
    if (!action || !action.payload) return;
    if (btn) btn.disabled = true;
    var body = new URLSearchParams();
    body.set('_mova_csrf', csrfToken());
    body.set('action_json', JSON.stringify(action));
    fetch('/hq/ai/action', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          var result = data.result || {};
          if (result.css_vars) applyCssVars(result.css_vars);
          var onStyle = (window.location.pathname || '').indexOf('/hq/style') === 0;
          appendMessage(
            'assistant',
            onStyle
              ? 'Colors applied live — no reload needed.'
              : 'Colors applied without a full reload. Open Style only if you want the form fields.',
            onStyle ? null : [{ type: 'navigate', label: 'Open Style (optional)', path: result.path || '/hq/style' }],
            null
          );
        } else {
          appendMessage('assistant', data.error || 'Could not apply design changes.', null, null);
        }
      })
      .catch(function () {
        appendMessage('assistant', 'Network error applying design changes.', null, null);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function copyText(text, btn) {
    var done = function () {
      if (btn) {
        var prev = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i>';
        setTimeout(function () { btn.innerHTML = prev; }, 1200);
      }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        fallbackCopy(text);
        done();
      });
    } else {
      fallbackCopy(text);
      done();
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
  }

  function appendMessage(role, text, actions, provider) {
    var box = el('mova-ai-messages');
    if (!box) return;
    var empty = box.querySelector('.mova-ai-empty');
    if (empty) empty.remove();

    text = role === 'assistant' ? cleanReply(text) : text;

    var div = document.createElement('div');
    div.className = 'mova-ai-msg ' + role;
    var html = escapeHtml(text || '').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    var codeBlocks = [];
    html = html.replace(/```([\w]*)\n?([\s\S]*?)```/g, function (_, lang, code) {
      var idx = codeBlocks.length;
      var cleaned = cleanCodeSnippet(code, lang);
      var langLabel = (lang || detectLang(cleaned, '') || 'code');
      codeBlocks.push({ lang: langLabel, code: cleaned });
      return '<div class="mova-ai-code-wrap" data-code-idx="' + idx + '">' +
        '<div class="mova-ai-code-bar">' +
        '<span>' + escapeHtml(langLabel) + '</span>' +
        '<button type="button" class="mova-ai-code-btn" data-code-copy="' + idx + '">Copy</button>' +
        '<button type="button" class="mova-ai-code-btn" data-code-insert="' + idx + '">Insert</button>' +
        '</div>' +
        '<pre class="mova-ai-code"><code>' + highlightCode(cleaned, langLabel) + '</code></pre></div>';
    });
    div.innerHTML = html;
    div.querySelectorAll('[data-code-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-code-copy'), 10);
        if (codeBlocks[i]) copyText(codeBlocks[i].code, btn);
      });
    });
    div.querySelectorAll('[data-code-insert]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-code-insert'), 10);
        if (!codeBlocks[i]) return;
        var ok = applyInsertCode({
          payload: {
            target: 'body',
            mode: 'append',
            language: codeBlocks[i].lang,
            code: codeBlocks[i].code
          }
        });
        var prev = btn.textContent;
        btn.textContent = ok ? 'Inserted' : 'No editor';
        setTimeout(function () { btn.textContent = prev; }, 1400);
      });
    });

    if (actions && actions.length) {
      var act = document.createElement('div');
      act.className = 'mova-ai-actions';
      actions.forEach(function (a) {
        if (!a || !a.type) return;
        if (a.type === 'navigate' && a.path) {
          var link = document.createElement('a');
          link.href = a.path;
          link.innerHTML =
            '<i class="fa-solid fa-arrow-up-right-from-square"></i> ' +
            escapeHtml(a.label || 'Open');
          act.appendChild(link);
        } else if (a.type === 'update_design_tokens') {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'mova-ai-apply';
          btn.innerHTML =
            '<i class="fa-solid fa-palette"></i> ' + escapeHtml(a.label || 'Apply colors');
          btn.addEventListener('click', function () {
            applyDesignAction(a, btn);
          });
          act.appendChild(btn);
        } else if (a.type === 'insert_code' && a.payload && a.payload.code) {
          var ib = document.createElement('button');
          ib.type = 'button';
          ib.className = 'mova-ai-apply';
          ib.innerHTML = '<i class="fa-solid fa-file-import"></i> ' + escapeHtml(a.label || 'Insert into body');
          ib.addEventListener('click', function () {
            applyInsertCode(a);
            ib.innerHTML = '<i class="fa-solid fa-check"></i> Inserted';
          });
          act.appendChild(ib);
        }
      });
      if (act.childNodes.length) div.appendChild(act);
    }

    if (role === 'assistant' && text) {
      var tools = document.createElement('div');
      tools.className = 'mova-ai-msg-tools';
      tools.innerHTML =
        '<button type="button" class="mova-ai-tool" data-act="copy" title="Copy"><i class="fa-regular fa-copy"></i></button>' +
        '<button type="button" class="mova-ai-tool" data-act="up" title="Helpful"><i class="fa-regular fa-thumbs-up"></i></button>' +
        '<button type="button" class="mova-ai-tool" data-act="down" title="Not helpful"><i class="fa-regular fa-thumbs-down"></i></button>' +
        '<button type="button" class="mova-ai-tool" data-act="regen" title="Regenerate"><i class="fa-solid fa-rotate"></i></button>';
      tools.addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]');
        if (!b) return;
        var actName = b.getAttribute('data-act');
        if (actName === 'copy') copyText(text, b);
        if (actName === 'up' || actName === 'down') b.classList.add('is-active');
        if (actName === 'regen' && lastUserMessage) {
          var ta = el('mova-ai-input');
          if (ta) {
            ta.value = lastUserMessage;
            send();
          }
        }
      });
      div.appendChild(tools);
    }

    if (provider) {
      var p = document.createElement('span');
      p.className = 'mova-ai-provider';
      p.textContent = provider;
      div.appendChild(p);
    }
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  function showEmpty() {
    var box = el('mova-ai-messages');
    if (!box) return;
    var g = timeGreeting();
    box.innerHTML =
      '<div class="mova-ai-empty">' +
      '<p class="mova-ai-greet">' + escapeHtml(g) + '</p>' +
      '<p class="mova-ai-greet-sub">Ask about content, design, settings, or paste code for help.</p>' +
      '</div>';
  }

  function loadSessions() {
    return fetch('/hq/ai/sessions', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var list = el('mova-ai-sessions-list');
        if (!list || !data.ok) return;
        list.innerHTML = '';
        var sessions = data.sessions || [];
        if (!sessions.length) {
          list.innerHTML = '<p class="mova-ai-sessions-empty">No saved chats yet.</p>';
          return;
        }
        sessions.forEach(function (s) {
          var row = document.createElement('div');
          row.className = 'mova-ai-session-row' + (state.sessionId === s.id ? ' is-active' : '');
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'mova-ai-session-item';
          btn.innerHTML =
            '<span class="mova-ai-session-title">' + escapeHtml(s.title || 'Chat') + '</span>' +
            '<time>' + escapeHtml((s.updated_at || '').slice(0, 16).replace('T', ' ')) + '</time>';
          btn.addEventListener('click', function () {
            loadSession(s.id);
          });
          var del = document.createElement('button');
          del.type = 'button';
          del.className = 'mova-ai-session-del';
          del.title = 'Clear session';
          del.innerHTML = '<i class="fa-regular fa-trash-can"></i>';
          del.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!confirm('Clear this chat session?')) return;
            var body = new URLSearchParams();
            body.set('_mova_csrf', csrfToken());
            fetch('/hq/ai/sessions/' + s.id + '/delete', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken()
              },
              body: body.toString()
            })
              .then(function (r) { return r.json(); })
              .then(function (res) {
                if (res.ok) {
                  if (state.sessionId === s.id) {
                    state.sessionId = null;
                    try { localStorage.removeItem(STORAGE_SESSION); } catch (e2) {}
                    showEmpty();
                  }
                  loadSessions();
                }
              });
          });
          row.appendChild(btn);
          row.appendChild(del);
          list.appendChild(row);
        });
      })
      .catch(function () {});
  }

  function loadSession(id) {
    state.sessionId = id;
    try {
      localStorage.setItem(STORAGE_SESSION, String(id));
    } catch (e) {}
    showSessionsView(false);
    return fetch('/hq/ai/sessions/' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var box = el('mova-ai-messages');
        if (!box || !data.ok) return;
        box.innerHTML = '';
        var msgs = data.messages || [];
        if (!msgs.length) {
          showEmpty();
        } else {
          msgs.forEach(function (m) {
            appendMessage(m.role === 'user' ? 'user' : 'assistant', m.content, m.actions || [], null);
          });
        }
      })
      .catch(function () {});
  }

  function newChat() {
    state.sessionId = null;
    try {
      localStorage.removeItem(STORAGE_SESSION);
    } catch (e) {}
    showSessionsView(false);
    showEmpty();
    var ta = el('mova-ai-input');
    if (ta) ta.focus();
  }

  function send() {
    var ta = el('mova-ai-input');
    var btn = el('mova-ai-send');
    if (!ta || state.busy) return;
    var text = (ta.value || '').trim();
    if (!text) return;

    showSessionsView(false);
    showJobsView(false);
    lastUserMessage = text;
    appendMessage('user', text, null, null);
    ta.value = '';

    var ctx = pageContext();
    var bgCheck = el('mova-ai-bg-mode');
    var useBg = state.bgMode;
    if (bgCheck) useBg = !!bgCheck.checked;
    // Auto-background for coding-looking prompts
    var looksCode = /\b(html|css|javascript|code|snippet|pricelist|price list|template:)\b/i.test(text) || text.indexOf('```') !== -1 || !!state.templateId;
    if (looksCode) useBg = true;

    if (useBg) {
      state.busy = false;
      if (btn) btn.disabled = false;
      showThinking();
      var thinkingLabel = el('mova-ai-thinking');
      if (thinkingLabel) {
        var st = el('mova-ai-thinking-status');
        if (st) st.textContent = looksCode ? 'Queued coding job in background…' : 'Running in background…';
      }

      if (!navigator.onLine) {
        enqueueOffline(text, ctx);
        removeThinking();
        appendMessage('assistant', 'You are offline. Prompt saved to the background queue and will run when you are back online.', null, 'offline');
        updateJobsBadge();
        return;
      }

      submitBackgroundJob(text, state.sessionId, ctx, null)
        .then(function (job) {
          var st = el('mova-ai-thinking-status');
          if (st) st.textContent = (job.progress || 'Working…') + ' — safe to keep browsing';
          // Keep light thinking until first progress poll finishes job
          startJobPolling();
        })
        .catch(function () {
          removeThinking();
          // Fallback to sync chat
          sendSync(text, ctx);
        });
      return;
    }

    sendSync(text, ctx);
  }

  function sendSync(text, ctx) {
    var btn = el('mova-ai-send');
    state.busy = true;
    if (btn) btn.disabled = true;
    showThinking();

    var body = new URLSearchParams();
    body.set('_mova_csrf', csrfToken());
    body.set('message', text);
    if (state.sessionId) body.set('session_id', String(state.sessionId));
    ctx = ctx || pageContext();
    body.set('route', ctx.route);
    body.set('area', ctx.area);
    body.set('layer', ctx.layer);
    if (ctx.entity_id) body.set('entity_id', String(ctx.entity_id));
    if (ctx.template_id) body.set('template_id', String(ctx.template_id));

    fetch('/hq/ai/chat', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        removeThinking();
        if (data.session_id) {
          state.sessionId = data.session_id;
          try {
            localStorage.setItem(STORAGE_SESSION, String(data.session_id));
          } catch (e) {}
        }
        var acts = data.actions || [];
        acts.forEach(function (a) {
          if (a && a.type === 'navigate' && a.soft) softFillEditor(a);
          if (a && a.type === 'insert_code') applyInsertCode(a);
        });
        appendMessage(
          'assistant',
          data.reply || data.error || 'No response',
          acts,
          data.provider || null
        );
      })
      .catch(function () {
        removeThinking();
        appendMessage('assistant', 'Network error talking to Mova AI.', null, null);
      })
      .finally(function () {
        removeThinking();
        state.busy = false;
        if (btn) btn.disabled = false;
      });
  }

  function bind() {
    var fab = el('mova-ai-fab');
    var panel = el('mova-ai-panel');
    if (!panel) return;

    if (fab) fab.addEventListener('click', function () { setOpen(true); });
    var closeBtn = el('mova-ai-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    var newBtn = el('mova-ai-new');
    if (newBtn) newBtn.addEventListener('click', newChat);
    var sessToggle = el('mova-ai-sessions-toggle');
    if (sessToggle) {
      sessToggle.addEventListener('click', function () {
        var show = state.view !== 'sessions';
        showSessionsView(show);
        if (show) loadSessions();
      });
    }
    var jobsToggle = el('mova-ai-jobs-toggle');
    if (jobsToggle) {
      jobsToggle.addEventListener('click', function () {
        var show = state.view !== 'jobs';
        showJobsView(show);
      });
    }
    var jobsRefresh = el('mova-ai-jobs-refresh');
    if (jobsRefresh) {
      jobsRefresh.addEventListener('click', refreshJobsList);
    }
    var bgCheck = el('mova-ai-bg-mode');
    if (bgCheck) {
      try {
        var saved = localStorage.getItem(STORAGE_BG_MODE);
        if (saved === '0') bgCheck.checked = false;
        if (saved === '1') bgCheck.checked = true;
      } catch (e) {}
      state.bgMode = !!bgCheck.checked;
      bgCheck.addEventListener('change', function () {
        state.bgMode = !!bgCheck.checked;
        try { localStorage.setItem(STORAGE_BG_MODE, state.bgMode ? '1' : '0'); } catch (e2) {}
      });
    }
    window.addEventListener('online', function () { flushOfflineQueue(); });
    startJobPolling();
    // Load active jobs on boot
    fetch('/hq/ai/jobs?active=1', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        ((data && data.jobs) || []).forEach(function (j) {
          state.activeJobs[j.id] = j;
          if (j.status === 'queued') kickProcess(j.id);
        });
        updateJobsBadge();
      })
      .catch(function () {});
    flushOfflineQueue();
    var sendBtn = el('mova-ai-send');
    if (sendBtn) sendBtn.addEventListener('click', send);
    var ta = el('mova-ai-input');
    if (ta) {
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          send();
        }
      });
    }

    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'j' || e.key === 'J')) {
        e.preventDefault();
        toggle();
      }
      if (e.key === 'Escape' && state.open) {
        if (state.view === 'sessions') showSessionsView(false);
        else setOpen(false);
      }
    });

    var preferOpen = false;
    try {
      preferOpen = localStorage.getItem(STORAGE_OPEN) === '1';
      var sid = localStorage.getItem(STORAGE_SESSION);
      if (sid) state.sessionId = parseInt(sid, 10) || null;
    } catch (e) {}

    setOpen(preferOpen);
    showSessionsView(false);
    if (state.sessionId) loadSession(state.sessionId);
    else showEmpty();

    bindGuide();
    try {
      var pendingTpl = sessionStorage.getItem('mova_ai_template_id');
      var openGuide = sessionStorage.getItem('mova_ai_open_guide');
      if (pendingTpl) {
        useTemplate(pendingTpl);
        sessionStorage.removeItem('mova_ai_template_id');
      }
      if (openGuide === '1') {
        setGuideOpen(true);
        sessionStorage.removeItem('mova_ai_open_guide');
        if (!preferOpen) setOpen(true);
      }
    } catch (e3) {}
  }

  /* —— Guided template flow —— */
  var guideCache = null;

  function setGuideOpen(open) {
    var g = el('mova-ai-guide');
    if (!g) return;
    g.hidden = !open;
    if (open) {
      showSessionsView(false);
      showJobsView(false);
    }
  }

  function guideShowStep(n) {
    document.querySelectorAll('.mova-ai-guide-step').forEach(function (step) {
      var sn = parseInt(step.getAttribute('data-guide-step'), 10);
      step.hidden = sn !== n;
    });
  }

  function loadGuideTemplates(category) {
    var box = el('mova-ai-guide-templates');
    var catBox = el('mova-ai-guide-categories');
    if (!box) return;
    box.innerHTML = '<p class="mova-ai-jobs-loading">Loading templates…</p>';
    var done = function (list) {
      guideCache = list || [];
      var cats = {};
      guideCache.forEach(function (t) {
        var c = t.category || 'general';
        cats[c] = (cats[c] || 0) + 1;
      });
      if (catBox) {
        var html = '<button type="button" class="mova-ai-chip' + (!category ? ' is-active' : '') + '" data-guide-cat="">All</button>';
        Object.keys(cats).forEach(function (c) {
          html += '<button type="button" class="mova-ai-chip' + (category === c ? ' is-active' : '') + '" data-guide-cat="' + escapeHtml(c) + '">' + escapeHtml(c) + '</button>';
        });
        catBox.innerHTML = html;
        catBox.querySelectorAll('[data-guide-cat]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            loadGuideTemplates(btn.getAttribute('data-guide-cat') || '');
            guideShowStep(3);
          });
        });
      }
      var filtered = !category ? guideCache : guideCache.filter(function (t) { return t.category === category; });
      if (!filtered.length) {
        box.innerHTML = '<p class="mova-ai-jobs-empty">No templates in this category.</p>';
        return;
      }
      box.innerHTML = filtered.map(function (t) {
        var img = t.preview ? '<img src="' + escapeHtml(t.preview) + '" alt="">' : '<span style="width:56px;height:34px;background:#e2e8f0;border-radius:4px;display:inline-block"></span>';
        return '<button type="button" class="mova-ai-guide-tpl" data-tpl-id="' + escapeHtml(t.id) + '">' +
          img +
          '<span class="mova-ai-guide-tpl-copy"><strong>' + escapeHtml(t.name) + '</strong><span>' + escapeHtml(t.description || t.id) + '</span></span></button>';
      }).join('');
      box.querySelectorAll('[data-tpl-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          useTemplate(btn.getAttribute('data-tpl-id'));
          guideShowStep(4);
          box.querySelectorAll('.mova-ai-guide-tpl').forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');
        });
      });
    };
    if (guideCache) {
      done(guideCache);
      return;
    }
    fetch('/hq/ai/templates', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) { done((data && data.templates) || []); })
      .catch(function () {
        box.innerHTML = '<p class="mova-ai-jobs-empty">Could not load templates.</p>';
      });
  }

  function useTemplate(id) {
    if (!id) return;
    state.templateId = id;
    var picked = el('mova-ai-guide-picked');
    if (picked) picked.textContent = '· ' + id;
    guideShowStep(4);
    setGuideOpen(true);
  }

  function bindGuide() {
    var toggle = el('mova-ai-guide-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var g = el('mova-ai-guide');
        var open = g && g.hidden;
        setGuideOpen(!!open);
        if (open) guideShowStep(1);
      });
    }
    var skip = el('mova-ai-guide-skip');
    if (skip) {
      skip.addEventListener('click', function () {
        state.templateId = null;
        setGuideOpen(false);
        var ta = el('mova-ai-input');
        if (ta) ta.focus();
      });
    }
    document.querySelectorAll('[data-guide-intent]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var intent = btn.getAttribute('data-guide-intent');
        if (intent === 'template') {
          loadGuideTemplates('');
          guideShowStep(2);
        } else if (intent === 'colors') {
          setGuideOpen(false);
          var ta = el('mova-ai-input');
          if (ta) {
            ta.value = 'Set primary color to a greenish tone and keep the rest of the palette balanced.';
            ta.focus();
          }
        } else {
          setGuideOpen(false);
          var ta2 = el('mova-ai-input');
          if (ta2) ta2.focus();
        }
      });
    });
    document.querySelectorAll('[data-guide-opt]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.classList.toggle('is-active');
      });
    });
    var gen = el('mova-ai-guide-generate');
    if (gen) {
      gen.addEventListener('click', function () {
        if (!state.templateId) {
          guideShowStep(2);
          loadGuideTemplates('');
          return;
        }
        var brief = (el('mova-ai-guide-brief') && el('mova-ai-guide-brief').value || '').trim();
        var opts = [];
        document.querySelectorAll('[data-guide-opt].is-active').forEach(function (b) {
          opts.push(b.getAttribute('data-guide-opt'));
        });
        var msg = 'template:' + state.templateId + '\n\n';
        msg += brief || 'Adapt this template for my site. Keep structure and classes.';
        if (opts.indexOf('keep_colors') !== -1) msg += '\nUse existing site color variables.';
        if (opts.indexOf('featured_middle') !== -1) msg += '\nKeep the middle plan featured.';
        var ta = el('mova-ai-input');
        if (ta) ta.value = msg;
        setGuideOpen(false);
        send();
      });
    }
  }

  window.MovaAiPanel = {
    useTemplate: useTemplate,
    openGuide: function () { setGuideOpen(true); guideShowStep(1); },
    open: function () { setOpen(true); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
