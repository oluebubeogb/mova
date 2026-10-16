<?php use Mova\Security\Csrf; use Mova\Core\Bootstrap; ?>

<style>
/* Media page layout — scoped so it always applies with this view */
.media-page {
    display: flex;
    align-items: stretch;
    gap: 0;
    width: 100%;
    min-height: 0;
    /* Breathing room between grid and detail panel when open */
    --media-sidebar-gap: 1.25rem;
}
.media-page.is-sidebar-open {
    gap: var(--media-sidebar-gap);
}
.media-page-main {
    flex: 1 1 auto;
    min-width: 0;
    transition: max-width 0.22s ease, padding-right 0.22s ease;
}
.media-page.is-sidebar-open .media-page-main {
    /* sidebar width + gap so the grid never collides with the panel */
    max-width: calc(100% - 30vw - var(--media-sidebar-gap));
    padding-right: 0.25rem;
}
@media (max-width: 900px) {
    .media-page {
        --media-sidebar-gap: 1rem;
    }
    .media-page.is-sidebar-open .media-page-main {
        max-width: calc(100% - 280px - var(--media-sidebar-gap));
    }
}
@media (max-width: 640px) {
    .media-page {
        flex-direction: column;
        --media-sidebar-gap: 0.75rem;
    }
    .media-page.is-sidebar-open .media-page-main {
        max-width: 100%;
        padding-right: 0;
    }
}

/* Grid thumbs: fixed height for images AND videos */
.media-page .media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
}
.media-page .media-card {
    background: var(--hq-surface, #fff);
    border: 1px solid var(--hq-border, #e2e8f0);
    border-radius: var(--hq-radius, 8px);
    overflow: hidden;
    position: relative;
}
.media-page .media-thumb {
    position: relative;
    width: 100%;
    height: 140px;
    max-height: 140px;
    overflow: hidden;
    background: var(--hq-bg, #f1f5f9);
    cursor: zoom-in;
}
.media-page .media-thumb img,
.media-page .media-thumb video,
.media-page .media-hover-video,
.media-page .media-poster {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 140px !important;
    object-fit: cover !important;
    display: block !important;
}
.media-page .media-hover-video {
    opacity: 0;
    pointer-events: none;
    z-index: 1;
    transition: opacity 0.2s ease;
}
.media-page .media-card.is-video:hover .media-hover-video {
    opacity: 1;
}
.media-page .media-card.is-video:hover .media-poster {
    opacity: 0;
}
.media-page .media-placeholder {
    position: absolute;
    inset: 0;
    height: auto !important;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hq-bg, #f1f5f9);
    color: var(--hq-muted);
    font-weight: 600;
}
.media-page .media-video-badge {
    position: absolute;
    bottom: 0.4rem;
    left: 0.4rem;
    z-index: 2;
    background: rgba(15, 23, 42, 0.72);
    color: #fff;
    border-radius: 999px;
    width: 1.6rem;
    height: 1.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    pointer-events: none;
}

/* Right sidebar: 30vw, pushes main content */
.media-page .media-sidebar {
    flex: 0 0 0;
    width: 0;
    max-width: 0;
    overflow: hidden;
    opacity: 0;
    border-left: 0 solid var(--hq-border, #e2e8f0);
    background: var(--hq-surface, #fff);
    align-self: stretch;
    position: sticky;
    top: 0;
    max-height: calc(100vh - 4rem);
    transition: flex-basis 0.22s ease, width 0.22s ease, max-width 0.22s ease, opacity 0.18s ease, border-width 0.22s ease;
    display: flex;
    flex-direction: column;
    z-index: 5;
}
.media-page.is-sidebar-open .media-sidebar {
    flex: 0 0 30vw;
    width: 30vw;
    max-width: 30vw;
    min-width: 260px;
    opacity: 1;
    border: 1px solid var(--hq-border, #e2e8f0);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
}
@media (max-width: 900px) {
    .media-page.is-sidebar-open .media-sidebar {
        flex-basis: 280px;
        width: 280px;
        max-width: 280px;
    }
}
@media (max-width: 640px) {
    .media-page.is-sidebar-open .media-sidebar {
        flex: 0 0 auto;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        max-height: none;
        position: relative;
        border-radius: 10px;
        margin-top: 0.25rem;
    }
}
.media-page .media-sidebar[hidden] {
    display: none !important;
}
.media-page.is-sidebar-open .media-sidebar {
    display: flex !important;
}
.media-page .media-sidebar-backdrop {
    display: none !important; /* push layout, no overlay */
}
.media-page .media-sidebar-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    width: 100%;
    transform: none !important;
    box-shadow: none;
    border: 0;
    background: transparent;
    overflow: hidden;
}
.media-page .media-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--hq-border, #e2e8f0);
    flex-shrink: 0;
}
.media-page .media-sidebar-preview {
    padding: 0.85rem 1rem 0;
    text-align: center;
    flex-shrink: 0;
}
.media-page .media-sidebar-preview img,
.media-page .media-sidebar-preview video {
    max-width: 100%;
    max-height: 180px;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 8px;
}
.media-page .media-sidebar-body {
    padding: 0.85rem 1rem 1.25rem;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}
.media-page .media-sidebar-body .form-group {
    margin-bottom: 0.85rem;
}
.media-page .media-sidebar-body label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: var(--hq-muted);
}
.media-page .ms-input {
    width: 100%;
    box-sizing: border-box;
    border-radius: 8px;
    border: 1px solid var(--hq-border, #e2e8f0);
    padding: 0.5rem 0.65rem;
    font: inherit;
    background: var(--hq-bg, #f8fafc);
}
.media-page .ms-save-hint {
    display: block;
    font-size: 0.72rem;
    color: var(--hq-muted);
    min-height: 1em;
    margin-top: 0.2rem;
}
.media-page .media-sidebar-hr {
    border: 0;
    border-top: 1px solid var(--hq-border, #e2e8f0);
    margin: 1rem 0;
}
.media-page .media-sidebar-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.75rem;
}
.media-page .media-sidebar-footer {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Lightbox: always viewport-fixed (moved to body in JS) */
#media-lightbox.media-lightbox {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    inset: 0 !important;
    z-index: 10000 !important;
    display: none;
    align-items: center;
    justify-content: center;
    margin: 0 !important;
    padding: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: none !important;
    max-height: none !important;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
#media-lightbox.media-lightbox.is-open {
    display: flex !important;
    opacity: 1;
    pointer-events: auto;
}
#media-lightbox .media-lightbox-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
#media-lightbox .media-lightbox-stage {
    position: relative;
    z-index: 1;
    max-width: min(92vw, 1100px);
    max-height: 82vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
#media-lightbox .media-lightbox-stage img,
#media-lightbox .media-lightbox-stage video {
    max-width: 92vw;
    max-height: 82vh;
    width: auto;
    height: auto;
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
    background: #0f172a;
    object-fit: contain;
}
#media-lightbox .media-lightbox-caption {
    position: absolute;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
    color: #f8fafc;
    font-size: 0.9rem;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
    max-width: 80vw;
    text-align: center;
}
#media-lightbox .media-lb-close,
#media-lightbox .media-lb-nav {
    position: absolute;
    z-index: 2;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    border-radius: 999px;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
}
#media-lightbox .media-lb-close { top: 1rem; right: 1rem; font-size: 1.5rem; }
#media-lightbox .media-lb-prev { left: 1rem; top: 50%; transform: translateY(-50%); }
#media-lightbox .media-lb-next { right: 1rem; top: 50%; transform: translateY(-50%); }
</style>

<div class="media-page" id="media-page">
<div class="media-page-main" id="media-page-main">

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Media library</h2>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
        <form id="upload-form" enctype="multipart/form-data" style="display:inline;">
            <?= Csrf::field() ?>
            <label class="btn-primary" style="cursor:pointer;margin:0;">
                Upload
                <input type="file" name="file" id="file-input" accept="image/*,audio/mpeg,audio/mp3,audio/wav,.mp3,.wav,video/mp4,video/webm,.mp4,.webm,.mpd,application/dash+xml,application/pdf,.pdf" multiple hidden>
            </label>
        </form>
        <button type="button" class="btn-ghost" id="btn-generate-image" title="Generate image with AI">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate image
        </button>
    </div>
</div>

<div id="upload-status"></div>
<div id="upload-queue" style="margin:0.75rem 0;display:flex;flex-direction:column;gap:0.4rem;"></div>
<div id="gen-status" style="margin:0.5rem 0;"></div>

<!-- AI image generate modal -->
<div id="gen-image-modal" hidden style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:80;display:none;align-items:center;justify-content:center;padding:1rem;">
  <div role="dialog" aria-labelledby="gen-image-title" style="background:var(--hq-surface,#fff);border-radius:12px;max-width:520px;width:100%;padding:1.25rem 1.35rem;box-shadow:0 20px 50px rgba(0,0,0,0.18);border:1px solid var(--hq-border,#e2e8f0);">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:0.85rem;">
      <h3 id="gen-image-title" style="margin:0;font-size:1.05rem;">Generate image</h3>
      <button type="button" class="btn-ghost btn-sm" id="gen-image-close" aria-label="Close">&times;</button>
    </div>
    <p style="margin:0 0 0.85rem;font-size:0.85rem;color:var(--hq-muted);">Runs in the background via Mova Image. You can leave this page — the file appears in the library when ready.</p>
    <div class="form-group" style="margin-bottom:0.75rem;">
      <label for="gen-prompt" style="display:block;font-weight:600;margin-bottom:0.35rem;">Prompt</label>
      <textarea id="gen-prompt" rows="4" style="width:100%;border-radius:8px;border:1px solid var(--hq-border);padding:0.55rem 0.65rem;font:inherit;" placeholder="A modern tea shop interior, warm afternoon light, minimalist wood design…"></textarea>
    </div>
    <div class="form-group" style="margin-bottom:0.75rem;">
      <label for="gen-negative" style="display:block;font-weight:600;margin-bottom:0.35rem;">Negative prompt <span style="font-weight:400;color:var(--hq-muted);">(optional)</span></label>
      <textarea id="gen-negative" rows="2" style="width:100%;border-radius:8px;border:1px solid var(--hq-border);padding:0.55rem 0.65rem;font:inherit;" placeholder="blurry, low-res, watermark, text, logo…"></textarea>
    </div>
    <div class="form-group" style="margin-bottom:1rem;">
      <span style="display:block;font-weight:600;margin-bottom:0.4rem;">Aspect</span>
      <div style="display:flex;flex-wrap:wrap;gap:0.5rem;" id="gen-aspect-group">
        <label class="btn-ghost btn-sm" style="cursor:pointer;"><input type="radio" name="gen-aspect" value="square" checked> Square 720×720</label>
        <label class="btn-ghost btn-sm" style="cursor:pointer;"><input type="radio" name="gen-aspect" value="landscape"> Landscape 1280×720</label>
        <label class="btn-ghost btn-sm" style="cursor:pointer;"><input type="radio" name="gen-aspect" value="portrait"> Portrait 720×1280</label>
      </div>
    </div>
    <div id="gen-progress" style="font-size:0.85rem;color:var(--hq-muted);min-height:1.25rem;margin-bottom:0.75rem;"></div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
      <button type="button" class="btn-ghost" id="gen-image-cancel">Cancel</button>
      <button type="button" class="btn-primary" id="gen-image-submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate</button>
    </div>
  </div>
</div>

<?php if (empty($items)): ?>
    <p class="empty">No media yet. Upload an image, audio (MP3), video, or MPD to get started.</p>
<?php else: ?>
    <div class="media-grid" id="media-grid">
        <?php
        $mediaIndex = [];
        foreach ($items as $item):
            $imgPath = $item['path'] ?? '';
            $variants = is_array($item['variants'] ?? null) ? $item['variants'] : [];
            $byWidth = [];
            foreach ($variants as $v) {
                $w = (int)($v['width'] ?? 0);
                if ($w > 0 && !empty($v['path'])) {
                    $byWidth[$w] = '/mova-uploads/' . ltrim((string)$v['path'], '/');
                }
            }
            $fullPublic = $imgPath ? '/mova-uploads/' . ltrim((string)$imgPath, '/') : '';
            if ($byWidth) {
                ksort($byWidth);
                $thumbPublic = reset($byWidth);
                $fullPublic = end($byWidth);
            } else {
                $thumbPublic = $fullPublic;
            }
            $id = (int) ($item['id'] ?? 0);
            $mime = (string)($item['mime_type'] ?? '');
            $isImage = strpos($mime, 'image/') === 0
                || str_ends_with(strtolower((string)$imgPath), '.webp');
            $isVideo = strpos($mime, 'video/') === 0;
            $posterPath = !empty($item['poster_path'])
                ? '/mova-uploads/' . ltrim((string)$item['poster_path'], '/')
                : '';
            $excluded = !empty($item['exclude_from_gallery']);
            $mediaIndex[] = [
                'id' => $id,
                'url' => $fullPublic,
                'thumb' => $thumbPublic ?: $posterPath,
                'poster' => $posterPath,
                'urls' => $byWidth,
                'name' => $item['original_name'] ?? '',
                'alt' => $item['alt_text'] ?? '',
                'description' => $item['description'] ?? '',
                'mime' => $mime,
                'is_image' => $isImage,
                'is_video' => $isVideo,
                'excluded' => $excluded ? 1 : 0,
            ];
            ?>
            <div class="media-card<?= $excluded ? ' is-gallery-excluded' : '' ?><?= $isVideo ? ' is-video' : '' ?>"
                 data-id="<?= $id ?>"
                 data-url="<?= htmlspecialchars($fullPublic) ?>"
                 data-thumb="<?= htmlspecialchars($thumbPublic ?: $posterPath) ?>"
                 data-poster="<?= htmlspecialchars($posterPath) ?>"
                 data-name="<?= htmlspecialchars($item['original_name'] ?? '') ?>"
                 data-alt="<?= htmlspecialchars($item['alt_text'] ?? '') ?>"
                 data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                 data-mime="<?= htmlspecialchars($mime) ?>"
                 data-excluded="<?= $excluded ? '1' : '0' ?>"
                 data-urls="<?= htmlspecialchars(json_encode($byWidth, JSON_UNESCAPED_SLASHES)) ?>">
                <div class="media-thumb media-open-preview" role="button" tabindex="0" title="Preview">
                    <?php if ($isVideo): ?>
                        <?php if ($posterPath): ?>
                            <img src="<?= htmlspecialchars($posterPath) ?>" alt="<?= htmlspecialchars($item['alt_text'] ?? '') ?>" loading="lazy" decoding="async" class="mova-img media-poster" onload="this.classList.add('is-loaded')">
                        <?php else: ?>
                            <div class="media-placeholder media-video-ph"><i class="fa-solid fa-film"></i></div>
                        <?php endif; ?>
                        <video class="media-hover-video" muted playsinline preload="metadata" loop src="<?= htmlspecialchars($fullPublic) ?>"></video>
                        <span class="media-video-badge"><i class="fa-solid fa-play"></i></span>
                    <?php elseif ($isImage && $thumbPublic): ?>
                        <img src="<?= htmlspecialchars($thumbPublic) ?>" alt="<?= htmlspecialchars($item['alt_text'] ?? '') ?>" loading="lazy" decoding="async" class="mova-img" onload="this.classList.add('is-loaded')" onerror="this.style.opacity=0.3">
                    <?php else: ?>
                        <div class="media-placeholder"><?= htmlspecialchars(strtoupper($item['extension'] ?? '')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="media-info">
                    <span class="media-name" title="<?= htmlspecialchars($item['original_name'] ?? '') ?>"><?= htmlspecialchars($item['original_name'] ?? '') ?></span>
                    <div class="media-card-actions">
                        <button type="button" class="btn-ghost btn-sm media-copy" data-url="<?= htmlspecialchars($fullPublic) ?>" title="Copy largest URL">
                            <i class="fa-solid fa-link"></i>
                        </button>
                        <button type="button" class="btn-ghost btn-sm media-more" aria-label="More actions" title="More">
                            <i class="fa-solid fa-ellipsis"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script type="application/json" id="media-index-data"><?= json_encode($mediaIndex, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

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

</div><!-- /.media-page-main -->

<!-- Detail sidebar: sits to the right of media-page-main, 30vw when open -->
<aside id="media-sidebar" class="media-sidebar" hidden aria-hidden="true">
    <div class="media-sidebar-panel" role="dialog" aria-labelledby="media-sidebar-title">
        <div class="media-sidebar-header">
            <h3 id="media-sidebar-title" style="margin:0;font-size:1rem;">Media details</h3>
            <button type="button" class="btn-ghost btn-sm" data-close-sidebar aria-label="Close">&times;</button>
        </div>
        <div class="media-sidebar-preview" id="ms-preview"></div>
        <div class="media-sidebar-body">
            <div class="form-group">
                <label for="ms-name">File name</label>
                <input type="text" id="ms-name" class="ms-input" autocomplete="off">
                <span class="ms-save-hint" id="ms-name-hint"></span>
            </div>
            <div class="form-group">
                <label for="ms-alt">Alt text</label>
                <input type="text" id="ms-alt" class="ms-input" autocomplete="off" placeholder="Describe the image for accessibility">
                <span class="ms-save-hint" id="ms-alt-hint"></span>
            </div>
            <div class="form-group">
                <label for="ms-desc">Description</label>
                <textarea id="ms-desc" class="ms-input" rows="3" placeholder="Optional description for feeds &amp; media.xml"></textarea>
                <span class="ms-save-hint" id="ms-desc-hint"></span>
            </div>
            <div class="form-group" id="ms-replace-wrap">
                <label>Replace image</label>
                <p class="ms-help">Keeps the same public URL. Upload a file or regenerate with AI.</p>
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
                    <label class="btn-ghost btn-sm" style="cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;margin:0;">
                        <i class="fa-solid fa-upload"></i> Choose file
                        <input type="file" id="ms-replace" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" hidden>
                    </label>
                    <button type="button" class="btn-ghost btn-sm" id="ms-regenerate-btn" title="AI regenerate — keeps this URL">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Regenerate
                    </button>
                </div>
                <span class="ms-save-hint" id="ms-replace-hint"></span>
            </div>
            <hr class="media-sidebar-hr">
            <div class="media-sidebar-links" id="ms-links"></div>
            <div class="media-sidebar-footer">
                <button type="button" class="btn-ghost btn-sm" id="ms-gallery-btn"><i class="fa-solid fa-eye"></i> Gallery</button>
                <button type="button" class="btn-ghost btn-sm" id="ms-delete-btn" style="color:var(--hq-danger,#b91c1c);"><i class="fa-solid fa-trash"></i> Delete</button>
            </div>
            <div id="ms-occurrences" class="ms-occurrences" hidden></div>
        </div>
    </div>
</aside>

</div><!-- /.media-page -->

<!-- Lightbox: moved to document.body by JS so position:fixed is viewport-relative -->
<div id="media-lightbox" class="media-lightbox" hidden aria-hidden="true">
    <div class="media-lightbox-backdrop"></div>
    <button type="button" class="media-lb-nav media-lb-prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
    <button type="button" class="media-lb-nav media-lb-next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
    <button type="button" class="media-lb-close" aria-label="Close">&times;</button>
    <div class="media-lightbox-stage" id="media-lb-stage"></div>
    <div class="media-lightbox-caption" id="media-lb-caption"></div>
</div>

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

    function movaAvailableWidths(maxW) {
        var breakpoints = [320, 480, 768, 1200];
        var out = [];
        breakpoints.forEach(function (w) { if (w < maxW) out.push(w); });
        if (out.indexOf(maxW) === -1) out.push(maxW);
        out.sort(function (a, b) { return a - b; });
        return out;
    }
    function movaResponsiveImgHtml(src, alt) {
        alt = alt || '';
        var m = (src || '').match(/^(.*?)-(\d+)(\.(?:webp|jpe?g|png|gif))$/i);
        if (!m) {
            return '<img src="' + src + '" alt="' + alt.replace(/"/g, '&quot;') + '" loading="lazy" decoding="async" class="mova-img" onload="this.classList.add(\'is-loaded\')">';
        }
        var base = m[1], maxW = parseInt(m[2], 10), ext = m[3];
        var widths = movaAvailableWidths(maxW);
        var srcset = widths.map(function (w) { return base + '-' + w + ext + ' ' + w + 'w'; }).join(', ');
        var preferred = base + '-' + maxW + ext;
        if (widths.indexOf(480) !== -1) preferred = base + '-480' + ext;
        else if (widths.indexOf(320) !== -1) preferred = base + '-320' + ext;
        var sizes;
        if (maxW <= 320) sizes = maxW + 'px';
        else if (maxW <= 480) sizes = '(max-width: 360px) 320px, ' + maxW + 'px';
        else if (maxW <= 768) sizes = '(max-width: 360px) 320px, (max-width: 640px) 480px, ' + maxW + 'px';
        else sizes = '(max-width: 360px) 320px, (max-width: 640px) 480px, (max-width: 1024px) 768px, ' + maxW + 'px';
        return '<img src="' + preferred + '" srcset="' + srcset + '" sizes="' + sizes + '" alt="' + alt.replace(/"/g, '&quot;') + '" loading="lazy" decoding="async" class="mova-img" onload="this.classList.add(\'is-loaded\')">';
    }

    function copyText(text, btn, okHtml, resetHtml) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (btn) {
                    btn.innerHTML = okHtml || '<i class="fa-solid fa-check"></i>';
                    setTimeout(function () { btn.innerHTML = resetHtml || '<i class="fa-solid fa-link"></i>'; }, 1200);
                }
            });
        } else {
            prompt('Copy', text);
        }
    }

    document.querySelectorAll('.media-copy').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            copyText(btn.getAttribute('data-url') || '', btn, '<i class="fa-solid fa-check"></i>', '<i class="fa-solid fa-link"></i>');
        });
    });

    // —— Video hover play (brief) ——
    document.querySelectorAll('.media-card.is-video').forEach(function (card) {
        var vid = card.querySelector('.media-hover-video');
        if (!vid) return;
        var timer = null;
        card.addEventListener('mouseenter', function () {
            try {
                vid.currentTime = 0;
                var p = vid.play();
                if (p && p.catch) p.catch(function () {});
                clearTimeout(timer);
                timer = setTimeout(function () {
                    try { vid.pause(); } catch (e) {}
                }, 2500);
            } catch (e) {}
        });
        card.addEventListener('mouseleave', function () {
            clearTimeout(timer);
            try { vid.pause(); vid.currentTime = 0; } catch (e) {}
        });
    });

    // —— Sidebar ——
    var sidebar = document.getElementById('media-sidebar');
    var mediaPage = document.getElementById('media-page');
    var currentId = null;
    var saveTimers = {};

    function parseUrls(card) {
        try {
            return JSON.parse(card.getAttribute('data-urls') || '{}') || {};
        } catch (e) { return {}; }
    }

    function openSidebar(card) {
        if (!sidebar || !card) return;
        currentId = card.getAttribute('data-id');
        var name = card.getAttribute('data-name') || '';
        var alt = card.getAttribute('data-alt') || '';
        var desc = card.getAttribute('data-description') || '';
        var url = card.getAttribute('data-url') || '';
        var thumb = card.getAttribute('data-thumb') || url;
        var poster = card.getAttribute('data-poster') || '';
        var mime = card.getAttribute('data-mime') || '';
        var isVideo = mime.indexOf('video/') === 0;
        var isImage = mime.indexOf('image/') === 0;
        var excluded = card.getAttribute('data-excluded') === '1';
        var urls = parseUrls(card);

        document.getElementById('ms-name').value = name;
        document.getElementById('ms-alt').value = alt;
        document.getElementById('ms-desc').value = desc;
        ['ms-name-hint', 'ms-alt-hint', 'ms-desc-hint', 'ms-replace-hint'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '';
        });

        var prev = document.getElementById('ms-preview');
        if (isVideo) {
            prev.innerHTML = '<video controls playsinline src="' + esc(url) + '"' +
                (poster ? ' poster="' + esc(poster) + '"' : '') +
                ' style="max-width:100%;max-height:220px;border-radius:8px;"></video>';
        } else if (isImage) {
            prev.innerHTML = '<img src="' + esc(thumb || url) + '" alt="" style="max-width:100%;max-height:220px;object-fit:contain;border-radius:8px;">';
        } else {
            prev.innerHTML = '<div class="media-placeholder" style="height:120px;">' + esc((mime.split('/')[1] || 'file').toUpperCase()) + '</div>';
        }

        document.getElementById('ms-replace-wrap').style.display = isImage ? '' : 'none';

        var links = document.getElementById('ms-links');
        var html = '';
        var preferredWidths = [480, 768, 1200];
        preferredWidths.forEach(function (w) {
            if (urls[w]) {
                html += '<button type="button" class="btn-ghost btn-sm ms-copy-size" data-url="' + esc(urls[w]) + '">' + w + 'px</button> ';
            }
        });
        Object.keys(urls).forEach(function (w) {
            w = parseInt(w, 10);
            if (preferredWidths.indexOf(w) === -1 && urls[w]) {
                html += '<button type="button" class="btn-ghost btn-sm ms-copy-size" data-url="' + esc(urls[w]) + '">' + w + 'px</button> ';
            }
        });
        html += '<button type="button" class="btn-ghost btn-sm ms-copy-size" data-url="' + esc(url) + '"><i class="fa-solid fa-link"></i> Largest</button> ';
        if (isImage) {
            html += '<button type="button" class="btn-ghost btn-sm" id="ms-copy-html"><i class="fa-solid fa-code"></i> HTML</button>';
        }
        links.innerHTML = html || '<span style="color:var(--hq-muted);font-size:0.85rem;">No variant URLs</span>';

        links.querySelectorAll('.ms-copy-size').forEach(function (btn) {
            btn.addEventListener('click', function () {
                copyText(btn.getAttribute('data-url') || '', btn, 'Copied', btn.textContent);
            });
        });
        var htmlBtn = document.getElementById('ms-copy-html');
        if (htmlBtn) {
            htmlBtn.addEventListener('click', function () {
                copyText(movaResponsiveImgHtml(url, document.getElementById('ms-alt').value), htmlBtn, 'Copied', '<i class="fa-solid fa-code"></i> HTML');
            });
        }

        var galBtn = document.getElementById('ms-gallery-btn');
        galBtn.innerHTML = excluded
            ? '<i class="fa-solid fa-eye-slash"></i> Excluded'
            : '<i class="fa-solid fa-eye"></i> In gallery';
        galBtn.style.color = excluded ? 'var(--hq-warning,#b45309)' : '';
        galBtn.setAttribute('data-excluded', excluded ? '1' : '0');

        document.getElementById('ms-occurrences').hidden = true;
        document.getElementById('ms-occurrences').innerHTML = '';

        sidebar.hidden = false;
        sidebar.removeAttribute('hidden');
        sidebar.setAttribute('aria-hidden', 'false');
        sidebar.classList.add('is-open');
        if (mediaPage) mediaPage.classList.add('is-sidebar-open');

        // Load occurrences + freshest meta
        fetch('/hq/media/' + currentId + '/json', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.media) return;
                var m = data.media;
                if (m.original_name) document.getElementById('ms-name').value = m.original_name;
                if (typeof m.alt_text === 'string') document.getElementById('ms-alt').value = m.alt_text;
                if (typeof m.description === 'string') document.getElementById('ms-desc').value = m.description;
                if (m.occurrences && m.occurrences.length) {
                    var occ = document.getElementById('ms-occurrences');
                    occ.hidden = false;
                    occ.innerHTML = '<hr class="media-sidebar-hr"><div style="font-size:0.8rem;font-weight:600;margin-bottom:0.35rem;">Appears on</div>' +
                        m.occurrences.map(function (o) {
                            return '<a href="' + esc(o.url) + '" target="_blank" rel="noopener" style="display:block;font-size:0.8rem;margin:0.15rem 0;">' + esc(o.title || o.slug) + '</a>';
                        }).join('');
                }
            }).catch(function () {});
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        sidebar.hidden = true;
        sidebar.setAttribute('hidden', '');
        sidebar.setAttribute('aria-hidden', 'true');
        if (mediaPage) mediaPage.classList.remove('is-sidebar-open');
        currentId = null;
    }

    document.querySelectorAll('.media-more').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openSidebar(btn.closest('.media-card'));
        });
    });
    if (sidebar) {
        sidebar.querySelectorAll('[data-close-sidebar]').forEach(function (el) {
            el.addEventListener('click', closeSidebar);
        });
    }

    function scheduleSave(field, value, hintId) {
        if (!currentId) return;
        clearTimeout(saveTimers[field]);
        var hint = document.getElementById(hintId);
        if (hint) hint.textContent = 'Saving…';
        saveTimers[field] = setTimeout(function () {
            var fd = new FormData();
            fd.append('_mova_csrf', csrfToken);
            fd.append(field, value);
            fetch('/hq/media/update/' + currentId, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (hint) hint.textContent = data.success ? 'Saved' : (data.error || 'Error');
                    if (data.success) {
                        var card = document.querySelector('.media-card[data-id="' + currentId + '"]');
                        if (card) {
                            if (field === 'original_name') {
                                card.setAttribute('data-name', value);
                                var nm = card.querySelector('.media-name');
                                if (nm) { nm.textContent = value; nm.title = value; }
                            }
                            if (field === 'alt_text') card.setAttribute('data-alt', value);
                            if (field === 'description') card.setAttribute('data-description', value);
                        }
                        setTimeout(function () { if (hint) hint.textContent = ''; }, 1500);
                    }
                })
                .catch(function () { if (hint) hint.textContent = 'Error'; });
        }, 450);
    }

    document.getElementById('ms-name').addEventListener('input', function () {
        scheduleSave('original_name', this.value, 'ms-name-hint');
    });
    document.getElementById('ms-alt').addEventListener('input', function () {
        scheduleSave('alt_text', this.value, 'ms-alt-hint');
    });
    document.getElementById('ms-desc').addEventListener('input', function () {
        scheduleSave('description', this.value, 'ms-desc-hint');
    });

    document.getElementById('ms-replace').addEventListener('change', function () {
        var file = this.files && this.files[0];
        this.value = '';
        if (!file || !currentId) return;
        var hint = document.getElementById('ms-replace-hint');
        if (hint) hint.textContent = 'Replacing…';
        var fd = new FormData();
        fd.append('_mova_csrf', csrfToken);
        fd.append('file', file);
        fetch('/hq/media/replace/' + currentId, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    if (hint) hint.textContent = data.error || 'Failed';
                    return;
                }
                if (hint) hint.textContent = 'Replaced (same URL)';
                var card = document.querySelector('.media-card[data-id="' + currentId + '"]');
                var newUrl = data.url || '';
                // Prefer relative path for display
                var rel = newUrl.replace(/^https?:\/\/[^/]+/, '');
                var urls = data.urls || {};
                var relUrls = {};
                Object.keys(urls).forEach(function (w) {
                    relUrls[w] = (urls[w] || '').replace(/^https?:\/\/[^/]+/, '');
                });
                if (card) {
                    card.setAttribute('data-url', rel);
                    card.setAttribute('data-urls', JSON.stringify(relUrls));
                    var copyBtn = card.querySelector('.media-copy');
                    if (copyBtn) copyBtn.setAttribute('data-url', rel);
                    var img = card.querySelector('.media-thumb img');
                    var thumbRel = rel;
                    var sorted = Object.keys(relUrls).map(Number).sort(function (a, b) { return a - b; });
                    if (sorted.length) thumbRel = relUrls[sorted[0]];
                    card.setAttribute('data-thumb', thumbRel);
                    if (img) {
                        img.src = thumbRel + (thumbRel.indexOf('?') === -1 ? '?t=' : '&t=') + Date.now();
                    }
                    if (data.media && data.media.original_name) {
                        card.setAttribute('data-name', data.media.original_name);
                        var nm = card.querySelector('.media-name');
                        if (nm) { nm.textContent = data.media.original_name; nm.title = data.media.original_name; }
                        document.getElementById('ms-name').value = data.media.original_name;
                    }
                }
                var prev = document.getElementById('ms-preview');
                if (prev) {
                    prev.innerHTML = '<img src="' + esc(rel) + '?t=' + Date.now() + '" alt="" style="max-width:100%;max-height:220px;object-fit:contain;border-radius:8px;">';
                }
                openSidebar(card || document.querySelector('.media-card[data-id="' + currentId + '"]'));
                setTimeout(function () { if (hint) hint.textContent = ''; }, 2000);
            })
            .catch(function () { if (hint) hint.textContent = 'Error'; });
    });

    document.getElementById('ms-gallery-btn').addEventListener('click', async function () {
        if (!currentId) return;
        var btn = this;
        var currently = btn.getAttribute('data-excluded') === '1';
        var next = currently ? '0' : '1';
        btn.disabled = true;
        try {
            var fd = new FormData();
            fd.append('_mova_csrf', csrfToken);
            fd.append('exclude', next);
            var res = await fetch('/hq/media/gallery-exclude/' + currentId, { method: 'POST', body: fd });
            var data = await res.json().catch(function () { return { success: false }; });
            if (data.success) {
                var excluded = String(data.exclude_from_gallery) === '1' || next === '1';
                btn.setAttribute('data-excluded', excluded ? '1' : '0');
                btn.innerHTML = excluded
                    ? '<i class="fa-solid fa-eye-slash"></i> Excluded'
                    : '<i class="fa-solid fa-eye"></i> In gallery';
                btn.style.color = excluded ? 'var(--hq-warning,#b45309)' : '';
                var card = document.querySelector('.media-card[data-id="' + currentId + '"]');
                if (card) {
                    card.classList.toggle('is-gallery-excluded', excluded);
                    card.setAttribute('data-excluded', excluded ? '1' : '0');
                }
            } else {
                alert(data.error || 'Could not update gallery visibility');
            }
        } catch (e) {
            alert('Could not update gallery visibility');
        }
        btn.disabled = false;
    });

    document.getElementById('ms-delete-btn').addEventListener('click', async function () {
        if (!currentId || !confirm('Delete this media file permanently?')) return;
        var btn = this;
        btn.disabled = true;
        try {
            var fd = new FormData();
            fd.append('_mova_csrf', csrfToken);
            var res = await fetch('/hq/media/delete/' + currentId, { method: 'POST', body: fd });
            var data = await res.json().catch(function () { return { success: res.ok }; });
            if (data.success || res.ok) {
                var card = document.querySelector('.media-card[data-id="' + currentId + '"]');
                if (card) card.remove();
                closeSidebar();
            } else {
                alert(data.error || 'Delete failed');
                btn.disabled = false;
            }
        } catch (e) {
            alert('Delete failed');
            btn.disabled = false;
        }
    });

    // —— Lightbox (reparent to body so fixed covers the viewport, not the footer) ——
    var lb = document.getElementById('media-lightbox');
    var lbStage = document.getElementById('media-lb-stage');
    var lbCaption = document.getElementById('media-lb-caption');
    var lbIndex = 0;
    if (lb && lb.parentNode !== document.body) {
        document.body.appendChild(lb);
    }

    function mediaCards() {
        return Array.prototype.slice.call(document.querySelectorAll('.media-card'));
    }

    function openLightbox(index) {
        if (!lb) return;
        var cards = mediaCards();
        if (!cards.length) return;
        lbIndex = ((index % cards.length) + cards.length) % cards.length;
        var card = cards[lbIndex];
        var url = card.getAttribute('data-url') || '';
        var name = card.getAttribute('data-name') || '';
        var mime = card.getAttribute('data-mime') || '';
        var poster = card.getAttribute('data-poster') || '';
        if (mime.indexOf('video/') === 0) {
            lbStage.innerHTML = '<video controls autoplay playsinline src="' + esc(url) + '"' +
                (poster ? ' poster="' + esc(poster) + '"' : '') + '></video>';
        } else if (mime.indexOf('image/') === 0 || /\.webp$/i.test(url)) {
            lbStage.innerHTML = '<img src="' + esc(url) + '" alt="' + esc(name) + '">';
        } else {
            lbStage.innerHTML = '<div class="media-placeholder" style="height:200px;width:280px;">' + esc(name || 'File') + '</div>';
        }
        lbCaption.textContent = name;
        lb.hidden = false;
        lb.removeAttribute('hidden');
        lb.setAttribute('aria-hidden', 'false');
        lb.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lb) return;
        lb.classList.remove('is-open');
        lb.hidden = true;
        lb.setAttribute('hidden', '');
        lb.setAttribute('aria-hidden', 'true');
        lbStage.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.media-open-preview').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
            var card = el.closest('.media-card');
            var cards = mediaCards();
            openLightbox(cards.indexOf(card));
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                el.click();
            }
        });
    });
    if (lb) {
        lb.querySelector('.media-lightbox-backdrop').addEventListener('click', closeLightbox);
        lb.querySelector('.media-lb-close').addEventListener('click', closeLightbox);
        lb.querySelector('.media-lb-prev').addEventListener('click', function () { openLightbox(lbIndex - 1); });
        lb.querySelector('.media-lb-next').addEventListener('click', function () { openLightbox(lbIndex + 1); });
    }
    document.addEventListener('keydown', function (e) {
        if (lb && lb.classList.contains('is-open')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') openLightbox(lbIndex - 1);
            if (e.key === 'ArrowRight') openLightbox(lbIndex + 1);
        }
        if (sidebar && sidebar.classList.contains('is-open') && e.key === 'Escape') closeSidebar();
    });

    // —— AI image generation (+ regenerate in-place) ——
    (function () {
        var modal = document.getElementById('gen-image-modal');
        var openBtn = document.getElementById('btn-generate-image');
        var closeBtn = document.getElementById('gen-image-close');
        var cancelBtn = document.getElementById('gen-image-cancel');
        var submitBtn = document.getElementById('gen-image-submit');
        var promptEl = document.getElementById('gen-prompt');
        var negEl = document.getElementById('gen-negative');
        var progressEl = document.getElementById('gen-progress');
        var statusEl = document.getElementById('gen-status');
        var titleEl = document.getElementById('gen-image-title');
        var regenBtn = document.getElementById('ms-regenerate-btn');
        var pollTimer = null;
        var busy = false;
        /** When set, generated image replaces this media id (same URL). */
        var replaceMediaId = 0;

        function showModal(opts) {
            if (!modal) return;
            opts = opts || {};
            replaceMediaId = opts.replaceMediaId ? parseInt(opts.replaceMediaId, 10) || 0 : 0;
            if (titleEl) {
                titleEl.textContent = replaceMediaId
                    ? 'Regenerate image (keep URL)'
                    : 'Generate image';
            }
            if (submitBtn) {
                submitBtn.innerHTML = replaceMediaId
                    ? '<i class="fa-solid fa-wand-magic-sparkles"></i> Regenerate'
                    : '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
            }
            // Prefill from current alt/name when regenerating
            if (replaceMediaId && promptEl && !(promptEl.value || '').trim()) {
                var alt = (document.getElementById('ms-alt') && document.getElementById('ms-alt').value) || '';
                var desc = (document.getElementById('ms-desc') && document.getElementById('ms-desc').value) || '';
                var seed = (desc || alt || '').trim();
                if (seed) promptEl.value = seed;
            }
            modal.hidden = false;
            modal.style.display = 'flex';
            if (promptEl) promptEl.focus();
        }
        function hideModal() {
            if (!modal) return;
            modal.hidden = true;
            modal.style.display = 'none';
            replaceMediaId = 0;
            if (titleEl) titleEl.textContent = 'Generate image';
            if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
        }
        function setBusy(on) {
            busy = !!on;
            if (submitBtn) submitBtn.disabled = busy;
            if (promptEl) promptEl.disabled = busy;
            if (negEl) negEl.disabled = busy;
        }
        function selectedAspect() {
            var r = document.querySelector('input[name="gen-aspect"]:checked');
            return r ? r.value : 'square';
        }

        if (openBtn) openBtn.addEventListener('click', function () { showModal({}); });
        if (regenBtn) {
            regenBtn.addEventListener('click', function () {
                if (!currentId) return;
                showModal({ replaceMediaId: currentId });
            });
        }
        if (closeBtn) closeBtn.addEventListener('click', function () { if (!busy) hideModal(); });
        if (cancelBtn) cancelBtn.addEventListener('click', function () { if (!busy) hideModal(); });
        if (modal) modal.addEventListener('click', function (e) {
            if (e.target === modal && !busy) hideModal();
        });

        function pollJob(jobId) {
            return fetch('/hq/media/jobs/' + jobId, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.job) throw new Error(data.error || 'Job not found');
                    return data.job;
                });
        }

        function kickProcess(jobId) {
            var body = new URLSearchParams();
            body.set('_mova_csrf', csrfToken);
            // Fire-and-forget style: long request may outlive the page
            return fetch('/hq/media/jobs/' + jobId + '/process', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: body.toString()
            }).then(function (r) { return r.json(); }).catch(function () {
                return null; // disconnect / timeout is OK — poll continues
            });
        }

        function onDone(job) {
            setBusy(false);
            var act = (job.actions && job.actions[0]) ? job.actions[0] : null;
            var wasReplace = !!(act && (act.type === 'media_replaced' || act.replaced));
            var keptId = wasReplace ? (act.media_id || replaceMediaId) : 0;
            hideModal();
            var msg = 'Image generated.';
            if (wasReplace) {
                msg = 'Image regenerated — same URL kept.';
                var hint = document.getElementById('ms-replace-hint');
                if (hint) hint.textContent = 'Regenerated (same URL)';
                // Bust cache on card + sidebar preview
                var card = document.querySelector('.media-card[data-id="' + keptId + '"]');
                if (card) {
                    var img = card.querySelector('.media-thumb img');
                    var thumb = card.getAttribute('data-thumb') || card.getAttribute('data-url') || '';
                    if (img && thumb) {
                        img.src = thumb + (thumb.indexOf('?') === -1 ? '?t=' : '&t=') + Date.now();
                    }
                }
                var prev = document.getElementById('ms-preview');
                if (prev && card) {
                    var u = card.getAttribute('data-url') || '';
                    prev.innerHTML = '<img src="' + esc(u) + '?t=' + Date.now() + '" alt="" style="max-width:100%;max-height:180px;object-fit:contain;border-radius:8px;">';
                }
                if (statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">' + esc(msg) + '</div>';
                }
                setTimeout(function () {
                    if (hint) hint.textContent = '';
                    window.location.href = '/hq/media?regenerated=' + encodeURIComponent(keptId);
                }, 700);
                return;
            }
            if (act && act.url) {
                msg = 'Image ready — added to the media library.';
            }
            if (statusEl) {
                statusEl.innerHTML = '<div class="alert alert-success">' + esc(msg) + ' <a href="/hq/media">Refresh</a> to see it at the top.</div>';
            }
            setTimeout(function () { window.location.href = '/hq/media?generated=1'; }, 600);
        }

        function onFail(err) {
            setBusy(false);
            if (progressEl) progressEl.textContent = '';
            if (statusEl) {
                statusEl.innerHTML = '<div class="alert alert-error">' + esc(err || 'Generation failed') + '</div>';
            }
            if (progressEl) progressEl.textContent = err || 'Failed';
        }

        function startPolling(jobId) {
            var attempts = 0;
            function tick() {
                attempts++;
                pollJob(jobId).then(function (job) {
                    if (progressEl) {
                        progressEl.textContent = job.progress || job.status || 'Working…';
                    }
                    if (job.status === 'done') {
                        onDone(job);
                        return;
                    }
                    if (job.status === 'failed' || job.status === 'cancelled') {
                        onFail(job.error || 'Generation failed');
                        return;
                    }
                    // Keep polling up to ~20 minutes
                    if (attempts < 240) {
                        pollTimer = setTimeout(tick, attempts < 10 ? 2500 : 4000);
                    } else {
                        onFail('Still running after a long wait. Refresh the media library later — the job may still complete.');
                    }
                }).catch(function (e) {
                    if (attempts < 240) {
                        pollTimer = setTimeout(tick, 4000);
                    } else {
                        onFail(e.message || 'Polling failed');
                    }
                });
            }
            tick();
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var prompt = (promptEl && promptEl.value || '').trim();
                if (!prompt) {
                    if (progressEl) progressEl.textContent = 'Please enter a prompt.';
                    return;
                }
                setBusy(true);
                if (progressEl) progressEl.textContent = 'Queuing…';
                if (statusEl) statusEl.innerHTML = '';

                var body = new URLSearchParams();
                body.set('_mova_csrf', csrfToken);
                body.set('prompt', prompt);
                body.set('aspect', selectedAspect());
                body.set('negative_prompt', (negEl && negEl.value) || '');
                body.set('client_key', (replaceMediaId ? 'regen-' : 'img-') + Date.now());
                if (replaceMediaId) {
                    body.set('replace_media_id', String(replaceMediaId));
                }

                fetch('/hq/media/generate', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body.toString()
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.job || !data.job.id) {
                        throw new Error(data.error || 'Could not queue job');
                    }
                    var jobId = data.job.id;
                    if (progressEl) progressEl.textContent = data.job.progress || 'Queued — starting…';
                    // Kick worker (survives tab close via ignore_user_abort)
                    kickProcess(jobId);
                    startPolling(jobId);
                })
                .catch(function (e) {
                    onFail(e.message || 'Request failed');
                });
            });
        }
    })();

})();
</script>
