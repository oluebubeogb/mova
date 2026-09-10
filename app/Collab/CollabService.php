<?php
/**
 * Mova — collaboration: notes, assignments, review workflow
 */

namespace Mova\Collab;

use Mova\Core\Database;
use Mova\Content\ContentRepository;

class CollabService
{
    public const WORKFLOW = [
        'draft'     => ['review'],
        'review'    => ['draft', 'approved'],
        'approved'  => ['published', 'draft'],
        'published' => ['archived', 'draft'],
        'archived'  => ['draft'],
        'scheduled' => ['draft', 'published'],
    ];

    public function addNote(int $contentId, int $userId, string $body): int
    {
        return Database::insert('content_notes', [
            'content_id' => $contentId,
            'user_id'    => $userId,
            'body'       => trim($body),
            'created_at' => date('c'),
        ]);
    }

    public function notes(int $contentId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS author_name
             FROM content_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.content_id = :id
             ORDER BY n.created_at DESC
             LIMIT 50",
            ['id' => $contentId]
        );
    }

    public function assign(int $contentId, int $assigneeId, ?int $assignedBy, string $note = ''): int
    {
        return Database::insert('content_assignments', [
            'content_id'  => $contentId,
            'assignee_id' => $assigneeId,
            'assigned_by' => $assignedBy,
            'note'        => $note,
            'status'      => 'open',
            'created_at'  => date('c'),
        ]);
    }

    public function assignments(int $contentId): array
    {
        return Database::fetchAll(
            "SELECT a.*, u.name AS assignee_name
             FROM content_assignments a
             LEFT JOIN users u ON u.id = a.assignee_id
             WHERE a.content_id = :id AND a.status = 'open'
             ORDER BY a.created_at DESC",
            ['id' => $contentId]
        );
    }

    public function transition(int $contentId, string $toStatus, ?int $userId, string $note = ''): array
    {
        $repo = new ContentRepository();
        $content = $repo->find($contentId);
        if (!$content) {
            return ['ok' => false, 'error' => 'Not found'];
        }

        $from = $content['status'] ?? 'draft';
        $allowed = self::WORKFLOW[$from] ?? [];

        // Always allow moving to trash/archived paths via existing UI; for review flow:
        if (!in_array($toStatus, $allowed, true) && !in_array($toStatus, ['draft', 'published', 'scheduled', 'archived', 'trash', 'review', 'approved'], true)) {
            return ['ok' => false, 'error' => "Cannot move from {$from} to {$toStatus}"];
        }

        $repo->update($contentId, ['status' => $toStatus]);
        Database::insert('content_reviews', [
            'content_id'  => $contentId,
            'from_status' => $from,
            'to_status'   => $toStatus,
            'note'        => $note,
            'user_id'     => $userId,
            'created_at'  => date('c'),
        ]);

        return ['ok' => true, 'from' => $from, 'to' => $toStatus];
    }

    public function reviewLog(int $contentId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT r.*, u.name AS user_name
             FROM content_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.content_id = :id
             ORDER BY r.created_at DESC
             LIMIT :lim",
            ['id' => $contentId, 'lim' => $limit]
        );
    }
}
