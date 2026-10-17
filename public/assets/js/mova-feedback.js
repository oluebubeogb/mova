/**
 * Mova Feedback — mounts .mova-feedback[data-form]
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

  function fieldHtml(f) {
    var wrap = el('div', 'mova-fb-field');
    if (f.type === 'hidden') {
      var hi = el('input', '', { type: 'hidden', name: f.key, value: f.placeholder || '' });
      wrap.appendChild(hi);
      return wrap;
    }
    var lab = el('label', '');
    lab.innerHTML = (f.label || f.key) + (f.required ? ' <span class="req">*</span>' : '');
    wrap.appendChild(lab);
    var input;
    if (f.type === 'textarea') {
      input = el('textarea', '', { name: f.key, placeholder: f.placeholder || '' });
      if (f.required) input.required = true;
    } else if (f.type === 'select') {
      input = el('select', '', { name: f.key });
      input.appendChild(el('option', '', { value: '', text: '—' }));
      (f.options || []).forEach(function (o) {
        input.appendChild(el('option', '', { value: o, text: o }));
      });
      if (f.required) input.required = true;
    } else if (f.type === 'radio' || f.type === 'checkbox') {
      var box = el('div', 'mova-fb-options');
      (f.options || []).forEach(function (o, i) {
        var id = f.key + '_' + i;
        var row = el('label', '');
        var inp = el('input', '', {
          type: f.type,
          name: f.type === 'checkbox' ? f.key + '[]' : f.key,
          value: o,
          id: id
        });
        if (f.required && i === 0 && f.type === 'radio') inp.required = true;
        row.appendChild(inp);
        row.appendChild(document.createTextNode(' ' + o));
        box.appendChild(row);
      });
      wrap.appendChild(box);
      if (f.help) wrap.appendChild(el('div', 'mova-fb-help', { text: f.help }));
      return wrap;
    } else {
      var type = f.type === 'phone' ? 'tel' : (f.type === 'date' ? 'date' : (f.type === 'email' ? 'email' : 'text'));
      input = el('input', '', { type: type, name: f.key, placeholder: f.placeholder || '' });
      if (f.required) input.required = true;
    }
    wrap.appendChild(input);
    if (f.help) wrap.appendChild(el('div', 'mova-fb-help', { text: f.help }));
    return wrap;
  }

  function mount(node) {
    if (node.dataset.movaMounted) return;
    node.dataset.movaMounted = '1';
    var slug = node.getAttribute('data-form') || 'contact';
    node.innerHTML = '<div class="mova-fb-card"><p class="mova-fb-desc">Loading form…</p></div>';
    fetch('/feedback/api/form/' + encodeURIComponent(slug), { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('Form not found');
        return r.json();
      })
      .then(function (data) {
        var formDef = data.form;
        var card = el('div', 'mova-fb-card');
        card.appendChild(el('h3', 'mova-fb-title', { text: formDef.title || 'Feedback' }));
        if (formDef.description) card.appendChild(el('p', 'mova-fb-desc', { text: formDef.description }));
        var form = el('form', 'mova-fb-form', { novalidate: 'novalidate' });
        (formDef.fields || []).forEach(function (f) { form.appendChild(fieldHtml(f)); });
        // honeypot
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
          btn.disabled = true;
          var fd = new FormData(form);
          var payload = {};
          fd.forEach(function (v, k) {
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
            body: JSON.stringify({
              values: payload,
              page_url: location.href
            })
          }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
              btn.disabled = false;
              if (!res.ok || !res.j.success) {
                msg.className = 'mova-fb-msg err';
                msg.textContent = (res.j && res.j.error) || 'Could not send.';
                return;
              }
              msg.className = 'mova-fb-msg ok';
              msg.textContent = res.j.message || formDef.success_message || 'Thank you.';
              form.reset();
            })
            .catch(function () {
              btn.disabled = false;
              msg.className = 'mova-fb-msg err';
              msg.textContent = 'Network error.';
            });
        });
        card.appendChild(form);
        node.innerHTML = '';
        node.appendChild(card);
      })
      .catch(function (err) {
        node.innerHTML = '<div class="mova-fb-card"><p class="mova-fb-msg err">' + (err.message || 'Unavailable') + '</p></div>';
      });
  }

  function boot() {
    document.querySelectorAll('.mova-feedback[data-form]').forEach(mount);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.MovaFeedback = { boot: boot, mount: mount };
})();
