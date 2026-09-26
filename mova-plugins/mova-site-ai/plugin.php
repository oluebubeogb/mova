<?php
/**
 * Mova Site AI — public assistant + knowledge bank (not HQ).
 */

declare(strict_types=1);

use Mova\Plugin\PluginManager;

require_once __DIR__ . '/src/SiteAiService.php';
require_once __DIR__ . '/src/KnowledgeBank.php';

PluginManager::addAction('mova.boot', function () {
    \MovaSiteAi\KnowledgeBank::ensureSchema();
});

// Widget HTML is injected from public/index.php (mova_inject_site_ai_widget)
// so we do not echo assets here — avoids double tags and stale ?v=1 cache.
