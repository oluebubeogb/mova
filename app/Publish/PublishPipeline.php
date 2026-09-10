<?php
/**
 * Mova — publishing pipeline
 * Validate → optimize hints → SEO defaults → schema readiness → cache → notify
 */

namespace Mova\Publish;

use Mova\Analysis\ContentAnalyzer;
use Mova\Cache\PageCache;
use Mova\Content\ContentRepository;
use Mova\Webhook\WebhookService;

class PublishPipeline
{
    public function run(int $contentId, array $options = []): array
    {
        $repo = new ContentRepository();
        $content = $repo->find($contentId);
        if (!$content) {
            return ['ok' => false, 'error' => 'Content not found'];
        }

        $steps = [];

        // 1. Validate
        $errors = [];
        if (trim((string) $content['title']) === '') {
            $errors[] = 'Title required';
        }
        if (trim((string) $content['slug']) === '') {
            $errors[] = 'Slug required';
        }
        $steps['validate'] = ['ok' => empty($errors), 'errors' => $errors];
        if ($errors) {
            return ['ok' => false, 'steps' => $steps];
        }

        // 2. Analyze / optimize suggestions (non-blocking)
        $analysis = (new ContentAnalyzer())->analyze($content);
        $steps['analyze'] = ['ok' => true, 'score' => $analysis['score'], 'grade' => $analysis['grade']];

        // 3. SEO defaults
        $meta = $content['meta'] ?? [];
        $changed = false;
        if (empty($meta['seo_title'])) {
            $meta['seo_title'] = $content['title'];
            $changed = true;
        }
        if (empty($meta['meta_description']) && !empty($content['excerpt'])) {
            $meta['meta_description'] = mb_substr(strip_tags($content['excerpt']), 0, 160);
            $changed = true;
        }
        if (empty($meta['robots'])) {
            $meta['robots'] = 'index, follow';
            $changed = true;
        }
        if ($changed) {
            $repo->saveMeta($contentId, $meta);
        }
        $steps['seo'] = ['ok' => true, 'defaults_applied' => $changed];

        // 4. Schema readiness (type-based)
        $steps['schema'] = ['ok' => true, 'type' => $content['type']];

        // 5. Ensure published status if requested
        $shouldPublish = !empty($options['publish']);
        if ($shouldPublish && ($content['status'] ?? '') !== 'published') {
            $repo->update($contentId, [
                'status' => 'published',
                'published_at' => $content['published_at'] ?: date('c'),
            ]);
            $content['status'] = 'published';
        }
        $steps['publish'] = ['ok' => true, 'status' => $content['status']];

        // 6. Cache
        PageCache::invalidateContent($content['slug']);
        $steps['cache'] = ['ok' => true];

        // 7. Notify
        if (($content['status'] ?? '') === 'published') {
            (new WebhookService())->dispatch('content.published', [
                'id' => $contentId,
                'slug' => $content['slug'],
                'title' => $content['title'],
            ]);
        }
        $steps['notify'] = ['ok' => true];

        return [
            'ok' => true,
            'steps' => $steps,
            'analysis' => $analysis,
            'content_id' => $contentId,
        ];
    }
}
