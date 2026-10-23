<?php
// Load Quick setup classes (plugin namespace is not covered by the core autoloader).
declare(strict_types=1);
foreach (['KitRepository', 'Packs/SchoolPack', 'Packs/OrgPack', 'SetupWizardService', 'KitRenderer', 'WizardDraft', 'PreviewRenderer'] as $__f) {
    require_once __DIR__ . '/' . $__f . '.php';
}
unset($__f);
