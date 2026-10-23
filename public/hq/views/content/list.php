<?php
use Mova\Security\Csrf;
$page = $page ?? 1;
$perPage = $perPage ?? 25;
$total = $total ?? count($items);
$totalPages = $totalPages ?? 1;
$allowedPerPage = $allowedPerPage ?? [10, 25, 50, 100];
$isTrash = ($status ?? '') === 'trash';

$canEdit = $canEdit ?? true;
$q = (string) ($q ?? '');
$types = is_array($types ?? null) ? $types : [];
$statusList = [];
foreach ((is_array($statuses ?? null) ? $statuses : []) as $k => $v) {
    $slug = is_string($k) ? $k : (string) $v;
    $statusList[$slug] = is_string($v) ? ucfirst($v) : ucfirst($slug);
}
if (!$statusList) {
    $statusList = ['published' => 'Published', 'draft' => 'Draft', 'scheduled' => 'Scheduled'];
}
$statusList['trash'] = 'Trash';

$queryBase = array_filter([
    'status' => $status ?? null,
    'type' => $type ?? null,
    'q' => $q !== '' ? $q : null,
    'per_page' => $perPage,
]);
$buildUrl = static function (array $extra = []) use ($queryBase): string {
    $q = array_merge($queryBase, $extra);
    return '/hq/content?' . http_build_query(array_filter($q, static fn($v) => $v !== null && $v !== ''));
};
?>
<header class="hq-page-head">
    <div>
        <h1><?= $isTrash ? 'Trash' : 'All content' ?></h1>
        <p class="hq-page-sub"><?= (int) $total ?> item<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . htmlspecialchars($q) . '”' : '' ?><?= !$canEdit ? ' · read-only view of published content' : '' ?></p>
    </div>
    <?php if ($canEdit): ?>
    <div class="hq-page-actions">
        <a class="btn btn-secondary btn-sm" href="/hq/content/upload" data-tip="Import pages from HTML or Markdown files"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Import</a>
        <a class="btn btn-primary" href="/hq/content/new"><i class="fa-solid fa-plus" aria-hidden="true"></i> New content</a>
    </div>
    <?php endif; ?>
</header>

<form method="get" action="/hq/content" class="cl-filters" role="search" aria-label="Filter content">
    <div class="cl-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search titles and text…" aria-label="Search content">
    </div>
    <?php if ($types): ?>
    <select name="type" aria-label="Filter by type" onchange="this.form.submit()">
        <option value="">All types</option>
        <?php foreach ($types as $slug => $name): ?>
            <option value="<?= htmlspecialchars((string) $slug) ?>" <?= ($type ?? '') === (string) $slug ? 'selected' : '' ?>><?= htmlspecialchars((string) $name) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
    <?php if ($canEdit && !empty($status)): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
    <?php if ($q !== '' || !empty($type) || ($canEdit && !empty($status))): ?>
        <a class="btn btn-ghost btn-sm" href="/hq/content">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($items)): ?>
    <div class="hq-empty-state">
        <div class="hq-empty-icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></div>
        <h2><?= $q !== '' ? 'Nothing matches that search' : ($isTrash ? 'Trash is empty' : 'No content yet') ?></h2>
        <p><?= $q !== '' ? 'Try a different word or clear the filters.' : ($canEdit && !$isTrash ? 'Create your first page — you can start from a blank page or a section.' : '') ?></p>
        <?php if ($canEdit && !$isTrash && $q === ''): ?><a class="btn btn-primary" href="/hq/content/new"><i class="fa-solid fa-plus" aria-hidden="true"></i> New content</a><?php endif; ?>
    </div>
<?php else: ?>
<form method="post" action="/hq/content/bulk" id="content-bulk-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="return_status" value="<?= htmlspecialchars($status ?? '') ?>">
    <input type="hidden" name="bulk_action" id="bulk_action" value="">

    <div class="content-list-card">
    <table class="content-list-table">
        <thead>
            <tr>
                <?php if ($canEdit): ?><th class="col-check"><span class="sr-only">Select</span></th><?php endif; ?>
                <th class="col-title">Title</th>
                <th class="col-type">Type</th>
                <th class="col-status">Status</th>
                <th class="col-updated">Updated</th>
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="content-row">
                    <?php if ($canEdit): ?>
                    <td class="col-check">
                        <input type="checkbox" name="ids[]" value="<?= (int)$item['id'] ?>" class="content-row-check" aria-label="Select <?= htmlspecialchars($item['title']) ?>">
                    </td>
                    <?php endif; ?>
                    <td class="col-title">
                        <?php if ($canEdit && !$isTrash): ?>
                            <a class="content-title-link" href="/hq/content/edit/<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                        <?php elseif ($item['status'] === 'published'): ?>
                            <a class="content-title-link" href="/<?= htmlspecialchars($item['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($item['title']) ?></a>
                        <?php else: ?>
                            <span class="content-title-link"><?= htmlspecialchars($item['title']) ?></span>
                        <?php endif; ?>
                        <span class="cl-slug">/<?= htmlspecialchars($item['slug']) ?></span>
                    </td>
                    <td class="col-type"><span class="type-pill"><?= htmlspecialchars(ucfirst($item['type'])) ?></span></td>
                    <td class="col-status"><span class="badge badge-<?= htmlspecialchars($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                    <td class="col-updated"><?= date('M j, Y', strtotime($item['updated_at'])) ?></td>
                    <td class="col-actions">
                        <div class="cl-row-actions">
                        <?php if ($canEdit && !$isTrash): ?>
                            <a class="row-action" href="/hq/content/edit/<?= (int)$item['id'] ?>" data-tip="Edit" aria-label="Edit <?= htmlspecialchars($item['title']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                            <a class="row-action cl-act-studio" href="/hq/studio/edit/<?= (int)$item['id'] ?>" data-tip="Open in Studio" aria-label="Open <?= htmlspecialchars($item['title']) ?> in Studio"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if ($item['status'] === 'published'): ?>
                            <a class="row-action" href="/<?= htmlspecialchars($item['slug']) ?>" target="_blank" rel="noopener" data-tip="View live" aria-label="View <?= htmlspecialchars($item['title']) ?> on the site"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if ($canEdit && !$isTrash): ?>
                            <button type="submit" form="trash-<?= (int)$item['id'] ?>" class="row-action danger" data-tip="Move to trash" aria-label="Move <?= htmlspecialchars($item['title']) ?> to trash"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        <?php endif; ?>
                        </div>
                        <?php if ($isTrash): ?>
                            <button type="submit" form="restore-<?= (int)$item['id'] ?>" class="row-action">Restore</button>
                            <button type="submit" form="delete-<?= (int)$item['id'] ?>" class="row-action danger"
                                    onclick="return confirm('Permanently delete this item?');">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</form>

<?php foreach ($items as $item): ?>
    <?php if ($canEdit && !$isTrash): ?>
        <form id="trash-<?= (int)$item['id'] ?>" method="post" action="/hq/content/trash/<?= (int)$item['id'] ?>" style="display:none;">
            <?= Csrf::field() ?>
        </form>
    <?php endif; ?>
    <?php if ($isTrash && $canEdit): ?>
        <form id="restore-<?= (int)$item['id'] ?>" method="post" action="/hq/content/restore/<?= (int)$item['id'] ?>" style="display:none;">
            <?= Csrf::field() ?>
        </form>
        <form id="delete-<?= (int)$item['id'] ?>" method="post" action="/hq/content/delete/<?= (int)$item['id'] ?>" style="display:none;">
            <?= Csrf::field() ?>
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<nav class="pagination content-list-footer">
    <div class="content-list-paging">
        <div class="paging-meta">Page <?= (int)$page ?> of <?= (int)$totalPages ?></div>
        <div class="paging-controls">
            <?php if ($page > 1): ?>
                <a class="btn-ghost btn-sm" href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
                <a class="btn-ghost btn-sm <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= htmlspecialchars($buildUrl(['page' => $p])) ?>"
                   style="<?= $p === $page ? 'border-color:var(--hq-accent);color:var(--hq-accent);' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn-ghost btn-sm" href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>">Next →</a>
            <?php endif; ?>
            <form method="get" action="/hq/content" class="goto-page-form">
                <?php if (!empty($status)): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
                <?php if (!empty($type)): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                <label for="goto_page">Go to</label>
                <input type="number" id="goto_page" name="page" min="1" max="<?= (int)$totalPages ?>" value="<?= (int)$page ?>">
                <button type="submit" class="btn-ghost btn-sm">Go</button>
            </form>
        </div>
    </div>

    <div class="toolbar bulk-toolbar content-list-toolbar">
        <?php if ($canEdit): ?><div class="bulk-actions">
            <label class="select-all-label">
                <input type="checkbox" id="select-all-content" title="Select all on this page" form="content-bulk-form">
                <span>Select all</span>
            </label>
            <?php if ($isTrash): ?>
                <button type="button" class="btn-ghost btn-sm" data-bulk="restore" form="content-bulk-form">Restore</button>
                <button type="button" class="btn-danger btn-sm" data-bulk="delete"
                        onclick="return confirm('Permanently delete selected items? This cannot be undone.');">Delete permanently</button>
            <?php else: ?>
                <button type="button" class="btn-ghost btn-sm" data-bulk="publish">Publish</button>
                <button type="button" class="btn-ghost btn-sm" data-bulk="draft">Move to draft</button>
                <button type="button" class="btn-ghost btn-sm" data-bulk="trash">Move to trash</button>
            <?php endif; ?>
        </div><?php endif; ?>
        <div class="per-page-control">
            <label for="per_page_select">Show</label>
            <select id="per_page_select" onchange="window.location.href=this.value">
                <?php foreach ($allowedPerPage as $n): ?>
                    <option value="<?= htmlspecialchars($buildUrl(['per_page' => $n, 'page' => 1])) ?>"
                        <?= $perPage === $n ? 'selected' : '' ?>><?= (int)$n ?></option>
                <?php endforeach; ?>
            </select>
            <span>per page · <?= (int)$total ?> total</span>
        </div>
    </div>
</nav>

<style>
.content-list-footer{
  display:flex;
  flex-direction:column;
  gap:0.75rem;
  margin-top:1rem;
}
.content-list-paging{
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:0.75rem;
}
.paging-meta{font-size:0.85rem;color:var(--hq-muted)}
.paging-controls{display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap}
.goto-page-form{display:flex;gap:0.35rem;align-items:center;margin-left:0.5rem}
.goto-page-form label{font-size:0.8rem;color:var(--hq-muted)}
.goto-page-form input[type="number"]{width:4rem;padding:0.3rem 0.4rem;border-radius:8px;border:1px solid var(--hq-border);background:var(--hq-input-bg);color:var(--hq-text);font:inherit}

.content-list-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding-top:0.65rem;border-top:1px solid var(--hq-border)}
.content-list-toolbar .bulk-actions{display:flex;align-items:center;gap:0.45rem;flex-wrap:wrap}
.select-all-label{display:inline-flex;align-items:center;gap:0.45rem;font-size:0.85rem;color:var(--hq-muted);cursor:pointer;margin:0}
.select-all-label input{width:15px;height:15px;margin:0;accent-color:var(--hq-accent)}
.per-page-control{display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--hq-muted)}
.per-page-control select{width:auto;padding:0.3rem 0.5rem;border-radius:8px;border:1px solid var(--hq-border);background:var(--hq-input-bg);color:var(--hq-text);font:inherit}

.content-list-card{
  background:var(--hq-surface,#fff);
  border:1px solid var(--hq-border);
  border-radius:12px;
  overflow:hidden;
}
.content-list-table{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
  font-size:0.9rem;
}
.content-list-table thead th{
  text-align:left;
  font-size:0.72rem;
  font-weight:600;
  letter-spacing:0.04em;
  text-transform:uppercase;
  color:var(--hq-muted);
  padding:0.75rem 0.85rem;
  border-bottom:1px solid var(--hq-border);
  background:rgba(0,0,0,0.015);
}
.content-list-table tbody td{
  padding:0.8rem 0.85rem;
  border-bottom:1px solid var(--hq-border);
  vertical-align:middle;
  color:var(--hq-text);
}
.content-list-table tbody tr:last-child td{border-bottom:none}
.content-list-table tbody tr:hover{background:rgba(59,130,246,0.03)}

.col-check{width:2.75rem;text-align:center !important}
.col-check input{
  width:15px;height:15px;margin:0;
  accent-color:var(--hq-accent);
  vertical-align:middle;
}
thead .col-check, tbody .col-check{
  padding-left:0.9rem;padding-right:0.5rem;
}
.col-title{width:auto}
.col-type{width:7.5rem}
.col-status{width:7.5rem}
.col-updated{width:7.5rem;color:var(--hq-muted);font-size:0.85rem;white-space:nowrap}
.col-actions{width:7rem;text-align:right !important;white-space:nowrap}

.content-title-link{
  color:var(--hq-text);
  text-decoration:none;
  font-weight:500;
}
.content-title-link:hover{color:var(--hq-accent)}
.type-pill{
  display:inline-block;
  font-size:0.78rem;
  color:var(--hq-muted);
  background:rgba(0,0,0,0.04);
  padding:0.15rem 0.5rem;
  border-radius:999px;
}
.row-action{
  background:none;border:none;padding:0.2rem 0.35rem;
  font:inherit;font-size:0.8rem;color:var(--hq-muted);
  cursor:pointer;text-decoration:none;border-radius:6px;
}
.row-action:hover{color:var(--hq-accent);background:rgba(59,130,246,0.08)}
.row-action.danger:hover{color:#b91c1c;background:rgba(185,28,28,0.08)}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}

@media (max-width:720px){
  .col-type,.col-updated{display:none}
  .col-actions{width:5rem}
}
</style>
<script>
(function () {
    var selectAll = document.getElementById('select-all-content');
    var checks = document.querySelectorAll('.content-row-check');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checks.forEach(function (c) { c.checked = selectAll.checked; });
        });
    }
    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-bulk');
            var any = Array.prototype.some.call(checks, function (c) { return c.checked; });
            if (!any) {
                alert('Select at least one item.');
                return;
            }
            document.getElementById('bulk_action').value = action;
            document.getElementById('content-bulk-form').submit();
        });
    });
})();
</script>
<?php endif; ?>
