/**
 * Mova AI panel — HQ (sessions, reactions, soft actions, friendly empty state)
 * Shortcut: Ctrl+J / Cmd+J
 */
(function () {
  'use strict';

  var STORAGE_OPEN = 'mova_ai_panel_open';
  var STORAGE_SESSION = 'mova_ai_session_id';
  var lastUserMessage = '';

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
    return {
      route: ctx.route || window.location.pathname + window.location.search,
      area: ctx.area || (document.body && document.body.getAttribute('data-workspace')) || '',
      layer: ctx.layer || new URLSearchParams(window.location.search).get('layer') || '',
      entity_id: entityId
    };
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
    view: 'chat'
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
    state.view = show ? 'sessions' : 'chat';
    var list = el('mova-ai-sessions');
    var messages = el('mova-ai-messages');
    var composer = document.querySelector('.mova-ai-composer');
    if (list) list.hidden = !show;
    if (messages) messages.hidden = !!show;
    if (composer) composer.hidden = !!show;
    var title = document.querySelector('.mova-ai-panel-header h2');
    if (title) {
      title.innerHTML = show
        ? 'Sessions <span class="mova-ai-badge">HQ</span>'
        : 'Mova AI <span class="mova-ai-badge">HQ</span>';
    }
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
      '<span class="mova-ai-thinking-label">Mova AI is working</span>' +
      '</div>' +
      '<span class="mova-ai-thinking-status" id="mova-ai-thinking-status">' +
      escapeHtml(thinkingPhrases[0]) +
      '</span>';
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;

    var i = 0;
    div._movaTimer = setInterval(function () {
      i = (i + 1) % thinkingPhrases.length;
      var st = el('mova-ai-thinking-status');
      if (st) st.textContent = thinkingPhrases[i];
    }, 2200);
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
    html = html.replace(/```([\s\S]*?)```/g, function (_, code) {
      return '<pre class="mova-ai-code"><code>' + escapeHtml(code.replace(/^\w+\n/, '')) + '</code></pre>';
    });
    div.innerHTML = html;

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
    state.busy = true;
    lastUserMessage = text;
    if (btn) btn.disabled = true;
    appendMessage('user', text, null, null);
    ta.value = '';
    showThinking();

    var body = new URLSearchParams();
    body.set('_mova_csrf', csrfToken());
    body.set('message', text);
    if (state.sessionId) body.set('session_id', String(state.sessionId));
    var ctx = pageContext();
    body.set('route', ctx.route);
    body.set('area', ctx.area);
    body.set('layer', ctx.layer);
    if (ctx.entity_id) body.set('entity_id', String(ctx.entity_id));

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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
