<?php
/** @var list<array{id:string,label:string,description:string}> $packs */
/** @var bool $ai_available */
$packs = $packs ?? [];
$ai_available = !empty($ai_available);
$csrf = \Mova\Security\Csrf::token();
?>
<link rel="stylesheet" href="/mova-plugins/mova-setup-wizard/assets/css/setup-wizard.css?v=1">

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
    <div class="sw-panel" data-panel="2">
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

    <!-- Step 3: Structure -->
    <div class="sw-panel" data-panel="3">
      <h2>Pages &amp; footer</h2>
      <p class="sw-hint">We’ll create the pack’s pages with short placeholders, set primary nav, and add footer columns <strong>above</strong> the usual Feed · llms.txt line. Theme toggle in the header stays.</p>
      <ul class="sw-page-list" id="sw-page-list">
        <li>Loading pack…</li>
      </ul>
      <div class="sw-actions">
        <button type="button" class="sw-btn" data-prev="2">Back</button>
        <button type="button" class="sw-btn sw-btn-primary" data-next="4">Continue</button>
      </div>
    </div>

    <!-- Step 4: Create -->
    <div class="sw-panel" data-panel="4">
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
          <span>Phase 2: AI-expand About, Contact, Team & Programs (falls back to smart local copy) and seed Site AI knowledge</span>
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

<script src="/mova-plugins/mova-setup-wizard/assets/js/setup-wizard.js?v=1" defer></script>
<script>
window.MOVA_SETUP_WIZARD = {
  packs: <?= json_encode($packs, JSON_UNESCAPED_UNICODE) ?>,
  packDetails: {
    school: { pages: ['Home', 'About us', 'Academics', 'Admissions', 'Contact'] },
    organization: { pages: ['Home', 'About us', 'Programs', 'Team', 'Contact'] },
    generic: { pages: ['Home', 'About us', 'Contact'] }
  },
  csrf: <?= json_encode($csrf) ?>,
  csrfField: '_mova_csrf'
};
</script>
