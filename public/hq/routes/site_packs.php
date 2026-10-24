<?php
/**
 * HQ → Operations → Backups → Export & import (site packs).
 * Owner/administrator only; every POST is CSRF-checked. See app/Backup/SitePack*.php.
 */

declare(strict_types=1);

use Mova\Auth\Auth;
use Mova\Backup\SitePack;
use Mova\Backup\SitePackExporter;
use Mova\Backup\SitePackImporter;
use Mova\Backup\SitePackJobs;
use Mova\Backup\SitePackReader;
use Mova\Backup\TextDiff;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Audit;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

/** Returns a 403 response unless the user is an owner/administrator (and, for POSTs, the CSRF token is valid). */
$spGuard = static function (bool $post = false, bool $json = false): ?Response {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return $json ? (new Response())->json(['ok' => false, 'error' => 'Forbidden'], 403) : (new Response())->status(403)->body('Forbidden');
    }
    if ($post && !Csrf::validate()) {
        return $json ? (new Response())->json(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 403) : (new Response())->status(403)->body('Forbidden');
    }
    return null;
};
$spBack = static fn (array $q = []): Response => (new Response())->redirect('/hq/backups/packs' . ($q ? '?' . http_build_query($q) : ''));

/** Job payload for the progress page (no plan, no paths). */
$spJobJson = static function (?array $j): array {
    if ($j === null) {
        return ['ok' => false, 'error' => 'Import not found.'];
    }
    return [
        'ok' => true, 'id' => $j['id'], 'status' => $j['status'], 'phase' => $j['phase'], 'progress' => (int) $j['progress'],
        'message' => (string) $j['message'], 'error' => (string) ($j['error'] ?? ''),
        'log' => array_map(static fn ($l) => ['t' => (($d = \Mova\Core\Time::parse((int) $l['t'])) ? $d->setTimezone(\Mova\Core\Time::zone())->format('g:i:s A') : ''), 'm' => (string) $l['m']], array_slice((array) ($j['log'] ?? []), -12)),
    ];
};

// ── main page ───────────────────────────────────────────────────────────
$router->get('/backups/packs', function (Request $req) use ($spGuard) {
    if ($r = $spGuard()) {
        return $r;
    }
    return renderHq('site_packs/index', [
        'title' => 'Export & import',
        'inventory' => SitePackExporter::inventory(),
        'groups' => SitePack::groups(),
        'exports' => SitePackExporter::listExports(),
        'imports' => SitePackReader::listImports(),
        'jobs' => array_slice(SitePackJobs::all(), 0, 25),
        'active' => SitePackJobs::active(),
        'undoable' => SitePackJobs::undoable(),
        'limits' => [
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'effective' => min(SitePack::iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, SitePack::iniBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX),
            'memory_limit' => (string) ini_get('memory_limit'),
        ],
        'importsRel' => 'storage/imports/',
        'version' => SitePack::version(),
    ]);
});

// ── export ──────────────────────────────────────────────────────────────
$router->post('/backups/packs/export', function (Request $req) use ($spGuard, $spBack) {
    if ($r = $spGuard(true)) {
        return $r;
    }
    $all = SitePack::groups();
    $groups = array_values(array_intersect(array_keys($all), array_map('strval', (array) $req->post('groups', []))));
    if ($groups === []) {
        return $spBack(['tab' => 'export', 'error' => 'Pick at least one group to export.']);
    }
    $types = array_values(array_filter(array_map('strval', (array) $req->post('types', [])), static fn ($t) => preg_match('/^[a-z0-9_-]{1,64}$/', $t)));
    $allTypes = array_keys(SitePackExporter::inventory()['content']['types'] ?? []);
    if (in_array('content', $groups, true) && $types === []) {
        return $spBack(['tab' => 'export', 'error' => 'Pick at least one content type, or untick Pages & content.']);
    }
    if (count(array_diff($allTypes, $types)) === 0) {
        $types = []; // all types
    }
    $minVersion = trim((string) $req->post('min_version', ''));
    if ($minVersion !== '' && !preg_match('/^\d+\.\d+(\.\d+)?$/', $minVersion)) {
        $minVersion = '';
    }
    $label = mb_substr(trim((string) $req->post('label', '')), 0, 80);
    @set_time_limit(0);
    try {
        $res = (new SitePackExporter())->export($groups, $types, ['min_version' => $minVersion, 'label' => $label]);
    } catch (\Throwable $e) {
        return $spBack(['tab' => 'export', 'error' => 'Export failed: ' . $e->getMessage()]);
    }
    Audit::log('site_pack.exported', 'site_pack', null, ['name' => $res['name'], 'groups' => $groups, 'types' => $types, 'size' => $res['size'], 'via' => 'hq']);
    return $spBack(['tab' => 'export', 'exported' => $res['name']]);
});

$router->get('/backups/packs/download', function (Request $req) use ($spGuard) {
    if ($r = $spGuard()) {
        return $r;
    }
    $name = basename((string) $req->query('file', ''));
    $path = null;
    foreach (SitePackExporter::listExports() as $x) {
        if ($x['name'] === $name) {
            $path = $x['path'];
        }
    }
    if ($path === null || !is_file($path)) {
        return (new Response())->status(404)->body('Not found');
    }
    Audit::log('site_pack.downloaded', 'site_pack', null, ['name' => $name]);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
});

$router->post('/backups/packs/delete', function (Request $req) use ($spGuard, $spBack) {
    if ($r = $spGuard(true)) {
        return $r;
    }
    $kind = (string) $req->post('kind', '');
    $name = basename((string) $req->post('file', ''));
    $list = $kind === 'import' ? SitePackReader::listImports() : SitePackExporter::listExports();
    $active = SitePackJobs::active();
    foreach ($list as $x) {
        if ($x['name'] === $name) {
            if ($active && realpath((string) $active['pack_path']) === realpath($x['path'])) {
                return $spBack(['tab' => $kind === 'import' ? 'import' : 'export', 'error' => 'That pack is being imported right now.']);
            }
            @unlink($x['path']);
            Audit::log('site_pack.deleted', 'site_pack', null, ['name' => $name, 'kind' => $kind === 'import' ? 'import' : 'export']);
            return $spBack(['tab' => $kind === 'import' ? 'import' : 'export', 'deleted' => $name]);
        }
    }
    return $spBack(['tab' => 'export', 'error' => 'File not found.']);
});

// ── import: upload / pick ───────────────────────────────────────────────
$router->post('/backups/packs/upload', function (Request $req) use ($spGuard, $spBack) {
    // An upload bigger than post_max_size arrives with an empty $_POST (no CSRF token): explain that instead of a bare 403.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        requireAuth();
        return $spBack(['tab' => 'import', 'error' => 'The upload is larger than this server allows (post_max_size ' . ini_get('post_max_size') . '). Upload the pack by FTP to storage/imports/ instead.']);
    }
    if ($r = $spGuard(true)) {
        return $r;
    }
    $f = $_FILES['pack'] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = is_array($f) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
        $msg = [
            UPLOAD_ERR_INI_SIZE => 'The pack is larger than upload_max_filesize (' . ini_get('upload_max_filesize') . '). Upload it by FTP to storage/imports/ instead.',
            UPLOAD_ERR_FORM_SIZE => 'The pack is too large for this form.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a .movapack file to upload.',
        ][$code] ?? ('Upload failed (code ' . $code . ').');
        return $spBack(['tab' => 'import', 'error' => $msg]);
    }
    $orig = basename((string) ($f['name'] ?? 'site.movapack'));
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['movapack', 'zip'], true) || !is_uploaded_file((string) $f['tmp_name'])) {
        return $spBack(['tab' => 'import', 'error' => 'Only .movapack (or .zip) site packs can be uploaded.']);
    }
    $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($orig, PATHINFO_FILENAME)), '-.') ?: 'site';
    $base = mb_substr($base, 0, 80);
    $dir = SitePack::importsDir();
    $name = $base . '.' . $ext;
    for ($i = 2; is_file($dir . '/' . $name); $i++) {
        $name = $base . '-' . $i . '.' . $ext;
    }
    if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)) {
        return $spBack(['tab' => 'import', 'error' => 'Could not save the upload into storage/imports/ (check folder permissions).']);
    }
    @chmod($dir . '/' . $name, 0640);
    $rd = new SitePackReader($dir . '/' . $name);
    if (!$rd->validate(true)) {
        $err = implode(' ', array_slice($rd->errors, 0, 3));
        $blocked = $rd->versionBlocked;
        $rd->close();
        @unlink($dir . '/' . $name);
        Audit::log('site_pack.upload_rejected', 'site_pack', null, ['name' => $orig, 'reason' => mb_substr($err, 0, 300)]);
        return $spBack(['tab' => 'import', 'error' => ($blocked ? '' : 'Rejected: ') . $err]);
    }
    $rd->close();
    Audit::log('site_pack.uploaded', 'site_pack', null, ['name' => $name, 'size' => (int) filesize($dir . '/' . $name)]);
    return (new Response())->redirect('/hq/backups/packs/preview?pack=' . rawurlencode($name));
});

// ── import: dry-run preview ─────────────────────────────────────────────
$router->get('/backups/packs/preview', function (Request $req) use ($spGuard, $spBack) {
    if ($r = $spGuard()) {
        return $r;
    }
    $name = (string) $req->query('pack', '');
    $path = SitePackReader::resolveImport($name);
    if ($path === null) {
        return $spBack(['tab' => 'import', 'error' => 'Pack not found in storage/imports/.']);
    }
    @set_time_limit(0);
    $rd = new SitePackReader($path);
    $ok = $rd->validate(true);
    $data = ['title' => 'Import preview', 'pack' => basename($path), 'packSize' => (int) filesize($path), 'errors' => $rd->errors,
        'versionBlocked' => $rd->versionBlocked, 'manifest' => $ok ? $rd->manifest() : [], 'plan' => null, 'active' => SitePackJobs::active()];
    if ($ok) {
        $packGroups = array_values(array_intersect((array) ($data['manifest']['groups'] ?? []), array_keys(SitePack::groups())));
        $chosen = $req->query('g');
        $groups = is_array($chosen) ? array_values(array_intersect($packGroups, array_map('strval', $chosen))) : $packGroups;
        $opts = [
            'groups' => $groups,
            'add_new' => $req->query('applied') === null ? true : (string) $req->query('add_new', '') === '1',
            'remove_missing' => (string) $req->query('remove_missing', '') === '1',
            'overrides' => [],
            // Enabling plugins the pack needs is on by default; rewriting links is opt-in.
            'activate_plugins' => $req->query('applied') === null ? true : (string) $req->query('activate_plugins', '') === '1',
            'rewrite_urls' => (string) $req->query('rewrite_urls', '') === '1',
        ];
        try {
            $data['plan'] = (new SitePackImporter($rd))->plan($opts);
        } catch (\Throwable $e) {
            $data['errors'][] = 'Could not read the pack: ' . $e->getMessage();
        }
        $data['packGroups'] = $packGroups;
        $data['opts'] = $opts;
    }
    $rd->close();
    return renderHq('site_packs/preview', $data);
});

$router->get('/backups/packs/diff', function (Request $req) use ($spGuard) {
    if ($r = $spGuard()) {
        return $r;
    }
    $path = SitePackReader::resolveImport((string) $req->query('pack', ''));
    $id = (int) $req->query('id', 0);
    if ($path === null || $id <= 0) {
        return (new Response())->status(404)->body('Not found');
    }
    $rd = new SitePackReader($path);
    if (!$rd->validate(true)) {
        return (new Response())->status(422)->body('Invalid pack');
    }
    $b = (new SitePackImporter($rd))->bodies($id);
    $rd->close();
    if ($b === null) {
        return (new Response())->status(404)->body('Not in pack');
    }
    $ops = TextDiff::diff(TextDiff::lines($b['old']), TextDiff::lines($b['new']));
    return renderHq('site_packs/diff', ['title' => 'Body changes', 'pack' => basename($path), 'id' => $id, 'b' => $b, 'hunks' => TextDiff::hunks($ops, 3),
        'stats' => ['add' => count(array_filter($ops, static fn ($o) => $o[0] === '+')), 'del' => count(array_filter($ops, static fn ($o) => $o[0] === '-'))]]);
});

// ── import: run ─────────────────────────────────────────────────────────
$router->post('/backups/packs/import', function (Request $req) use ($spGuard, $spBack) {
    if ($r = $spGuard(true)) {
        return $r;
    }
    $path = SitePackReader::resolveImport((string) $req->post('pack', ''));
    if ($path === null) {
        return $spBack(['tab' => 'import', 'error' => 'Pack not found in storage/imports/.']);
    }
    $remove = (string) $req->post('remove_missing', '') === '1';
    if ($remove && strtoupper(trim((string) $req->post('confirm_remove', ''))) !== 'REMOVE') {
        return (new Response())->redirect('/hq/backups/packs/preview?' . http_build_query(['pack' => basename($path), 'g' => (array) $req->post('groups', []),
            'applied' => 1, 'add_new' => (string) $req->post('add_new', ''), 'remove_missing' => 1,
            'activate_plugins' => (string) $req->post('activate_plugins', ''), 'rewrite_urls' => (string) $req->post('rewrite_urls', ''), 'error' => 'Type REMOVE to confirm deleting items that are not in the pack.']));
    }
    $overrides = array_values(array_filter(array_map('strval', (array) $req->post('overrides', [])), static fn ($o) => preg_match('/^[a-z_]+:[A-Za-z0-9_.-]+$/', $o)));
    try {
        $job = SitePackJobs::create($path, [
            'groups' => array_map('strval', (array) $req->post('groups', [])),
            'add_new' => (string) $req->post('add_new', '') === '1',
            'remove_missing' => $remove,
            'overrides' => $overrides,
            'activate_plugins' => (string) $req->post('activate_plugins', '') === '1',
            'rewrite_urls' => (string) $req->post('rewrite_urls', '') === '1',
        ], Auth::id(), 'hq');
    } catch (\Throwable $e) {
        return $spBack(['tab' => 'import', 'error' => $e->getMessage()]);
    }
    return (new Response())->redirect('/hq/backups/packs/job?id=' . rawurlencode($job['id']));
});

$router->get('/backups/packs/job', function (Request $req) use ($spGuard, $spBack, $spJobJson) {
    if ($r = $spGuard()) {
        return $r;
    }
    $job = SitePackJobs::load((string) $req->query('id', ''));
    if ($job === null) {
        return $spBack(['tab' => 'history', 'error' => 'Import not found.']);
    }
    $u = SitePackJobs::undoable();
    return renderHq('site_packs/job', ['title' => 'Import progress', 'job' => $job, 'state' => $spJobJson($job), 'canUndo' => $u !== null && $u['id'] === $job['id']]);
});

$router->get('/backups/packs/job/status', function (Request $req) use ($spGuard, $spJobJson) {
    if ($r = $spGuard(false, true)) {
        return $r;
    }
    return (new Response())->json($spJobJson(SitePackJobs::load((string) $req->query('id', ''))));
});

/** The browser drives the import in short steps when no background worker is running (shared hosting). */
$router->post('/backups/packs/job/step', function (Request $req) use ($spGuard, $spJobJson) {
    if ($r = $spGuard(true, true)) {
        return $r;
    }
    $id = (string) $req->post('id', '');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // don't block other HQ tabs while this step runs
    }
    $job = SitePackJobs::load($id);
    if ($job !== null && in_array($job['status'], ['queued', 'running'], true)) {
        $max = (int) ini_get('max_execution_time');
        $budget = $max > 0 ? max(2.0, min(8.0, $max / 3)) : 8.0;
        $job = SitePackJobs::step($id, $budget);
    }
    return (new Response())->json($spJobJson($job));
});

$router->post('/backups/packs/undo', function (Request $req) use ($spGuard, $spBack) {
    if ($r = $spGuard(true)) {
        return $r;
    }
    $id = (string) $req->post('id', '');
    @set_time_limit(0);
    try {
        SitePackJobs::undo($id, Auth::id());
    } catch (\Throwable $e) {
        return $spBack(['tab' => 'history', 'error' => 'Undo failed: ' . $e->getMessage()]);
    }
    return $spBack(['tab' => 'history', 'undone' => $id]);
});
