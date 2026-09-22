<?php
/**
 * Studio — Phase 2 editor
 * Elements-style Col2 panels, attribute editor, Monaco code, live sync
 */
use Mova\Security\Csrf;

$content = $content ?? null;
$body    = $body ?? '';
$css     = $css ?? '';
$js      = $js ?? '';
$id      = (int) ($content['id'] ?? 0);
$title   = (string) ($content['title'] ?? 'Untitled');
$csrfToken = Csrf::token();
?>

<div class="studio-app studio-phase2"
     id="studio-app"
     data-content-id="<?= $id ?>"
     data-csrf="<?= htmlspecialchars($csrfToken) ?>"
     data-save-url="/hq/studio/<?= $id ?>/save"
     data-monaco-cdn="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs">

  <!-- Global toolbar -->
  <div class="studio-toolbar" role="toolbar" aria-label="Studio actions">
    <div class="studio-toolbar-left">
      <a href="/hq/studio" class="studio-back" title="Back to Studio list"><i class="fa-solid fa-arrow-left"></i></a>
      <input type="text" id="studio-title" class="studio-title-input" value="<?= htmlspecialchars($title) ?>" placeholder="Title" aria-label="Content title">
      <span class="studio-dirty" id="studio-dirty" hidden title="Unsaved changes">●</span>
      <span class="studio-error-badge" id="studio-error-badge" hidden>0 errors</span>
    </div>
    <div class="studio-toolbar-center">
      <button type="button" class="studio-btn" id="studio-undo" title="Undo (Ctrl+Z)" aria-label="Undo" disabled>
        <i class="fa-solid fa-rotate-left"></i>
      </button>
      <button type="button" class="studio-btn" id="studio-redo" title="Redo (Ctrl+Y)" aria-label="Redo" disabled>
        <i class="fa-solid fa-rotate-right"></i>
      </button>
      <span class="studio-sep"></span>
      <button type="button" class="studio-btn studio-btn-ghost" id="studio-add-code" title="Show code panel" hidden>
        <i class="fa-solid fa-code"></i> Code
      </button>
      <button type="button" class="studio-btn studio-btn-ghost" id="studio-add-preview" title="Show preview" hidden>
        <i class="fa-solid fa-eye"></i> Preview
      </button>
    </div>
    <div class="studio-toolbar-right">
      <button type="button" class="studio-btn studio-btn-ghost" id="studio-fullscreen" title="Fullscreen" aria-label="Toggle fullscreen">
        <i class="fa-solid fa-expand"></i> <span class="studio-fs-label">Fullscreen</span>
      </button>
      <a href="/hq/content/edit/<?= $id ?>" class="studio-btn studio-btn-ghost" title="Open in classic editor">
        <i class="fa-solid fa-pen-to-square"></i> Classic
      </a>
      <button type="button" class="studio-btn studio-btn-primary" id="studio-save" title="Save (Ctrl+S)">
        <i class="fa-solid fa-floppy-disk"></i> <span>Save</span>
      </button>
    </div>
  </div>

  <div class="studio-toast" id="studio-toast" hidden role="status" aria-live="polite"></div>

  <div class="studio-cols" id="studio-cols">

    <!-- COL 1 — Structure -->
    <div class="studio-col studio-col-nav" id="studio-col-nav" data-col="nav">
      <div class="studio-col-head">
        <span class="studio-col-title" title="Structure"><i class="fa-solid fa-sitemap"></i><span class="studio-col-label">Nav</span></span>
        <button type="button" class="studio-col-collapse" data-collapse="nav" title="Collapse" aria-label="Collapse navigator">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
      </div>
      <div class="studio-col-body">
        <div class="studio-nav-section" data-section="ids">
          <div class="studio-nav-heading">IDs</div>
          <div class="studio-nav-list" id="studio-nav-ids"></div>
        </div>
        <div class="studio-nav-section" data-section="classes">
          <div class="studio-nav-heading">Classes</div>
          <div class="studio-nav-list" id="studio-nav-classes"></div>
        </div>
        <div class="studio-nav-section" data-section="tags">
          <div class="studio-nav-heading">Tags</div>
          <div class="studio-nav-list" id="studio-nav-tags"></div>
        </div>
        <div class="studio-nav-section studio-nav-actions">
          <div class="studio-nav-heading">Tools</div>
          <button type="button" class="studio-nav-item" id="studio-open-attrs" title="Quick attribute editor">
            <i class="fa-solid fa-sliders"></i><span class="studio-nav-short">Attrs</span>
          </button>
        </div>
      </div>
    </div>

    <div class="studio-splitter" data-split="0" role="separator" aria-orientation="vertical"></div>

    <div class="studio-col2-host" id="studio-col2-host"></div>

    <div class="studio-splitter" data-split="host-code" role="separator" aria-orientation="vertical"></div>

    <!-- CODE -->
    <div class="studio-col studio-col-code" id="studio-col-code" data-col="code">
      <div class="studio-col-head">
        <span class="studio-col-title"><i class="fa-solid fa-code"></i><span class="studio-col-label">Code</span></span>
        <div class="studio-code-tabs" role="tablist">
          <button type="button" class="studio-code-tab is-active" data-tab="html" role="tab">HTML</button>
          <button type="button" class="studio-code-tab" data-tab="css" role="tab">CSS</button>
          <button type="button" class="studio-code-tab" data-tab="js" role="tab">JS</button>
        </div>
        <button type="button" class="studio-col-close" data-close="code" title="Close code panel" aria-label="Close code">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="studio-col-body studio-code-body">
        <!-- Monaco mounts (Phase 2) -->
        <div id="studio-monaco-html" class="studio-monaco is-active" data-lang="html"></div>
        <div id="studio-monaco-css" class="studio-monaco" data-lang="css" hidden></div>
        <div id="studio-monaco-js" class="studio-monaco" data-lang="js" hidden></div>
        <!-- Fallback textareas (hidden when Monaco loads) -->
        <textarea id="studio-html" class="studio-code-area" spellcheck="false" aria-label="HTML" hidden><?= htmlspecialchars($body) ?></textarea>
        <textarea id="studio-css" class="studio-code-area" spellcheck="false" aria-label="CSS" hidden><?= htmlspecialchars($css) ?></textarea>
        <textarea id="studio-js" class="studio-code-area" spellcheck="false" aria-label="JavaScript" hidden><?= htmlspecialchars($js) ?></textarea>
      </div>
    </div>

    <div class="studio-splitter" data-split="code-preview" role="separator" aria-orientation="vertical"></div>

    <!-- PREVIEW -->
    <div class="studio-col studio-col-preview" id="studio-col-preview" data-col="preview">
      <div class="studio-col-head">
        <span class="studio-col-title"><i class="fa-solid fa-eye"></i><span class="studio-col-label">Preview</span></span>
        <div class="studio-preview-tools">
          <button type="button" class="studio-icon-btn is-active" data-vp="desktop" title="Desktop" aria-label="Desktop viewport">
            <i class="fa-solid fa-desktop"></i>
          </button>
          <button type="button" class="studio-icon-btn" data-vp="mobile" title="Mobile" aria-label="Mobile viewport">
            <i class="fa-solid fa-mobile-screen"></i>
          </button>
          <button type="button" class="studio-icon-btn" id="studio-theme-toggle" title="Toggle light/dark" aria-label="Toggle theme">
            <i class="fa-solid fa-circle-half-stroke"></i>
          </button>
          <button type="button" class="studio-icon-btn" id="studio-open-tab" title="Open in new tab" aria-label="Open in new tab">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </button>
        </div>
        <button type="button" class="studio-col-close" data-close="preview" title="Close preview" aria-label="Close preview">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="studio-col-body studio-preview-body">
        <div class="studio-preview-frame-wrap" data-vp="desktop" data-theme="light" id="studio-preview-wrap">
          <iframe id="studio-preview" title="Preview" sandbox="allow-scripts allow-same-origin"></iframe>
        </div>
      </div>
      <div class="studio-edge-resize" id="studio-preview-edge" data-edge="preview-right" title="Drag to resize preview" role="separator" aria-orientation="vertical"></div>
    </div>

  </div>
</div>

<?php
// Reliable variable delivery (avoid data-attribute size/encoding limits)
$__studioVarMapJson = json_encode($varMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
if ($__studioVarMapJson === false) { $__studioVarMapJson = '{}'; }
$__studioCssVars = (string) ($siteCssVars ?? '');
?>
<script type="application/json" id="studio-var-map"><?= $__studioVarMapJson ?></script>
<style type="text/css" id="studio-site-css-vars"><?= $__studioCssVars ?></style>


<!-- Col2: Elements-style style panel -->
<template id="studio-col2-style-template">
  <div class="studio-col studio-col-detail studio-col-style" data-col2 data-panel="style">
    <div class="studio-col-head">
      <span class="studio-col-title">
        <i class="fa-solid fa-palette"></i>
        <span class="studio-col-label studio-col2-label">Style</span>
      </span>
      <div class="studio-bp-row">
        <button type="button" class="studio-bp-btn is-active" data-bp="desktop" title="Desktop">D</button>
        <button type="button" class="studio-bp-btn" data-bp="tablet" title="Tablet">T</button>
        <button type="button" class="studio-bp-btn" data-bp="mobile" title="Mobile">M</button>
      </div>
      <button type="button" class="studio-col-close studio-col2-close" title="Close panel" aria-label="Close panel">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="studio-col-body">
      <div class="studio-pseudo-row">
        <button type="button" class="studio-pseudo-btn is-active" data-pseudo="base">Base</button>
        <button type="button" class="studio-pseudo-btn" data-pseudo="hover">:hover</button>
        <button type="button" class="studio-pseudo-btn" data-pseudo="focus">:focus</button>
      </div>
      <div class="studio-style-groups"></div>
      <div class="studio-style-advanced">
        <label class="studio-field-label">Custom CSS</label>
        <textarea class="studio-custom-css" rows="4" placeholder="/* extra rules for this selector */"></textarea>
      </div>
    </div>
  </div>
</template>

<!-- Col2: Attribute editor -->
<template id="studio-col2-attrs-template">
  <div class="studio-col studio-col-detail studio-col-attrs" data-col2 data-panel="attrs">
    <div class="studio-col-head">
      <span class="studio-col-title">
        <i class="fa-solid fa-sliders"></i>
        <span class="studio-col-label">Attributes</span>
      </span>
      <button type="button" class="studio-col-close studio-col2-close" title="Close panel" aria-label="Close panel">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="studio-col-body">
      <p class="studio-col2-hint">Edit id, class list, and data-* on a matched node in the HTML.</p>
      <div class="studio-field">
        <label class="studio-field-label">Find by selector</label>
        <input type="text" class="studio-attr-selector input" placeholder="#hero, .card, h1 …">
      </div>
      <div class="studio-field">
        <label class="studio-field-label">id</label>
        <input type="text" class="studio-attr-id input" placeholder="section-id">
      </div>
      <div class="studio-field">
        <label class="studio-field-label">class</label>
        <input type="text" class="studio-attr-class input" placeholder="btn primary">
      </div>
      <div class="studio-field">
        <label class="studio-field-label">data-* (JSON object)</label>
        <textarea class="studio-attr-data input" rows="3" placeholder='{"foo":"bar"}'></textarea>
      </div>
      <button type="button" class="studio-btn studio-btn-primary studio-attr-apply">Apply to HTML</button>
      <p class="studio-attr-status" hidden></p>
    </div>
  </div>
</template>

<link rel="stylesheet" href="/assets/css/studio.css?v=20260922fullwidth2">
<script src="/assets/js/studio.js?v=20260921vars" defer></script>
