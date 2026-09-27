/**
 * Mova Site AI widget
 * — sessions (localStorage + optional server mirror via msa_vid)
 * — working timer, copy / regen / like / dislike
 * — clickable page links, current-page awareness across full reloads
 */
(function () {
  'use strict';

  var root = document.getElementById('mova-site-ai-root');
  if (!root) return;

  var name = root.getAttribute('data-name') || 'Assistant';
  var api = root.getAttribute('data-api') || '/api/site-ai/chat';
  var apiBase = api.replace(/\/chat\/?$/, '');
  var primaryOverride = root.getAttribute('data-primary');
  var accentOverride = root.getAttribute('data-accent');
  var bgLight = root.getAttribute('data-bg-light');
  var bgDark = root.getAttribute('data-bg-dark');

  var LS_SESSIONS = 'msa_sessions';
  var LS_ACTIVE = 'msa_active_session';
  var LS_OPEN = 'msa_panel_open';
  var LS_VID = 'msa_vid';
  var MAX_SESSIONS = 20;
  var MAX_MSGS = 50;

  if (primaryOverride) root.style.setProperty('--msa-primary', primaryOverride);
  if (accentOverride) root.style.setProperty('--msa-accent', accentOverride);

  function applyBg() {
    var dark = false;
    try {
      dark = document.documentElement.getAttribute('data-theme') === 'dark'
        || document.documentElement.classList.contains('dark')
        || document.body.classList.contains('dark')
        || document.documentElement.getAttribute('data-color-scheme') === 'dark'
        || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            && !document.documentElement.getAttribute('data-theme'));
    } catch (e) {}
    if (dark && bgDark) {
      root.style.setProperty('--msa-surface', bgDark);
      root.style.setProperty('--msa-bg', bgDark);
    } else if (!dark && bgLight) {
      root.style.setProperty('--msa-surface', bgLight);
      root.style.setProperty('--msa-bg', bgLight);
    } else if (bgDark) {
      root.style.setProperty('--msa-surface-dark', bgDark);
    }
  }
  applyBg();
  try {
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyBg);
    }
  } catch (e) {}

  function hourGreet() {
    var h = new Date().getHours();
    if (h < 12) return 'Good morning — I\'m ' + name + '.';
    if (h < 18) return 'Hi, I\'m ' + name + '. How can I help?';
    return 'Good evening — ' + name + ' here.';
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function uid() {
    try {
      return crypto.randomUUID();
    } catch (e) {
      return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }
  }

  function getVisitorId() {
    var v = '';
    try { v = localStorage.getItem(LS_VID) || ''; } catch (e) {}
    if (!v) {
      var m = document.cookie.match(/(?:^|; )msa_vid=([^;]*)/);
      if (m) v = decodeURIComponent(m[1]);
    }
    if (!v) {
      v = uid().replace(/-/g, '');
    }
    try { localStorage.setItem(LS_VID, v); } catch (e2) {}
    return v;
  }

  function setVisitorId(v) {
    if (!v) return;
    try { localStorage.setItem(LS_VID, v); } catch (e) {}
  }

  function loadSessions() {
    try {
      var raw = localStorage.getItem(LS_SESSIONS);
      if (!raw) return [];
      var list = JSON.parse(raw);
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function saveSessions(list) {
    try {
      list = (list || []).slice(0, MAX_SESSIONS);
      list.forEach(function (s) {
        if (s.messages && s.messages.length > MAX_MSGS) {
          s.messages = s.messages.slice(-MAX_MSGS);
        }
      });
      localStorage.setItem(LS_SESSIONS, JSON.stringify(list));
    } catch (e) {}
  }

  function getActiveId() {
    try { return localStorage.getItem(LS_ACTIVE) || ''; } catch (e) { return ''; }
  }

  function setActiveId(id) {
    try {
      if (id) localStorage.setItem(LS_ACTIVE, id);
      else localStorage.removeItem(LS_ACTIVE);
    } catch (e) {}
  }

  var state = {
    open: false,
    view: 'chat',
    busy: false,
    activeId: getActiveId(),
    lastUserMessage: '',
    visitorId: getVisitorId(),
    serverSessionId: null
  };

  var fab = document.createElement('button');
  fab.type = 'button';
  fab.className = 'msa-fab';
  fab.setAttribute('aria-label', 'Open ' + name);
  fab.innerHTML = '✦';

  var panel = document.createElement('div');
  panel.className = 'msa-panel';
  panel.innerHTML =
    '<div class="msa-head">' +
      '<strong data-title>' + escapeHtml(name) + '</strong>' +
      '<button type="button" class="msa-icon-btn" data-sessions title="Sessions" aria-label="Sessions">☰</button>' +
      '<button type="button" class="msa-icon-btn" data-new title="New chat" aria-label="New chat">＋</button>' +
      '<button type="button" data-close aria-label="Close">&times;</button>' +
    '</div>' +
    '<div class="msa-sessions" data-sessions-list hidden></div>' +
    '<div class="msa-msgs" data-msgs><div class="msa-greet">' + escapeHtml(hourGreet()) + '</div></div>' +
    '<div class="msa-compose">' +
      '<input type="text" placeholder="Ask anything…" data-input autocomplete="off">' +
      '<button type="button" data-send>Send</button>' +
    '</div>';

  document.body.appendChild(fab);
  document.body.appendChild(panel);

  var msgs = panel.querySelector('[data-msgs]');
  var sessionsEl = panel.querySelector('[data-sessions-list]');
  var input = panel.querySelector('[data-input]');
  var titleEl = panel.querySelector('[data-title]');

  function setOpen(open) {
    state.open = !!open;
    panel.classList.toggle('is-open', state.open);
    try { localStorage.setItem(LS_OPEN, state.open ? '1' : '0'); } catch (e) {}
    if (state.open) {
      setTimeout(function () { input.focus(); }, 80);
    }
  }

  fab.addEventListener('click', function () { setOpen(true); });
  panel.querySelector('[data-close]').addEventListener('click', function () { setOpen(false); });

  function formatElapsed(sec) {
    sec = Math.max(0, Math.floor(sec));
    if (sec < 60) return sec + 's';
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
  }

  function showThinking() {
    removeThinking();
    var div = document.createElement('div');
    div.className = 'msa-bubble bot msa-thinking';
    div.setAttribute('data-thinking', '1');
    div.innerHTML =
      '<div class="msa-thinking-row">' +
        '<span class="msa-dots"><i></i><i></i><i></i></span>' +
        '<span class="msa-thinking-label">Working for <strong data-elapsed>0s</strong></span>' +
      '</div>';
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    var started = Date.now();
    div._timer = setInterval(function () {
      var el = div.querySelector('[data-elapsed]');
      if (el) el.textContent = formatElapsed((Date.now() - started) / 1000);
    }, 1000);
  }

  function removeThinking() {
    var t = msgs.querySelector('[data-thinking]');
    if (t) {
      if (t._timer) clearInterval(t._timer);
      t.remove();
    }
  }

  function linkify(text) {
    var html = escapeHtml(text || '');
    html = html.replace(/\[([^\]]+)\]\((\/[^)\s]+)\)/g, function (_, label, path) {
      if (path.indexOf('/hq') === 0) return escapeHtml('[' + label + '](' + path + ')');
      return '<a class="msa-inline-link" href="' + path + '">' + label + '</a>';
    });
    html = html.replace(/(^|[\s(])(\/[a-zA-Z0-9][a-zA-Z0-9\-\/_]*)/g, function (full, pre, path) {
      if (path.indexOf('/hq') === 0) return full;
      return pre + '<a class="msa-inline-link" href="' + path + '">' + path + '</a>';
    });
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    return html;
  }

  function addBubble(role, text, links, opts) {
    opts = opts || {};
    var g = msgs.querySelector('.msa-greet');
    if (g) g.remove();
    var d = document.createElement('div');
    d.className = 'msa-bubble ' + (role === 'user' ? 'user' : 'bot');
    if (role === 'user') {
      d.textContent = text || '';
    } else {
      d.innerHTML = linkify(text || '');
      if (links && links.length) {
        var wrap = document.createElement('div');
        wrap.className = 'msa-links';
        var seen = {};
        links.forEach(function (l) {
          if (!l || !l.path || seen[l.path]) return;
          if (String(l.path).indexOf('/hq') === 0) return;
          seen[l.path] = true;
          var a = document.createElement('a');
          a.href = l.path;
          a.textContent = l.label || l.path;
          wrap.appendChild(a);
        });
        if (wrap.childNodes.length) d.appendChild(wrap);
      }
      if (!opts.noTools && text) {
        var tools = document.createElement('div');
        tools.className = 'msa-msg-tools';
        tools.innerHTML =
          '<button type="button" data-act="copy" title="Copy">Copy</button>' +
          '<button type="button" data-act="regen" title="Regenerate">↻</button>' +
          '<button type="button" data-act="up" title="Helpful">👍</button>' +
          '<button type="button" data-act="down" title="Not helpful">👎</button>';
        var plain = text;
        tools.addEventListener('click', function (e) {
          var b = e.target.closest('[data-act]');
          if (!b) return;
          var act = b.getAttribute('data-act');
          if (act === 'copy') {
            copyText(plain, b);
          } else if (act === 'regen' && state.lastUserMessage) {
            input.value = state.lastUserMessage;
            send();
          } else if (act === 'up' || act === 'down') {
            tools.querySelectorAll('[data-act="up"],[data-act="down"]').forEach(function (x) {
              x.classList.remove('is-active');
            });
            b.classList.add('is-active');
            sendFeedback(act === 'up' ? 'up' : 'down', plain);
          }
        });
        d.appendChild(tools);
      }
    }
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
    return d;
  }

  function copyText(text, btn) {
    var done = function () {
      if (btn) {
        var prev = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = prev; }, 1200);
      }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        fallbackCopy(text); done();
      });
    } else {
      fallbackCopy(text); done();
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

  function sendFeedback(rating, text) {
    var body = new URLSearchParams();
    body.set('visitor_id', state.visitorId);
    body.set('rating', rating);
    body.set('session_id', state.activeId || String(state.serverSessionId || ''));
    body.set('message_hash', simpleHash(text));
    body.set('page', location.pathname);
    fetch(apiBase + '/feedback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data && data.visitor_id) setVisitorId(data.visitor_id);
    }).catch(function () {});
  }

  function simpleHash(s) {
    var h = 0;
    s = String(s || '');
    for (var i = 0; i < s.length; i++) {
      h = ((h << 5) - h) + s.charCodeAt(i);
      h |= 0;
    }
    return String(h);
  }

  function currentSession() {
    var list = loadSessions();
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === state.activeId) return list[i];
    }
    return null;
  }

  function persistMessage(role, text, links) {
    var list = loadSessions();
    var sess = null;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === state.activeId) {
        sess = list[i];
        break;
      }
    }
    if (!sess) {
      sess = {
        id: state.activeId || uid(),
        title: role === 'user' ? (text || 'New chat').slice(0, 48) : 'New chat',
        updatedAt: new Date().toISOString(),
        messages: [],
        serverId: state.serverSessionId || null
      };
      state.activeId = sess.id;
      setActiveId(sess.id);
      list.unshift(sess);
    }
    sess.messages = sess.messages || [];
    sess.messages.push({
      role: role,
      content: text || '',
      links: links || [],
      at: new Date().toISOString()
    });
    if (role === 'user' && (!sess.title || sess.title === 'New chat')) {
      sess.title = (text || 'Chat').slice(0, 48);
    }
    sess.updatedAt = new Date().toISOString();
    if (state.serverSessionId) sess.serverId = state.serverSessionId;
    list = list.filter(function (s) { return s.id !== sess.id; });
    list.unshift(sess);
    saveSessions(list);
  }

  function renderSessionMessages(sess) {
    msgs.innerHTML = '';
    if (!sess || !sess.messages || !sess.messages.length) {
      msgs.innerHTML = '<div class="msa-greet">' + escapeHtml(hourGreet()) + '</div>';
      return;
    }
    sess.messages.forEach(function (m) {
      addBubble(m.role === 'user' ? 'user' : 'bot', m.content, m.links || [], { noTools: m.role === 'user' });
    });
  }

  function showSessionsView(show) {
    state.view = show ? 'sessions' : 'chat';
    sessionsEl.hidden = !show;
    msgs.hidden = !!show;
    panel.querySelector('.msa-compose').hidden = !!show;
    if (titleEl) {
      titleEl.textContent = show ? 'Sessions' : name;
    }
    if (show) renderSessionsList();
  }

  function renderSessionsList() {
    var list = loadSessions();
    sessionsEl.innerHTML = '';
    if (!list.length) {
      sessionsEl.innerHTML = '<p class="msa-sessions-empty">No saved chats yet.</p>';
      return;
    }
    list.forEach(function (s) {
      var row = document.createElement('div');
      row.className = 'msa-session-row' + (s.id === state.activeId ? ' is-active' : '');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'msa-session-item';
      var when = (s.updatedAt || '').slice(0, 16).replace('T', ' ');
      btn.innerHTML =
        '<span class="msa-session-title">' + escapeHtml(s.title || 'Chat') + '</span>' +
        '<time>' + escapeHtml(when) + '</time>';
      btn.addEventListener('click', function () {
        state.activeId = s.id;
        state.serverSessionId = s.serverId || null;
        setActiveId(s.id);
        showSessionsView(false);
        renderSessionMessages(s);
      });
      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'msa-session-del';
      del.title = 'Delete';
      del.textContent = '×';
      del.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!confirm('Clear this chat?')) return;
        var next = loadSessions().filter(function (x) { return x.id !== s.id; });
        saveSessions(next);
        if (state.activeId === s.id) {
          state.activeId = '';
          state.serverSessionId = null;
          setActiveId('');
          msgs.innerHTML = '<div class="msa-greet">' + escapeHtml(hourGreet()) + '</div>';
        }
        if (s.serverId) {
          var body = new URLSearchParams();
          body.set('visitor_id', state.visitorId);
          fetch(apiBase + '/sessions/' + s.serverId + '/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
          }).catch(function () {});
        }
        renderSessionsList();
      });
      row.appendChild(btn);
      row.appendChild(del);
      sessionsEl.appendChild(row);
    });
  }

  function newChat() {
    state.activeId = uid();
    state.serverSessionId = null;
    state.lastUserMessage = '';
    setActiveId(state.activeId);
    showSessionsView(false);
    msgs.innerHTML = '<div class="msa-greet">' + escapeHtml(hourGreet()) + '</div>';
    input.focus();
  }

  panel.querySelector('[data-sessions]').addEventListener('click', function () {
    showSessionsView(state.view !== 'sessions');
  });
  panel.querySelector('[data-new]').addEventListener('click', newChat);

  function pageTitle() {
    try {
      return (document.title || '').split('|')[0].trim().slice(0, 120);
    } catch (e) {
      return '';
    }
  }

  function send() {
    var text = (input.value || '').trim();
    if (!text || state.busy) return;
    input.value = '';
    state.busy = true;
    state.lastUserMessage = text;
    showSessionsView(false);

    if (!state.activeId) {
      state.activeId = uid();
      setActiveId(state.activeId);
    }

    addBubble('user', text);
    persistMessage('user', text, []);
    showThinking();

    var body = new URLSearchParams();
    body.set('message', text);
    body.set('page', location.pathname);
    body.set('page_title', pageTitle());
    body.set('visitor_id', state.visitorId);
    body.set('client_id', state.activeId);
    if (state.serverSessionId) body.set('session_id', String(state.serverSessionId));

    fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        removeThinking();
        if (data && data.visitor_id) {
          state.visitorId = data.visitor_id;
          setVisitorId(data.visitor_id);
        }
        if (data && data.session_id) {
          state.serverSessionId = data.session_id;
        }
        var reply = (data && (data.reply || data.error)) || 'Sorry, try again.';
        var links = (data && data.links) || [];
        addBubble('bot', reply, links);
        persistMessage('assistant', reply, links);
      })
      .catch(function () {
        removeThinking();
        addBubble('bot', 'Connection issue — please try again.', []);
      })
      .finally(function () {
        removeThinking();
        state.busy = false;
      });
  }

  panel.querySelector('[data-send]').addEventListener('click', send);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      send();
    }
  });

  try {
    if (localStorage.getItem(LS_OPEN) === '1') setOpen(true);
  } catch (e) {}

  var existing = currentSession();
  if (existing) {
    state.serverSessionId = existing.serverId || null;
    renderSessionMessages(existing);
  }

  if (!existing || !existing.messages || !existing.messages.length) {
    fetch(apiBase + '/sessions?visitor_id=' + encodeURIComponent(state.visitorId), {
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.sessions || !data.sessions.length) return;
        if (data.visitor_id) {
          state.visitorId = data.visitor_id;
          setVisitorId(data.visitor_id);
        }
        var local = loadSessions();
        if (local.length) return;
        var first = data.sessions[0];
        fetch(apiBase + '/sessions/' + first.id + '?visitor_id=' + encodeURIComponent(state.visitorId), {
          credentials: 'same-origin'
        })
          .then(function (r2) { return r2.json(); })
          .then(function (d2) {
            if (!d2 || !d2.ok) return;
            var msgsIn = (d2.messages || []).map(function (m) {
              return {
                role: m.role,
                content: m.content,
                links: m.links || [],
                at: m.created_at
              };
            });
            var sess = {
              id: uid(),
              title: (first.title || 'Chat').slice(0, 48),
              updatedAt: first.updated_at || new Date().toISOString(),
              messages: msgsIn,
              serverId: first.id
            };
            saveSessions([sess]);
            state.activeId = sess.id;
            state.serverSessionId = first.id;
            setActiveId(sess.id);
            renderSessionMessages(sess);
          })
          .catch(function () {});
      })
      .catch(function () {});
  }
})();
