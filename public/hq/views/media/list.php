<?php use Mova\Security\Csrf; use Mova\Core\Bootstrap; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Media library</h2>
    <form id="upload-form" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <label class="btn-primary" style="cursor:pointer;">
            Upload
            <input type="file" name="file" id="file-input" accept="image/*" hidden>
        </label>
    </form>
</div>

<div id="upload-status"></div>

<?php if (empty($items)): ?>
    <p class="empty">No media yet. Upload an image to get started.</p>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($items as $item):
            $imgPath = $item['path'] ?? '';
            if (!empty($item['variants']) && is_array($item['variants'])) {
                $best = null;
                foreach ($item['variants'] as $v) {
                    if ($best === null || ($v['width'] ?? 0) > ($best['width'] ?? 0)) {
                        $best = $v;
                    }
                }
                if ($best && !empty($best['path'])) {
                    $imgPath = $best['path'];
                }
            }
            $public = '/mova-uploads/' . ltrim((string) $imgPath, '/');
            $id = (int) ($item['id'] ?? 0);
            ?>
            <div class="media-card" data-id="<?= $id ?>">
                <?php if (strpos((string)($item['mime_type'] ?? ''), 'image/') === 0 || str_ends_with(strtolower($imgPath), '.webp')): ?>
                    <img src="<?= htmlspecialchars($public) ?>" alt="<?= htmlspecialchars($item['alt_text'] ?? '') ?>" loading="lazy" onerror="this.style.opacity=0.3">
                <?php else: ?>
                    <div class="media-placeholder"><?= htmlspecialchars(strtoupper($item['extension'] ?? '')) ?></div>
                <?php endif; ?>
                <div class="media-info">
                    <span class="media-name" title="<?= htmlspecialchars($item['original_name'] ?? '') ?>"><?= htmlspecialchars($item['original_name'] ?? '') ?></span>
                    <code class="media-path"><?= htmlspecialchars($public) ?></code>
                    <div class="media-actions" style="margin-top:0.5rem;display:flex;gap:0.35rem;flex-wrap:wrap;">
                        <button type="button" class="btn-ghost btn-sm media-copy" data-url="<?= htmlspecialchars($public) ?>" title="Copy URL">
                            <i class="fa-solid fa-link"></i> Copy
                        </button>
                        <button type="button" class="btn-ghost btn-sm media-delete" data-id="<?= $id ?>" style="color:var(--hq-danger,#b91c1c);" title="Delete">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $page = (int) ($page ?? 1);
    $totalPages = (int) ($totalPages ?? 1);
    $total = (int) ($total ?? 0);
    $perPage = (int) ($perPage ?? 25);
    if ($totalPages > 1):
    ?>
    <nav class="pagination" style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;margin-top:1.25rem;font-size:0.9rem;" aria-label="Media pagination">
        <span style="color:var(--hq-muted);margin-right:0.5rem;"><?= (int) $total ?> items · page <?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page > 1): ?>
            <a class="btn-ghost btn-sm" href="/hq/media?page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
            <a class="btn-ghost btn-sm<?= $p === $page ? ' is-active' : '' ?>" href="/hq/media?page=<?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a class="btn-ghost btn-sm" href="/hq/media?page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var csrf = document.querySelector('#upload-form input[name="_mova_csrf"]');
    var csrfToken = csrf ? csrf.value : '';

    document.getElementById('file-input').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;
        const form = document.getElementById('upload-form');
        const fd = new FormData(form);
        fd.set('file', file);
        const status = document.getElementById('upload-status');
        status.innerHTML = '<div class="alert">Uploading…</div>';
        try {
            const res = await fetch('/hq/media/upload', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                status.innerHTML = '<div class="alert alert-success">Uploaded. Reloading…</div>';
                location.reload();
            } else {
                status.innerHTML = '<div class="alert alert-error">' + (data.error || 'Upload failed') + '</div>';
            }
        } catch (e) {
            status.innerHTML = '<div class="alert alert-error">Upload failed</div>';
        }
    });

    document.querySelectorAll('.media-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url') || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                    setTimeout(function () { btn.innerHTML = '<i class="fa-solid fa-link"></i> Copy'; }, 1500);
                });
            } else {
                prompt('Copy URL', url);
            }
        });
    });

    document.querySelectorAll('.media-delete').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = btn.getAttribute('data-id');
            if (!id || !confirm('Delete this media file permanently?')) return;
            btn.disabled = true;
            try {
                var fd = new FormData();
                fd.append('_mova_csrf', csrfToken);
                fd.append('_method', 'DELETE');
                var res = await fetch('/hq/media/delete/' + id, { method: 'POST', body: fd });
                var data = await res.json().catch(function () { return { success: res.ok }; });
                if (data.success || res.ok) {
                    var card = btn.closest('.media-card');
                    if (card) card.remove();
                } else {
                    alert(data.error || 'Delete failed');
                    btn.disabled = false;
                }
            } catch (e) {
                alert('Delete failed');
                btn.disabled = false;
            }
        });
    });
})();
</script>
