<?php
/**
 * PostExtras — the reading furniture under a blog post (all themes, server-rendered, no JS needed):
 * category chip, related posts, previous / next and plain share links. mova-ui.js adds the
 * optional extras (reading progress, table of contents, "copy link") on top.
 */

declare(strict_types=1);

namespace Mova\Theme;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class PostExtras
{
    public const POST_TYPES = ['post', 'article', 'news'];

    private static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** @return array{id:int,name:string,slug:string,url:string,tone:int}|null */
    public static function category(array $content): ?array
    {
        try {
            $row = Database::fetch(
                'SELECT c.id, c.name, c.slug FROM content_categories cc JOIN categories c ON c.id = cc.category_id
                 WHERE cc.content_id = ? ORDER BY c.id LIMIT 1',
                [(int) ($content['id'] ?? 0)]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $url = '/blog';
        try {
            // A published blog with the same slug as the category is its natural archive.
            $b = Database::fetch("SELECT slug FROM blogs WHERE slug = ? AND status = 'published' LIMIT 1", [(string) $row['slug']]);
            if ($b) {
                $url = '/blog/' . rawurlencode((string) $b['slug']);
            }
        } catch (\Throwable $e) {
        }
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'url' => $url, 'tone' => ((int) $row['id']) % 6];
    }

    private static function publicWhere(): string
    {
        return "c.status = 'published' AND (c.visibility IS NULL OR c.visibility = '' OR c.visibility = 'public')"
            . " AND c.type IN ('" . implode("','", self::POST_TYPES) . "')";
    }

    /** Up to $n posts sharing a category, topped up with the latest posts. */
    public static function related(array $content, int $n = 3): array
    {
        $id = (int) ($content['id'] ?? 0);
        $out = [];
        try {
            $rows = Database::fetchAll(
                'SELECT DISTINCT c.id, c.title, c.slug, c.featured_image, c.published_at, c.created_at FROM content c
                 JOIN content_categories cc ON cc.content_id = c.id
                 WHERE ' . self::publicWhere() . ' AND c.id != ? AND cc.category_id IN (SELECT category_id FROM content_categories WHERE content_id = ?)
                 ORDER BY COALESCE(c.published_at, c.created_at) DESC LIMIT ' . (int) $n,
                [$id, $id]
            );
            foreach ($rows as $r) {
                $out[(int) $r['id']] = $r;
            }
            if (count($out) < $n) {
                $more = Database::fetchAll(
                    'SELECT c.id, c.title, c.slug, c.featured_image, c.published_at, c.created_at FROM content c
                     WHERE ' . self::publicWhere() . ' AND c.id != ? ORDER BY COALESCE(c.published_at, c.created_at) DESC LIMIT ' . (int) ($n * 2),
                    [$id]
                );
                foreach ($more as $r) {
                    if (count($out) >= $n) {
                        break;
                    }
                    $out[(int) $r['id']] = $out[(int) $r['id']] ?? $r;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return array_values($out);
    }

    /** @return array{prev:?array,next:?array} prev = older, next = newer */
    public static function adjacent(array $content): array
    {
        $id = (int) ($content['id'] ?? 0);
        $when = (string) ($content['published_at'] ?? $content['created_at'] ?? '');
        $res = ['prev' => null, 'next' => null];
        if ($when === '') {
            return $res;
        }
        $k = 'COALESCE(c.published_at, c.created_at)';
        try {
            $res['prev'] = Database::fetch('SELECT c.id, c.title, c.slug FROM content c WHERE ' . self::publicWhere()
                . " AND c.id != ? AND ($k < ? OR ($k = ? AND c.id < ?)) ORDER BY $k DESC, c.id DESC LIMIT 1", [$id, $when, $when, $id]) ?: null;
            $res['next'] = Database::fetch('SELECT c.id, c.title, c.slug FROM content c WHERE ' . self::publicWhere()
                . " AND c.id != ? AND ($k > ? OR ($k = ? AND c.id > ?)) ORDER BY $k ASC, c.id ASC LIMIT 1", [$id, $when, $when, $id]) ?: null;
        } catch (\Throwable $e) {
        }
        return $res;
    }

    public static function absoluteUrl(string $path): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            return ($https ? 'https' : 'http') . '://' . $host . $path;
        }
        return rtrim((string) Bootstrap::config('app_url', ''), '/') . $path;
    }

    public static function render(array $content): string
    {
        $title = (string) ($content['title'] ?? '');
        $url = self::absoluteUrl('/' . ltrim((string) ($content['slug'] ?? ''), '/'));
        $u = rawurlencode($url);
        $t = rawurlencode($title);
        $h = '<footer class="mx-post-extras">';
        $h .= '<div class="mx-share" aria-label="Share this post"><span class="mx-share__label">Share</span>'
            . '<a class="mx-share__btn" href="https://x.com/intent/post?url=' . $u . '&amp;text=' . $t . '" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z"/></svg><span>X</span></a>'
            . '<a class="mx-share__btn" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $u . '" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.75h4V21H3V9.75Zm6.5 0h3.8v1.6h.06c.53-1 1.84-2.06 3.79-2.06 4.05 0 4.8 2.67 4.8 6.13V21h-4v-4.9c0-1.17-.02-2.68-1.63-2.68-1.64 0-1.89 1.28-1.89 2.6V21h-4V9.75Z"/></svg><span>LinkedIn</span></a>'
            . '<a class="mx-share__btn" href="https://wa.me/?text=' . $t . '%20' . $u . '" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.8 2.3.9 2.5c.1.2 1.6 2.5 4 3.5 1.5.6 2 .7 2.8.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg><span>WhatsApp</span></a>'
            . '<a class="mx-share__btn" href="mailto:?subject=' . $t . '&amp;body=' . $u . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1 2.4V17h16V7.4l-8 5.3-8-5.3ZM5.4 7l6.6 4.4L18.6 7H5.4Z"/></svg><span>Email</span></a>'
            . '<button type="button" class="mx-share__btn" data-mx-copy="' . self::e($url) . '" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.6 13.4a1 1 0 0 1 0-1.4l3.5-3.5a3 3 0 1 1 4.2 4.2l-2 2a1 1 0 1 1-1.4-1.4l2-2a1 1 0 1 0-1.4-1.4l-3.5 3.5a1 1 0 0 1-1.4 0Zm2.8-2.8a1 1 0 0 1 0 1.4l-3.5 3.5a3 3 0 1 1-4.2-4.2l2-2a1 1 0 0 1 1.4 1.4l-2 2a1 1 0 1 0 1.4 1.4l3.5-3.5a1 1 0 0 1 1.4 0Z"/></svg><span>Copy link</span></button>'
            . '</div>';

        $adj = self::adjacent($content);
        if ($adj['prev'] || $adj['next']) {
            $h .= '<nav class="mx-post-nav" aria-label="More posts">';
            foreach (['prev' => 'Previous', 'next' => 'Next'] as $k => $lbl) {
                $p = $adj[$k];
                $h .= $p
                    ? '<a class="mx-post-nav__item is-' . $k . '" href="/' . self::e(ltrim((string) $p['slug'], '/')) . '" rel="' . $k . '"><small>' . $lbl . '</small><span>' . self::e($p['title']) . '</span></a>'
                    : '<span class="mx-post-nav__item is-' . $k . ' is-empty" aria-hidden="true"></span>';
            }
            $h .= '</nav>';
        }

        $rel = self::related($content, 3);
        if ($rel) {
            $h .= '<section class="mx-related" aria-labelledby="mx-related-h"><h2 id="mx-related-h" class="mx-related__title">Keep reading</h2><div class="mx-related__grid">';
            foreach ($rel as $r) {
                $img = trim((string) ($r['featured_image'] ?? ''));
                $date = \Mova\Core\Time::fmt($r['published_at'] ?? $r['created_at'] ?? '', 'M j, Y');
                $h .= '<a class="mx-related__card" href="/' . self::e(ltrim((string) $r['slug'], '/')) . '">'
                    . ($img !== '' ? '<span class="mx-related__img"><img src="' . self::e($img) . '" alt="" loading="lazy" decoding="async"></span>' : '<span class="mx-related__img is-blank" aria-hidden="true"></span>')
                    . '<span class="mx-related__body">' . ($date !== '' ? '<time>' . self::e($date) . '</time>' : '') . '<strong>' . self::e($r['title']) . '</strong></span></a>';
            }
            $h .= '</div></section>';
        }
        return $h . '</footer>';
    }
}
