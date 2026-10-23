<?php
/** @var array $items */
$items = $items ?? [];
$error = $_GET['error'] ?? '';
?>
<section class="hq-page">
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
      <h1>Blogs</h1>
      <p class="muted">Content feeds with infinite scroll. Default lives at <code>/blog</code>.</p>
    </div>
    <a class="btn-primary" href="/hq/blogs/new"><i class="fa-solid fa-plus"></i> New blog</a>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!$items): ?>
    <p class="muted">No blogs yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
          <th>Mode</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $b): ?>
        <tr>
          <td><a href="/hq/blogs/edit/<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['title']) ?></a></td>
          <td><code>/<?= htmlspecialchars($b['slug'] === 'blog' ? 'blog' : 'blog/' . $b['slug']) ?></code></td>
          <td><?= htmlspecialchars($b['mode'] ?? 'filter') ?></td>
          <td><?= htmlspecialchars($b['status'] ?? '') ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="btn-ghost btn-sm" href="/<?= $b['slug'] === 'blog' ? 'blog' : 'blog/' . htmlspecialchars($b['slug']) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn-ghost btn-sm" href="/hq/blogs/edit/<?= (int)$b['id'] ?>">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
