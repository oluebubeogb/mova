<?php
use Mova\Security\Csrf;
$topTags = $topTags ?? [];
$allTags = $allTags ?? [];
$styledTags = $styledTags ?? [];
$styles = $styles ?? [];
$initialTag = isset($_GET['tag']) ? preg_replace('/[^a-z0-9]/', '', strtolower((string) $_GET['tag'])) : 'h1';
if ($initialTag === '') {
    $initialTag = 'h1';
}
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Element styles saved. Public cache cleared.</div>
<?php endif; ?>
<?php if (isset($_GET['imported'])): ?>
    <div class="alert alert-success">Imported <?= (int) $_GET['imported'] ?> tag style set(s).</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error">Could not complete that action (<?= htmlspecialchars((string) $_GET['error']) ?>).</div>
<?php endif; ?>

<p class="design-intro">Style HTML tags across the public site (<code>.site-main</code>). Pseudos, targeting, export/import, and optional JS.</p>

<div class="hq-layers" data-layer-key="mova_hq_layer_elements">
    <div class="hq-layer-tabs" role="tablist">
        <button type="button" class="hq-layer-tab is-active" data-layer="editor" role="tab">
            <i class="fa-solid fa-code" aria-hidden="true"></i><span>Editor</span>
        </button>
        <button type="button" class="hq-layer-tab" data-layer="applied" role="tab">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Applied</span>
        </button>
    </div>
    <hr class="hq-layer-rule">
    <div class="hq-layer-panels">
        <div class="hq-layer-panel is-active el-panel-shell" data-layer-panel="editor">
            <form method="post" action="/hq/elements" id="el-form" class="el-workspace">
                <?= Csrf::field() ?>
                <input type="hidden" name="tag" id="el-tag-input" value="<?= htmlspecialchars($initialTag) ?>">
                <input type="hidden" name="props_json" id="el-props-json" value="{}">
                <input type="hidden" name="custom_css" id="el-custom-css" value="">
                <input type="hidden" name="breakpoints_json" id="el-breakpoints-json" value="{}">
                <input type="hidden" name="entry_json" id="el-entry-json" value="{}">

                <div class="el-cols" id="el-cols"
                     data-all-tags="<?= htmlspecialchars(json_encode(array_values($allTags)), ENT_QUOTES, 'UTF-8') ?>"
                     data-top-tags="<?= htmlspecialchars(json_encode(array_values($topTags)), ENT_QUOTES, 'UTF-8') ?>"
                     data-styled="<?= htmlspecialchars(json_encode(array_values($styledTags)), ENT_QUOTES, 'UTF-8') ?>"
                     data-initial-tag="<?= htmlspecialchars($initialTag) ?>">

                    <section class="el-col el-col-tags" data-col="tags">
                        <header class="el-col-head">
                            <button type="button" class="el-col-collapse" data-collapse-col="tags" title="Collapse" aria-label="Collapse tags">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <span class="el-col-title"><i class="fa-solid fa-tags"></i> Tags</span>
                        </header>
                        <div class="el-col-body">
                            <div class="el-search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" id="el-tag-search" placeholder="Search tags…" autocomplete="off">
                            </div>
                            <ul class="el-tag-list" id="el-tag-list" role="listbox"></ul>
                            <p class="el-col-hint">Max 15 shown. Empty search lists top tags.</p>
                        </div>
                    </section>

                    <div class="el-splitter" data-split="0" title="Drag to resize"></div>

                    <section class="el-col el-col-css" data-col="css">
                        <header class="el-col-head">
                            <button type="button" class="el-col-collapse" data-collapse-col="css" title="Collapse" aria-label="Collapse CSS">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <span class="el-col-title"><i class="fa-solid fa-palette"></i> CSS · <span id="el-active-tag-label"><?= htmlspecialchars($initialTag) ?></span></span>
                        </header>
                        <div class="el-col-body" id="el-css-body">
                            <div class="el-bp-bar" id="el-bp-bar" role="tablist" aria-label="Breakpoint">
                                <button type="button" class="el-bp-btn is-active" data-bp="desktop" title="Desktop" aria-label="Desktop">
                                    <i class="fa-solid fa-desktop" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="el-bp-btn" data-bp="tablet" title="Tablet" aria-label="Tablet">
                                    <i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="el-bp-btn" data-bp="mobile" title="Mobile" aria-label="Mobile">
                                    <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="el-pseudo-bar" id="el-pseudo-bar" role="tablist" aria-label="Pseudo">
                                <button type="button" class="el-pseudo-btn is-active" data-pseudo="base">Base</button>
                                <button type="button" class="el-pseudo-btn" data-pseudo="hover">:hover</button>
                                <button type="button" class="el-pseudo-btn" data-pseudo="focus">:focus</button>
                                <button type="button" class="el-pseudo-btn" data-pseudo="focus-visible">:focus-visible</button>
                                <button type="button" class="el-pseudo-btn" data-pseudo="first-child">:first-child</button>
                                <button type="button" class="el-pseudo-btn" data-pseudo="last-child">:last-child</button>
                            </div>
                            <div class="el-css-groups" id="el-css-groups"></div>
                        </div>
                    </section>

                    <div class="el-splitter" data-split="1" title="Drag to resize"></div>

                    <section class="el-col el-col-preview" data-col="preview">
                        <header class="el-col-head">
                            <button type="button" class="el-col-collapse" data-collapse-col="preview" title="Collapse" aria-label="Collapse preview">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <span class="el-col-title"><i class="fa-solid fa-eye"></i> Preview</span>
                            <button type="button" class="el-preview-theme" id="el-preview-theme" title="Toggle preview theme" aria-label="Toggle preview theme">
                                <i class="fa-solid fa-circle-half-stroke"></i>
                            </button>
                            <button type="button" class="el-preview-open" id="el-preview-open" title="Open preview in new tab" aria-label="Open preview in new tab">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </button>
                        </header>
                        <div class="el-col-body">
                            <div class="el-preview-toolbar" id="el-pw-toolbar">
                                <button type="button" class="el-pw-btn el-pw-trigger is-active" data-pw="desktop" title="Preview width" aria-label="Preview width" id="el-pw-trigger">
                                    <i class="fa-solid fa-desktop" aria-hidden="true"></i>
                                </button>
                                <div class="el-pw-menu" id="el-pw-menu" hidden>
                                    <button type="button" data-pw="desktop" class="is-active"><i class="fa-solid fa-desktop"></i> Desktop</button>
                                    <button type="button" data-pw="tablet"><i class="fa-solid fa-tablet-screen-button"></i> Tablet</button>
                                    <button type="button" data-pw="mobile"><i class="fa-solid fa-mobile-screen-button"></i> Mobile</button>
                                </div>
                            </div>
                            <script>
                            (function(){
                              var trig=document.getElementById('el-pw-trigger');
                              var menu=document.getElementById('el-pw-menu');
                              var frame=document.getElementById('el-preview-frame');
                              if(!trig||!menu) return;
                              var icons={desktop:'fa-desktop',tablet:'fa-tablet-screen-button',mobile:'fa-mobile-screen-button'};
                              trig.addEventListener('click',function(e){e.stopPropagation();menu.hidden=!menu.hidden;});
                              document.addEventListener('click',function(){menu.hidden=true;});
                              menu.addEventListener('click',function(e){e.stopPropagation();});
                              menu.querySelectorAll('button[data-pw]').forEach(function(btn){
                                btn.addEventListener('click',function(){
                                  var pw=btn.getAttribute('data-pw');
                                  if(frame) frame.setAttribute('data-pw',pw);
                                  menu.querySelectorAll('button').forEach(function(b){b.classList.toggle('is-active',b===btn);});
                                  var icon=trig.querySelector('i');
                                  if(icon){icon.className='fa-solid '+(icons[pw]||'fa-desktop');}
                                  trig.setAttribute('data-pw',pw);
                                  menu.hidden=true;
                                  document.querySelectorAll('.el-pw-btn[data-pw]').forEach(function(b){
                                    if(b!==trig) b.classList.toggle('is-active',b.getAttribute('data-pw')===pw);
                                  });
                                  // fire existing handler if any
                                  document.dispatchEvent(new CustomEvent('el:preview-width',{detail:{pw:pw}}));
                                });
                              });
                            })();
                            </script>
                            <div class="el-preview-frame site-main" id="el-preview-frame" data-theme="light" data-pw="desktop">
                                <div id="el-preview-content"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="hq-layer-actions el-actions">
                    <button type="submit" class="btn-primary" id="el-save">Save element styles</button>
                    <button type="button" class="btn-ghost" id="el-reset-tag">Reset tag</button>
                    <button type="button" class="btn-ghost" id="el-copy-css">Copy CSS</button>
                    <a class="btn-ghost" href="/hq/elements/export">Export JSON</a>
                    <span class="el-dirty" id="el-dirty" hidden>Unsaved changes</span>
                </div>
            </form>
        </div>

        <div class="hq-layer-panel" data-layer-panel="applied" hidden>
            <h3>Applied rules</h3>
            <p class="field-hint">Tags with saved styles. Click to edit in the Editor tab.</p>
            <ul class="el-applied-list" id="el-applied-list">
                <?php if (!$styledTags): ?>
                    <li class="el-tag-empty">No element styles yet.</li>
                <?php else: ?>
                    <?php foreach ($styledTags as $st): ?>
                        <li>
                            <button type="button" class="el-applied-item" data-goto-tag="<?= htmlspecialchars($st) ?>">
                                <code>&lt;<?= htmlspecialchars($st) ?>&gt;</code>
                                <span>Edit</span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <hr class="hq-layer-rule" style="margin:1.25rem 0;">
            <h3>Import</h3>
            <p class="field-hint" style="margin-bottom:0.75rem;">
                Paste JSON or upload a <code>.json</code> file.
                <a href="/assets/samples/element-styles-sample.json" download>Download full sample</a>
                (h2 + responsive, pseudos, animation, light/dark via custom CSS, targeting).
            </p>
            <form method="post" action="/hq/elements/import" class="el-import-form" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <div class="form-group">
                    <label>Upload .json file</label>
                    <input type="file" name="import_file" accept=".json,application/json" id="el-import-file">
                </div>
                <div class="form-group">
                    <label>Or paste element styles JSON</label>
                    <textarea name="import_json" rows="8" placeholder='{ "h2": { "desktop": { "props": { "font-size": "2.25rem", "animation": "mova-fade-in 0.45s ease both" } }, "tablet": { "props": { "font-size": "1.85rem" } }, "mobile": { "props": { "font-size": "1.5rem" } }, "target": { "class": "", "id": "" } } }'></textarea>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="import_merge" value="1" checked>
                        Merge with existing (unchecked replaces all)
                    </label>
                </div>
                <button type="submit" class="btn-primary">Import</button>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/elements-editor.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="/assets/js/elements-editor.js" defer></script>
<script src="/assets/js/hq-layers.js" defer></script>
<?php include __DIR__ . '/_design_styles.php'; ?>
