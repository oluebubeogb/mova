<?php
$variables = $variables ?? [];
?>
<p class="design-intro">
    Mark variables to build an export list. Copy or download as plain text for reuse in CSS, docs, or another site.
</p>

<div class="var-export-layout">
    <aside class="var-export-sidebar" aria-label="Variable list">
        <div class="var-export-sidebar-head">
            <strong>All variables</strong>
            <label class="var-export-multimark">
                <input type="checkbox" id="var-mark-all" title="Mark all">
                <span>Mark all</span>
            </label>
        </div>
        <div class="var-export-list" id="var-export-list">
            <?php foreach ($variables as $i => $row):
                $name = (string) ($row['name'] ?? '');
                $value = (string) ($row['value'] ?? '');
                $desc = (string) ($row['description'] ?? '');
                $src = (string) ($row['source'] ?? '');
                $isCustom = $src === 'custom';
                $cssName = class_exists(\Mova\Theme\VariableService::class)
                    ? \Mova\Theme\VariableService::toCssName($name)
                    : ('--' . $name);
                $line = $name . ' = ' . $value;
                ?>
                <label class="var-export-item">
                    <input type="checkbox" class="var-mark" value="<?= (int) $i ?>"
                           data-name="<?= htmlspecialchars($name) ?>"
                           data-value="<?= htmlspecialchars($value) ?>"
                           data-css="<?= htmlspecialchars($cssName) ?>"
                           data-line="<?= htmlspecialchars($line) ?>"
                           data-desc="<?= htmlspecialchars($desc) ?>">
                    <span class="var-export-item-body">
                        <span class="var-export-item-name"><code><?= htmlspecialchars($name) ?></code></span>
                        <span class="var-export-item-meta">
                            <?php if ($isCustom): ?>
                                <span class="var-badge var-badge-custom">custom</span>
                            <?php else: ?>
                                <span class="var-badge">system</span>
                            <?php endif; ?>
                            <span class="var-export-item-val" title="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars(mb_strlen($value) > 36 ? mb_substr($value, 0, 36) . '…' : $value) ?></span>
                        </span>
                        <?php if ($desc !== ''): ?>
                            <span class="var-export-item-desc"><?= htmlspecialchars($desc) ?></span>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
            <?php if (!$variables): ?>
                <p class="empty" style="padding:0.75rem;">No variables to export.</p>
            <?php endif; ?>
        </div>
    </aside>

    <section class="var-export-main">
        <div class="var-export-main-head">
            <strong>Selected</strong>
            <span class="var-export-count" id="var-export-count">0 marked</span>
        </div>
        <div class="var-export-inbox-wrap">
            <textarea id="var-export-inbox" class="var-export-inbox" readonly placeholder="Marked variables appear here…" rows="8"></textarea>
        </div>
        <div class="var-export-actions">
            <button type="button" class="btn-primary" id="var-export-copy" disabled>
                <i class="fa-solid fa-copy"></i> Copy
            </button>
            <button type="button" class="btn-ghost" id="var-export-download" disabled>
                <i class="fa-solid fa-download"></i> Export .txt
            </button>
            <button type="button" class="btn-ghost" id="var-export-clear" disabled>
                Clear marks
            </button>
        </div>
        <p class="field-hint" style="margin-top:0.75rem;">
            Format: <code>name = value</code> per line. CSS name is available as a comment above each entry when exporting.
        </p>
    </section>
</div>

<style>
.var-export-layout {
    display: grid;
    grid-template-columns: minmax(240px, 340px) 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) {
    .var-export-layout { grid-template-columns: 1fr; }
}
.var-export-sidebar {
    border: 1px solid var(--hq-border, #e2e8f0);
    border-radius: 12px;
    background: var(--hq-surface, #fff);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}
.var-export-sidebar-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--hq-border, #e2e8f0);
    font-size: 0.9rem;
}
.var-export-multimark {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: var(--hq-muted);
    cursor: pointer;
    font-weight: 500;
}
.var-export-list {
    overflow: auto;
    padding: 0.35rem;
    flex: 1;
}
.var-export-item {
    display: flex;
    gap: 0.55rem;
    align-items: flex-start;
    padding: 0.55rem 0.65rem;
    border-radius: 8px;
    cursor: pointer;
    margin-bottom: 0.15rem;
}
.var-export-item:hover { background: var(--hq-bg, #f8fafc); }
.var-export-item:has(input:checked) {
    background: color-mix(in srgb, var(--hq-accent, #2563eb) 10%, transparent);
}
.var-export-item input { margin-top: 0.2rem; flex-shrink: 0; }
.var-export-item-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}
.var-export-item-name code {
    font-size: 0.85rem;
    font-weight: 600;
}
.var-export-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
    font-size: 0.75rem;
    color: var(--hq-muted);
}
.var-export-item-val {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}
.var-export-item-desc {
    font-size: 0.72rem;
    color: var(--hq-muted);
    opacity: 0.9;
}
.var-badge {
    font-size: 0.65rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
}
.var-badge-custom {
    background: #dbeafe;
    color: #1e40af;
}
.var-export-main {
    border: 1px solid var(--hq-border, #e2e8f0);
    border-radius: 12px;
    background: var(--hq-surface, #fff);
    padding: 1rem 1.15rem 1.15rem;
    min-width: 0;
}
.var-export-main-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
    font-size: 0.9rem;
}
.var-export-count { color: var(--hq-muted); font-size: 0.85rem; }
.var-export-inbox-wrap {
    padding: 0;
}
.var-export-inbox {
    width: 100%;
    max-height: 80vh;
    min-height: 12rem;
    height: auto;
    box-sizing: border-box;
    padding: 0.85rem 1rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82rem;
    line-height: 1.5;
    border: 1px solid var(--hq-border, #e2e8f0);
    border-radius: 10px;
    background: var(--hq-bg, #f8fafc);
    color: var(--hq-text);
    resize: vertical;
    field-sizing: content;
}
.var-export-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}
</style>

<script>
(function () {
  var list = document.getElementById('var-export-list');
  var inbox = document.getElementById('var-export-inbox');
  var countEl = document.getElementById('var-export-count');
  var markAll = document.getElementById('var-mark-all');
  var btnCopy = document.getElementById('var-export-copy');
  var btnDl = document.getElementById('var-export-download');
  var btnClear = document.getElementById('var-export-clear');
  if (!list || !inbox) return;

  function marks() {
    return Array.prototype.slice.call(list.querySelectorAll('.var-mark:checked'));
  }

  function buildText(withCssComment) {
    return marks().map(function (cb) {
      var line = cb.getAttribute('data-line') || '';
      var css = cb.getAttribute('data-css') || '';
      if (withCssComment && css) {
        return '# ' + css + '\n' + line;
      }
      return line;
    }).join('\n\n');
  }

  function refresh() {
    var selected = marks();
    var n = selected.length;
    var text = buildText(true);
    inbox.value = text;
    // Grow toward 80vh based on content
    inbox.style.height = 'auto';
    var maxPx = Math.floor(window.innerHeight * 0.8);
    var next = Math.min(Math.max(inbox.scrollHeight + 4, 160), maxPx);
    inbox.style.height = next + 'px';
    if (countEl) countEl.textContent = n + ' marked';
    if (btnCopy) btnCopy.disabled = n === 0;
    if (btnDl) btnDl.disabled = n === 0;
    if (btnClear) btnClear.disabled = n === 0;
    if (markAll) {
      var all = list.querySelectorAll('.var-mark');
      markAll.checked = all.length > 0 && selected.length === all.length;
      markAll.indeterminate = selected.length > 0 && selected.length < all.length;
    }
  }

  list.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('var-mark')) refresh();
  });

  if (markAll) {
    markAll.addEventListener('change', function () {
      var on = markAll.checked;
      list.querySelectorAll('.var-mark').forEach(function (cb) { cb.checked = on; });
      refresh();
    });
  }

  if (btnClear) {
    btnClear.addEventListener('click', function () {
      list.querySelectorAll('.var-mark').forEach(function (cb) { cb.checked = false; });
      if (markAll) { markAll.checked = false; markAll.indeterminate = false; }
      refresh();
    });
  }

  if (btnCopy) {
    btnCopy.addEventListener('click', function () {
      var text = inbox.value;
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          var prev = btnCopy.innerHTML;
          btnCopy.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
          setTimeout(function () { btnCopy.innerHTML = prev; }, 1500);
        });
      } else {
        inbox.select();
        document.execCommand('copy');
      }
    });
  }

  if (btnDl) {
    btnDl.addEventListener('click', function () {
      var text = inbox.value;
      if (!text) return;
      var blob = new Blob([text + '\n'], { type: 'text/plain;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'mova-variables.txt';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    });
  }

  refresh();
})();
</script>
