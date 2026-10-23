<?php
/** @var list<array> $themes */
/** @var string $active */
/** @var bool $hasContent */
/** @var string $demoKit */
$themes = $themes ?? [];
$hasContent = !empty($hasContent);
$demoKit = $demoKit ?? 'org-modern';
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$activeName = '';
foreach ($themes as $t) {
    if (!empty($t['active'])) {
        $activeName = $t['name'];
    }
}
?>
<link rel="stylesheet" href="/assets/css/hq-section-picker.css?v=20261023p5x">
<link rel="stylesheet" href="/assets/css/hq-themes.css?v=20261023p5x">
<link rel="stylesheet" href="/assets/fonts/fonts.css">

<div class="mtg">
  <header class="mtg-hero">
    <div>
      <p class="mtg-eyebrow">Design · Themes</p>
      <h1>Pick the look of your site</h1>
      <p>Every theme is responsive, dark-mode ready and uses your brand colours, fonts and logo.
        Hover a theme to see it live, preview it on your own pages at desktop, tablet or phone size, then apply it in one click — your content stays exactly as it is.</p>
    </div>
    <div class="mtg-hero-actions" style="display:flex;gap:.5rem;flex-wrap:wrap">
      <a class="msp-btn msp-btn--ghost" href="/hq/style">Colours &amp; fonts</a>
      <a class="msp-btn msp-btn--ghost" href="/hq/setup-wizard">Quick setup</a>
    </div>
  </header>
  <div class="mtg-bar">
    <span class="mtg-note">
      Active: <strong id="mtg-active-name"><?= $e($activeName ?: '—') ?></strong>
      · Live previews show <?= $hasContent ? 'your own pages' : 'demo content until you publish pages' ?>.
    </span>
  </div>
  <div id="mtg-grid" aria-live="polite"></div>
  <noscript>
    <form method="post" action="/hq/themes/apply">
      <?= \Mova\Security\Csrf::field() ?>
      <?php foreach ($themes as $t): ?>
        <button class="msp-btn msp-btn--ghost" name="theme" value="<?= $e($t['slug']) ?>">Apply <?= $e($t['name']) ?></button>
      <?php endforeach; ?>
    </form>
  </noscript>
</div>

<script src="/assets/js/hq-section-picker.js?v=20261023p5x"></script>
<script src="/assets/js/hq-theme-gallery.js?v=p3"></script>
<script>
(function () {
  var themes = <?= json_encode($themes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
  var csrf = <?= json_encode(\Mova\Security\Csrf::token()) ?>;
  var hasContent = <?= $hasContent ? 'true' : 'false' ?>;
  var demoKit = <?= json_encode($demoKit) ?>;
  var names = {}; themes.forEach(function (t) { names[t.slug] = t.name; });
  MovaThemeGallery.mount(document.getElementById('mtg-grid'), {
    themes: themes,
    liveUrl: function (slug) {
      return hasContent ? '/?mova_theme=' + encodeURIComponent(slug)
        : '/_preview/kit/' + demoKit + '/home?font_pair=theme&theme=' + encodeURIComponent(slug);
    },
    onApply: function (slug) {
      var fd = new FormData(); fd.append('theme', slug);
      return fetch('/hq/themes/apply', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Could not apply the theme');
          document.getElementById('mtg-active-name').textContent = names[slug] || slug;
          return true;
        });
    }
  });
})();
</script>
