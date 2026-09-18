<?php
/**
 * Find & Replace — IDE-style bulk text replacement across content
 */
use Mova\Security\Csrf;
$csrfToken = Csrf::token();
?>
<section class="fr-page">
  <header class="fr-header">
    <div class="fr-header-text">
      <p class="hq-landing-kicker"><i class="fa-solid fa-magnifying-glass-arrow-right"></i> Content</p>
      <h1 class="fr-title">Find &amp; Replace</h1>
      <p class="fr-desc">Search and replace text across titles, excerpts, and bodies — like a code editor. Every action is logged and reversible.</p>
    </div>
    <a href="/hq/content-hub" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Content hub</a>
  </header>

  <div class="fr-layout">
    <!-- Left: editor-style panel -->
    <div class="fr-panel fr-editor">
      <div class="fr-find-bar">
        <div class="fr-field-row">
          <label class="fr-label" for="fr-find">Find</label>
          <div class="fr-input-wrap">
            <input type="text" id="fr-find" class="fr-input" placeholder="e.g. goodgod" autocomplete="off" spellcheck="false">
            <button type="button" class="fr-icon-btn" id="fr-match-case" title="Match case" aria-pressed="false">
              <span class="fr-case-label">Aa</span>
            </button>
          </div>
        </div>
        <div class="fr-field-row">
          <label class="fr-label" for="fr-replace">Replace</label>
          <div class="fr-input-wrap">
            <input type="text" id="fr-replace" class="fr-input" placeholder="e.g. Good God" autocomplete="off" spellcheck="false">
          </div>
        </div>
      </div>

      <div class="fr-scope">
        <div class="fr-scope-block">
          <span class="fr-scope-title">Fields</span>
          <label class="fr-check"><input type="checkbox" name="fr-field" value="title" checked> Title</label>
          <label class="fr-check"><input type="checkbox" name="fr-field" value="excerpt" checked> Excerpt</label>
          <label class="fr-check"><input type="checkbox" name="fr-field" value="body" checked> Body</label>
        </div>
        <div class="fr-scope-block">
          <span class="fr-scope-title">Status</span>
          <label class="fr-check"><input type="checkbox" name="fr-status" value="__all__" checked id="fr-status-all"> All statuses</label>
          <?php foreach ($statuses as $key => $label): ?>
            <?php if ($key === 'trash') continue; ?>
            <label class="fr-check fr-status-opt"><input type="checkbox" name="fr-status" value="<?= htmlspecialchars($key) ?>"> <?= htmlspecialchars(is_string($label) ? $label : $key) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="fr-scope-block fr-scope-content">
          <span class="fr-scope-title">Contents</span>
          <div class="fr-content-mode">
            <label class="fr-radio"><input type="radio" name="fr-scope-mode" value="all" checked> All matching</label>
            <label class="fr-radio"><input type="radio" name="fr-scope-mode" value="selected"> Selected only</label>
          </div>
          <div class="fr-content-picker" id="fr-content-picker" hidden>
            <input type="search" id="fr-content-search" class="fr-input fr-input-sm" placeholder="Search content to select…" autocomplete="off">
            <div class="fr-content-list" id="fr-content-list"></div>
            <div class="fr-selected-chips" id="fr-selected-chips"></div>
          </div>
        </div>
      </div>

      <div class="fr-actions">
        <button type="button" class="btn-ghost" id="fr-btn-preview"><i class="fa-solid fa-eye"></i> Preview matches</button>
        <button type="button" class="btn-primary" id="fr-btn-apply" disabled><i class="fa-solid fa-check"></i> Replace all</button>
        <span class="fr-stats" id="fr-stats"></span>
      </div>

      <div class="fr-results" id="fr-results">
        <div class="fr-results-empty" id="fr-results-empty">
          <i class="fa-solid fa-code"></i>
          <p>Enter text to find, then preview. Matches appear here with context snippets — just like a code editor.</p>
        </div>
        <div class="fr-results-list" id="fr-results-list" hidden></div>
      </div>
    </div>

    <!-- Right: change log -->
    <aside class="fr-panel fr-log">
      <div class="fr-log-header">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Change log</h2>
        <span class="fr-log-count"><?= (int) $totalActions ?> action<?= $totalActions === 1 ? '' : 's' ?></span>
      </div>

      <?php if (!empty($actionDetail)): ?>
        <div class="fr-log-detail">
          <a href="/hq/content/find-replace" class="fr-log-back"><i class="fa-solid fa-chevron-left"></i> All actions</a>
          <div class="fr-log-detail-meta">
            <strong><?= htmlspecialchars($actionDetail['find_text'] ?? '') ?></strong>
            <span class="fr-arrow">→</span>
            <strong><?= htmlspecialchars($actionDetail['replace_text'] ?? '') ?></strong>
            <?php if (!empty($actionDetail['match_case'])): ?>
              <span class="fr-badge">Match case</span>
            <?php endif; ?>
            <span class="fr-badge fr-badge-<?= ($actionDetail['status'] ?? '') === 'reverted' ? 'muted' : 'ok' ?>">
              <?= htmlspecialchars($actionDetail['status'] ?? '') ?>
            </span>
          </div>
          <p class="fr-log-meta-line">
            by <?= htmlspecialchars($actionDetail['display_name'] ?: ($actionDetail['username'] ?? 'unknown')) ?>
            · <?= htmlspecialchars($actionDetail['created_at'] ?? '') ?>
            · <?= (int) ($actionDetail['match_count'] ?? 0) ?> occurrence(s)
            in <?= (int) ($actionDetail['content_count'] ?? 0) ?> content item(s)
          </p>
          <?php if (($actionDetail['status'] ?? '') === 'applied'): ?>
            <button type="button" class="btn-danger btn-sm" id="fr-btn-revert" data-action-id="<?= (int) $actionDetail['id'] ?>">
              <i class="fa-solid fa-rotate-left"></i> Revert this action
            </button>
          <?php endif; ?>
          <div class="fr-change-table-wrap">
            <table class="fr-change-table">
              <thead>
                <tr>
                  <th>Content</th>
                  <th>Field</th>
                  <th>Occurrences</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($actionDetail['changes'] ?? []) as $ch): ?>
                  <tr>
                    <td>
                      <?php if (!empty($ch['content_id'])): ?>
                        <a href="/hq/content/edit/<?= (int) $ch['content_id'] ?>">
                          <?= htmlspecialchars($ch['content_title'] ?? ('#' . $ch['content_id'])) ?>
                        </a>
                        <span class="fr-muted"><?= htmlspecialchars($ch['content_status'] ?? '') ?></span>
                      <?php else: ?>
                        <span class="fr-muted">Deleted</span>
                      <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($ch['field_name'] ?? '') ?></code></td>
                    <td><?= (int) ($ch['occurrence_count'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php else: ?>
        <div class="fr-log-list" id="fr-log-list">
          <?php if (empty($actions)): ?>
            <p class="fr-log-empty">No replacements yet. Run a find &amp; replace to see the history here.</p>
          <?php else: ?>
            <?php foreach ($actions as $a): ?>
              <a class="fr-log-item" href="/hq/content/find-replace?action=<?= (int) $a['id'] ?>">
                <div class="fr-log-item-main">
                  <span class="fr-log-find"><?= htmlspecialchars($a['find_text'] ?? '') ?></span>
                  <span class="fr-arrow">→</span>
                  <span class="fr-log-replace"><?= htmlspecialchars($a['replace_text'] ?? '') ?></span>
                </div>
                <div class="fr-log-item-meta">
                  <span><?= htmlspecialchars($a['display_name'] ?: ($a['username'] ?? 'user')) ?></span>
                  <span>·</span>
                  <span><?= (int) ($a['match_count'] ?? 0) ?> hits</span>
                  <span>·</span>
                  <span class="fr-badge fr-badge-<?= ($a['status'] ?? '') === 'reverted' ? 'muted' : 'ok' ?>"><?= htmlspecialchars($a['status'] ?? '') ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="fr-log-pager">
            <?php if ($page > 1): ?>
              <a href="?log_page=<?= $page - 1 ?>" class="btn-ghost btn-sm">Prev</a>
            <?php endif; ?>
            <span>Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a href="?log_page=<?= $page + 1 ?>" class="btn-ghost btn-sm">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
</section>

<style>
.fr-page { max-width: 1280px; margin: 0 auto; padding: 0 1rem 2rem; }
.fr-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.fr-title { font-size: 1.5rem; margin: 0.15rem 0; }
.fr-desc { color: var(--hq-muted, #6b7280); margin: 0; max-width: 42rem; }
.fr-layout { display: grid; grid-template-columns: 1fr minmax(280px, 340px); gap: 1rem; align-items: start; }
@media (max-width: 900px) { .fr-layout { grid-template-columns: 1fr; } }
.fr-panel { background: var(--hq-surface, #fff); border: 1px solid var(--hq-border, #e5e7eb); border-radius: 12px; padding: 1rem 1.1rem; }
.fr-editor { min-height: 420px; }
.fr-find-bar { display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1rem; }
.fr-field-row { display: grid; grid-template-columns: 4.5rem 1fr; align-items: center; gap: 0.5rem; }
.fr-label { font-size: 0.8rem; font-weight: 600; color: var(--hq-muted, #6b7280); }
.fr-input-wrap { display: flex; align-items: stretch; gap: 0.35rem; }
.fr-input {
  flex: 1; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.9rem; padding: 0.5rem 0.65rem; border: 1px solid var(--hq-border, #d1d5db);
  border-radius: 8px; background: var(--hq-input-bg, #fafafa); color: inherit;
}
.fr-input:focus { outline: 2px solid var(--hq-accent, #2563eb); outline-offset: 1px; border-color: transparent; }
.fr-input-sm { font-size: 0.85rem; padding: 0.4rem 0.55rem; }
.fr-icon-btn {
  min-width: 2.25rem; border: 1px solid var(--hq-border, #d1d5db); border-radius: 8px;
  background: var(--hq-surface, #fff); cursor: pointer; font-weight: 700; font-size: 0.8rem;
  color: var(--hq-muted, #6b7280);
}
.fr-icon-btn[aria-pressed="true"] { background: var(--hq-accent, #2563eb); color: #fff; border-color: transparent; }
.fr-scope { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem 1rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--hq-subtle, #f9fafb); border-radius: 8px; }
.fr-scope-title { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; color: var(--hq-muted, #6b7280); margin-bottom: 0.35rem; }
.fr-check, .fr-radio { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; margin: 0.2rem 0; cursor: pointer; }
.fr-content-mode { display: flex; flex-direction: column; gap: 0.15rem; }
.fr-content-picker { margin-top: 0.5rem; }
.fr-content-list { max-height: 160px; overflow: auto; margin-top: 0.4rem; border: 1px solid var(--hq-border, #e5e7eb); border-radius: 8px; background: #fff; }
.fr-content-item { display: flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.5rem; font-size: 0.8rem; border-bottom: 1px solid var(--hq-border, #f3f4f6); cursor: pointer; }
.fr-content-item:hover { background: #f3f4f6; }
.fr-content-item .fr-muted { color: #9ca3af; margin-left: auto; font-size: 0.75rem; }
.fr-selected-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.4rem; }
.fr-chip { display: inline-flex; align-items: center; gap: 0.25rem; background: #e0e7ff; color: #3730a3; font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 999px; }
.fr-chip button { border: 0; background: transparent; cursor: pointer; color: inherit; padding: 0; line-height: 1; }
.fr-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
.fr-stats { font-size: 0.85rem; color: var(--hq-muted, #6b7280); margin-left: auto; }
.fr-results { border: 1px solid var(--hq-border, #e5e7eb); border-radius: 8px; min-height: 180px; background: #0d1117; color: #e6edf3; overflow: hidden; }
.fr-results-empty { text-align: center; padding: 2.5rem 1.5rem; color: #8b949e; }
.fr-results-empty i { font-size: 1.75rem; margin-bottom: 0.5rem; opacity: 0.7; }
.fr-results-list { max-height: 420px; overflow: auto; }
.fr-match-card { border-bottom: 1px solid #21262d; padding: 0.65rem 0.85rem; }
.fr-match-card:last-child { border-bottom: 0; }
.fr-match-head { display: flex; flex-wrap: wrap; gap: 0.4rem 0.75rem; align-items: baseline; margin-bottom: 0.35rem; }
.fr-match-title { font-weight: 600; color: #58a6ff; text-decoration: none; font-size: 0.9rem; }
.fr-match-title:hover { text-decoration: underline; }
.fr-match-meta { font-size: 0.75rem; color: #8b949e; }
.fr-snippet { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8rem; line-height: 1.45; color: #c9d1d9; margin: 0.2rem 0; white-space: pre-wrap; word-break: break-word; }
.fr-snippet mark { background: #9e6a03; color: #fff; padding: 0 0.15em; border-radius: 2px; }
.fr-field-tag { display: inline-block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em; background: #21262d; color: #8b949e; padding: 0.1rem 0.35rem; border-radius: 4px; margin-right: 0.25rem; }

/* Log panel */
.fr-log-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.fr-log-header h2 { font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 0.4rem; }
.fr-log-count { font-size: 0.75rem; color: var(--hq-muted, #6b7280); }
.fr-log-empty { font-size: 0.85rem; color: var(--hq-muted, #6b7280); }
.fr-log-item { display: block; padding: 0.55rem 0.4rem; border-bottom: 1px solid var(--hq-border, #f3f4f6); text-decoration: none; color: inherit; border-radius: 6px; }
.fr-log-item:hover { background: var(--hq-subtle, #f9fafb); }
.fr-log-item-main { font-size: 0.85rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
.fr-log-find { color: #b45309; }
.fr-log-replace { color: #047857; }
.fr-arrow { color: #9ca3af; margin: 0 0.25rem; }
.fr-log-item-meta { font-size: 0.75rem; color: var(--hq-muted, #6b7280); display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.2rem; }
.fr-badge { display: inline-block; font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 999px; background: #e5e7eb; color: #374151; text-transform: uppercase; letter-spacing: 0.03em; }
.fr-badge-ok { background: #d1fae5; color: #065f46; }
.fr-badge-muted { background: #f3f4f6; color: #6b7280; }
.fr-log-back { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8rem; margin-bottom: 0.5rem; text-decoration: none; }
.fr-log-detail-meta { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.9rem; margin-bottom: 0.35rem; }
.fr-log-meta-line { font-size: 0.8rem; color: var(--hq-muted, #6b7280); margin: 0 0 0.75rem; }
.fr-change-table-wrap { overflow: auto; max-height: 360px; margin-top: 0.75rem; }
.fr-change-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.fr-change-table th, .fr-change-table td { text-align: left; padding: 0.35rem 0.4rem; border-bottom: 1px solid var(--hq-border, #e5e7eb); }
.fr-change-table th { font-size: 0.7rem; text-transform: uppercase; color: var(--hq-muted, #6b7280); }
.fr-muted { color: #9ca3af; font-size: 0.75rem; margin-left: 0.25rem; }
.fr-log-pager { display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-top: 0.75rem; font-size: 0.8rem; }
[data-theme="dark"] .fr-panel, .hq-dark .fr-panel { background: #161b22; border-color: #30363d; }
[data-theme="dark"] .fr-input, .hq-dark .fr-input { background: #0d1117; border-color: #30363d; color: #e6edf3; }
[data-theme="dark"] .fr-scope, .hq-dark .fr-scope { background: #0d1117; }
[data-theme="dark"] .fr-content-list, .hq-dark .fr-content-list { background: #0d1117; border-color: #30363d; }
[data-theme="dark"] .fr-content-item:hover, .hq-dark .fr-content-item:hover { background: #21262d; }
</style>

<script>
(function () {
  const CSRF = <?= json_encode($csrfToken) ?>;
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  const findInput = $('#fr-find');
  const replaceInput = $('#fr-replace');
  const matchCaseBtn = $('#fr-match-case');
  const btnPreview = $('#fr-btn-preview');
  const btnApply = $('#fr-btn-apply');
  const statsEl = $('#fr-stats');
  const resultsEmpty = $('#fr-results-empty');
  const resultsList = $('#fr-results-list');
  const contentPicker = $('#fr-content-picker');
  const contentSearch = $('#fr-content-search');
  const contentList = $('#fr-content-list');
  const selectedChips = $('#fr-selected-chips');
  const statusAll = $('#fr-status-all');

  let selectedIds = new Set();
  let lastPreview = null;
  let matchCase = false;

  matchCaseBtn.addEventListener('click', () => {
    matchCase = !matchCase;
    matchCaseBtn.setAttribute('aria-pressed', matchCase ? 'true' : 'false');
  });

  // Status: "all" toggles others
  statusAll.addEventListener('change', () => {
    if (statusAll.checked) {
      $$('.fr-status-opt input').forEach(cb => { cb.checked = false; });
    }
  });
  $$('.fr-status-opt input').forEach(cb => {
    cb.addEventListener('change', () => {
      if (cb.checked) statusAll.checked = false;
      const any = $$('.fr-status-opt input').some(c => c.checked);
      if (!any) statusAll.checked = true;
    });
  });

  // Scope mode
  $$('input[name="fr-scope-mode"]').forEach(r => {
    r.addEventListener('change', () => {
      const selected = $('input[name="fr-scope-mode"]:checked').value === 'selected';
      contentPicker.hidden = !selected;
      if (selected) loadContentList('');
    });
  });

  let searchTimer = null;
  contentSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadContentList(contentSearch.value.trim()), 250);
  });

  function loadContentList(q) {
    fetch('/hq/content/find-replace/search-content?q=' + encodeURIComponent(q), {
      credentials: 'same-origin'
    })
      .then(r => r.json())
      .then(data => {
        contentList.innerHTML = '';
        (data.items || []).forEach(item => {
          const row = document.createElement('label');
          row.className = 'fr-content-item';
          const checked = selectedIds.has(item.id) ? 'checked' : '';
          row.innerHTML = `<input type="checkbox" value="${item.id}" ${checked}> <span>${escapeHtml(item.title)}</span> <span class="fr-muted">${escapeHtml(item.status)}</span>`;
          row.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) selectedIds.add(item.id);
            else selectedIds.delete(item.id);
            renderChips();
          });
          contentList.appendChild(row);
        });
      })
      .catch(() => { contentList.innerHTML = '<p class="fr-muted" style="padding:0.5rem">Could not load content</p>'; });
  }

  function renderChips() {
    selectedChips.innerHTML = '';
    selectedIds.forEach(id => {
      const chip = document.createElement('span');
      chip.className = 'fr-chip';
      chip.innerHTML = `#${id} <button type="button" aria-label="Remove">&times;</button>`;
      chip.querySelector('button').addEventListener('click', () => {
        selectedIds.delete(id);
        renderChips();
        const cb = contentList.querySelector(`input[value="${id}"]`);
        if (cb) cb.checked = false;
      });
      selectedChips.appendChild(chip);
    });
  }

  function gatherOpts() {
    const fields = $$('input[name="fr-field"]:checked').map(c => c.value);
    let statuses = [];
    if (!statusAll.checked) {
      statuses = $$('.fr-status-opt input:checked').map(c => c.value);
    }
    const mode = $('input[name="fr-scope-mode"]:checked').value;
    const opts = {
      find: findInput.value,
      replace: replaceInput.value,
      match_case: matchCase,
      fields: fields,
      statuses: statuses,
      content_ids: mode === 'selected' ? Array.from(selectedIds) : [],
      _csrf: CSRF
    };
    return opts;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function renderPreview(data) {
    lastPreview = data;
    resultsEmpty.hidden = true;
    resultsList.hidden = false;
    resultsList.innerHTML = '';
    const total = data.total_occurrences || 0;
    const contents = data.total_contents || 0;
    statsEl.textContent = total === 0
      ? 'No matches'
      : `${total} occurrence${total === 1 ? '' : 's'} in ${contents} content item${contents === 1 ? '' : 's'}`;
    btnApply.disabled = total === 0;

    if (!data.matches || !data.matches.length) {
      resultsList.innerHTML = '<div class="fr-results-empty"><p>No matches found for the current scope.</p></div>';
      return;
    }

    data.matches.forEach(m => {
      const card = document.createElement('div');
      card.className = 'fr-match-card';
      let body = `<div class="fr-match-head">
        <a class="fr-match-title" href="/hq/content/edit/${m.id}" target="_blank">${escapeHtml(m.title)}</a>
        <span class="fr-match-meta">${escapeHtml(m.status)} · ${m.occurrences} hit${m.occurrences === 1 ? '' : 's'}</span>
      </div>`;
      (m.fields || []).forEach(f => {
        body += `<div><span class="fr-field-tag">${escapeHtml(f.field)}</span>`;
        (f.snippets || []).forEach(sn => {
          body += `<div class="fr-snippet">${escapeHtml(sn.before)}<mark>${escapeHtml(sn.match)}</mark>${escapeHtml(sn.after)}</div>`;
        });
        body += `</div>`;
      });
      card.innerHTML = body;
      resultsList.appendChild(card);
    });
  }

  btnPreview.addEventListener('click', () => {
    const opts = gatherOpts();
    if (!opts.find) {
      findInput.focus();
      return;
    }
    btnPreview.disabled = true;
    statsEl.textContent = 'Searching…';
    fetch('/hq/content/find-replace/preview', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(opts)
    })
      .then(r => r.json())
      .then(data => {
        if (data.error) throw new Error(data.error);
        renderPreview(data);
      })
      .catch(err => {
        statsEl.textContent = err.message || 'Preview failed';
        btnApply.disabled = true;
      })
      .finally(() => { btnPreview.disabled = false; });
  });

  btnApply.addEventListener('click', () => {
    const opts = gatherOpts();
    if (!opts.find) return;
    const total = lastPreview ? lastPreview.total_occurrences : 0;
    if (!confirm(`Replace ${total} occurrence(s) of “${opts.find}” with “${opts.replace}”? This is logged and can be reverted.`)) {
      return;
    }
    btnApply.disabled = true;
    statsEl.textContent = 'Applying…';
    fetch('/hq/content/find-replace/apply', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(opts)
    })
      .then(r => r.json())
      .then(data => {
        if (data.error) throw new Error(data.error);
        statsEl.textContent = `Done — ${data.match_count} replaced in ${data.content_count} item(s)`;
        window.location.href = '/hq/content/find-replace?action=' + data.action_id;
      })
      .catch(err => {
        statsEl.textContent = err.message || 'Replace failed';
        btnApply.disabled = false;
      });
  });

  // Enter in find → preview
  findInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); btnPreview.click(); }
  });
  replaceInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); btnPreview.click(); }
  });

  const revertBtn = $('#fr-btn-revert');
  if (revertBtn) {
    revertBtn.addEventListener('click', () => {
      const id = revertBtn.getAttribute('data-action-id');
      if (!confirm('Revert this find & replace? Content will be restored to values before the replace (where unchanged since).')) return;
      revertBtn.disabled = true;
      fetch('/hq/content/find-replace/revert', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ action_id: parseInt(id, 10), _csrf: CSRF })
      })
        .then(r => r.json())
        .then(data => {
          if (data.error) throw new Error(data.error);
          window.location.href = '/hq/content/find-replace?action=' + id;
        })
        .catch(err => {
          alert(err.message || 'Revert failed');
          revertBtn.disabled = false;
        });
    });
  }

  // JSON body parsing: HQ Request may expect form data — ensure server reads JSON
})();
</script>
