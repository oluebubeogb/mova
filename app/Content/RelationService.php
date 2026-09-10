<?php
/**
 * Mova — Content relations / graph (V2 Phase 1)
 */

namespace Mova\Content;

use Mova\Core\Database;

class RelationService
{
    public const TYPES = [
        'related'  => 'Related',
        'parent'   => 'Parent',
        'child'    => 'Child',
        'source'   => 'Source',
        'mentions' => 'Mentions',
    ];

    public function getRelated(int $contentId, ?string $relationType = null): array
    {
        $sql = "SELECT r.*, c.id AS related_content_id, c.title, c.slug, c.type, c.status, c.excerpt
                FROM content_relations r
                JOIN content c ON c.id = r.related_id
                WHERE r.content_id = :cid";
        $params = ['cid' => $contentId];
        if ($relationType) {
            $sql .= " AND r.relation_type = :rt";
            $params['rt'] = $relationType;
        }
        $sql .= " ORDER BY r.sort_order ASC, r.id ASC";
        return Database::fetchAll($sql, $params);
    }

    public function getRelatedIds(int $contentId, string $relationType = 'related'): array
    {
        $rows = Database::fetchAll(
            "SELECT related_id FROM content_relations WHERE content_id = :cid AND relation_type = :rt ORDER BY sort_order",
            ['cid' => $contentId, 'rt' => $relationType]
        );
        return array_map(static fn ($r) => (int) $r['related_id'], $rows);
    }

    public function sync(int $contentId, array $relatedIds, string $relationType = 'related'): void
    {
        Database::delete(
            'content_relations',
            'content_id = :cid AND relation_type = :rt',
            ['cid' => $contentId, 'rt' => $relationType]
        );

        $relatedIds = array_unique(array_filter(array_map('intval', $relatedIds)));
        $order = 0;
        $now = date('c');
        foreach ($relatedIds as $rid) {
            if ($rid === $contentId || $rid < 1) {
                continue;
            }
            Database::insert('content_relations', [
                'content_id'    => $contentId,
                'related_id'    => $rid,
                'relation_type' => $relationType,
                'sort_order'    => $order++,
                'created_at'    => $now,
            ]);
        }
    }

    public function add(int $contentId, int $relatedId, string $relationType = 'related'): void
    {
        if ($contentId === $relatedId) {
            return;
        }
        $exists = Database::fetch(
            "SELECT id FROM content_relations WHERE content_id = :c AND related_id = :r AND relation_type = :t",
            ['c' => $contentId, 'r' => $relatedId, 't' => $relationType]
        );
        if ($exists) {
            return;
        }
        Database::insert('content_relations', [
            'content_id'    => $contentId,
            'related_id'    => $relatedId,
            'relation_type' => $relationType,
            'sort_order'    => 0,
            'created_at'    => date('c'),
        ]);
    }

    public function remove(int $contentId, int $relatedId, string $relationType = 'related'): void
    {
        Database::delete(
            'content_relations',
            'content_id = :c AND related_id = :r AND relation_type = :t',
            ['c' => $contentId, 'r' => $relatedId, 't' => $relationType]
        );
    }
}
