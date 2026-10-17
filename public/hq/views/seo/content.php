<?php
use Mova\Security\Csrf;
$rows = $rows ?? [];
$q = $q ?? '';
?>
<style>

.seo-page { max-width: 100%; }
.seo-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 0.65rem;
  align-items: center;
  margin-bottom: 1rem;
  padding: 0.65rem 0.85rem;
  background: var(--hq-surface, #fff);
  border: 1px solid var(--hq-border, #e2e8f0);
  border-radius: 10px;
}
.seo-toolbar .input {
  max-width: 220px;
  border: 1px solid var(--hq-border, #d1d5db);
  border-radius: 6px;
  padding: 0.4rem 0.55rem;
  background: var(--hq-input-bg, #f8fafc);
  font: inherit;
}
.seo-toolbar .btn, .seo-toolbar label.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.7rem;
  border: 1px solid var(--hq-border, #d1d5db);
  border-radius: 6px;
  background: var(--hq-input-bg, #f8fafc);
  color: var(--hq-text, #0f172a);
  font-size: 0.875rem;
  cursor: pointer;
  text-decoration: none;
}
.seo-toolbar .btn:hover, .seo-toolbar label.btn:hover {
  background: #eef2f7;
}
.seo-toolbar label.mark-all {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.875rem;
  margin: 0;
  color: var(--hq-muted, #64748b);
}
.seo-banner { border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.75rem; font-size: 0.9rem; }
.seo-banner.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.seo-banner.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.seo-banner ul { margin: 0.35rem 0 0; padding-left: 1.2rem; }
.seo-table-wrap {
  overflow: auto;
  border: 1px solid var(--hq-border, #e5e7eb);
  border-radius: 10px;
  background: #fff;
}
.seo-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; table-layout: fixed; }
.seo-table th, .seo-table td {
  padding: 0.5rem 0.55rem;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
  vertical-align: middle;
}
.seo-table th { background: #f8fafc; font-weight: 600; white-space: nowrap; }
.seo-table tr:hover td { background: #fafbfc; }
.seo-table col.col-mark { width: 2.4rem; }
.seo-table col.col-id { width: 3.5rem; }
.seo-table col.col-thumb { width: 3.6rem; }
.seo-table col.col-status { width: 5.5rem; }
.seo-table col.col-fields { width: 28%; }
.seo-table col.col-edit { width: auto; }
.seo-thumb {
  width: 44px; height: 44px; object-fit: cover; border-radius: 6px;
  background: #f1f5f9; display: block;
}
.seo-links { padding-right: 0.25rem !important; }
.seo-links a {
  margin-right: 0.5rem;
  color: var(--hq-accent, #0f766e);
  text-decoration: none;
  font-size: 0.85rem;
}
.seo-links a:hover { text-decoration: underline; }
.seo-links a.is-active { font-weight: 600; text-decoration: underline; }
.seo-edit { padding-left: 0.15rem !important; }
.seo-edit input,
.seo-edit textarea {
  width: 100%;
  font: inherit;
  padding: 0.4rem 0.5rem;
  border: 1px solid var(--hq-border, #d1d5db);
  border-radius: 6px;
  background: var(--hq-input-bg, #f8fafc);
  color: inherit;
  box-shadow: none;
  outline: none;
}
.seo-edit input:focus,
.seo-edit textarea:focus {
  border-color: var(--hq-border, #cbd5e1);
  box-shadow: none;
  outline: none;
}
.seo-edit textarea { min-height: 64px; resize: vertical; }
.seo-hint { font-size: 0.75rem; color: #64748b; min-height: 1em; margin-top: 0.25rem; }
.seo-id { font-variant-numeric: tabular-nums; color: #475569; }
.seo-muted { color: #94a3b8; font-size: 0.8rem; }
.seo-status {
  font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 4px;
  background: #f1f5f9; color: #475569;
}
</style>

<div class="seo-page">
  <div class="page-header" style="margin-bottom:1rem;">
    <h1>Content SEO</h1>
    <p class="text-muted" style="margin:0;">Edit title, SEO title, meta description, excerpt, and slug. Export only marked rows. Empty CSV cells leave values unchanged.</p>
  </div>

  <div id="seo-import-results"></div>

  <div class="seo-toolbar">
    <form method="get" action="/hq/seo/content" style="display:flex;gap:.4rem;align-items:center;">
      <input class="input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Filter…">
      <button class="btn btn-sm" type="submit">Filter</button>
    </form>
    <label class="mark-all"><input type="checkbox" id="seo-mark-all"> Mark all</label>
    <button type="button" class="btn btn-sm" id="seo-export">Export marked (CSV)</button>
    <label class="btn btn-sm" style="cursor:pointer;margin:0;">
      Import CSV
      <input type="file" id="seo-import" accept=".csv,text/csv" hidden>
    </label>
    <span class="seo-muted" id="seo-mark-count">0 marked</span>
  </div>

  <div class="seo-table-wrap">
    <table class="seo-table" id="seo-table">
      <colgroup>
        <col class="col-mark"><col class="col-id"><col class="col-status"><col class="col-fields"><col class="col-edit">
      </colgroup>
      <thead>
        <tr>
          <th style="width:2.5rem;"></th>
          <th>ID</th>
          <th>Status</th>
          <th>Fields</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>"
            data-title="<?= htmlspecialchars($r['title'], ENT_QUOTES) ?>"
            data-slug="<?= htmlspecialchars($r['slug'], ENT_QUOTES) ?>"
            data-seo_title="<?= htmlspecialchars($r['seo_title'], ENT_QUOTES) ?>"
            data-meta_description="<?= htmlspecialchars($r['meta_description'], ENT_QUOTES) ?>"
            data-excerpt="<?= htmlspecialchars($r['excerpt'], ENT_QUOTES) ?>">
          <td><input type="checkbox" class="seo-mark" value="<?= (int) $r['id'] ?>"></td>
          <td class="seo-id"><?= (int) $r['id'] ?></td>
          <td><span class="seo-status"><?= htmlspecialchars($r['status']) ?></span></td>
          <td class="seo-links">
            <a href="#" data-field="title">Title</a>
            <a href="#" data-field="seo_title">SEO title</a>
            <a href="#" data-field="meta_description">Meta desc</a>
            <a href="#" data-field="excerpt">Excerpt</a>
            <a href="#" data-field="slug">Slug</a>
            <div class="seo-muted" style="margin-top:.2rem;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($r['title'] !== '' ? $r['title'] : '(no title)') ?>
              <?php if ($r['slug']): ?> · /<?= htmlspecialchars($r['slug']) ?><?php endif; ?>
            </div>
          </td>
          <td class="seo-edit">
            <div class="seo-edit-slot" data-id="<?= (int) $r['id'] ?>"></div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="seo-muted">No content found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= Csrf::field() ?>
<script>
(function () {
  var csrf = document.querySelector('input[name="_mova_csrf"]');
  var csrfToken = csrf ? csrf.value : '';
  var timers = {};
  var longFields = { meta_description: 1, excerpt: 1 };

  function markCount() {
    var n = document.querySelectorAll('.seo-mark:checked').length;
    var el = document.getElementById('seo-mark-count');
    if (el) el.textContent = n + ' marked';
  }

  document.getElementById('seo-mark-all').addEventListener('change', function () {
    document.querySelectorAll('.seo-mark').forEach(function (cb) { cb.checked = this.checked; }.bind(this));
    markCount();
  });
  document.querySelectorAll('.seo-mark').forEach(function (cb) {
    cb.addEventListener('change', markCount);
  });

  function saveField(id, field, value, hintEl) {
    if (hintEl) hintEl.textContent = 'Saving…';
    var fd = new FormData();
    fd.append('_mova_csrf', csrfToken);
    fd.append(field, value);
    fetch('/hq/seo/content/' + id, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          if (hintEl) hintEl.textContent = data.error || 'Error';
          return;
        }
        var tr = document.querySelector('tr[data-id="' + id + '"]');
        if (tr && data.content) {
          ['title','slug','seo_title','meta_description','excerpt'].forEach(function (k) {
            if (data.content[k] !== undefined) tr.setAttribute('data-' + k, data.content[k] || '');
          });
          var muted = tr.querySelector('.seo-muted');
          if (muted) {
            muted.textContent = (data.content.title || '(no title)') +
              (data.content.slug ? ' · /' + data.content.slug : '');
          }
        }
        if (hintEl) hintEl.textContent = 'Saved';
        setTimeout(function () { if (hintEl && hintEl.textContent === 'Saved') hintEl.textContent = ''; }, 1500);
      })
      .catch(function () { if (hintEl) hintEl.textContent = 'Error'; });
  }

  document.getElementById('seo-table').addEventListener('click', function (e) {
    var a = e.target.closest('a[data-field]');
    if (!a) return;
    e.preventDefault();
    var tr = a.closest('tr');
    if (!tr) return;
    var id = tr.getAttribute('data-id');
    var field = a.getAttribute('data-field');
    var slot = tr.querySelector('.seo-edit-slot');
    tr.querySelectorAll('.seo-links a').forEach(function (x) { x.classList.remove('is-active'); });
    a.classList.add('is-active');
    var val = tr.getAttribute('data-' + field) || '';
    if (longFields[field]) {
      slot.innerHTML = '<textarea data-field="' + field + '"></textarea><div class="seo-hint"></div>';
      slot.querySelector('textarea').value = val;
    } else {
      slot.innerHTML = '<input type="text" data-field="' + field + '"><div class="seo-hint"></div>';
      slot.querySelector('input').value = val;
    }
    var input = slot.querySelector('input, textarea');
    var hint = slot.querySelector('.seo-hint');
    input.focus();
    input.addEventListener('input', function () {
      clearTimeout(timers[id + field]);
      timers[id + field] = setTimeout(function () {
        saveField(id, field, input.value, hint);
      }, 450);
    });
  });

  document.getElementById('seo-export').addEventListener('click', function () {
    var ids = [];
    document.querySelectorAll('.seo-mark:checked').forEach(function (cb) { ids.push(cb.value); });
    if (!ids.length) { alert('Mark at least one row to export.'); return; }
    var fd = new FormData();
    fd.append('_mova_csrf', csrfToken);
    fd.append('ids', ids.join(','));
    fetch('/hq/seo/content/export', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) return r.text().then(function (t) { throw new Error(t || 'Export failed'); });
        return r.blob();
      })
      .then(function (blob) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'content-seo-export.csv';
        a.click();
        URL.revokeObjectURL(a.href);
      })
      .catch(function (err) { alert(err.message || 'Export failed'); });
  });

  document.getElementById('seo-import').addEventListener('change', function () {
    var file = this.files && this.files[0];
    this.value = '';
    if (!file) return;
    var fd = new FormData();
    fd.append('_mova_csrf', csrfToken);
    fd.append('file', file);
    fetch('/hq/seo/content/import', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var box = document.getElementById('seo-import-results');
        if (!data.success) {
          box.innerHTML = '<div class="seo-banner err"><strong>Import failed</strong> — ' + (data.error || '') + '</div>';
          return;
        }
        var ok = data.imported || [];
        var fail = data.failed || [];
        var html = '';
        if (ok.length) {
          html += '<div class="seo-banner ok"><strong>Imported (' + ok.length + ')</strong><ul>';
          ok.forEach(function (x) {
            html += '<li>Line ' + x.line + ' · id ' + x.id + (x.slug ? ' · /' + x.slug : '') + '</li>';
          });
          html += '</ul></div>';
        }
        if (fail.length) {
          html += '<div class="seo-banner err"><strong>Not imported (' + fail.length + ')</strong><ul>';
          fail.forEach(function (x) {
            html += '<li>Line ' + x.line + ' — ' + (x.reason || '') + '</li>';
          });
          html += '</ul></div>';
        }
        if (!ok.length && !fail.length) html = '<div class="seo-banner err">No rows processed.</div>';
        box.innerHTML = html;
        if (ok.length) setTimeout(function () { location.reload(); }, 1200);
      })
      .catch(function (err) {
        document.getElementById('seo-import-results').innerHTML =
          '<div class="seo-banner err">' + (err.message || 'Import error') + '</div>';
      });
  });
})();
</script>
