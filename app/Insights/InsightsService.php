<?php
/**
 * Mova CMS - Lightweight insights
 */

namespace Mova\Insights;

use Mova\Core\Database;

class InsightsService
{
    public function summary(int $days = 30): array
    {
        $since = date('c', time() - ($days * 86400));

        $totalViews = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM page_views WHERE viewed_at >= :s",
            ['s' => $since]
        )['c'] ?? 0);

        $contentCount = Database::count('content', "status = 'published'");
        $mediaCount = Database::count('media');
        $subscriberCount = 0;
        try {
            $subscriberCount = Database::count('subscribers', "status = 'active'");
        } catch (\Throwable $e) {
        }

        return [
            'days'        => $days,
            'total_views' => $totalViews,
            'published'   => $contentCount,
            'media'       => $mediaCount,
            'subscribers' => $subscriberCount,
            'avg_per_day' => $days > 0 ? round($totalViews / max($days, 1), 1) : 0,
        ];
    }

    public function popularContent(int $limit = 10, int $days = 30): array
    {
        $since = date('c', time() - ($days * 86400));
        return Database::fetchAll(
            "SELECT c.id, c.title, c.slug, c.type, COUNT(pv.id) AS views
             FROM page_views pv
             JOIN content c ON c.id = pv.content_id
             WHERE pv.viewed_at >= :s AND pv.content_id IS NOT NULL
             GROUP BY c.id
             ORDER BY views DESC
             LIMIT :limit",
            ['s' => $since, 'limit' => $limit]
        );
    }

    public function viewsByDay(int $days = 14): array
    {
        $since = date('Y-m-d', time() - ($days * 86400));
        $rows = Database::fetchAll(
            "SELECT substr(viewed_at, 1, 10) AS day, COUNT(*) AS views
             FROM page_views
             WHERE viewed_at >= :s
             GROUP BY day
             ORDER BY day ASC",
            ['s' => $since]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['day']] = (int) $r['views'];
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', time() - ($i * 86400));
            $out[] = ['day' => $d, 'views' => $map[$d] ?? 0];
        }
        return $out;
    }

    public function topReferrers(int $limit = 10, int $days = 30): array
    {
        $since = date('c', time() - ($days * 86400));
        return Database::fetchAll(
            "SELECT COALESCE(NULLIF(referrer, ''), '(direct)') AS referrer, COUNT(*) AS hits
             FROM page_views
             WHERE viewed_at >= :s
             GROUP BY referrer
             ORDER BY hits DESC
             LIMIT :limit",
            ['s' => $since, 'limit' => $limit]
        );
    }

    public function recentViews(int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT pv.path, pv.referrer, pv.viewed_at, c.title
             FROM page_views pv
             LEFT JOIN content c ON c.id = pv.content_id
             ORDER BY pv.viewed_at DESC
             LIMIT :limit",
            ['limit' => $limit]
        );
    }
}
