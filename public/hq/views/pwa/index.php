<?php
use Mova\Security\Csrf;
use Mova\Site\Pwa;

/** @var array $raw @var array $cfg @var array $defaults @var bool $gd @var bool $https @var bool $secure */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$val = static fn(string $k): string => (string) ($raw['pwa_' . $k] ?? '');
$tip = static function (string $text) use ($e): string {
    return '<span class="hq-help" tabindex="0" role="note" data-tip="' . $e($text) . '"><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span class="hq-sr-only">' . $e($text) . '</span></span>';
};
$iconPreview = Pwa::iconUrl('512') ?? '/pwa-icon.svg';
$saved = isset($_GET['saved']);
$err = (string) ($_GET['err'] ?? '');
?>
<link rel="stylesheet" href="/assets/css/pwa.css?v=20261023p5x">
<div class="pwa-hq" data-pwa-hq>
  <div class="toolbar">
    <h2 style="margin:0;font-size:1rem;"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i> App (PWA)</h2>
    <?php if ($cfg['enabled']): ?>
      <span class="pwa-pill pwa-pill--on"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Installable</span>
    <?php else: ?>
      <span class="pwa-pill">Off</span>
    <?php endif; ?>
  </div>

  <?php if ($saved && $err === ''): ?><div class="alert alert-success">App settings saved. Visitors get the new version on their next visit.</div><?php endif; ?>
  <?php if ($err === 'icon'): ?><div class="alert alert-error">The icon upload was rejected. Use a PNG, JPG, WebP or GIF up to 5 MB (square, 512×512 or larger works best). Other settings were saved.</div><?php endif; ?>
  <?php if (!$https): ?>
    <div class="alert alert-warning pwa-https" role="status">
      <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
      <div><strong>HTTPS required.</strong> Browsers only install apps and run service workers on sites served over HTTPS<?= $secure ? ' (localhost is allowed for testing, which is why this page works here)' : '' ?>. Turn on SSL for your domain before you enable the app on your live site.</div>
    </div>
  <?php endif; ?>
  <?php if (!$gd): ?>
    <div class="alert alert-warning" role="status"><strong>GD isn't available on this server.</strong> Mova can't resize icons, so it uses your logo as-is plus a vector icon. For the best install experience, upload a square 512×512 PNG.</div>
  <?php endif; ?>

  <form method="post" action="/hq/pwa" enctype="multipart/form-data" class="pwa-grid" id="pwa-form">
    <?= Csrf::field() ?>
    <div class="pwa-main">
      <section class="panel pwa-card">
        <label class="pwa-switch">
          <input type="checkbox" name="pwa_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?> id="pwa_enabled">
          <span class="pwa-switch__track" aria-hidden="true"></span>
          <span><strong>Make this site installable</strong> <?= $tip('Adds a web app manifest and service worker so visitors can install your site like an app on phones and desktops.') ?><br><small>Manifest, service worker and app icons for every theme.</small></span>
        </label>
      </section>

      <section class="panel pwa-card">
        <h3>Identity</h3>
        <div class="pwa-row">
          <div class="form-group">
            <label for="pwa_name">App name <?= $tip('Shown in install prompts and app lists. Leave blank to use your site name.') ?></label>
            <input type="text" id="pwa_name" name="pwa_name" maxlength="60" value="<?= $e($val('name')) ?>" placeholder="<?= $e($defaults['name']) ?>" data-pwa-live="name">
          </div>
          <div class="form-group">
            <label for="pwa_short_name">Short name <?= $tip('Shown under the home-screen icon. Keep it to 12 characters or fewer so it isn\'t cut off.') ?></label>
            <input type="text" id="pwa_short_name" name="pwa_short_name" maxlength="24" value="<?= $e($val('short_name')) ?>" placeholder="<?= $e($defaults['short_name']) ?>" data-pwa-live="short">
          </div>
        </div>
        <div class="pwa-row">
          <div class="form-group">
            <label for="pwa_theme_hex">Theme colour <?= $tip('Colours the browser toolbar and the app title bar. Defaults to your brand colour.') ?></label>
            <div class="pwa-color">
              <input type="color" id="pwa_theme_picker" value="<?= $e($cfg['theme_color']) ?>" aria-label="Pick theme colour">
              <input type="text" id="pwa_theme_hex" name="pwa_theme_color" value="<?= $e($val('theme_color')) ?>" placeholder="<?= $e($defaults['theme_color']) ?>" pattern="#[0-9a-fA-F]{3,6}" data-pwa-live="theme">
            </div>
          </div>
          <div class="form-group">
            <label for="pwa_bg_hex">Background colour <?= $tip('The splash screen colour shown while the app opens. Defaults to your theme background.') ?></label>
            <div class="pwa-color">
              <input type="color" id="pwa_bg_picker" value="<?= $e($cfg['bg_color']) ?>" aria-label="Pick background colour">
              <input type="text" id="pwa_bg_hex" name="pwa_bg_color" value="<?= $e($val('bg_color')) ?>" placeholder="<?= $e($defaults['bg_color']) ?>" pattern="#[0-9a-fA-F]{3,6}" data-pwa-live="bg">
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="pwa_icon">App icon <?= $tip('Mova makes 192px, 512px, maskable and Apple touch icons from this image. Leave blank to use your site logo, or a lettermark in your theme colour if there\'s no logo.') ?></label>
          <div class="pwa-icon-field">
            <img src="<?= $e($iconPreview) ?>" alt="Current app icon" width="56" height="56" class="pwa-icon-thumb">
            <div style="flex:1;min-width:0;">
              <input type="text" id="pwa_icon" name="pwa_icon" value="<?= $e($val('icon')) ?>" placeholder="<?= $e($defaults['icon'] !== '' ? $defaults['icon'] : 'Site logo (none set: lettermark)') ?>">
              <input type="file" name="pwa_icon_file" accept="image/png,image/jpeg,image/webp,image/gif" aria-label="Upload icon" style="margin-top:.5rem;">
            </div>
          </div>
        </div>
      </section>

      <section class="panel pwa-card">
        <h3>Behaviour</h3>
        <div class="pwa-row">
          <div class="form-group">
            <label for="pwa_display">Display mode <?= $tip('Standalone looks like a native app with no browser UI. Minimal UI keeps back and reload buttons. Fullscreen hides the status bar. Browser opens a normal tab.') ?></label>
            <select id="pwa_display" name="pwa_display">
              <?php foreach (['standalone' => 'Standalone (recommended)', 'minimal-ui' => 'Minimal UI', 'fullscreen' => 'Fullscreen', 'browser' => 'Browser tab'] as $k => $l): ?>
                <option value="<?= $e($k) ?>" <?= $cfg['display'] === $k ? 'selected' : '' ?>><?= $e($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="pwa_start_url">Start URL <?= $tip('The page the app opens on. Must be a path on this site. HQ and API paths aren\'t allowed.') ?></label>
            <input type="text" id="pwa_start_url" name="pwa_start_url" value="<?= $e($val('start_url')) ?>" placeholder="/">
          </div>
        </div>
        <label class="pwa-check">
          <input type="checkbox" name="pwa_offline" value="1" <?= $cfg['offline'] ? 'checked' : '' ?>>
          <span>Offline page <?= $tip('When a visitor is offline and opens a page they haven\'t visited before, they see a branded "You\'re offline" page instead of the browser error. Pages they have visited still open from the cache.') ?></span>
        </label>
      </section>

      <section class="panel pwa-card">
        <h3>Install banner</h3>
        <label class="pwa-check">
          <input type="checkbox" name="pwa_banner" value="1" <?= $cfg['banner'] ? 'checked' : '' ?> id="pwa_banner">
          <span>Show an install banner <?= $tip('A small, dismissible card that invites visitors to install. It never shows inside the installed app, and on iPhone/iPad it explains Share > Add to Home Screen.') ?></span>
        </label>
        <div class="form-group">
          <label for="pwa_banner_msg">Message</label>
          <input type="text" id="pwa_banner_msg" name="pwa_banner_msg" maxlength="160" value="<?= $e($val('banner_msg')) ?>" placeholder="<?= $e($defaults['banner_msg']) ?>" data-pwa-live="msg">
        </div>
        <div class="pwa-row pwa-row--3">
          <div class="form-group">
            <label for="pwa_banner_trigger">Show it <?= $tip('Wait a few seconds on the first page, or wait until the visitor has viewed several pages.') ?></label>
            <select id="pwa_banner_trigger" name="pwa_banner_trigger">
              <option value="delay" <?= $cfg['banner_trigger'] === 'delay' ? 'selected' : '' ?>>After a delay</option>
              <option value="pageviews" <?= $cfg['banner_trigger'] === 'pageviews' ? 'selected' : '' ?>>After N page views</option>
            </select>
          </div>
          <div class="form-group" data-pwa-when="delay">
            <label for="pwa_banner_delay">Delay (seconds)</label>
            <input type="number" id="pwa_banner_delay" name="pwa_banner_delay" min="0" max="600" value="<?= (int) $cfg['banner_delay'] ?>">
          </div>
          <div class="form-group" data-pwa-when="pageviews">
            <label for="pwa_banner_pageviews">Page views</label>
            <input type="number" id="pwa_banner_pageviews" name="pwa_banner_pageviews" min="1" max="50" value="<?= (int) $cfg['banner_pageviews'] ?>">
          </div>
          <div class="form-group">
            <label for="pwa_banner_snooze_days">Snooze after dismiss <?= $tip('How many days to wait before showing the banner again after a visitor closes it. 0 means show it again on the next visit.') ?></label>
            <input type="number" id="pwa_banner_snooze_days" name="pwa_banner_snooze_days" min="0" max="365" value="<?= (int) $cfg['banner_snooze_days'] ?>">
          </div>
        </div>
      </section>

      <div class="pwa-actions">
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save app settings</button>
        <?php if ($cfg['enabled']): ?>
          <a class="btn" href="/manifest.webmanifest" target="_blank" rel="noopener">View manifest</a>
        <?php endif; ?>
      </div>
    </div>

    <aside class="pwa-side" aria-label="Live preview">
      <section class="panel pwa-card pwa-sticky">
        <h3>Preview</h3>
        <div class="pwa-phone" id="pwa-phone" style="--pwa-theme:<?= $e($cfg['theme_color']) ?>;--pwa-bg:<?= $e($cfg['bg_color']) ?>;">
          <div class="pwa-phone__bar"></div>
          <div class="pwa-phone__screen">
            <div class="pwa-home">
              <span class="pwa-home__app pwa-home__app--ghost"></span>
              <span class="pwa-home__app pwa-home__app--ghost"></span>
              <figure class="pwa-home__mine">
                <img src="<?= $e($iconPreview) ?>" alt="" width="60" height="60">
                <figcaption id="pwa-prev-short"><?= $e($cfg['short_name']) ?></figcaption>
              </figure>
              <span class="pwa-home__app pwa-home__app--ghost"></span>
            </div>
            <div class="pwa-preview-banner" style="--color-primary:<?= $e($cfg['theme_color']) ?>;">
              <div class="mova-pwa-banner is-visible" aria-hidden="true">
                <img class="mova-pwa-icon" src="<?= $e($iconPreview) ?>" alt="" width="48" height="48">
                <div class="mova-pwa-body">
                  <p class="mova-pwa-title" id="pwa-prev-name"><?= $e($cfg['name']) ?></p>
                  <p class="mova-pwa-msg" id="pwa-prev-msg"><?= $e($cfg['banner_msg']) ?></p>
                  <div class="mova-pwa-actions"><span class="mova-pwa-btn mova-pwa-btn--ghost">Not now</span><span class="mova-pwa-btn mova-pwa-btn--primary">Install</span></div>
                </div>
                <span class="mova-pwa-close"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
              </div>
            </div>
          </div>
        </div>
        <p class="pwa-note">The live banner takes on each theme's fonts, colours and corner radius.</p>
      </section>
    </aside>
  </form>
</div>

<style>
.pwa-hq .alert-warning{background:rgba(245,158,11,.12);color:#92400e;border:1px solid rgba(245,158,11,.35);}
[data-theme=dark] .pwa-hq .alert-warning{color:#fcd34d;}
.pwa-hq .toolbar{display:flex;align-items:center;gap:.75rem;}
.pwa-pill{display:inline-flex;align-items:center;gap:.35rem;font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;background:var(--hq-surface-2,#f1f5f9);color:var(--hq-muted,#64748b);}
.pwa-pill--on{background:#dcfce7;color:#166534;}
.pwa-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start;}
.pwa-main{display:flex;flex-direction:column;gap:16px;min-width:0;}
.pwa-card{margin:0;}
.pwa-card h3{margin:0 0 .9rem;font-size:.95rem;}
.pwa-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.pwa-row--3{grid-template-columns:repeat(4,minmax(0,1fr));}
.pwa-row .form-group{min-width:0;}
.pwa-color{display:flex;gap:.5rem;align-items:center;}
.pwa-color input[type=color]{width:42px;height:38px;padding:2px;border-radius:8px;flex:none;cursor:pointer;}
.pwa-color input[type=text]{flex:1;min-width:0;}
.pwa-icon-field{display:flex;gap:14px;align-items:flex-start;}
.pwa-icon-thumb{width:56px;height:56px;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.15);flex:none;background:#fff;}
.pwa-switch{display:flex;gap:12px;align-items:flex-start;cursor:pointer;margin:0;}
.pwa-switch input{position:absolute;opacity:0;width:1px;height:1px;}
.pwa-switch__track{flex:none;width:42px;height:24px;border-radius:999px;background:#cbd5e1;position:relative;transition:background .2s;margin-top:2px;}
.pwa-switch__track::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s;}
.pwa-switch input:checked + .pwa-switch__track{background:var(--hq-primary,#2563eb);}
.pwa-switch input:checked + .pwa-switch__track::after{transform:translateX(18px);}
.pwa-switch input:focus-visible + .pwa-switch__track{outline:3px solid rgba(37,99,235,.35);outline-offset:2px;}
.pwa-switch small{color:var(--hq-muted,#64748b);}
.pwa-check{display:flex;gap:.55rem;align-items:center;font-weight:500;margin:0 0 1rem;}
.pwa-https{display:flex;gap:.75rem;align-items:flex-start;}
.pwa-https i{margin-top:3px;}
.pwa-actions{display:flex;gap:.6rem;align-items:center;}
.pwa-sticky{position:sticky;top:84px;}
.pwa-phone{width:100%;max-width:300px;margin:0 auto;border-radius:34px;padding:10px;background:#0f172a;box-shadow:0 20px 50px -20px rgba(15,23,42,.6);}
.pwa-phone__bar{height:14px;background:var(--pwa-theme);border-radius:24px 24px 0 0;}
.pwa-phone__screen{position:relative;height:420px;border-radius:0 0 24px 24px;overflow:hidden;background:linear-gradient(160deg,color-mix(in srgb,var(--pwa-theme) 35%,#1e293b),#0f172a);}
.pwa-home{display:grid;grid-template-columns:repeat(4,1fr);gap:16px 10px;padding:22px 16px;}
.pwa-home__app--ghost{display:block;width:52px;height:52px;margin:0 auto;border-radius:14px;background:rgba(255,255,255,.14);}
.pwa-home__mine{margin:0;text-align:center;}
.pwa-home__mine img{width:52px;height:52px;border-radius:14px;display:block;margin:0 auto;box-shadow:0 4px 10px rgba(0,0,0,.35);}
.pwa-home__mine figcaption{color:#fff;font-size:11px;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pwa-preview-banner{position:absolute;left:8px;right:8px;bottom:10px;}
.pwa-preview-banner .mova-pwa-banner{position:relative;left:auto;right:auto;bottom:auto;width:auto;padding:12px 34px 12px 12px;gap:10px;font-size:13px;z-index:auto;}
.pwa-preview-banner .mova-pwa-icon{width:40px;height:40px;border-radius:10px;}
.pwa-preview-banner .mova-pwa-title{font-size:13.5px;}
.pwa-preview-banner .mova-pwa-msg{font-size:12.5px;}
.pwa-preview-banner .mova-pwa-btn{font-size:12.5px;min-height:32px;padding:8px 12px;cursor:default;}
.pwa-note{font-size:12px;color:var(--hq-muted,#64748b);margin:.75rem 0 0;text-align:center;}
@media (max-width:1100px){.pwa-grid{grid-template-columns:1fr;}.pwa-sticky{position:static;}.pwa-row--3{grid-template-columns:1fr 1fr;}}
@media (max-width:600px){.pwa-row,.pwa-row--3{grid-template-columns:1fr;}}
</style>
<script>
(function () {
  var f = document.getElementById('pwa-form');
  if (!f) return;
  var $ = function (id) { return document.getElementById(id); };
  var phone = $('pwa-phone'), banner = f.querySelector('.pwa-preview-banner');
  function pair(picker, hex, apply) {
    var p = $(picker), h = $(hex);
    p.addEventListener('input', function () { h.value = p.value; apply(p.value); });
    h.addEventListener('input', function () { if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(h.value)) { p.value = h.value.length === 4 ? '#' + h.value.slice(1).replace(/./g, '$&$&') : h.value; apply(h.value); } else if (!h.value) { apply(h.placeholder); } });
  }
  pair('pwa_theme_picker', 'pwa_theme_hex', function (c) { phone.style.setProperty('--pwa-theme', c); banner.style.setProperty('--color-primary', c); });
  pair('pwa_bg_picker', 'pwa_bg_hex', function (c) { phone.style.setProperty('--pwa-bg', c); });
  [['pwa_name', 'pwa-prev-name'], ['pwa_short_name', 'pwa-prev-short'], ['pwa_banner_msg', 'pwa-prev-msg']].forEach(function (m) {
    var i = $(m[0]), o = $(m[1]);
    i.addEventListener('input', function () { o.textContent = i.value.trim() || i.placeholder; });
  });
  var trig = $('pwa_banner_trigger');
  function sync() { f.querySelectorAll('[data-pwa-when]').forEach(function (g) { g.hidden = g.getAttribute('data-pwa-when') !== trig.value; }); }
  trig.addEventListener('change', sync); sync();
  var bt = $('pwa_banner');
  function sb() { banner.style.opacity = bt.checked ? '1' : '.35'; }
  bt.addEventListener('change', sb); sb();
})();
</script>
