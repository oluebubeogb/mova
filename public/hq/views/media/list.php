<?php use Mova\Security\Csrf; use Mova\Core\Bootstrap; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Media library</h2>
    <form id="upload-form" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <label class="btn-primary" style="cursor:pointer;">
            Upload
            <input type="file" name="file" id="file-input" accept="image/*" multiple hidden>
        </label>
    </form>
</div>

<div id="upload-status"></div>
<div id="upload-queue" style="margin:0.75rem 0;display:flex;flex-direction:column;gap:0.4rem;"></div>

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

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function uploadOne(file, rowEl) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('_mova_csrf', csrfToken);
            fd.append('file', file);

            var bar = rowEl.querySelector('.uq-bar');
            var label = rowEl.querySelector('.uq-label');
            var pctEl = rowEl.querySelector('.uq-pct');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/hq/media/upload');
            xhr.upload.onprogress = function (e) {
                if (!e.lengthComputable) return;
                var pct = Math.round((e.loaded / e.total) * 100);
                if (bar) bar.style.width = pct + '%';
                if (pctEl) pctEl.textContent = pct + '%';
                if (label) label.textContent = 'Uploading ' + esc(file.name) + '…';
            };
            xhr.onload = function () {
                var data = {};
                try { data = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status >= 200 && xhr.status < 300 && data.success) {
                    if (bar) bar.style.width = '100%';
                    if (pctEl) pctEl.textContent = '100%';
                    if (label) label.innerHTML = '<span style="color:var(--hq-success,#15803d);">✓ ' + esc(file.name) + '</span>';
                    rowEl.classList.add('uq-ok');
                    resolve({ ok: true, data: data });
                } else {
                    if (label) label.innerHTML = '<span style="color:var(--hq-danger,#b91c1c);">✗ ' + esc(file.name) + ' — ' + esc(data.error || 'Upload failed') + '</span>';
                    rowEl.classList.add('uq-fail');
                    resolve({ ok: false, error: data.error || 'Upload failed' });
                }
            };
            xhr.onerror = function () {
                if (label) label.innerHTML = '<span style="color:var(--hq-danger,#b91c1c);">✗ ' + esc(file.name) + ' — network error</span>';
                rowEl.classList.add('uq-fail');
                resolve({ ok: false, error: 'Network error' });
            };
            xhr.send(fd);
        });
    }

    document.getElementById('file-input').addEventListener('change', async function() {
        var files = Array.prototype.slice.call(this.files || []);
        this.value = ''; // allow re-selecting same files
        if (!files.length) return;

        var status = document.getElementById('upload-status');
        var queue = document.getElementById('upload-queue');
        status.innerHTML = '<div class="alert">Uploading ' + files.length + ' file' + (files.length > 1 ? 's' : '') + ' (queued)…</div>';
        queue.innerHTML = '';

        var rows = files.map(function (file) {
            var row = document.createElement('div');
            row.className = 'uq-row';
            row.style.cssText = 'background:var(--hq-surface,#f8fafc);border:1px solid var(--hq-border,#e2e8f0);border-radius:8px;padding:0.5rem 0.75rem;';
            row.innerHTML =
                '<div style="display:flex;justify-content:space-between;gap:0.5rem;font-size:0.85rem;margin-bottom:0.35rem;">' +
                    '<span class="uq-label">Queued: ' + esc(file.name) + '</span>' +
                    '<span class="uq-pct" style="font-variant-numeric:tabular-nums;color:var(--hq-muted);">0%</span>' +
                '</div>' +
                '<div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;">' +
                    '<div class="uq-bar" style="height:100%;width:0%;background:var(--hq-accent,#2563eb);transition:width 0.15s linear;"></div>' +
                '</div>';
            queue.appendChild(row);
            return row;
        });

        var okCount = 0;
        // Sequential queue so we don't overwhelm the server / session
        for (var i = 0; i < files.length; i++) {
            var result = await uploadOne(files[i], rows[i]);
            if (result.ok) okCount++;
        }

        if (okCount === files.length) {
            status.innerHTML = '<div class="alert alert-success">All ' + okCount + ' file(s) uploaded. Reloading…</div>';
            setTimeout(function () { location.reload(); }, 600);
        } else if (okCount > 0) {
            status.innerHTML = '<div class="alert alert-success">' + okCount + ' of ' + files.length + ' uploaded. Refresh to see new items.</div>';
        } else {
            status.innerHTML = '<div class="alert alert-error">All uploads failed. See details above.</div>';
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
