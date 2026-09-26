<?php
/**
 * Mova Site AI — public assistant + knowledge bank (not HQ).
 */

declare(strict_types=1);

use Mova\Plugin\PluginManager;
use Mova\Core\Database;

require_once __DIR__ . '/src/SiteAiService.php';
require_once __DIR__ . '/src/KnowledgeBank.php';

PluginManager::addAction('mova.boot', function () {
    \MovaSiteAi\KnowledgeBank::ensureSchema();
});

// Inject widget on public theme footer
PluginManager::addAction('theme.render.footer', function (array $ctx = []) {
    $cfg = \MovaSiteAi\SiteAiService::config();
    if (empty($cfg['enabled'])) {
        return;
    }
    $name = htmlspecialchars((string) ($cfg['name'] ?? 'Assistant'));
    $primary = htmlspecialchars((string) ($cfg['primary'] ?? ''));
    $accent = htmlspecialchars((string) ($cfg['accent'] ?? ''));
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    // assets served from plugin path via public symlink or inline minimal
    $css = '/mova-plugins/mova-site-ai/assets/css/widget.css';
    $js = '/mova-plugins/mova-site-ai/assets/js/widget.js';
    // Prefer design tokens when primary empty
    echo '<link rel="stylesheet" href="' . $css . '?v=1">';
    echo '<div id="mova-site-ai-root" data-name="' . $name . '"'
        . ($primary !== '' ? ' data-primary="' . $primary . '"' : '')
        . ($accent !== '' ? ' data-accent="' . $accent . '"' : '')
        . ' data-api="/api/site-ai/chat"></div>';
    echo '<script src="' . $js . '?v=1" defer></script>';
});
