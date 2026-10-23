<?php
/**
 * Quick setup — one guided flow: Type → Kit → Brand → Theme → Content → Review → Publish.
 * All state lives in the autosaved draft (WizardDraft); the page is driven by setup-wizard.js.
 *
 * @var list<array> $packs
 * @var list<array> $kits
 * @var list<array> $themes
 * @var list<array> $presets
 * @var list<array> $fontPairs
 * @var array $draft
 * @var bool $ai_available
 */
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$swAssetDir = \Mova\Core\Bootstrap::path('plugins') . '/mova-setup-wizard/assets';
$swVer = static function (string $rel) use ($swAssetDir): string {
    $f = $swAssetDir . '/' . $rel;
    return is_file($f) ? substr(md5((string) filemtime($f) . filesize($f)), 0, 10) : '0';
};
$boot = [
    'csrf' => \Mova\Security\Csrf::token(),
    'packs' => $packs ?? [],
    'kits' => $kits ?? [],
    'themes' => $themes ?? [],
    'presets' => $presets ?? [],
    'fontPairs' => $fontPairs ?? [],
    'draft' => $draft ?? [],
    'ai' => !empty($ai_available),
];
$steps = [
    1 => ['Type', 'What are you building?'],
    2 => ['Kit', 'Choose a starting design'],
    3 => ['Brand', 'Name, logo, colours & fonts'],
    4 => ['Theme', 'Pick the look'],
    5 => ['Content', 'Facts & copy'],
    6 => ['Review', 'Check every page'],
    7 => ['Publish', 'Go live'],
];
$packIcons = ['school' => 'fa-graduation-cap', 'organization' => 'fa-people-group', 'generic' => 'fa-briefcase'];
?>
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/css/hq-section-picker.css?v=20261023p5x">
<link rel="stylesheet" href="/assets/css/hq-themes.css?v=20261023p5x">
<link rel="stylesheet" href="/hq/setup-wizard/asset/css?v=<?= $swVer('css/setup-wizard.css') ?>">
<style>
/* Critical: one step visible even if the stylesheet fails to load */
.qs-panel{display:none}.qs-panel.is-on{display:block}
</style>

<div class="qs" id="qs" data-step="1">
  <header class="qs-top">
    <div class="qs-top__title">
      <span class="qs-kicker"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Quick setup</span>
      <h1>Build your site in a few minutes</h1>
    </div>
    <div class="qs-top__status">
      <span class="qs-save" id="qs-save" role="status" aria-live="polite"><i class="fa-solid fa-cloud" aria-hidden="true"></i> <span>Draft autosaves</span></span>
      <button type="button" class="qs-link" id="qs-reset">Start over</button>
    </div>
  </header>

  <nav class="qs-progress" aria-label="Setup steps">
    <div class="qs-progress__bar" aria-hidden="true"><i id="qs-bar"></i></div>
    <ol>
      <?php foreach ($steps as $n => [$label, $hint]): ?>
        <li><button type="button" class="qs-dot" data-go="<?= $n ?>" aria-label="Step <?= $n ?>: <?= $e($label) ?>">
          <span class="qs-dot__n"><?= $n ?></span><span class="qs-dot__l"><?= $e($label) ?></span>
        </button></li>
      <?php endforeach; ?>
    </ol>
    <p class="qs-progress__mobile" id="qs-mstep" aria-hidden="true"></p>
  </nav>

  <main class="qs-body">
    <!-- 1 · Type -->
    <section class="qs-panel" data-panel="1" aria-labelledby="qs-h1">
      <div class="qs-head">
        <h2 id="qs-h1">What are you building?</h2>
        <p>We’ll suggest pages, navigation and copy that fit. You can change everything later.</p>
      </div>
      <div class="qs-packs" role="radiogroup" aria-label="Site type">
        <?php foreach (($packs ?? []) as $p): ?>
          <label class="qs-pack">
            <input type="radio" name="pack" value="<?= $e($p['id']) ?>">
            <span class="qs-pack__icon"><i class="fa-solid <?= $e($packIcons[$p['id']] ?? 'fa-globe') ?>" aria-hidden="true"></i></span>
            <strong><?= $e($p['label']) ?></strong>
            <span><?= $e($p['description']) ?></span>
            <i class="qs-check fa-solid fa-circle-check" aria-hidden="true"></i>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 2 · Kit -->
    <section class="qs-panel" data-panel="2" aria-labelledby="qs-h2">
      <div class="qs-head">
        <h2 id="qs-h2">Choose a starting design</h2>
        <p>Real screenshots of each kit. Open one to click through every page on desktop, tablet and phone.</p>
      </div>
      <div class="qs-kits" id="qs-kits" role="radiogroup" aria-label="Kit"></div>
    </section>

    <!-- 3 · Brand -->
    <section class="qs-panel" data-panel="3" aria-labelledby="qs-h3">
      <div class="qs-split">
        <div class="qs-form">
          <div class="qs-head">
            <h2 id="qs-h3">Make it yours</h2>
            <p>Your name, logo, colours and fonts are applied across every page.</p>
          </div>
          <div class="qs-field">
            <label for="qs-name">Site name <b aria-hidden="true">*</b></label>
            <input id="qs-name" name="site_name" type="text" maxlength="80" autocomplete="organization" required placeholder="e.g. Harbour Community Trust">
            <p class="qs-err" id="qs-name-err" hidden>Add your site name to continue.</p>
          </div>
          <div class="qs-field">
            <label for="qs-tagline">Tagline <small>optional</small></label>
            <input id="qs-tagline" name="tagline" type="text" maxlength="140" placeholder="">
          </div>
          <div class="qs-field">
            <span class="qs-label">Logo <small>optional · PNG, SVG, JPG or WebP, max 2 MB</small></span>
            <div class="qs-logo" id="qs-logo">
              <div class="qs-logo__img" id="qs-logo-img"><i class="fa-regular fa-image" aria-hidden="true"></i></div>
              <div class="qs-logo__txt">
                <label class="msp-btn msp-btn--ghost" for="qs-logo-file"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload logo</label>
                <input id="qs-logo-file" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" class="qs-sr">
                <button type="button" class="qs-link" id="qs-logo-remove" hidden>Remove</button>
                <span class="qs-hint">or drop an image here. Without a logo we show your name in the header.</span>
              </div>
            </div>
          </div>
          <fieldset class="qs-field">
            <legend class="qs-label">Colours</legend>
            <div class="qs-presets" id="qs-presets" role="radiogroup" aria-label="Colour presets"></div>
            <div class="qs-colors">
              <label><input type="color" name="primary" id="qs-primary"><span>Primary</span><code id="qs-primary-v"></code></label>
              <label><input type="color" name="accent" id="qs-accent"><span>Accent</span><code id="qs-accent-v"></code></label>
              <label><input type="color" name="secondary" id="qs-secondary"><span>Secondary</span><code id="qs-secondary-v"></code></label>
            </div>
          </fieldset>
          <fieldset class="qs-field">
            <legend class="qs-label">Font pairing</legend>
            <div class="qs-fonts" id="qs-fonts" role="radiogroup" aria-label="Font pairing"></div>
          </fieldset>
        </div>
        <aside class="qs-live" aria-label="Live preview">
          <div class="qs-live__bar"><span></span><span></span><span></span><em>Live preview · home</em>
            <button type="button" class="qs-link" data-full="home">Full screen</button></div>
          <div class="qs-live__screen"><iframe id="qs-live" title="Live preview of your home page" loading="lazy"></iframe><div class="qs-live__veil">Updating…</div></div>
        </aside>
      </div>
    </section>

    <!-- 4 · Theme -->
    <section class="qs-panel" data-panel="4" aria-labelledby="qs-h4">
      <div class="qs-head">
        <h2 id="qs-h4">Pick the look</h2>
        <p>Themes change the header, footer, type and rhythm — your kit, colours and content stay. Hover to see your site live; open any theme full screen.</p>
      </div>
      <div id="qs-themes"></div>
    </section>

    <!-- 5 · Content -->
    <section class="qs-panel" data-panel="5" aria-labelledby="qs-h5">
      <div class="qs-split qs-split--even">
        <div class="qs-form">
          <div class="qs-head">
            <h2 id="qs-h5">A few facts</h2>
            <p>All optional. Anything you leave blank is simply left out — no placeholder phone numbers on your live site.</p>
          </div>
          <div class="qs-field">
            <label for="qs-about">About you <small>one or two sentences</small></label>
            <textarea id="qs-about" name="about" rows="4" maxlength="1200"></textarea>
          </div>
          <div class="qs-grid2">
            <div class="qs-field"><label for="qs-city">Town or city</label><input id="qs-city" name="city" type="text" maxlength="80" autocomplete="address-level2"></div>
            <div class="qs-field"><label for="qs-phone">Phone</label><input id="qs-phone" name="phone" type="tel" maxlength="40" autocomplete="tel"></div>
            <div class="qs-field"><label for="qs-email">Email</label><input id="qs-email" name="email" type="email" maxlength="120" autocomplete="email"></div>
            <div class="qs-field"><label for="qs-address">Address</label><input id="qs-address" name="address" type="text" maxlength="200" autocomplete="street-address"></div>
          </div>
        </div>
        <div class="qs-ai" id="qs-ai">
          <div class="qs-ai__icon"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i></div>
          <h3>Page copy</h3>
          <div id="qs-ai-body"></div>
        </div>
      </div>
    </section>

    <!-- 6 · Review -->
    <section class="qs-panel" data-panel="6" aria-labelledby="qs-h6">
      <div class="qs-head qs-head--row">
        <div>
          <h2 id="qs-h6">Review every page</h2>
          <p>This is exactly what will be published. Click links inside the preview to move between pages.</p>
        </div>
        <div class="msp-seg qs-seg" role="group" aria-label="Device">
          <button type="button" data-dev="desktop" class="is-on" aria-pressed="true"><i class="fa-solid fa-desktop" aria-hidden="true"></i><span>Desktop</span></button>
          <button type="button" data-dev="tablet" aria-pressed="false"><i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i><span>Tablet</span></button>
          <button type="button" data-dev="mobile" aria-pressed="false"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><span>Mobile</span></button>
        </div>
      </div>
      <div class="qs-tabs" id="qs-pages" role="tablist" aria-label="Pages"></div>
      <div class="qs-review" id="qs-review-stage" data-dev="desktop">
        <div class="qs-review__device"><iframe id="qs-review" title="Page preview"></iframe></div>
      </div>
      <p class="qs-ainote" id="qs-review-ai" hidden></p>
    </section>

    <!-- 7 · Publish -->
    <section class="qs-panel" data-panel="7" aria-labelledby="qs-h7">
      <div class="qs-publish" id="qs-publish">
        <div class="qs-head">
          <h2 id="qs-h7">Ready to go live</h2>
          <p>We’ll create your pages, menu, footer and homepage, apply your theme and brand, and keep any pages you already wrote untouched.</p>
        </div>
        <dl class="qs-summary" id="qs-summary"></dl>
        <div class="qs-status">
          <label class="qs-radio"><input type="radio" name="status" value="published"> <span><strong>Publish now</strong><small>Pages go live immediately.</small></span></label>
          <label class="qs-radio"><input type="radio" name="status" value="draft"> <span><strong>Save as drafts</strong><small>Review in Content first, publish later.</small></span></label>
        </div>
        <button type="button" class="qs-go" id="qs-go"><i class="fa-solid fa-rocket" aria-hidden="true"></i> <span>Publish my site</span></button>
        <p class="qs-err" id="qs-pub-err" role="alert" hidden></p>
      </div>
      <div class="qs-done" id="qs-done" hidden tabindex="-1">
        <div class="qs-done__badge"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
        <h2>Your site is live</h2>
        <p id="qs-done-msg"></p>
        <div class="qs-done__actions">
          <a class="qs-go" href="/" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View my site</a>
          <a class="msp-btn msp-btn--ghost" href="/hq/content?type=page">Edit pages</a>
          <a class="msp-btn msp-btn--ghost" href="/hq/themes">Themes</a>
          <a class="msp-btn msp-btn--ghost" href="/hq/style">Colours &amp; fonts</a>
        </div>
        <ul class="qs-done__pages" id="qs-done-pages"></ul>
      </div>
    </section>
  </main>

  <footer class="qs-foot" id="qs-foot">
    <button type="button" class="msp-btn msp-btn--ghost" id="qs-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
    <span class="qs-foot__hint" id="qs-hint"></span>
    <button type="button" class="qs-next" id="qs-next"><span>Continue</span> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
  </footer>
</div>

<noscript><p class="qs-noscript">Quick setup needs JavaScript. You can still build pages in <a href="/hq/content">Content</a> and choose a look in <a href="/hq/themes">Themes</a>.</p></noscript>
<script type="application/json" id="qs-boot"><?= json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/hq-section-picker.js?v=20261023p5x"></script>
<script src="/assets/js/hq-theme-gallery.js?v=p3"></script>
<script src="/hq/setup-wizard/asset/js?v=<?= $swVer('js/setup-wizard.js') ?>"></script>
