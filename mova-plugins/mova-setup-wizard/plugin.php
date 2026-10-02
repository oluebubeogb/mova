<?php
/**
 * Mova Quick Setup Wizard — always-on plugin for fast school/org site setup.
 *
 * Phase 1: packs, palette, static page shells, nav, footer columns, HQ UI.
 * Phase 2: AiAssist copy, KnowledgeBank seed, optional background jobs.
 */

declare(strict_types=1);

use Mova\Plugin\PluginManager;

require_once __DIR__ . '/src/KitRepository.php';
require_once __DIR__ . '/src/SetupWizardService.php';
require_once __DIR__ . '/src/Packs/SchoolPack.php';
require_once __DIR__ . '/src/Packs/OrgPack.php';

PluginManager::addAction('mova.boot', function () {
    // Ensure this plugin stays active on every install (core + existing sites).
    \MovaSetupWizard\SetupWizardService::ensureActivated();

// Frontend: inject kit CSS/JS when page was created by wizard (works even if Dev Editor is off)
PluginManager::addFilter('content.render.body', function (string $body, array $content = []) {
    $meta = $content['meta'] ?? [];
    if (empty($meta['wizard_kit']) || empty($meta['raw_css'])) {
        return $body;
    }
    // If Dev Editor already wraps, don't double-wrap
    if (strpos($body, 'mova-dev-isolate') !== false || strpos($body, 'mova-kit-isolate') !== false) {
        return $body;
    }
    $id = (int) ($content['id'] ?? 0);
    $rid = 'mova-kit-root-' . ($id > 0 ? $id : 'x');
    return '<div id="' . htmlspecialchars($rid) . '" class="mova-kit-isolate" data-mova-kit="1">' . $body . '</div>';
}, 20);

PluginManager::addAction('theme.render.head', function (array $context = []) {
    $content = $context['content'] ?? null;
    if (!$content) {
        return;
    }
    $meta = $content['meta'] ?? [];
    $css = trim((string) ($meta['raw_css'] ?? ''));
    if ($css === '' || empty($meta['wizard_kit'])) {
        return;
    }
    // Skip if Dev Editor will handle (editor_mode=dev and plugin active)
    if (($meta['editor_mode'] ?? '') === 'dev' && class_exists(\MovaDevEditor\DevEditorPlugin::class)) {
        // still output if dev editor not booted — safe to always emit scoped kit CSS
    }
    $id = (int) ($content['id'] ?? 0);
    $rid = 'mova-kit-root-' . ($id > 0 ? $id : 'x');
    // Prefer mova-dev-root when editor_mode=dev so one root matches
    if (($meta['editor_mode'] ?? '') === 'dev') {
        $rid = 'mova-dev-root-' . ($id > 0 ? $id : 'x');
    }
    $css = str_replace('</style>', '<\/style>', $css);
    echo "\n<!-- Mova Setup Wizard kit CSS -->\n<style id=\"mova-kit-css\">\n";
    echo '#' . $rid . " { isolation: isolate; position: relative; }\n";
    // naive scope: prefix top-level rules is hard; inject unscoped + root isolation
    // Kit CSS uses classes (.kit-*) so global is OK on content pages
    echo $css . "\n</style>\n";
}, 20);

PluginManager::addAction('theme.render.footer', function (array $context = []) {
    $content = $context['content'] ?? null;
    if (!$content) {
        return;
    }
    $meta = $content['meta'] ?? [];
    $js = trim((string) ($meta['raw_js'] ?? ''));
    if ($js === '' || empty($meta['wizard_kit'])) {
        return;
    }
    $js = str_replace('</script>', '<\/script>', $js);
    echo "\n<!-- Mova Setup Wizard kit JS -->\n<script id=\"mova-kit-js\">\n" . $js . "\n</script>\n";
}, 20);

});
