<?php
/**
 * Upload Contents — multi-file Dev Mode import
 * Groups home.html + home.css + home.js into one content item (slug "home").
 */
use Mova\Security\Csrf;

$types = $types ?? [];
$statuses = $statuses ?? [];
$error = $_GET['error'] ?? '';
?>
<style>
.mova-upload-page { max-width: 720px; margin: 0 auto; }
.mova-upload-drop {
  border: 2px dashed var(--hq-border, #cbd5e1);
  border-radius: 14px;
  padding: 2rem 1.5rem;
  text-align: center;
  background: color-mix(in srgb, var(--hq-surface, #fff) 92%, var(--hq-border));
  transition: border-color .15s, background .15s;
  cursor: pointer;
}
.mova-upload-drop.is-dragover {
  border-color: var(--hq-accent, #2563eb);
  background: color-mix(in srgb, var(--hq-accent, #2563eb) 8%, var(--hq-surface, #fff));
}
.mova-upload-drop .icon { font-size: 2rem; color: var(--hq-muted, #64748b); margin-bottom: 0.5rem; }
.mova-upload-drop strong { display: block; margin-bottom: 0.25rem; }
.mova-upload-drop span { font-size: 0.85rem; color: var(--hq-muted, #64748b); }
.mova-upload-hint {
  font-size: 0.8rem;
  color: var(--hq-muted, #64748b);
  margin: 0.75rem 0 1.25rem;
  line-height: 1.45;
}
.mova-upload-groups { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.mova-upload-group {
  border: 1px solid var(--hq-border, #e2e8f0);
  border-radius: 12px;
  background: var(--hq-surface, #fff);
  overflow: hidden;
}
.mova-upload-group-head {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.75rem 1rem;
  cursor: pointer;
  user-select: none;
  background: color-mix(in srgb, var(--hq-surface) 90%, var(--hq-border));
}
.mova-upload-group-head:hover { background: color-mix(in srgb, var(--hq-surface) 80%, var(--hq-border)); }
.mova-upload-chevron {
  transition: transform .15s;
  color: var(--hq-muted);
  font-size: 0.75rem;
  width: 1rem;
  text-align: center;
}
.mova-upload-group.is-open .mova-upload-chevron { transform: rotate(90deg); }
.mova-upload-group-title {
  font-weight: 600;
  flex: 1;
  min-width: 0;
}
.mova-upload-group-title .editable {
  display: inline-block;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  border: 1px solid transparent;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mova-upload-group-title .editable:focus,
.mova-upload-group-title .editable.is-editing {
  border-color: var(--hq-accent, #2563eb);
  outline: none;
  background: var(--hq-input-bg, #fff);
  white-space: normal;
}
.mova-upload-group-meta {
  font-size: 0.75rem;
  color: var(--hq-muted);
  white-space: nowrap;
}
.mova-upload-group-body {
  display: none;
  padding: 0.85rem 1rem 1rem;
  border-top: 1px solid var(--hq-border, #e2e8f0);
}
.mova-upload-group.is-open .mova-upload-group-body { display: block; }
.mova-upload-files {
  list-style: none;
  margin: 0 0 0.85rem;
  padding: 0;
  font-size: 0.85rem;
}
.mova-upload-files li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0;
  color: var(--hq-text);
}
.mova-upload-files .file-badge {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  background: #e0e7ff;
  color: #3730a3;
}
.mova-upload-files .file-badge.is-css { background: #fce7f3; color: #9d174d; }
.mova-upload-files .file-badge.is-js { background: #fef3c7; color: #92400e; }
.mova-upload-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
}
@media (max-width: 560px) { .mova-upload-fields { grid-template-columns: 1fr; } }
.mova-upload-fields .form-group { margin: 0; }
.mova-upload-fields label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--hq-muted);
  margin-bottom: 0.25rem;
}
.mova-upload-fields input,
.mova-upload-fields select {
  width: 100%;
  border: 1px solid var(--hq-border);
  border-radius: var(--hq-radius, 8px);
  background: var(--hq-input-bg, var(--hq-surface));
  color: var(--hq-text);
  padding: 0.45rem 0.65rem;
  font: inherit;
  box-sizing: border-box;
}
.mova-upload-fields input:focus,
.mova-upload-fields select:focus {
  outline: none;
  border-color: var(--hq-accent, #2563eb);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--hq-accent) 25%, transparent);
}
.mova-upload-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
  justify-content: flex-end;
  margin-top: 0.5rem;
}
.mova-upload-actions .btn-primary { font-weight: 700; }
.mova-upload-empty {
  text-align: center;
  color: var(--hq-muted);
  padding: 1.5rem;
  font-size: 0.9rem;
}
.mova-upload-error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 0.65rem 1rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}
.mova-upload-remove {
  appearance: none;
  border: 0;
  background: transparent;
  color: var(--hq-muted);
  cursor: pointer;
  padding: 0.25rem;
  font-size: 0.85rem;
}
.mova-upload-remove:hover { color: #b91c1c; }
</style>

<div class="mova-upload-page">
  <p class="mova-upload-hint">
    Select one or more <code>.html</code> files (and optional matching <code>.css</code> / <code>.js</code>).
    Files named <code>home.html</code>, <code>home.css</code>, <code>home.js</code> are grouped into a single content item with slug <strong>home</strong> and title <strong>Home</strong>.
    Every group must include an HTML file. Double-click the title or slug to edit.
  </p>

  <?php if ($error !== ''): ?>
    <div class="mova-upload-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="mova-upload-drop" id="upload-drop" role="button" tabindex="0" aria-label="Select files">
    <div class="icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
    <strong>Drop files here or click to browse</strong>
    <span>Accepts .html, .css, .js — multi-select supported</span>
    <input type="file" id="upload-input" accept=".html,.css,.js,text/html,text/css,text/javascript,application/javascript" multiple hidden>
  </div>

  <div id="upload-groups" class="mova-upload-groups" hidden></div>
  <div id="upload-empty" class="mova-upload-empty" hidden>No valid groups yet. Add at least one <code>.html</code> file.</div>

  <form method="post" action="/hq/content/upload" id="upload-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="items" id="upload-items-json" value="[]">
    <div class="mova-upload-actions">
      <button type="button" class="btn-ghost" id="upload-clear" hidden>Clear all</button>
      <button type="submit" class="btn-primary" id="upload-continue" disabled>
        <i class="fa-solid fa-check"></i> Continue
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  'use strict';

  const types = <?= json_encode($types) ?>;
  const statuses = <?= json_encode($statuses) ?>;
  const defaultType = Object.keys(types)[0] || 'page';
  const defaultStatus = statuses.draft ? 'draft' : Object.keys(statuses)[0] || 'draft';

  const drop = document.getElementById('upload-drop');
  const input = document.getElementById('upload-input');
  const groupsEl = document.getElementById('upload-groups');
  const emptyEl = document.getElementById('upload-empty');
  const continueBtn = document.getElementById('upload-continue');
  const clearBtn = document.getElementById('upload-clear');
  const itemsJson = document.getElementById('upload-items-json');
  const form = document.getElementById('upload-form');

  /** @type {Map<string, {base:string, title:string, slug:string, type:string, status:string, html?:string, css?:string, js?:string, files:string[]}>} */
  const groups = new Map();

  function baseName(filename) {
    const name = filename.replace(/^.*[\\/]/, '');
    const m = name.match(/^(.+)\.(html?|css|js)$/i);
    return m ? m[1].toLowerCase() : null;
  }

  function extOf(filename) {
    const m = filename.match(/\.(html?|css|js)$/i);
    if (!m) return null;
    const e = m[1].toLowerCase();
    return e === 'htm' ? 'html' : e;
  }

  function titleFromBase(base) {
    if (!base) return 'Untitled';
    return base
      .replace(/[-_]+/g, ' ')
      .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function slugFromBase(base) {
    return (base || 'untitled')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'untitled';
  }

  function readFile(file) {
    return new Promise(function (resolve, reject) {
      const r = new FileReader();
      r.onload = function () { resolve(String(r.result || '')); };
      r.onerror = function () { reject(new Error('Failed to read ' + file.name)); };
      r.readAsText(file);
    });
  }

  async function addFiles(fileList) {
    const files = Array.from(fileList || []);
    for (const file of files) {
      const ext = extOf(file.name);
      const base = baseName(file.name);
      if (!ext || !base) continue;

      let g = groups.get(base);
      if (!g) {
        g = {
          base: base,
          title: titleFromBase(base),
          slug: slugFromBase(base),
          type: defaultType,
          status: defaultStatus,
          files: []
        };
        groups.set(base, g);
      }

      try {
        const text = await readFile(file);
        if (ext === 'html') g.html = text;
        else if (ext === 'css') g.css = text;
        else if (ext === 'js') g.js = text;
        if (g.files.indexOf(file.name) === -1) g.files.push(file.name);
      } catch (e) {
        console.warn(e);
      }
    }
    render();
  }

  function removeGroup(base) {
    groups.delete(base);
    render();
  }

  function validGroups() {
    const out = [];
    groups.forEach(function (g) {
      if (g.html != null && String(g.html).trim() !== '') out.push(g);
    });
    return out;
  }

  function render() {
    const valid = validGroups();
    groupsEl.innerHTML = '';

    if (groups.size === 0) {
      groupsEl.hidden = true;
      emptyEl.hidden = true;
      continueBtn.disabled = true;
      clearBtn.hidden = true;
      return;
    }

    groupsEl.hidden = false;
    emptyEl.hidden = valid.length > 0;
    continueBtn.disabled = valid.length === 0;
    clearBtn.hidden = false;

    groups.forEach(function (g) {
      const hasHtml = g.html != null && String(g.html).trim() !== '';
      const card = document.createElement('div');
      card.className = 'mova-upload-group is-open';
      card.dataset.base = g.base;

      const fileCount = g.files.length;
      const parts = [];
      if (g.html != null) parts.push('HTML');
      if (g.css != null) parts.push('CSS');
      if (g.js != null) parts.push('JS');

      card.innerHTML =
        '<div class="mova-upload-group-head">' +
          '<span class="mova-upload-chevron"><i class="fa-solid fa-chevron-right"></i></span>' +
          '<div class="mova-upload-group-title">' +
            '<span class="editable" data-field="title" title="Double-click to edit">' + escapeHtml(g.title) + '</span>' +
            ' <span style="color:var(--hq-muted);font-weight:400;">/</span> ' +
            '<span class="editable" data-field="slug" title="Double-click to edit" style="font-weight:500;color:var(--hq-muted);">' + escapeHtml(g.slug) + '</span>' +
          '</div>' +
          '<span class="mova-upload-group-meta">' + (hasHtml ? parts.join(' · ') : '<span style="color:#b91c1c">needs .html</span>') +
            ' · ' + fileCount + ' file' + (fileCount !== 1 ? 's' : '') + '</span>' +
          '<button type="button" class="mova-upload-remove" title="Remove" aria-label="Remove group"><i class="fa-solid fa-xmark"></i></button>' +
        '</div>' +
        '<div class="mova-upload-group-body">' +
          '<ul class="mova-upload-files">' + g.files.map(function (fn) {
            const e = extOf(fn) || '';
            const badgeClass = e === 'css' ? 'is-css' : (e === 'js' ? 'is-js' : '');
            return '<li><span class="file-badge ' + badgeClass + '">' + e.toUpperCase() + '</span> ' + escapeHtml(fn) + '</li>';
          }).join('') + '</ul>' +
          '<div class="mova-upload-fields">' +
            '<div class="form-group"><label>Title</label><input type="text" data-field="title" value="' + escapeAttr(g.title) + '"></div>' +
            '<div class="form-group"><label>Slug</label><input type="text" data-field="slug" value="' + escapeAttr(g.slug) + '"></div>' +
            '<div class="form-group"><label>Content type</label><select data-field="type">' +
              Object.keys(types).map(function (k) {
                return '<option value="' + escapeAttr(k) + '"' + (k === g.type ? ' selected' : '') + '>' + escapeHtml(types[k]) + '</option>';
              }).join('') +
            '</select></div>' +
            '<div class="form-group"><label>Status</label><select data-field="status">' +
              Object.keys(statuses).map(function (k) {
                return '<option value="' + escapeAttr(k) + '"' + (k === g.status ? ' selected' : '') + '>' + escapeHtml(statuses[k]) + '</option>';
              }).join('') +
            '</select></div>' +
          '</div>' +
        '</div>';

      card.querySelector('.mova-upload-group-head').addEventListener('click', function (ev) {
        if (ev.target.closest('.mova-upload-remove') || ev.target.closest('.editable.is-editing')) return;
        card.classList.toggle('is-open');
      });

      card.querySelector('.mova-upload-remove').addEventListener('click', function (ev) {
        ev.stopPropagation();
        removeGroup(g.base);
      });

      card.querySelectorAll('.mova-upload-group-title .editable').forEach(function (el) {
        el.addEventListener('dblclick', function (ev) {
          ev.stopPropagation();
          el.contentEditable = 'true';
          el.classList.add('is-editing');
          el.focus();
          try {
            const range = document.createRange();
            range.selectNodeContents(el);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
          } catch (e) {}
        });
        el.addEventListener('blur', function () {
          el.contentEditable = 'false';
          el.classList.remove('is-editing');
          const field = el.getAttribute('data-field');
          let val = el.textContent.trim();
          if (field === 'slug') val = slugFromBase(val);
          if (field === 'title' && !val) val = titleFromBase(g.base);
          g[field] = val;
          const inp = card.querySelector('input[data-field="' + field + '"]');
          if (inp) inp.value = val;
          el.textContent = val;
        });
        el.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter') { ev.preventDefault(); el.blur(); }
        });
      });

      card.querySelectorAll('[data-field]').forEach(function (el) {
        if (el.classList.contains('editable')) return;
        el.addEventListener('change', function () {
          const field = el.getAttribute('data-field');
          let val = el.value;
          if (field === 'slug') val = slugFromBase(val);
          g[field] = val;
          if (field === 'title' || field === 'slug') {
            const span = card.querySelector('.editable[data-field="' + field + '"]');
            if (span) span.textContent = val;
          }
        });
      });

      groupsEl.appendChild(card);
    });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function escapeAttr(s) {
    return escapeHtml(s).replace(/'/g, '&#39;');
  }

  drop.addEventListener('click', function () { input.click(); });
  drop.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); }
  });
  input.addEventListener('change', function () {
    if (input.files && input.files.length) addFiles(input.files);
    input.value = '';
  });

  ['dragenter', 'dragover'].forEach(function (evName) {
    drop.addEventListener(evName, function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      drop.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach(function (evName) {
    drop.addEventListener(evName, function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      drop.classList.remove('is-dragover');
    });
  });
  drop.addEventListener('drop', function (ev) {
    if (ev.dataTransfer && ev.dataTransfer.files) addFiles(ev.dataTransfer.files);
  });

  clearBtn.addEventListener('click', function () {
    groups.clear();
    render();
  });

  form.addEventListener('submit', function (ev) {
    const valid = validGroups();
    if (!valid.length) {
      ev.preventDefault();
      return;
    }
    const payload = valid.map(function (g) {
      return {
        title: g.title,
        slug: g.slug,
        type: g.type,
        status: g.status,
        html: g.html || '',
        css: g.css || '',
        js: g.js || ''
      };
    });
    itemsJson.value = JSON.stringify(payload);
    continueBtn.disabled = true;
    continueBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';
  });
})();
</script>
