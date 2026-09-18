<?php
/**
 * Dev Mode panels — included from EditorRenderer
 * Variables: $mode, $css, $js, $body, $monaco, $cssFile, $jsFile
 */
$isDev = ($mode === 'dev');
$useSiteChrome = !empty($useSiteChrome);
?>
<div class="mova-dev-editor" id="mova-dev-editor" data-mode="<?= htmlspecialchars($mode) ?>">
    <div class="mova-dev-toolbar">
        <label class="mova-dev-toggle">
            <input type="checkbox" id="mova-dev-mode-toggle" name="editor_mode_toggle" value="1" <?= $isDev ? 'checked' : '' ?>>
            <span>Dev Mode</span>
            <span class="mova-dev-hint">HTML · CSS · JS with live errors</span>
        </label>
        <label class="mova-dev-toggle mova-dev-chrome-toggle" id="mova-chrome-toggle-wrap" <?= $isDev ? '' : 'hidden' ?>>
            <input type="checkbox" name="use_site_chrome" value="1" id="mova-use-site-chrome" <?= !empty($useSiteChrome) ? 'checked' : '' ?>>
            <span>Show site header &amp; footer</span>
            <span class="mova-dev-hint">off by default</span>
        </label>
        <span id="mova-dev-error-badge" class="mova-dev-badge" hidden>0 errors</span>
    </div>

    <input type="hidden" name="editor_mode" id="mova-editor-mode" value="<?= htmlspecialchars($mode) ?>">

    <!-- Visual editor stays in the page; we show/hide it via JS -->
    <div id="mova-dev-panels" class="mova-dev-panels" <?= $isDev ? '' : 'hidden' ?>>
        <div class="mova-dev-tabs" role="tablist">
            <button type="button" class="mova-dev-tab is-active" data-tab="html" role="tab">HTML</button>
            <button type="button" class="mova-dev-tab" data-tab="css" role="tab">CSS</button>
            <button type="button" class="mova-dev-tab" data-tab="js" role="tab">JavaScript</button>
            <div class="mova-dev-io-actions">
                <button type="button" class="mova-dev-io-btn" id="mova-dev-import-btn" title="Import HTML, CSS, JS, or mixed .txt">Import</button>
                <button type="button" class="mova-dev-io-btn" id="mova-dev-export-btn" title="Download current HTML, CSS &amp; JS as a zip">Export</button>
                <input type="file" id="mova-dev-import-input" accept=".html,.css,.js,.txt,text/html,text/css,text/javascript,text/plain" multiple hidden>
            </div>
        </div>
        <div class="mova-dev-panes">
            <div class="mova-dev-pane is-active" data-pane="html">
                <div id="mova-monaco-html" class="mova-monaco"></div>
                <?php /* Do NOT use name="body" here — it collides with the visual editor's #body-input
                       and PHP keeps the last value, so visual edits never reach the database.
                       name is assigned only in Dev Mode on submit (see editor-boot.js). */ ?>
                <textarea id="mova-dev-html-input" class="mova-dev-fallback" hidden><?= htmlspecialchars($body) ?></textarea>
            </div>
            <div class="mova-dev-pane" data-pane="css">
                <div id="mova-monaco-css" class="mova-monaco"></div>
                <textarea name="raw_css" id="mova-dev-css-input" class="mova-dev-fallback" hidden><?= htmlspecialchars($css) ?></textarea>
            </div>
            <div class="mova-dev-pane" data-pane="js">
                <div id="mova-monaco-js" class="mova-monaco"></div>
                <textarea name="raw_js" id="mova-dev-js-input" class="mova-dev-fallback" hidden><?= htmlspecialchars($js) ?></textarea>
            </div>
        </div>
        <div id="mova-dev-error-list" class="mova-dev-error-list" hidden></div>
        <p class="mova-dev-note">
            You can paste a full <code>index.html</code> (with &lt;style&gt; / &lt;script&gt; inside) into the HTML box —
            Mova extracts CSS/JS automatically. Or split into the three panels.
            Use <strong>Import</strong> to load <code>.html</code> / <code>.css</code> / <code>.js</code> (multiple OK) or a mixed <code>.txt</code>; <strong>Export</strong> downloads a zip. Import auto-saves.
            Uncheck “Show site header &amp; footer” for a blank canvas.
        </p>
        <details class="mova-dev-note" style="margin-top:0.5rem;">
            <summary style="cursor:pointer;font-weight:600;">Theme-aware CSS sample (dark / light)</summary>
            <p style="margin:0.5rem 0 0.35rem;">Use Mova’s CSS variables so your content follows the site theme toggle:</p>
            <pre class="mova-dev-sample-css" style="margin:0;padding:0.75rem;overflow:auto;font-size:0.78rem;line-height:1.45;background:var(--hq-code-bg,#0f172a);color:#e2e8f0;border-radius:8px;"><?= htmlspecialchars(<<<'CSS'
/* Works with Mova light / dark toggle (data-theme on <html>) */
.my-card {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 1px 2px rgba(0,0,0,.04);
}

.my-card h2 {
  color: var(--color-text);
  margin: 0 0 0.5rem;
}

.my-card p {
  color: var(--color-muted);
  margin: 0 0 1rem;
}

.my-card .btn {
  display: inline-block;
  background: var(--color-accent);
  color: #fff;
  padding: 0.55rem 1rem;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
}
.my-card .btn:hover {
  background: var(--color-accent-hover);
}

/* Optional: extra tweaks only in dark mode */
[data-theme="dark"] .my-card {
  box-shadow: 0 0 0 1px rgba(255,255,255,.04);
}

/* Optional: only when system is dark and user has not forced light */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) .my-card {
    /* same vars already flip — use for rare overrides */
  }
}
CSS
) ?></pre>
            <p style="margin:0.5rem 0 0;font-size:0.85rem;opacity:0.85;">Available tokens: <code>--color-bg</code>, <code>--color-surface</code>, <code>--color-text</code>, <code>--color-muted</code>, <code>--color-accent</code>, <code>--color-accent-hover</code>, <code>--color-border</code>, <code>--color-code-bg</code>, <code>--color-quote-border</code>, <code>--color-header-bg</code>.</p>
        </details>
    </div>
</div>

<style>
<?php
if (is_file($cssFile)) {
    echo file_get_contents($cssFile);
}
?>
</style>

<script>
window.MOVA_DEV_EDITOR = {
    mode: <?= json_encode($mode) ?>,
    monacoCdn: <?= json_encode($monaco) ?>,
    initial: {
        html: <?= json_encode($body) ?>,
        css: <?= json_encode($css) ?>,
        js: <?= json_encode($js) ?>
    }
};
</script>
<script>
<?php
if (is_file($jsFile)) {
    echo file_get_contents($jsFile);
}
?>
</script>
