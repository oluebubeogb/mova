<?php
/** @var list<array{id:string,label:string,description:string}> $packs */
/** @var bool $ai_available */
/** @var list $kits */
$packs = $packs ?? [];
$kits = $kits ?? [];
$ai_available = !empty($ai_available);
$csrf = \Mova\Security\Csrf::token();
?>
<style id="sw-critical">
/* Critical: only one step visible even if external CSS fails */
.sw-wizard .sw-panel { display: none !important; }
.sw-wizard .sw-panel.is-active { display: block !important; }
.sw-progress {
  display: flex; flex-wrap: wrap; gap: 0.5rem;
  margin: 1.25rem 0 1.5rem;
}
.sw-step {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.45rem 0.85rem; border-radius: 999px;
  border: 1px solid var(--hq-border, #e5e7eb);
  background: var(--hq-surface, #fff);
  color: var(--hq-muted, #6b7280);
  font-size: 0.875rem; cursor: pointer;
}
.sw-step span {
  display: inline-flex; width: 1.35rem; height: 1.35rem;
  align-items: center; justify-content: center; border-radius: 50%;
  background: var(--hq-border, #e5e7eb); font-weight: 600; font-size: 0.75rem;
}
.sw-step.is-active {
  border-color: var(--hq-accent, #2563eb);
  color: var(--hq-text, #111); font-weight: 600;
}
.sw-step.is-active span, .sw-step.is-done span {
  background: var(--hq-accent, #2563eb); color: #fff;
}
</style>
<link rel="stylesheet" href="/assets/css/hq-setup-wizard.css?v=112">

<section class="hq-landing sw-wizard" id="setup-wizard" data-ai="<?= $ai_available ? '1' : '0' ?>">
  <header class="hq-landing-header">
    <p class="hq-landing-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Quick Setup</p>
    <h1 class="hq-landing-title">Build your site in minutes</h1>
    <p class="hq-landing-desc">Choose a pack, set brand colors, add a few facts — we create pages, nav, palette, and footer columns. Header theme toggle and Feed · llms.txt stay as usual.</p>
  </header>

  <div class="sw-progress" role="navigation" aria-label="Wizard steps">
    <button type="button" class="sw-step is-active" data-step="1"><span>1</span> Context</button>
    <button type="button" class="sw-step" data-step="2"><span>2</span> Brand</button>
    <button type="button" class="sw-step" data-step="3"><span>3</span> Structure</button>
    <button type="button" class="sw-step" data-step="4"><span>4</span> Create</button>
  </div>

  <form id="sw-form" class="sw-form" enctype="multipart/form-data">
    <input type="hidden" name="_mova_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="palette_json" id="sw-palette-json" value="">
    <input type="hidden" name="pack_id" id="sw-pack-id" value="school">

    <!-- Step 1: Context -->
    <div class="sw-panel is-active" data-panel="1">
      <h2>What are you building?</h2>
      <div class="sw-packs">
        <?php foreach ($packs as $i => $pack): ?>
          <label class="sw-pack <?= $i === 0 ? 'is-selected' : '' ?>">
            <input type="radio" name="pack_radio" value="<?= htmlspecialchars($pack['id']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <strong><?= htmlspecialchars($pack['label']) ?></strong>
            <span><?= htmlspecialchars($pack['description']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="sw-fields">
        <label>
          <span>Site name</span>
          <input type="text" name="site_name" id="sw-site-name" required placeholder="e.g. Greenfield Academy" maxlength="120">
        </label>
        <label>
          <span>Tagline (optional)</span>
          <input type="text" name="tagline" id="sw-tagline" placeholder="A short line under your name" maxlength="200">
        </label>
        <label>
          <span>About / core facts</span>
          <textarea name="about" id="sw-about" rows="4" placeholder="A few sentences about who you are…"></textarea>
        </label>
        <label>
          <span>Contact details</span>
          <textarea name="contact" id="sw-contact" rows="3" placeholder="Address, phone, email…"></textarea>
        </label>
        <label>
          <span>Or upload a .txt / .md with core info</span>
          <input type="file" name="seed_file" id="sw-seed-file" accept=".txt,.md,text/plain">
        </label>
      </div>
      <div class="sw-actions">
        <button type="button" class="sw-btn sw-btn-primary" data-next="2">Continue</button>
      </div>
    </div>

    <!-- Step 2: Brand -->
    <div class="sw-panel" data-panel="2" hidden>
      <h2>Brand colors</h2>
      <p class="sw-hint">Enter one to three main colors. We’ll suggest palettes you can apply in one click.</p>
      <div class="sw-colors">
        <label>
          <span>Primary</span>
          <input type="color" id="sw-color1" value="#2563eb">
          <input type="text" id="sw-color1-hex" value="#2563eb" maxlength="7">
        </label>
        <label>
          <span>Accent (optional)</span>
          <input type="color" id="sw-color2" value="#7c3aed">
          <input type="text" id="sw-color2-hex" value="#7c3aed" maxlength="7">
        </label>
        <label>
          <span>Third (optional)</span>
          <input type="color" id="sw-color3" value="#0ea5e9">
          <input type="text" id="sw-color3-hex" value="" placeholder="skip" maxlength="7">
        </label>
      </div>
      <button type="button" class="sw-btn" id="sw-gen-palettes">Generate palettes</button>
      <div class="sw-palettes" id="sw-palettes" hidden></div>
      <div class="sw-actions">
        <button type="button" class="sw-btn" data-prev="1">Back</button>
        <button type="button" class="sw-btn sw-btn-primary" data-next="3">Continue</button>
      </div>
    </div>

    <!-- Step 3: Look + structure -->
    <div class="sw-panel" data-panel="3" hidden>
      <h2>Choose a look</h2>
      <p class="sw-hint">Fully built HTML/CSS/JS kits use your site name, tagline, and colors. Header theme toggle and Feed · llms.txt stay.</p>
      <input type="hidden" name="kit_id" id="sw-kit-id" value="">
      <div class="sw-kits" id="sw-kits"></div>
      <h3 style="margin:1.25rem 0 .5rem;font-size:1rem">Pages that will be created</h3>
      <ul class="sw-page-list" id="sw-page-list">
        <li>Select a look…</li>
      </ul>
      <div class="sw-actions">
        <button type="button" class="sw-btn" data-prev="2">Back</button>
        <button type="button" class="sw-btn sw-btn-primary" data-next="4">Continue</button>
      </div>
    </div>

    <!-- Step 4: Create -->
    <div class="sw-panel" data-panel="4" hidden>
      <h2>Create site</h2>
      <div class="sw-fields">
        <label class="sw-check">
          <input type="radio" name="status" value="draft" checked>
          <span>Create as <strong>drafts</strong> (recommended)</span>
        </label>
        <label class="sw-check">
          <input type="radio" name="status" value="published">
          <span>Create as <strong>published</strong></span>
        </label>
        <?php if ($ai_available): ?>
        <label class="sw-check">
          <input type="checkbox" name="use_ai" id="sw-use-ai" value="1">
          <span>Phase 2: AI-expand About, Contact, Team &amp; Programs (falls back to smart local copy) and seed Site AI knowledge</span>
        </label>
        <?php endif; ?>
      </div>
      <div class="sw-summary" id="sw-summary"></div>
      <div class="sw-actions">
        <button type="button" class="sw-btn" data-prev="3">Back</button>
        <button type="submit" class="sw-btn sw-btn-primary" id="sw-run">Create site</button>
      </div>
      <div class="sw-result" id="sw-result" hidden></div>
    </div>
  </form>
</section>

<script>
window.MOVA_SETUP_WIZARD = {
  packs: <?= json_encode($packs, JSON_UNESCAPED_UNICODE) ?>,
  kits: <?= json_encode($kits ?? [], JSON_UNESCAPED_UNICODE) ?>,
  packDetails: {
    school: { pages: ['Home', 'About us', 'Academics', 'Admissions', 'Contact'] },
    organization: { pages: ['Home', 'About us', 'Programs', 'Team', 'Contact'] },
    generic: { pages: ['Home', 'About us', 'Contact'] }
  },
  csrf: <?= json_encode($csrf) ?>,
  csrfField: '_mova_csrf'
};
</script>
<script src="/assets/js/hq-setup-wizard.js?v=112" defer></script>
<script>
/* Immediate step isolation (runs even if deferred script is slow/missing) */
(function () {
  var root = document.getElementById('setup-wizard');
  if (!root) return;
  function show(step) {
    root.querySelectorAll('.sw-panel').forEach(function (p) {
      var n = parseInt(p.getAttribute('data-panel'), 10);
      var on = n === step;
      p.classList.toggle('is-active', on);
      if (on) p.removeAttribute('hidden');
      else p.setAttribute('hidden', 'hidden');
    });
    root.querySelectorAll('.sw-step').forEach(function (s) {
      var n = parseInt(s.getAttribute('data-step'), 10);
      s.classList.toggle('is-active', n === step);
      s.classList.toggle('is-done', n < step);
    });
  }
  show(1);
  // If full wizard JS never loads, still allow Continue/Back via data-next/data-prev
  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-next],[data-prev]');
    if (!t || !root.contains(t)) return;
    if (window.__swFullJs) return; // full script handles it
    e.preventDefault();
    var next = t.getAttribute('data-next');
    var prev = t.getAttribute('data-prev');
    if (next) {
      if (parseInt(next, 10) === 2) {
        var name = (document.getElementById('sw-site-name') || {}).value || '';
        if (!String(name).trim()) {
          var el = document.getElementById('sw-site-name');
          if (el) el.focus();
          return;
        }
      }
      show(parseInt(next, 10));
    }
    if (prev) show(parseInt(prev, 10));
  });
})();
</script>
