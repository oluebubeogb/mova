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
            Uncheck “Show site header &amp; footer” for a blank canvas.
        </p>
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
