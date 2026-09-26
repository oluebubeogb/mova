(function () {
  var root = document.getElementById('mova-site-ai-root');
  if (!root) return;
  var name = root.getAttribute('data-name') || 'Assistant';
  var api = root.getAttribute('data-api') || '/api/site-ai/chat';
  var primary = root.getAttribute('data-primary');
  var accent = root.getAttribute('data-accent');
  if (primary) root.style.setProperty('--msa-primary', primary);
  if (accent) root.style.setProperty('--msa-accent', accent);

  function hourGreet() {
    var h = new Date().getHours();
    if (h < 12) return 'Good morning — I\'m ' + name + '.';
    if (h < 18) return 'Hi, I\'m ' + name + '. How can I help?';
    return 'Good evening — ' + name + ' here.';
  }

  var fab = document.createElement('button');
  fab.type = 'button';
  fab.className = 'msa-fab';
  fab.setAttribute('aria-label', 'Open ' + name);
  fab.innerHTML = '✦';
  var panel = document.createElement('div');
  panel.className = 'msa-panel';
  panel.innerHTML =
    '<div class="msa-head"><strong>' + name + '</strong><button type="button" data-close>&times;</button></div>' +
    '<div class="msa-msgs" data-msgs><div class="msa-greet">' + hourGreet() + '</div></div>' +
    '<div class="msa-compose"><input type="text" placeholder="Ask anything…" data-input><button type="button" data-send>Send</button></div>';
  document.body.appendChild(fab);
  document.body.appendChild(panel);

  var msgs = panel.querySelector('[data-msgs]');
  var input = panel.querySelector('[data-input]');
  function open() { panel.classList.add('is-open'); input.focus(); }
  function close() { panel.classList.remove('is-open'); }
  fab.addEventListener('click', open);
  panel.querySelector('[data-close]').addEventListener('click', close);

  function add(role, text, links) {
    var g = msgs.querySelector('.msa-greet');
    if (g) g.remove();
    var d = document.createElement('div');
    d.className = 'msa-bubble ' + (role === 'user' ? 'user' : 'bot');
    d.textContent = text || '';
    if (links && links.length) {
      var wrap = document.createElement('div');
      wrap.className = 'msa-links';
      links.forEach(function (l) {
        var a = document.createElement('a');
        a.href = l.path;
        a.textContent = l.label || l.path;
        wrap.appendChild(a);
      });
      d.appendChild(wrap);
    }
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function send() {
    var text = (input.value || '').trim();
    if (!text) return;
    input.value = '';
    add('user', text);
    var body = new URLSearchParams();
    body.set('message', text);
    body.set('page', location.pathname);
    fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        add('bot', data.reply || 'Sorry, try again.', data.links || []);
      })
      .catch(function () {
        add('bot', 'Connection issue — please try again.');
      });
  }
  panel.querySelector('[data-send]').addEventListener('click', send);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); send(); }
  });
})();
