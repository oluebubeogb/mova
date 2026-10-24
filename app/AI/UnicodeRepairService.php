<?php
/**
 * Repairs content damaged by the pre-1.x AI JSON-recovery bug, where stripcslashes() removed the
 * backslash of escapes like \u2019 and left "Nigeriau2019s", "fastu2011changing", "20u202f%".
 *
 * Only the corrupted pattern is touched: a letter/digit immediately followed by "u" + one of the
 * known punctuation codepoints (2010–2015, 2018–201F, 2026, 202F, 00A0, 2032–2033). See
 * AiText::repairCorrupted() for the exact rules that avoid false positives such as "menu2019".
 *
 * Used by bin/fix-unicode-escapes.php and HQ → Operations → Text repair.
 */

declare(strict_types=1);

namespace Mova\AI;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class UnicodeRepairService
{
    /** table => [primary key, content id column, text columns] */
    private const TARGETS = [
        'content'      => ['id', 'id', ['title', 'excerpt', 'body']],
        'content_meta' => ['id', 'content_id', ['meta_value']],
        'revisions'    => ['id', 'content_id', ['title', 'excerpt', 'body', 'meta']],
    ];

    /**
     * @return list<array{table:string,id:int,content_id:int,column:string,hits:list<array{match:string,replacement:string,context:string}>,new:string}>
     */
    public function scan(?int $contentId = null): array
    {
        $pdo = Database::connection();
        $found = [];
        foreach (self::TARGETS as $table => [$pk, $cidCol, $cols]) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $available = $this->columns($table);
            $cols = array_values(array_intersect($cols, $available));
            if ($cols === []) {
                continue;
            }
            // Cheap pre-filter in SQL: the text must contain "u20", "u00a0" (case-insensitive by LIKE).
            $like = [];
            foreach ($cols as $c) {
                $like[] = "({$c} LIKE '%u20%' OR {$c} LIKE '%u00a0%')";
            }
            $sql = "SELECT {$pk} AS _pk, {$cidCol} AS _cid, " . implode(', ', $cols) . " FROM {$table} WHERE (" . implode(' OR ', $like) . ')';
            $params = [];
            if ($contentId !== null) {
                $sql .= " AND {$cidCol} = ?";
                $params[] = $contentId;
            }
            $sql .= " ORDER BY {$pk}";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                foreach ($cols as $c) {
                    $old = (string) ($row[$c] ?? '');
                    if ($old === '') {
                        continue;
                    }
                    $new = AiText::repairCorrupted($old, $hits);
                    if ($hits !== [] && $new !== $old) {
                        $found[] = [
                            'table' => $table,
                            'id' => (int) $row['_pk'],
                            'content_id' => (int) $row['_cid'],
                            'column' => $c,
                            'hits' => $hits,
                            'new' => $new,
                        ];
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Back up, then write the repaired values. Returns the scan result plus the backup path.
     *
     * @return array{findings:list<array>,backup:?string,rows:int,matches:int}
     */
    public function apply(?int $contentId = null): array
    {
        $findings = $this->scan($contentId);
        if ($findings === []) {
            return ['findings' => [], 'backup' => null, 'rows' => 0, 'matches' => 0];
        }
        $backup = $this->backup($findings);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($findings as $f) {
                [$pk] = self::TARGETS[$f['table']];
                $st = $pdo->prepare("UPDATE {$f['table']} SET {$f['column']} = ? WHERE {$pk} = ?");
                $st->execute([$f['new'], $f['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->afterWrite();
        $matches = 0;
        foreach ($findings as $f) {
            $matches += count($f['hits']);
        }
        return ['findings' => $findings, 'backup' => $backup, 'rows' => count($findings), 'matches' => $matches];
    }

    /** Full SQLite snapshot (VACUUM INTO) + a JSON file with the exact old values of every changed field. */
    private function backup(array $findings): string
    {
        $dir = Bootstrap::path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stamp = date('Ymd-His');
        $dbFile = $dir . '/pre-unicode-fix-' . $stamp . '.sqlite';
        $pdo = Database::connection();
        try {
            $pdo->exec('VACUUM INTO ' . $pdo->quote($dbFile));
        } catch (\Throwable $e) {
            $src = (string) Bootstrap::config('database.path');
            try {
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable $e2) {
            }
            if (!@copy($src, $dbFile)) {
                throw new \RuntimeException('Could not back up the database before repairing: ' . $e->getMessage());
            }
        }
        @chmod($dbFile, 0600);
        $rows = [];
        foreach ($findings as $f) {
            [$pk] = self::TARGETS[$f['table']];
            $old = Database::fetch("SELECT {$f['column']} AS v FROM {$f['table']} WHERE {$pk} = ?", [$f['id']]);
            $rows[] = ['table' => $f['table'], 'id' => $f['id'], 'column' => $f['column'], 'old' => $old['v'] ?? null];
        }
        $jsonFile = $dir . '/pre-unicode-fix-' . $stamp . '.json';
        file_put_contents($jsonFile, json_encode(['created_at' => date('c'), 'rows' => $rows], AiText::JSON_FLAGS | JSON_PRETTY_PRINT));
        @chmod($jsonFile, 0600);
        return $dbFile;
    }

    /** Page cache + search indexes (content_fts is kept in sync by triggers). */
    private function afterWrite(): void
    {
        foreach ([
            static fn () => \Mova\Cache\PageCache::flush(),
            static fn () => \Mova\Hq\SearchIndex::rebuild(),
            static fn () => class_exists(\MovaSiteAi\SiteIndex::class) ? \MovaSiteAi\SiteIndex::rebuild() : null,
        ] as $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
            }
        }
    }

    private function tableExists(string $t): bool
    {
        return Database::fetch("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]) !== null;
    }

    /** @return list<string> */
    private function columns(string $t): array
    {
        return array_column(Database::fetchAll('PRAGMA table_info(' . $t . ')'), 'name');
    }
}
