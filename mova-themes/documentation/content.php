<?php
/**
 * Documentation — single content view with a table-of-contents sidebar.
 * Canvas / bare dev pages and pages with fewer than two headings fall back to the
 * shared implementation (resources/theme-parts/content.php).
 */
$content = $content ?? null;
if (!$content) {
    echo '<div class="empty-state"><p>Content not found.</p></div>';
    return;
}
$__isDev = (($content['meta']['editor_mode'] ?? '') === 'dev');
$__bare = $__isDev && (($content['meta']['use_site_chrome'] ?? '0') !== '1');
if ($__bare || \Mova\Theme\SiteFrame::isCanvas($content)) {
    include \Mova\Theme\SiteFrame::partsPath() . '/content.php';
    return;
}

$__body = \Mova\Theme\SiteFrame::body($content);
$__toc = [];
if (class_exists(\DOMDocument::class) && stripos($__body, '<h2') !== false) {
    $__doc = new \DOMDocument();
    $__prev = libxml_use_internal_errors(true);
    $__ok = $__doc->loadHTML('<?xml encoding="UTF-8"><div id="doc-root">' . $__body . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($__prev);
    if ($__ok) {
        $__xp = new \DOMXPath($__doc);
        $__used = [];
        foreach ($__xp->query('//h2|//h3') as $__h) {
            $__text = trim(preg_replace('/\s+/', ' ', $__h->textContent));
            if ($__text === '') {
                continue;
            }
            $__id = $__h->getAttribute('id');
            if ($__id === '') {
                $__id = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($__text)), '-') ?: 'section';
                $__base = $__id;
                $__n = 2;
                while (isset($__used[$__id])) {
                    $__id = $__base . '-' . $__n++;
                }
                $__h->setAttribute('id', $__id);
            }
            $__used[$__id] = true;
            $__toc[] = ['id' => $__id, 'text' => $__text, 'level' => strtolower($__h->nodeName) === 'h3' ? 3 : 2];
        }
        if (count($__toc) >= 2) {
            $__root = $__doc->getElementById('doc-root');
            $__html = '';
            if ($__root) {
                foreach ($__root->childNodes as $__c) {
                    $__html .= $__doc->saveHTML($__c);
                }
                $__body = $__html;
            }
        }
    }
}
if (count($__toc) < 2) {
    include \Mova\Theme\SiteFrame::partsPath() . '/content.php';
    return;
}
$__hide = !empty($content['meta']['hide_article_chrome']) && $content['meta']['hide_article_chrome'] !== '0';
$__isPost = in_array((string) ($content['type'] ?? ''), ['post', 'article', 'news'], true);
$__words = str_word_count(strip_tags($__body));
?>
<div class="doc-layout">
    <aside class="doc-toc" aria-label="On this page">
        <details class="doc-toc__box" open>
            <summary class="doc-toc__title">On this page</summary>
            <ol class="doc-toc__list">
<?php foreach ($__toc as $__t): ?>
                <li class="doc-toc__item doc-toc__item--h<?= (int) $__t['level'] ?>"><a href="#<?= htmlspecialchars($__t['id']) ?>"><?= htmlspecialchars($__t['text']) ?></a></li>
<?php endforeach; ?>
            </ol>
            <a class="doc-toc__top" href="#main">Back to top</a>
        </details>
    </aside>
    <article class="article doc-article<?= $__isPost ? ' article--post' : ' article--page' ?>">
<?php if (!$__hide): ?>
        <header class="article-header">
            <p class="doc-kicker"><?= htmlspecialchars(ucfirst((string) ($content['type'] ?? 'page'))) ?><?php if ($__words > 120): ?> · <?= max(1, (int) round($__words / 220)) ?> min read<?php endif; ?></p>
            <h1><?= htmlspecialchars((string) $content['title']) ?></h1>
<?php if (!empty($content['excerpt']) && strlen((string) $content['excerpt']) < 240): ?>
            <p class="article-lead"><?= htmlspecialchars((string) $content['excerpt']) ?></p>
<?php endif; ?>
        </header>
<?php endif; ?>
        <div class="article-body">
            <?= $__body ?>
        </div>
    </article>
</div>
