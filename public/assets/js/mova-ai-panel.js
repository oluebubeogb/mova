/**
 * Mova AI panel — global HQ sidebar (Phases 1–5)
 * Shortcut: Ctrl+J / Cmd+J
 * Loading feedback while the model works.
 */
(function () {
  'use strict';

  var STORAGE_OPEN = 'mova_ai_panel_open';
  var STORAGE_SESSION = 'mova_ai_session_id';

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
    return {
      route: ctx.route || window.location.pathname + window.location.search,
      area: ctx.area || (document.body && document.body.getAttribute('data-workspace')) || '',
      layer: ctx.layer || new URLSearchParams(window.location.search).get('layer') || '',
      entity_id: ctx.entityId || 0
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

  var state = {
    open: false,
    sessionId: null,
    busy: false
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
          var path = (data.result && data.result.path) || '/hq/style';
          appendMessage(
            'assistant',
            'Colors applied. Open Style to review.',
            [{ type: 'navigate', label: 'Open Style', path: path }],
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

  function appendMessage(role, text, actions, provider) {
    var box = el('mova-ai-messages');
    if (!box) return;
    var empty = box.querySelector('.mova-ai-empty');
    if (empty) empty.remove();

    var div = document.createElement('div');
    div.className = 'mova-ai-msg ' + role;
    var html = escapeHtml(text || '').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
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
    box.innerHTML =
      '<div class="mova-ai-empty">Ask Mova AI anything about HQ.<br><br>' +
      'Try: “Where is the site icon?”, “Create an About Us page”, or “Set primary to #0ea5e9”.<br><br>' +
      'Shortcut: <kbd>Ctrl</kbd>+<kbd>J</kbd></div>';
  }

  function loadSessions() {
    return fetch('/hq/ai/sessions', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var list = el('mova-ai-sessions-list');
        if (!list || !data.ok) return;
        list.innerHTML = '';
        (data.sessions || []).forEach(function (s) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'mova-ai-session-item' + (state.sessionId === s.id ? ' is-active' : '');
          btn.innerHTML =
            escapeHtml(s.title || 'Chat') +
            '<time>' + escapeHtml((s.updated_at || '').slice(0, 16).replace('T', ' ')) + '</time>';
          btn.addEventListener('click', function () {
            loadSession(s.id);
          });
          list.appendChild(btn);
        });
      })
      .catch(function () {});
  }

  function loadSession(id) {
    state.sessionId = id;
    try {
      localStorage.setItem(STORAGE_SESSION, String(id));
    } catch (e) {}
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
        loadSessions();
      })
      .catch(function () {});
  }

  function newChat() {
    state.sessionId = null;
    try {
      localStorage.removeItem(STORAGE_SESSION);
    } catch (e) {}
    showEmpty();
    loadSessions();
    var ta = el('mova-ai-input');
    if (ta) ta.focus();
  }

  function send() {
    var ta = el('mova-ai-input');
    var btn = el('mova-ai-send');
    if (!ta || state.busy) return;
    var text = (ta.value || '').trim();
    if (!text) return;

    state.busy = true;
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
        appendMessage(
          'assistant',
          data.reply || data.error || 'No response',
          data.actions || [],
          data.provider || null
        );
        loadSessions();
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
        var list = el('mova-ai-sessions');
        if (list) list.hidden = !list.hidden;
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
        setOpen(false);
      }
    });

    var preferOpen = false;
    try {
      preferOpen = localStorage.getItem(STORAGE_OPEN) === '1';
      var sid = localStorage.getItem(STORAGE_SESSION);
      if (sid) state.sessionId = parseInt(sid, 10) || null;
    } catch (e) {}

    setOpen(preferOpen);
    if (state.sessionId) {
      loadSession(state.sessionId);
    } else {
      showEmpty();
      loadSessions();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
