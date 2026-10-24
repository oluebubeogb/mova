<?php
/**
 * Export a Mova site pack (.movapack).
 *
 *   php bin/mova-export.php                       # everything except personal data
 *   php bin/mova-export.php --groups=content,design,media --types=page
 *   php bin/mova-export.php --groups=all --min-version=1.2.0 --out=/tmp/site.movapack
 *
 * Groups: content, blogs, galleries, media, menus, taxonomy, design, settings, site_ai, forms,
 *         and (personal data, opt-in only) subscribers, form_entries, comments. "all" = default groups.
 * Users, passwords, API keys, provider keys, secret settings, mail accounts, integrations, sessions,
 * logs and analytics are never exported.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

/** getopt() stops at the first positional argument, so parse "--x", "--x=v" and "-v" anywhere on the line. */
function mova_cli_opts(array $args): array
{
    $o = [];
    foreach ($args as $a) {
        if (strncmp($a, '--', 2) === 0) {
            $p = strpos($a, '=');
            if ($p === false) {
                $o[substr($a, 2)] = false;
            } else {
                $o[substr($a, 2, $p - 2)] = substr($a, $p + 1);
            }
        } elseif ($a !== '-' && $a !== '' && $a[0] === '-') {
            foreach (str_split(substr($a, 1)) as $ch) {
                $o[$ch] = false;
            }
        }
    }
    return $o;
}

use Mova\Backup\SitePack;
use Mova\Backup\SitePackExporter;

$o = mova_cli_opts(array_slice($argv, 1));
if (isset($o['h']) || isset($o['help'])) {
    fwrite(STDOUT, preg_replace('/^.*?\/\*\*|\*\/.*$/s', '', file_get_contents(__FILE__)) . "\n");
    exit(0);
}
if (isset($o['list'])) {
    foreach (SitePackExporter::inventory() as $g => $i) {
        fwrite(STDOUT, str_pad($g, 14) . str_pad((string) $i['count'], 7, ' ', STR_PAD_LEFT) . '  ' . $i['detail'] . "\n");
    }
    exit(0);
}
$groups = (string) ($o['groups'] ?? 'all');
$list = array_filter(array_map('trim', explode(',', $groups)));
$groups = [];
foreach ($list as $g) {
    // "all" expands to the default (non-personal) groups and can be combined, e.g. --groups=all,subscribers
    $groups = array_merge($groups, $g === 'all' ? SitePack::defaultGroups() : [$g]);
}
$groups = array_values(array_unique($groups));
$unknown = array_diff($groups, array_keys(SitePack::groups()));
if ($unknown) {
    fwrite(STDERR, 'Unknown group(s): ' . implode(', ', $unknown) . "\n");
    exit(2);
}
$types = isset($o['types']) ? array_filter(array_map('trim', explode(',', (string) $o['types']))) : [];
try {
    $res = (new SitePackExporter())->export(array_values($groups), array_values($types), [
        'min_version' => (string) ($o['min-version'] ?? ''),
        'out' => (string) ($o['out'] ?? ''),
        'label' => (string) ($o['label'] ?? ''),
    ]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Export failed: ' . $e->getMessage() . "\n");
    exit(1);
}
\Mova\Security\Audit::log('site_pack.exported', 'site_pack', null, ['name' => $res['name'], 'groups' => $res['manifest']['groups'], 'size' => $res['size'], 'via' => 'cli']);
if (!isset($o['q'])) {
    fwrite(STDOUT, 'Pack: ' . $res['path'] . "\n");
    fwrite(STDOUT, 'Size: ' . SitePack::fmtBytes($res['size']) . ' · ' . count($res['manifest']['files']) . " files\n");
    foreach ($res['manifest']['counts'] as $t => $n) {
        fwrite(STDOUT, '  ' . str_pad($t, 22) . $n . "\n");
    }
    foreach ($res['manifest']['warnings'] as $w) {
        fwrite(STDOUT, 'warning: ' . $w . "\n");
    }
} else {
    fwrite(STDOUT, $res['path'] . "\n");
}
