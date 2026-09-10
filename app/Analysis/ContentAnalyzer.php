<?php
/**
 * Mova — SEO & readability analysis (local heuristics)
 */

namespace Mova\Analysis;

class ContentAnalyzer
{
    public function analyze(array $content): array
    {
        $title = trim((string) ($content['title'] ?? ''));
        $bodyHtml = (string) ($content['body'] ?? '');
        $body = trim(strip_tags($bodyHtml));
        $excerpt = trim((string) ($content['excerpt'] ?? ''));
        $meta = $content['meta'] ?? [];
        $seoTitle = trim((string) ($meta['seo_title'] ?? ''));
        $metaDesc = trim((string) ($meta['meta_description'] ?? ''));

        $words = preg_split('/\s+/', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        $sentences = max(1, preg_match_all('/[.!?]+/', $body));
        $avgWordsPerSentence = $wordCount / $sentences;

        $h2 = preg_match_all('/<h2\b/i', $bodyHtml);
        $h3 = preg_match_all('/<h3\b/i', $bodyHtml);
        $images = preg_match_all('/<img\b/i', $bodyHtml);
        $links = preg_match_all('/<a\b/i', $bodyHtml);

        $issues = [];
        $score = 100;

        if ($title === '') {
            $issues[] = ['level' => 'error', 'msg' => 'Title is missing.'];
            $score -= 25;
        } elseif (mb_strlen($title) < 20) {
            $issues[] = ['level' => 'warn', 'msg' => 'Title is short (< 20 characters).'];
            $score -= 5;
        } elseif (mb_strlen($title) > 70) {
            $issues[] = ['level' => 'warn', 'msg' => 'Title is long (> 70 characters).'];
            $score -= 5;
        }

        if ($wordCount < 50) {
            $issues[] = ['level' => 'error', 'msg' => 'Body is very short (< 50 words).'];
            $score -= 20;
        } elseif ($wordCount < 300) {
            $issues[] = ['level' => 'warn', 'msg' => 'Body is under 300 words — fine for pages, thin for articles.'];
            $score -= 8;
        }

        if ($excerpt === '') {
            $issues[] = ['level' => 'warn', 'msg' => 'No excerpt set.'];
            $score -= 5;
        }

        if ($seoTitle === '' && $title !== '') {
            $issues[] = ['level' => 'info', 'msg' => 'SEO title empty — will fall back to title.'];
        } elseif ($seoTitle !== '' && (mb_strlen($seoTitle) < 30 || mb_strlen($seoTitle) > 65)) {
            $issues[] = ['level' => 'warn', 'msg' => 'SEO title ideally 30–65 characters (currently ' . mb_strlen($seoTitle) . ').'];
            $score -= 4;
        }

        if ($metaDesc === '') {
            $issues[] = ['level' => 'warn', 'msg' => 'Meta description missing.'];
            $score -= 8;
        } elseif (mb_strlen($metaDesc) < 120 || mb_strlen($metaDesc) > 165) {
            $issues[] = ['level' => 'info', 'msg' => 'Meta description ideally 120–160 characters (currently ' . mb_strlen($metaDesc) . ').'];
            $score -= 3;
        }

        if ($wordCount > 200 && $h2 < 1) {
            $issues[] = ['level' => 'warn', 'msg' => 'No H2 headings — structure may be weak.'];
            $score -= 6;
        }

        if ($avgWordsPerSentence > 25) {
            $issues[] = ['level' => 'info', 'msg' => 'Long average sentence length — consider shorter sentences.'];
            $score -= 3;
        }

        if ($images === 0 && $wordCount > 400) {
            $issues[] = ['level' => 'info', 'msg' => 'No images in a long article.'];
            $score -= 2;
        }

        if ($links === 0 && $wordCount > 300) {
            $issues[] = ['level' => 'info', 'msg' => 'No links — consider internal or external references.'];
            $score -= 2;
        }

        $score = max(0, min(100, $score));

        $grade = 'poor';
        if ($score >= 85) {
            $grade = 'excellent';
        } elseif ($score >= 70) {
            $grade = 'good';
        } elseif ($score >= 50) {
            $grade = 'fair';
        }

        return [
            'score' => $score,
            'grade' => $grade,
            'stats' => [
                'words' => $wordCount,
                'sentences' => $sentences,
                'avg_sentence_words' => round($avgWordsPerSentence, 1),
                'h2' => $h2,
                'h3' => $h3,
                'images' => $images,
                'links' => $links,
                'title_len' => mb_strlen($title),
                'meta_len' => mb_strlen($metaDesc),
            ],
            'issues' => $issues,
        ];
    }
}
