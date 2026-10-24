<?php
/**
 * Opens and validates a .movapack without extracting it blindly:
 *  - zip integrity, manifest schema, sha256 of every file (streamed, resumable for background jobs)
 *  - min_mova_version <= installed version ("upgrade Mova first")
 *  - zip-slip / path traversal, symlinks, PHP or executables anywhere, only expected media types
 * Entries are only ever read through streams into paths we build ourselves.
 */

declare(strict_types=1);

namespace Mova\Backup;

final class SitePackReader
{
    private \ZipArchive $zip;
    private string $path;
    private ?array $manifest = null;
    /** @var array<string,int> entry name => index */
    private array $entries = [];
    public array $errors = [];
    public array $warnings = [];
    public bool $versionBlocked = false;

    private const MAX_ENTRIES = 200000;
    private const MAX_DATA_BYTES = 256 * 1048576;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->zip = new \ZipArchive();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function name(): string
    {
        return basename($this->path);
    }

    /** Structural checks (fast). Returns true when the pack may be previewed. */
    public function open(): bool
    {
        $this->errors = [];
        if (!is_file($this->path) || is_link($this->path)) {
            $this->errors[] = 'Pack file not found.';
            return false;
        }
        $rc = $this->zip->open($this->path, \ZipArchive::CHECKCONS | \ZipArchive::RDONLY);
        if ($rc !== true) {
            $this->errors[] = 'Not a valid .movapack (zip integrity check failed, code ' . (int) $rc . ').';
            return false;
        }
        $n = $this->zip->numFiles;
        if ($n < 1 || $n > self::MAX_ENTRIES) {
            $this->errors[] = 'The pack has ' . $n . ' entries — refusing.';
            return false;
        }
        $allowedData = array_flip(SitePack::allowedDataFiles());
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $st = $this->zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
            if (!$st) {
                $this->errors[] = 'Unreadable entry #' . $i . '.';
                continue;
            }
            $name = (string) $st['name'];
            $why = self::badEntryName($name);
            if ($why === null && str_ends_with($name, '/')) {
                continue; // directory entry
            }
            if ($why === null) {
                $opsys = 0;
                $attr = 0;
                if ($this->zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === \ZipArchive::OPSYS_UNIX) {
                    $mode = ($attr >> 16) & 0170000;
                    if ($mode === 0120000) {
                        $why = 'symbolic links are not allowed';
                    }
                }
            }
            if ($why === null) {
                if ($name === 'manifest.json') {
                    // ok
                } elseif (str_starts_with($name, 'data/')) {
                    if (!isset($allowedData[$name])) {
                        $why = 'unexpected data file (only site content tables are accepted; users, keys and logs are never imported)';
                    } elseif ((int) $st['size'] > self::MAX_DATA_BYTES) {
                        $why = 'data file too large';
                    }
                } elseif (str_starts_with($name, 'media/')) {
                    $rel = substr($name, 6);
                    if (SitePack::safeRelPath($rel) === null) {
                        $why = 'unsafe media path';
                    } elseif (!SitePack::mediaExtAllowed($rel)) {
                        $why = 'file type not allowed in media';
                    }
                } else {
                    $why = 'unexpected file outside manifest.json, data/ and media/';
                }
            }
            if ($why === null && (int) $st['size'] > 10485760 && (int) $st['comp_size'] > 0 && $st['size'] / max(1, $st['comp_size']) > 200) {
                $why = 'suspicious compression ratio (zip bomb?)';
            }
            if ($why !== null) {
                $this->errors[] = 'Rejected entry "' . self::clip($name) . '": ' . $why . '.';
                continue;
            }
            if (isset($this->entries[$name])) {
                $this->errors[] = 'Duplicate entry "' . self::clip($name) . '".';
                continue;
            }
            $this->entries[$name] = $i;
            $total += (int) $st['size'];
        }
        if ($this->errors) {
            return false;
        }
        $free = @disk_free_space(dirname($this->path));
        if ($free !== false && $total > $free * 0.9) {
            $this->errors[] = 'Not enough disk space to import this pack (' . SitePack::fmtBytes($total) . ' needed).';
            return false;
        }
        // Manifest schema
        if (!isset($this->entries['manifest.json'])) {
            $this->errors[] = 'manifest.json is missing — this is not a Mova site pack.';
            return false;
        }
        $m = json_decode((string) $this->zip->getFromIndex($this->entries['manifest.json']), true);
        if (!is_array($m)) {
            $this->errors[] = 'manifest.json is not valid JSON.';
            return false;
        }
        $schema = [];
        if (($m['format'] ?? '') !== SitePack::FORMAT) {
            $schema[] = 'format must be "' . SitePack::FORMAT . '"';
        }
        if (!is_int($m['pack_format'] ?? null) || $m['pack_format'] < 1) {
            $schema[] = 'pack_format must be a positive integer';
        } elseif ($m['pack_format'] > SitePack::PACK_FORMAT) {
            $schema[] = 'pack_format ' . $m['pack_format'] . ' is newer than this Mova understands (' . SitePack::PACK_FORMAT . ') — upgrade Mova first';
            $this->versionBlocked = true;
        }
        foreach (['mova_version', 'min_mova_version', 'exported_at'] as $k) {
            if (!is_string($m[$k] ?? null) || $m[$k] === '') {
                $schema[] = $k . ' is required';
            }
        }
        if (!is_array($m['groups'] ?? null) || $m['groups'] === []) {
            $schema[] = 'groups must be a non-empty list';
        } else {
            $unknown = array_diff($m['groups'], array_keys(SitePack::groups()));
            if ($unknown) {
                $schema[] = 'unknown groups: ' . implode(', ', array_map([self::class, 'clip'], $unknown));
            }
        }
        if (!is_array($m['files'] ?? null)) {
            $schema[] = 'files (checksums) is required';
        } else {
            foreach ($m['files'] as $fname => $info) {
                if (!is_array($info) || !is_string($info['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $info['sha256'])) {
                    $schema[] = 'bad checksum entry for ' . self::clip((string) $fname);
                    break;
                }
                if (!isset($this->entries[$fname])) {
                    $schema[] = 'file listed in manifest is missing: ' . self::clip((string) $fname);
                }
            }
            foreach ($this->entries as $ename => $_) {
                if ($ename !== 'manifest.json' && !isset($m['files'][$ename])) {
                    $schema[] = 'file not listed in manifest checksums: ' . self::clip($ename);
                }
            }
        }
        if ($schema) {
            foreach (array_slice($schema, 0, 20) as $s) {
                $this->errors[] = 'Manifest: ' . $s . '.';
            }
            return false;
        }
        $min = (string) $m['min_mova_version'];
        if (version_compare($min, SitePack::version(), '>')) {
            $this->versionBlocked = true;
            $this->errors[] = 'This pack needs Mova ' . $min . ' or newer; this site runs ' . SitePack::version() . '. Upgrade Mova first, then import.';
            $this->manifest = $m;
            return false;
        }
        $this->manifest = $m;
        return true;
    }

    /** Why an entry name is unsafe, or null. */
    public static function badEntryName(string $name): ?string
    {
        if ($name === '' || str_contains($name, "\0")) {
            return 'empty or NUL in name';
        }
        if (str_contains($name, '\\')) {
            return 'backslash in path';
        }
        if ($name[0] === '/' || preg_match('/^[A-Za-z]:/', $name)) {
            return 'absolute path';
        }
        foreach (explode('/', rtrim($name, '/')) as $seg) {
            if ($seg === '..' || $seg === '.') {
                return 'path traversal (..)';
            }
        }
        if (preg_match(SitePack::FORBIDDEN_EXT_PATTERN, $name)) {
            return 'PHP/executable files are never accepted';
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $name)) {
            return 'control characters in name';
        }
        return null;
    }

    private static function clip(string $s): string
    {
        $s = preg_replace('/[\x00-\x1f\x7f]/', '?', $s) ?? '';
        return mb_strlen($s) > 120 ? mb_substr($s, 0, 117) . '…' : $s;
    }

    public function manifest(): array
    {
        return $this->manifest ?? [];
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    /** @return list<string> */
    public function mediaEntries(): array
    {
        return array_values(array_filter(array_keys($this->entries), static fn ($n) => str_starts_with($n, 'media/') && !str_ends_with($n, '/')));
    }

    /** sha256 from the manifest for an entry. */
    public function sha(string $name): ?string
    {
        return $this->manifest['files'][$name]['sha256'] ?? null;
    }

    /**
     * Verify checksums (streamed). Resumable: pass the index to start from and a deadline (microtime);
     * returns the next index to process, or -1 when every file was verified. Errors go to $this->errors.
     */
    public function verifyChecksums(int $from = 0, float $deadline = 0.0, ?callable $progress = null): int
    {
        $names = array_keys($this->manifest['files'] ?? []);
        sort($names);
        $count = count($names);
        for ($i = $from; $i < $count; $i++) {
            $name = $names[$i];
            $res = $this->hashEntry($name);
            if ($res === null) {
                $this->errors[] = 'Cannot read "' . self::clip($name) . '" from the pack.';
            } elseif (!hash_equals((string) $this->sha($name), $res['sha'])) {
                $this->errors[] = 'Checksum mismatch for "' . self::clip($name) . '" (the pack was changed or damaged after export).';
            } elseif ($res['php']) {
                $this->errors[] = 'Rejected "' . self::clip($name) . '": contains PHP code.';
            } elseif ($res['exe'] && str_starts_with($name, 'media/')) {
                $this->errors[] = 'Rejected "' . self::clip($name) . '": looks like an executable.';
            }
            if ($progress) {
                $progress($i + 1, $count);
            }
            if ($deadline > 0 && microtime(true) > $deadline && $i + 1 < $count) {
                return $i + 1;
            }
        }
        return -1;
    }

    public function fileCount(): int
    {
        return count($this->manifest['files'] ?? []);
    }

    /** @return array{sha:string,php:bool,exe:bool,size:int}|null */
    private function hashEntry(string $name): ?array
    {
        $st = $this->stream($name);
        if (!$st) {
            return null;
        }
        $ctx = hash_init('sha256');
        $tail = '';
        $php = false;
        $first = true;
        $exe = false;
        $size = 0;
        $isMedia = str_starts_with($name, 'media/');
        while (!feof($st)) {
            $buf = fread($st, 1048576);
            if ($buf === false || $buf === '') {
                break;
            }
            $size += strlen($buf);
            hash_update($ctx, $buf);
            if ($isMedia) {
                if ($first) {
                    $exe = str_starts_with($buf, "\x7fELF") || str_starts_with($buf, 'MZ') || str_starts_with($buf, '#!');
                    $first = false;
                }
                if (!$php && stripos($tail . $buf, '<?php') !== false) {
                    $php = true;
                }
                $tail = substr($buf, -8);
            }
        }
        fclose($st);
        return ['sha' => hash_final($ctx), 'php' => $php, 'exe' => $exe, 'size' => $size];
    }

    /** @return resource|null */
    public function stream(string $name)
    {
        if (!isset($this->entries[$name])) {
            return null;
        }
        $s = $this->zip->getStreamIndex($this->entries[$name]);
        return $s ?: null;
    }

    /**
     * Decode a data/ file. @return array{table:string,group?:string,columns?:array,rows:list<array>}|null
     */
    public function data(string $name): ?array
    {
        if (!isset($this->entries[$name])) {
            return null;
        }
        $raw = $this->zip->getFromIndex($this->entries[$name]);
        if ($raw === false) {
            return null;
        }
        $j = json_decode($raw, true, 64);
        unset($raw);
        if (!is_array($j) || !is_array($j['rows'] ?? null)) {
            throw new \RuntimeException($name . ' is not valid pack data (expected {"rows":[…]}).');
        }
        $rows = [];
        foreach ($j['rows'] as $r) {
            if (is_array($r)) {
                $rows[] = $r;
            }
        }
        $j['rows'] = $rows;
        return $j;
    }

    /**
     * Copy one media entry to $dest while hashing it; refuses on checksum mismatch or PHP content.
     */
    public function extractTo(string $name, string $dest): void
    {
        $st = $this->stream($name);
        if (!$st) {
            throw new \RuntimeException('Cannot read ' . self::clip($name) . ' from the pack.');
        }
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            fclose($st);
            throw new \RuntimeException('Cannot create staging folder.');
        }
        $out = fopen($dest, 'wb');
        if (!$out) {
            fclose($st);
            throw new \RuntimeException('Cannot write staging file.');
        }
        $ctx = hash_init('sha256');
        $tail = '';
        $php = false;
        while (!feof($st)) {
            $buf = fread($st, 1048576);
            if ($buf === false || $buf === '') {
                break;
            }
            hash_update($ctx, $buf);
            if (!$php && stripos($tail . $buf, '<?php') !== false) {
                $php = true;
            }
            $tail = substr($buf, -8);
            fwrite($out, $buf);
        }
        fclose($st);
        fclose($out);
        if ($php || !hash_equals((string) $this->sha($name), hash_final($ctx))) {
            @unlink($dest);
            throw new \RuntimeException('Media file failed verification: ' . self::clip($name));
        }
    }

    public function close(): void
    {
        try {
            $this->zip->close();
        } catch (\Throwable $e) {
        }
    }

    // ── validation cache (so a background job does not re-hash a pack the preview just verified) ──

    private static function cacheFile(): string
    {
        return SitePack::importsDir() . '/.verified.json';
    }

    public function fingerprint(): string
    {
        clearstatcache(true, $this->path);
        return sha1($this->name() . '|' . (int) @filesize($this->path) . '|' . (int) @filemtime($this->path) . '|' . (string) @fileinode($this->path));
    }

    public function markVerified(): void
    {
        $all = json_decode((string) @file_get_contents(self::cacheFile()), true) ?: [];
        $all[$this->name()] = ['fp' => $this->fingerprint(), 'at' => time()];
        if (count($all) > 50) {
            uasort($all, static fn ($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));
            $all = array_slice($all, 0, 50, true);
        }
        @file_put_contents(self::cacheFile(), json_encode($all), LOCK_EX);
    }

    public function isVerified(): bool
    {
        $all = json_decode((string) @file_get_contents(self::cacheFile()), true) ?: [];
        return ($all[$this->name()]['fp'] ?? '') === $this->fingerprint();
    }

    /**
     * Full validation in one go (preview/CLI). @return bool
     */
    public function validate(bool $useCache = true): bool
    {
        if (!$this->open()) {
            return false;
        }
        if ($useCache && $this->isVerified()) {
            return true;
        }
        @set_time_limit(0);
        $this->verifyChecksums(0);
        if ($this->errors) {
            return false;
        }
        $this->markVerified();
        return true;
    }

    // ── packs waiting in storage/imports (upload or FTP) ──

    public static function listImports(): array
    {
        $dir = SitePack::importsDir();
        $out = [];
        foreach (array_merge(glob($dir . '/*.' . SitePack::EXT) ?: [], glob($dir . '/*.zip') ?: []) as $p) {
            if (is_link($p)) {
                continue;
            }
            $out[] = ['name' => basename($p), 'path' => $p, 'size' => (int) filesize($p), 'mtime' => (int) filemtime($p)];
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /** Resolve a pack name in storage/imports safely (no paths). */
    public static function resolveImport(string $name): ?string
    {
        $name = basename($name);
        if ($name === '' || !preg_match('/^[A-Za-z0-9._ ()+-]+\.(movapack|zip)$/', $name)) {
            return null;
        }
        $p = SitePack::importsDir() . '/' . $name;
        return is_file($p) && !is_link($p) ? $p : null;
    }
}
