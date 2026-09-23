<?php
/**
 * Public gallery — explore / single gallery / search
 * Variables: $mode, $query, $sidebar, $recent, $stream, $gallery, $items, $searchResults
 */
$mode = $mode ?? 'explore';
$query = $query ?? '';
$sidebar = $sidebar ?? [];
$recent = $recent ?? [];
$stream = $stream ?? ['items' => [], 'has_more' => false, 'next_before' => null];
$gallery = $gallery ?? null;
$items = $items ?? [];
$searchResults = $searchResults ?? null;

$streamItems = $stream['items'] ?? [];
$hasMore = !empty($stream['has_more']);
$nextBefore = $stream['next_before'] ?? null;

// Group stream by month label
$months = [];
foreach ($streamItems as $img) {
    $key = substr((string) ($img['created_at'] ?? ''), 0, 7);
    if ($key === '') {
        $key = 'unknown';
    }
    if (!isset($months[$key])) {
        $months[$key] = [];
    }
    $months[$key][] = $img;
}

$formatMonth = static function (string $ym): string {
    if ($ym === 'unknown' || strlen($ym) < 7) {
        return 'Undated';
    }
    $ts = strtotime($ym . '-01');
    return $ts ? date('F Y', $ts) : $ym;
};

$formatDate = static function (?string $iso): string {
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : '';
};
?>
<link rel="stylesheet" href="/assets/css/gallery.css?v=20260923">

<section class="mova-gallery" data-mode="<?= htmlspecialchars($mode) ?>"
         data-next-before="<?= htmlspecialchars((string) $nextBefore) ?>"
         data-has-more="<?= $hasMore ? '1' : '0' ?>"
         <?php if ($gallery): ?>data-gallery-slug="<?= htmlspecialchars($gallery['slug']) ?>"<?php endif; ?>>

  <aside class="mova-gallery-sidebar" id="gallery-sidebar" aria-label="Saved galleries">
    <div class="mova-gallery-sidebar-head">
      <h2>Galleries</h2>
      <button type="button" class="mova-gallery-sidebar-close" data-sidebar-close aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <nav class="mova-gallery-sidebar-nav">
      <a href="/gallery" class="mova-gallery-side-link<?= $mode === 'explore' || $mode === 'search' ? ' is-active' : '' ?>">
        <i class="fa-solid fa-border-all"></i> Explore
      </a>
      <?php if (empty($sidebar)): ?>
        <p class="mova-gallery-side-empty">No saved galleries yet.</p>
      <?php else: ?>
        <?php foreach ($sidebar as $g): ?>
          <a href="/gallery/<?= htmlspecialchars($g['slug']) ?>"
             class="mova-gallery-side-link<?= ($gallery && (int)$gallery['id'] === (int)$g['id']) ? ' is-active' : '' ?>">
            <span class="mova-gallery-side-title"><?= htmlspecialchars($g['title']) ?></span>
            <span class="mova-gallery-side-meta"><?= (int)($g['item_count'] ?? 0) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="mova-gallery-main">
    <div class="mova-gallery-toolbar">
      <button type="button" class="mova-gallery-menu-btn" data-sidebar-open aria-label="Open galleries">
        <i class="fa-solid fa-bars"></i>
      </button>
      <form class="mova-gallery-search" method="get" action="/gallery" role="search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($query) ?>"
               placeholder="Search titles, descriptions, dates…"
               autocomplete="off" aria-label="Search gallery">
      </form>
    </div>

    <?php if ($mode === 'search' && $searchResults !== null): ?>
      <header class="mova-gallery-page-head">
        <h1>Search results</h1>
        <p class="mova-gallery-lead">
          <?= count($searchResults['galleries'] ?? []) + count($searchResults['images'] ?? []) ?>
          match<?= (count($searchResults['galleries'] ?? []) + count($searchResults['images'] ?? [])) === 1 ? '' : 'es' ?>
          for “<?= htmlspecialchars($query) ?>”
        </p>
      </header>

      <?php if (!empty($searchResults['galleries'])): ?>
        <h2 class="mova-gallery-section-title">Galleries</h2>
        <div class="mova-gallery-cards">
          <?php foreach ($searchResults['galleries'] as $g): ?>
            <a class="mova-gallery-card" href="/gallery/<?= htmlspecialchars($g['slug']) ?>">
              <div class="mova-gallery-card-thumb">
                <?php if (!empty($g['cover_thumb'])): ?>
                  <img src="<?= htmlspecialchars($g['cover_thumb']) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <div class="mova-gallery-card-placeholder"><i class="fa-solid fa-images"></i></div>
                <?php endif; ?>
              </div>
              <div class="mova-gallery-card-body">
                <strong><?= htmlspecialchars($g['title']) ?></strong>
                <?php if (!empty($g['description'])): ?>
                  <span><?= htmlspecialchars(mb_strimwidth($g['description'], 0, 80, '…')) ?></span>
                <?php endif; ?>
                <span class="mova-gallery-card-meta"><?= (int)($g['item_count'] ?? 0) ?> images</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($searchResults['images'])): ?>
        <h2 class="mova-gallery-section-title">Images</h2>
        <div class="mova-gallery-grid" data-gallery-grid data-context="search">
          <?php foreach ($searchResults['images'] as $idx => $img): ?>
            <button type="button" class="mova-gallery-tile"
                    data-lightbox-index="<?= (int)$idx ?>"
                    data-url="<?= htmlspecialchars($img['url'] ?? '') ?>"
                    data-thumb="<?= htmlspecialchars($img['thumb_url'] ?? '') ?>"
                    data-alt="<?= htmlspecialchars($img['alt'] ?? '') ?>"
                    data-caption="<?= htmlspecialchars($img['caption'] ?? ($img['alt'] ?? '')) ?>"
                    data-date="<?= htmlspecialchars($formatDate($img['created_at'] ?? null)) ?>">
              <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url'] ?? '') ?>"
                   alt="<?= htmlspecialchars($img['alt'] ?? '') ?>" loading="lazy">
              <?php if (!empty($img['gallery_title'])): ?>
                <span class="mova-gallery-tile-badge"><?= htmlspecialchars($img['gallery_title']) ?></span>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (empty($searchResults['galleries']) && empty($searchResults['images'])): ?>
        <div class="mova-gallery-empty">
          <i class="fa-solid fa-magnifying-glass"></i>
          <p>No matches. Try different keywords.</p>
        </div>
      <?php endif; ?>

    <?php elseif ($mode === 'gallery' && $gallery): ?>
      <header class="mova-gallery-page-head">
        <div class="mova-gallery-page-head-row">
          <div>
            <h1><?= htmlspecialchars($gallery['title']) ?></h1>
            <p class="mova-gallery-meta">
              <?= $formatDate($gallery['created_at'] ?? null) ?>
              · <?= count($items) ?> image<?= count($items) === 1 ? '' : 's' ?>
            </p>
            <?php if (!empty($gallery['description'])): ?>
              <p class="mova-gallery-lead"><?= nl2br(htmlspecialchars($gallery['description'])) ?></p>
            <?php endif; ?>
          </div>
          <?php if (count($items) > 0): ?>
            <button type="button" class="mova-gallery-slideshow-btn" data-slideshow-start>
              <i class="fa-solid fa-play"></i> Slideshow
            </button>
          <?php endif; ?>
        </div>
      </header>

      <?php if (empty($items)): ?>
        <div class="mova-gallery-empty">
          <i class="fa-solid fa-images"></i>
          <p>This gallery has no images yet.</p>
        </div>
      <?php else: ?>
        <div class="mova-gallery-grid" data-gallery-grid data-context="gallery">
          <?php foreach ($items as $idx => $it): ?>
            <button type="button" class="mova-gallery-tile"
                    data-lightbox-index="<?= (int)$idx ?>"
                    data-url="<?= htmlspecialchars($it['url'] ?? '') ?>"
                    data-thumb="<?= htmlspecialchars($it['thumb_url'] ?? '') ?>"
                    data-alt="<?= htmlspecialchars($it['alt'] ?? '') ?>"
                    data-caption="<?= htmlspecialchars($it['caption'] ?? ($it['alt'] ?? '')) ?>"
                    data-date="<?= htmlspecialchars($formatDate($it['date'] ?? null)) ?>">
              <img src="<?= htmlspecialchars($it['thumb_url'] ?? $it['url'] ?? '') ?>"
                   alt="<?= htmlspecialchars($it['alt'] ?? '') ?>" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: /* explore */ ?>
      <header class="mova-gallery-page-head">
        <h1>Explore our gallery</h1>
        <?php if (!empty($recent)): ?>
          <p class="mova-gallery-placeholders">
            <?php
            $bits = [];
            foreach (array_slice($recent, 0, 4) as $g) {
                $bits[] = htmlspecialchars(mb_strimwidth($g['title'], 0, 28, '…'));
            }
            echo implode(' · ', $bits);
            ?>
          </p>
        <?php else: ?>
          <p class="mova-gallery-lead">Browse recent collections and the latest images.</p>
        <?php endif; ?>
      </header>

      <?php if (!empty($recent)): ?>
        <div class="mova-gallery-cards mova-gallery-cards--row">
          <?php foreach ($recent as $g): ?>
            <a class="mova-gallery-card" href="/gallery/<?= htmlspecialchars($g['slug']) ?>">
              <div class="mova-gallery-card-thumb">
                <?php if (!empty($g['cover_thumb'])): ?>
                  <img src="<?= htmlspecialchars($g['cover_thumb']) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <div class="mova-gallery-card-placeholder"><i class="fa-solid fa-images"></i></div>
                <?php endif; ?>
              </div>
              <div class="mova-gallery-card-body">
                <strong><?= htmlspecialchars($g['title']) ?></strong>
                <span class="mova-gallery-card-meta">
                  <?= (int)($g['item_count'] ?? 0) ?> · <?= $formatDate($g['updated_at'] ?? null) ?>
                </span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div id="mova-gallery-stream">
        <?php if (empty($months)): ?>
          <div class="mova-gallery-empty">
            <i class="fa-solid fa-image"></i>
            <p>No images yet. Upload media in HQ to populate the gallery.</p>
          </div>
        <?php else: ?>
          <?php
          $globalIdx = 0;
          foreach ($months as $ym => $imgs):
          ?>
            <h2 class="mova-gallery-section-title"><?= htmlspecialchars($formatMonth($ym)) ?></h2>
            <div class="mova-gallery-grid" data-gallery-grid data-context="stream">
              <?php foreach ($imgs as $img): ?>
                <button type="button" class="mova-gallery-tile"
                        data-lightbox-index="<?= (int)$globalIdx ?>"
                        data-url="<?= htmlspecialchars($img['url'] ?? '') ?>"
                        data-thumb="<?= htmlspecialchars($img['thumb_url'] ?? '') ?>"
                        data-alt="<?= htmlspecialchars($img['alt'] ?? '') ?>"
                        data-caption="<?= htmlspecialchars($img['alt'] ?? '') ?>"
                        data-date="<?= htmlspecialchars($formatDate($img['created_at'] ?? null)) ?>">
                  <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url'] ?? '') ?>"
                       alt="<?= htmlspecialchars($img['alt'] ?? '') ?>" loading="lazy">
                </button>
                <?php $globalIdx++; ?>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($hasMore): ?>
        <div class="mova-gallery-more-wrap">
          <button type="button" class="mova-gallery-more-btn" data-load-more>
            See more
          </button>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Lightbox -->
<div class="mova-lightbox" id="mova-lightbox" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="Image preview">
  <button type="button" class="mova-lightbox-close" data-lb-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
  <button type="button" class="mova-lightbox-nav mova-lightbox-prev" data-lb-prev aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
  <button type="button" class="mova-lightbox-nav mova-lightbox-next" data-lb-next aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
  <div class="mova-lightbox-stage">
    <img src="" alt="" id="mova-lightbox-img">
    <div class="mova-lightbox-meta">
      <p class="mova-lightbox-caption" id="mova-lightbox-caption"></p>
      <p class="mova-lightbox-date" id="mova-lightbox-date"></p>
    </div>
  </div>
  <div class="mova-lightbox-slide-controls" id="mova-lightbox-slide-controls" hidden>
    <button type="button" data-lb-slide-toggle aria-label="Pause slideshow"><i class="fa-solid fa-pause"></i></button>
  </div>
</div>

<script src="/assets/js/gallery.js?v=20260923" defer></script>
