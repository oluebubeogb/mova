<?php
declare(strict_types=1);

namespace Mova\Blog;

use Mova\Core\Database;
use Mova\Content\ContentRepository;
use Mova\Content\TaxonomyService;
use Mova\Plugin\PluginManager;

class BlogService
{
    private ContentRepository $content;
    private TaxonomyService $tax;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->tax = new TaxonomyService();
    }

    public function all(bool $publishedOnly = false, int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT * FROM blogs';
        $params = [];
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $sql .= ' ORDER BY CASE WHEN slug = \'blog\' THEN 0 ELSE 1 END, title ASC LIMIT :lim OFFSET :off';
        $params['lim'] = $limit;
        $params['off'] = $offset;
        return array_map([$this, 'hydrate'], Database::fetchAll($sql, $params));
    }

    public function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM blogs WHERE id = :id', ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = false): ?array
    {
        $sql = 'SELECT * FROM blogs WHERE slug = :s';
        $params = ['s' => $slug];
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $row = Database::fetch($sql, $params);
        return $row ? $this->hydrate($row) : null;
    }

    public function create(array $data): int
    {
        $now = date('c');
        $slug = $this->uniqueSlug((string) ($data['slug'] ?? $data['title'] ?? 'blog'));
        $id = Database::insert('blogs', [
            'title' => trim((string) ($data['title'] ?? 'Blog')) ?: 'Blog',
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => in_array($data['status'] ?? 'published', ['published', 'draft'], true)
                ? ($data['status'] ?? 'published') : 'published',
            'mode' => ($data['mode'] ?? 'filter') === 'manual' ? 'manual' : 'filter',
            'allowed_types' => json_encode($this->normalizeTypes($data['allowed_types'] ?? ['article'])),
            'category_ids' => json_encode($this->normalizeIdList($data['category_ids'] ?? [])),
            'tag_ids' => json_encode($this->normalizeIdList($data['tag_ids'] ?? [])),
            'sort' => $this->normalizeSort($data['sort'] ?? 'date_desc'),
            'per_page' => max(1, min(50, (int) ($data['per_page'] ?? 12))),
            'excerpt_chars' => max(20, min(500, (int) ($data['excerpt_chars'] ?? 130))),
            'template_mode' => $this->normalizeTemplateMode($data['template_mode'] ?? 'default'),
            'template_html' => $this->normalizeTemplateMode($data['template_mode'] ?? 'default') === 'custom'
                ? (string) ($data['template_html'] ?? '') : null,
            'embed_options' => json_encode($this->normalizeEmbedOptions($data['embed_options'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (($data['mode'] ?? '') === 'manual' && !empty($data['content_ids']) && is_array($data['content_ids'])) {
            $this->setItems($id, $data['content_ids']);
        }
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $row = Database::fetch('SELECT * FROM blogs WHERE id = :id', ['id' => $id]);
        if (!$row) {
            return false;
        }
        $update = ['updated_at' => date('c')];
        if (array_key_exists('title', $data)) {
            $update['title'] = trim((string) $data['title']) ?: 'Blog';
        }
        if (array_key_exists('slug', $data)) {
            $slug = $this->slugify((string) $data['slug']);
            // Protect default blog slug from being stolen by others
            if ($row['slug'] === 'blog') {
                $update['slug'] = 'blog';
            } elseif ($slug !== '') {
                $update['slug'] = $this->uniqueSlug($slug, $id);
            }
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = trim((string) $data['description']) ?: null;
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = in_array($data['status'], ['published', 'draft'], true) ? $data['status'] : 'published';
        }
        if (array_key_exists('mode', $data)) {
            $update['mode'] = $data['mode'] === 'manual' ? 'manual' : 'filter';
        }
        if (array_key_exists('allowed_types', $data)) {
            $update['allowed_types'] = json_encode($this->normalizeTypes($data['allowed_types']));
        }
        if (array_key_exists('category_ids', $data)) {
            $update['category_ids'] = json_encode($this->normalizeIdList($data['category_ids']));
        }
        if (array_key_exists('tag_ids', $data)) {
            $update['tag_ids'] = json_encode($this->normalizeIdList($data['tag_ids']));
        }
        if (array_key_exists('sort', $data)) {
            $update['sort'] = $this->normalizeSort($data['sort']);
        }
        if (array_key_exists('per_page', $data)) {
            $update['per_page'] = max(1, min(50, (int) $data['per_page']));
        }
        if (array_key_exists('excerpt_chars', $data)) {
            $update['excerpt_chars'] = max(20, min(500, (int) $data['excerpt_chars']));
        }
        if (array_key_exists('template_mode', $data)) {
            $update['template_mode'] = $this->normalizeTemplateMode($data['template_mode']);
        }
        if (array_key_exists('template_html', $data)) {
            $update['template_html'] = (string) $data['template_html'];
        }
        if (array_key_exists('embed_options', $data)) {
            $update['embed_options'] = json_encode($this->normalizeEmbedOptions($data['embed_options']));
        }
        Database::update('blogs', $update, 'id = :id', ['id' => $id]);
        if (array_key_exists('content_ids', $data) && is_array($data['content_ids'])) {
            $this->setItems($id, $data['content_ids']);
        }
        return true;
    }

    public function delete(int $id): bool
    {
        $row = Database::fetch('SELECT * FROM blogs WHERE id = :id', ['id' => $id]);
        if (!$row) {
            return false;
        }
        if (($row['slug'] ?? '') === 'blog') {
            throw new \RuntimeException('The default /blog cannot be deleted.');
        }
        Database::delete('blog_items', 'blog_id = :id', ['id' => $id]);
        return Database::delete('blogs', 'id = :id', ['id' => $id]) > 0;
    }

    public function setItems(int $blogId, array $contentIds): void
    {
        Database::delete('blog_items', 'blog_id = :id', ['id' => $blogId]);
        $now = date('c');
        $order = 0;
        foreach ($contentIds as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            try {
                Database::insert('blog_items', [
                    'blog_id' => $blogId,
                    'content_id' => $cid,
                    'sort_order' => $order++,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // skip dupes
            }
        }
    }

    public function itemIds(int $blogId): array
    {
        $rows = Database::fetchAll(
            'SELECT content_id FROM blog_items WHERE blog_id = :id ORDER BY sort_order ASC, id ASC',
            ['id' => $blogId]
        );
        return array_map(fn($r) => (int) $r['content_id'], $rows);
    }

    /**
     * Paginated feed for public / embed.
     * @return array{items: array, has_more: bool, next_offset: int, total: int}
     */
    public function feed(int $blogId, int $limit = 12, int $offset = 0): array
    {
        $blog = $this->find($blogId);
        if (!$blog || ($blog['status'] ?? '') !== 'published') {
            return ['items' => [], 'has_more' => false, 'next_offset' => $offset, 'total' => 0];
        }
        $limit = max(1, min(50, $limit ?: (int) $blog['per_page']));
        $offset = max(0, $offset);

        if (($blog['mode'] ?? 'filter') === 'manual') {
            return $this->feedManual($blog, $limit, $offset);
        }
        return $this->feedFilter($blog, $limit, $offset);
    }

    private function feedManual(array $blog, int $limit, int $offset): array
    {
        $ids = $this->itemIds((int) $blog['id']);
        $total = count($ids);
        $slice = array_slice($ids, $offset, $limit);
        $items = [];
        foreach ($slice as $cid) {
            $row = $this->content->find($cid);
            if ($row && ($row['status'] ?? '') === 'published') {
                $items[] = $this->presentItem($row, (int) ($blog['excerpt_chars'] ?? 130));
            }
        }
        $next = $offset + $limit;
        return [
            'items' => $items,
            'has_more' => $next < $total,
            'next_offset' => $next,
            'total' => $total,
        ];
    }

    private function feedFilter(array $blog, int $limit, int $offset): array
    {
        $types = $blog['allowed_types'] ?: ['article'];
        $catIds = $blog['category_ids'] ?: [];
        $tagIds = $blog['tag_ids'] ?: [];
        $sort = $blog['sort'] ?? 'date_desc';

        $where = ["c.status = 'published'"];
        // Prefer public visibility when set
        $where[] = "(c.visibility IS NULL OR c.visibility = '' OR c.visibility = 'public')";
        $params = [];

        if ($types) {
            $in = [];
            foreach (array_values($types) as $i => $t) {
                $k = 't' . $i;
                $in[] = ':' . $k;
                $params[$k] = $t;
            }
            $where[] = 'c.type IN (' . implode(',', $in) . ')';
        }
        if ($catIds) {
            $in = [];
            foreach (array_values($catIds) as $i => $id) {
                $k = 'c' . $i;
                $in[] = ':' . $k;
                $params[$k] = (int) $id;
            }
            $where[] = 'EXISTS (SELECT 1 FROM content_categories cc WHERE cc.content_id = c.id AND cc.category_id IN (' . implode(',', $in) . '))';
        }
        if ($tagIds) {
            $in = [];
            foreach (array_values($tagIds) as $i => $id) {
                $k = 'g' . $i;
                $in[] = ':' . $k;
                $params[$k] = (int) $id;
            }
            $where[] = 'EXISTS (SELECT 1 FROM content_tags ct WHERE ct.content_id = c.id AND ct.tag_id IN (' . implode(',', $in) . '))';
        }

        $order = match ($sort) {
            'date_asc' => 'COALESCE(c.published_at, c.created_at) ASC',
            'title' => 'c.title ASC',
            'category' => '(SELECT MIN(cat.name) FROM categories cat JOIN content_categories cc2 ON cc2.category_id = cat.id WHERE cc2.content_id = c.id) ASC, COALESCE(c.published_at, c.created_at) DESC',
            default => 'COALESCE(c.published_at, c.created_at) DESC',
        };

        $whereSql = implode(' AND ', $where);
        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM content c WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $params['lim'] = $limit;
        $params['off'] = $offset;
        $rows = Database::fetchAll(
            "SELECT c.* FROM content c WHERE {$whereSql} ORDER BY {$order} LIMIT :lim OFFSET :off",
            $params
        );

        $items = [];
        $exLen = (int) ($blog['excerpt_chars'] ?? 130);
        foreach ($rows as $row) {
            $items[] = $this->presentItem($this->content->find((int) $row['id']) ?: $row, $exLen);
        }
        $next = $offset + $limit;
        return [
            'items' => $items,
            'has_more' => $next < $total,
            'next_offset' => $next,
            'total' => $total,
        ];
    }

    public function presentItem(array $row, ?int $excerptLen = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $cats = [];
        $tags = [];
        try {
            $catIds = $this->tax->getContentCategoryIds($id);
            foreach ($catIds as $cid) {
                $c = $this->tax->findCategory((int) $cid);
                if ($c) {
                    $cats[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'slug' => $c['slug']];
                }
            }
            $tagRows = $this->tax->getContentTags($id);
            foreach ($tagRows as $tg) {
                $tags[] = ['id' => (int) $tg['id'], 'name' => $tg['name'], 'slug' => $tg['slug']];
            }
        } catch (\Throwable $e) {
        }

        $excerpt = trim((string) ($row['excerpt'] ?? ''));
        if ($excerpt === '' && !empty($row['body'])) {
            $excerpt = ContentRepository::excerptFromBody((string) $row['body'], 160);
        }
        $len = max(20, min(500, (int) ($excerptLen ?? 130)));
        $excerptN = ContentRepository::excerptFromBody($excerpt !== '' ? $excerpt : (string) ($row['body'] ?? ''), $len);

        $slug = (string) ($row['slug'] ?? '');
        $url = $slug !== '' ? '/' . ltrim($slug, '/') : '#';
        $featured = trim((string) ($row['featured_image'] ?? ''));

        return [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'slug' => $slug,
            'url' => $url,
            'excerpt' => $excerpt,
            'excerpt_130' => $excerptN,
            'excerpt_n' => $excerptN,
            'featured_image' => $featured,
            'has_image' => $featured !== '',
            'type' => (string) ($row['type'] ?? ''),
            'category' => $cats[0]['name'] ?? '',
            'categories' => $cats,
            'tags' => $tags,
            'date' => $this->formatDate($row['published_at'] ?? $row['created_at'] ?? null),
            'date_iso' => (string) ($row['published_at'] ?? $row['created_at'] ?? ''),
            'updated' => $this->formatDate($row['updated_at'] ?? null),
            'updated_iso' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function renderCard(array $item): string
    {
        $hasImg = !empty($item['has_image']) && !empty($item['featured_image']);
        $cat = htmlspecialchars((string) ($item['category'] ?? ''), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $excerpt = htmlspecialchars((string) ($item['excerpt_130'] ?? $item['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
        $img = htmlspecialchars((string) ($item['featured_image'] ?? ''), ENT_QUOTES, 'UTF-8');

        $catHtml = $cat !== ''
            ? '<span class="mova-blog-cat">' . $cat . '</span>'
            : '';

        $col1 = '<div class="mova-blog-col1">'
            . $catHtml
            . '<h3 class="mova-blog-title">' . $title . '</h3>'
            . ($excerpt !== '' ? '<p class="mova-blog-excerpt">' . $excerpt . '</p>' : '')
            . '<span class="mova-blog-more">Read more</span>'
            . '</div>';

        $col2 = $hasImg
            ? '<div class="mova-blog-col2"><img src="' . $img . '" alt="" loading="lazy"></div>'
            : '';

        $cls = 'mova-blog-card' . ($hasImg ? ' has-image' : ' no-image');
        return '<a class="' . $cls . '" href="' . $url . '">' . $col1 . $col2 . '</a>';
    }

    public function renderDefaultList(array $items): string
    {
        $html = '<div class="mova-blog-grid">';
        foreach ($items as $it) {
            $html .= $this->renderCard($it);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Simple {{var}} template for one item, with {{#items}}…{{/items}} loop support.
     */
    public function renderCustom(string $template, array $items, array $blog = []): string
    {
        $template = $template !== '' ? $template : $this->defaultTemplateSource();
        if (preg_match('/\{\{#items\}\}(.*?)\{\{\/items\}\}/s', $template, $m)) {
            $itemTpl = $m[1];
            $built = '';
            foreach ($items as $it) {
                $built .= $this->applyVars($itemTpl, $it);
            }
            $out = str_replace($m[0], $built, $template);
            return $this->applyVars($out, [
                'blog_title' => $blog['title'] ?? '',
                'blog_slug' => $blog['slug'] ?? '',
            ]);
        }
        $built = '';
        foreach ($items as $it) {
            $built .= $this->applyVars($template, $it);
        }
        return $built;
    }

    private function applyVars(string $tpl, array $it): string
    {
        $map = [
            'title' => (string) ($it['title'] ?? ''),
            'url' => (string) ($it['url'] ?? ''),
            'excerpt' => (string) ($it['excerpt'] ?? ''),
            'excerpt_130' => (string) ($it['excerpt_130'] ?? ''),
            'featured_image' => (string) ($it['featured_image'] ?? ''),
            'has_image' => !empty($it['has_image']) ? '1' : '',
            'category' => (string) ($it['category'] ?? ''),
            'categories' => implode(', ', array_map(fn($c) => $c['name'] ?? '', $it['categories'] ?? [])),
            'tags' => implode(', ', array_map(fn($c) => $c['name'] ?? '', $it['tags'] ?? [])),
            'date' => (string) ($it['date'] ?? ''),
            'date_iso' => (string) ($it['date_iso'] ?? ''),
            'updated' => (string) ($it['updated'] ?? ''),
            'updated_iso' => (string) ($it['updated_iso'] ?? ''),
            'type' => (string) ($it['type'] ?? ''),
            'blog_title' => (string) ($it['blog_title'] ?? ''),
            'blog_slug' => (string) ($it['blog_slug'] ?? ''),
        ];
        // Simple conditionals {{#has_image}}…{{/has_image}}
        $tpl = preg_replace_callback('/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s', function ($m) use ($map) {
            $key = $m[1];
            $inner = $m[2];
            if (!empty($map[$key])) {
                return $inner;
            }
            return '';
        }, $tpl) ?? $tpl;

        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($map) {
            $k = $m[1];
            return htmlspecialchars((string) ($map[$k] ?? ''), ENT_QUOTES, 'UTF-8');
        }, $tpl) ?? $tpl;
    }

    public function defaultTemplateSource(): string
    {
        return '{{#items}}<a class="mova-blog-card{{#has_image}} has-image{{/has_image}}" href="{{url}}">'
            . '<div class="mova-blog-col1">{{#category}}<span class="mova-blog-cat">{{category}}</span>{{/category}}'
            . '<h3 class="mova-blog-title">{{title}}</h3>'
            . '<p class="mova-blog-excerpt">{{excerpt_130}}</p>'
            . '<span class="mova-blog-more">Read more</span></div>'
            . '{{#has_image}}<div class="mova-blog-col2"><img src="{{featured_image}}" alt="" loading="lazy"></div>{{/has_image}}'
            . '</a>{{/items}}';
    }

    public function modernTemplateSource(): string
    {
        return '{{#items}}<a class="mova-blog-card is-modern{{#has_image}} has-image{{/has_image}}" href="{{url}}">'
            . '{{#has_image}}<div class="mova-blog-col2"><img src="{{featured_image}}" alt="{{title}}" loading="lazy"></div>{{/has_image}}'
            . '<div class="mova-blog-col1">'
            . '<div class="mova-blog-meta">{{#category}}<span class="mova-blog-cat">{{category}}</span>{{/category}}'
            . '<span>{{date}}</span>{{#type}} · <span>{{type}}</span>{{/type}}</div>'
            . '<h3 class="mova-blog-title">{{title}}</h3>'
            . '<p class="mova-blog-excerpt">{{excerpt_130}}</p>'
            . '<span class="mova-blog-more">Read article</span></div>'
            . '</a>{{/items}}';
    }

    public function minimalTemplateSource(): string
    {
        return '{{#items}}<a class="mova-blog-card is-minimal{{#has_image}} has-image{{/has_image}}" href="{{url}}">'
            . '<div class="mova-blog-col1">'
            . '{{#category}}<span class="mova-blog-cat">{{category}}</span>{{/category}}'
            . '<h3 class="mova-blog-title">{{title}}</h3>'
            . '<p class="mova-blog-excerpt">{{excerpt_130}}</p>'
            . '<div class="mova-blog-meta" style="font-size:0.75rem;color:var(--color-muted,#64748b);margin-top:0.25rem">{{date}}</div>'
            . '</div>'
            . '{{#has_image}}<div class="mova-blog-col2"><img src="{{featured_image}}" alt="" loading="lazy"></div>{{/has_image}}'
            . '</a>{{/items}}';
    }

    /** Sample full of vars for designers (download) */
    public function richSampleTemplateSource(): string
    {
        return "<!-- Mova blog template — uses most available vars -->\n"
            . "{{#items}}\n"
            . '<article class="mova-blog-card{{#has_image}} has-image{{/has_image}}">\n'
            . '  <a href="{{url}}" style="display:contents;color:inherit;text-decoration:none">\n'
            . '    <div class="mova-blog-col1">\n'
            . '      {{#category}}<span class="mova-blog-cat">{{category}}</span>{{/category}}\n'
            . '      <h3 class="mova-blog-title">{{title}}</h3>\n'
            . '      <p class="mova-blog-excerpt">{{excerpt_130}}</p>\n'
            . '      <p class="mova-blog-excerpt" style="font-size:0.8rem;opacity:0.85">{{excerpt}}</p>\n'
            . '      <div style="font-size:0.75rem;color:var(--color-muted,#64748b);display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.35rem">\n'
            . '        <span>{{date}}</span>\n'
            . '        <span>Updated {{updated}}</span>\n'
            . '        <span>{{type}}</span>\n'
            . '        <span>{{categories}}</span>\n'
            . '        <span>{{tags}}</span>\n'
            . '      </div>\n'
            . '      <span class="mova-blog-more">Read more</span>\n'
            . '    </div>\n'
            . '    {{#has_image}}<div class="mova-blog-col2"><img src="{{featured_image}}" alt="{{title}}" loading="lazy"></div>{{/has_image}}\n'
            . '  </a>\n'
            . '</article>\n'
            . "{{/items}}\n";
    }

    public function templateSourceForMode(string $mode): string
    {
        return match ($this->normalizeTemplateMode($mode)) {
            'modern' => $this->modernTemplateSource(),
            'minimal' => $this->minimalTemplateSource(),
            'custom' => '',
            default => $this->defaultTemplateSource(),
        };
    }

    public function renderFeedHtml(array $blog, array $items): string
    {
        $mode = $this->normalizeTemplateMode($blog['template_mode'] ?? 'default');
        if ($mode === 'custom' && trim((string) ($blog['template_html'] ?? '')) !== '') {
            return $this->renderCustom((string) $blog['template_html'], $items, $blog);
        }
        if ($mode === 'default') {
            return $this->renderDefaultList($items);
        }
        $src = $this->templateSourceForMode($mode);
        $html = $this->renderCustom($src, $items, $blog);
        // wrap if template didn't include grid
        if (strpos($html, 'mova-blog-grid') === false) {
            $html = '<div class="mova-blog-grid">' . $html . '</div>';
        }
        return $html;
    }

    public function mountHtml(int $id): string
    {
        return '<div class="mova-blog" data-blog="' . (int) $id . '"></div>';
    }

    public function shortcode(int $id): string
    {
        return '[blog id="' . (int) $id . '"]';
    }

    public static function registerShortcodes(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!class_exists(PluginManager::class)) {
            return;
        }
        PluginManager::addFilter('content.render.body', function (string $body, array $content = []) {
            if (strpos($body, '[blog') === false && strpos($body, 'mova-blog') === false) {
                return $body;
            }
            $svc = new self();
            $body = preg_replace_callback('/\[blog\s+([^\]]*)\]/i', function ($m) use ($svc) {
                $attrs = $m[1];
                $id = 0;
                $slug = '';
                if (preg_match('/\bid=["\']?(\d+)/i', $attrs, $mm)) {
                    $id = (int) $mm[1];
                }
                if (preg_match('/\bslug=["\']([^"\']+)["\']/i', $attrs, $mm)) {
                    $slug = $mm[1];
                }
                if ($id <= 0 && $slug !== '') {
                    $g = $svc->findBySlug($slug, true);
                    $id = $g ? (int) $g['id'] : 0;
                }
                if ($id <= 0) {
                    return '<!-- blog: not found -->';
                }
                return $svc->mountHtml($id);
            }, $body) ?? $body;

            static $assets = false;
            if (!$assets && (strpos($body, 'mova-blog') !== false || strpos($body, '[blog') !== false)) {
                $assets = true;
                $body .= "\n" . '<link rel="stylesheet" href="/assets/css/mova-blog.css?v=4">'
                    . '<script src="/assets/js/mova-blog.js?v=3" defer></script>';
            }
            return $body;
        });
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        foreach (['allowed_types', 'category_ids', 'tag_ids', 'embed_options'] as $k) {
            if (!empty($row[$k]) && is_string($row[$k])) {
                $row[$k] = json_decode($row[$k], true) ?: [];
            }
            if (!isset($row[$k]) || !is_array($row[$k])) {
                $row[$k] = $k === 'allowed_types' ? ['article'] : [];
            }
        }
        $row['per_page'] = (int) ($row['per_page'] ?? 12);
        $row['excerpt_chars'] = (int) ($row['excerpt_chars'] ?? 130);
        if (empty($row['mode'])) {
            $row['mode'] = 'filter';
        }
        if (empty($row['template_mode'])) {
            $row['template_mode'] = 'default';
        }
        return $row;
    }

    private function normalizeTypes($types): array
    {
        if (is_string($types)) {
            $types = array_filter(array_map('trim', explode(',', $types)));
        }
        if (!is_array($types) || $types === []) {
            return ['article'];
        }
        $out = [];
        foreach ($types as $t) {
            $t = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $t) ?? '');
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out ?: ['article'];
    }

    private function normalizeIdList($ids): array
    {
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)));
        }
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function normalizeTemplateMode($mode): string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, ['default', 'modern', 'minimal', 'custom'], true) ? $mode : 'default';
    }

    private function normalizeSort($sort): string
    {
        $sort = (string) $sort;
        return in_array($sort, ['date_desc', 'date_asc', 'title', 'category'], true) ? $sort : 'date_desc';
    }

    private function normalizeEmbedOptions($opts): array
    {
        if (is_string($opts)) {
            $opts = json_decode($opts, true) ?: [];
        }
        if (!is_array($opts)) {
            $opts = [];
        }
        return [
            'limit' => max(1, min(50, (int) ($opts['limit'] ?? 6))),
        ];
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'blog';
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $this->slugify($base);
        $slug = $base;
        $i = 2;
        while (true) {
            $row = Database::fetch('SELECT id FROM blogs WHERE slug = :s', ['s' => $slug]);
            if (!$row || ($ignoreId && (int) $row['id'] === $ignoreId)) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '';
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return (string) $value;
        }
        return date('M j, Y', $ts);
    }
}
