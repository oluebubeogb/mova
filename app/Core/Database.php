<?php
/**
 * Mova CMS - Database (SQLite)
 */

namespace Mova\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $path = Bootstrap::config('database.path');
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Enable foreign keys
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');

        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    /** Close this request's connection (before the database file is replaced). */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /**
     * Write a consistent copy of the live database to $dest. VACUUM INTO reads through the WAL in one
     * transaction; a raw copy() of the main file can miss committed pages that still live in -wal.
     */
    public static function snapshotTo(string $dest): void
    {
        @unlink($dest);
        $pdo = self::connection();
        try {
            $pdo->exec('VACUUM INTO ' . $pdo->quote($dest));
            return;
        } catch (\Throwable $e) {
            @unlink($dest);
        }
        $busy = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
        if ($busy && (int) $busy[0] !== 0) {
            throw new \RuntimeException('Database is busy; could not take a consistent snapshot. Try again.');
        }
        if (!copy((string) Bootstrap::config('database.path'), $dest)) {
            throw new \RuntimeException('Could not write the database snapshot.');
        }
    }

    private static bool $ftsHealed = false;

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            // The public search index (content_fts) is written by triggers on `content`. If it is damaged
            // every page save fails. It is derived data: rebuild it once, then retry the statement.
            if (self::$ftsHealed || stripos($e->getMessage(), 'content_fts') === false
                || self::connection()->inTransaction() || !class_exists(\Mova\Hq\SearchIndex::class)) {
                throw $e;
            }
            self::$ftsHealed = true;
            \Mova\Core\ErrorHandler::report('content.fts.heal', $e);
            \Mova\Hq\SearchIndex::repairContentFts();
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        self::query($sql, $data);
        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);
        $stmt = self::query($sql, array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return self::query($sql, $params)->rowCount();
    }

    public static function exists(string $table, string $where, array $params = []): bool
    {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        return (bool) self::fetch($sql, $params);
    }

    public static function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}";
        $row = self::fetch($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }
}
