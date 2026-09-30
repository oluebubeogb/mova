<?php
/**
 * Mova Quick Setup Wizard — always-on plugin for fast school/org site setup.
 *
 * Phase 1: packs, palette, static page shells, nav, footer columns, HQ UI.
 * Phase 2: AiAssist copy, KnowledgeBank seed, optional background jobs.
 */

declare(strict_types=1);

use Mova\Plugin\PluginManager;

require_once __DIR__ . '/src/SetupWizardService.php';
require_once __DIR__ . '/src/Packs/SchoolPack.php';
require_once __DIR__ . '/src/Packs/OrgPack.php';

PluginManager::addAction('mova.boot', function () {
    // Ensure this plugin stays active on every install (core + existing sites).
    \MovaSetupWizard\SetupWizardService::ensureActivated();
});
