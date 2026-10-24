<?php
/**
 * Mova AI — structured map of HQ screens for navigation and grounding.
 */

namespace Mova\AI;

class HqMap
{
    /**
     * Derived from the single HQ route registry (Mova\Hq\RouteRegistry), filtered to the
     * signed-in user's role so the assistant never suggests screens they cannot open.
     *
     * @return list<array{id:string,label:string,path:string,workspace:string,keywords:list<string>,description:string}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (\Mova\Hq\RouteRegistry::destinations(false) as $d) {
            $label = (string) $d['label'];
            $out[] = [
                'id' => (string) $d['id'],
                'label' => $label,
                'path' => (string) $d['path'],
                'workspace' => (string) $d['section'],
                'keywords' => array_values(array_map('strval', (array) $d['keywords'])),
                'description' => (string) ($d['desc'] ?: ($d['section_label'] . ' › ' . $label)),
            ];
        }
        return $out;
    }

    /**
     * Rank HQ destinations by keyword overlap with the user message.
     *
     * @return list<array{id:string,label:string,path:string,workspace:string,keywords:list<string>,description:string,score:float}>
     */
    public static function search(string $query, int $limit = 5): array
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        $scored = [];
        foreach (self::all() as $item) {
            $score = 0.0;
            $hay = mb_strtolower($item['label'] . ' ' . $item['description'] . ' ' . implode(' ', $item['keywords']));
            if (str_contains($hay, $q)) {
                $score += 3.0;
            }
            foreach ($item['keywords'] as $kw) {
                $kw = mb_strtolower($kw);
                if ($kw === $q) {
                    $score += 5.0;
                } elseif (str_contains($q, $kw) || str_contains($kw, $q)) {
                    $score += 2.0;
                }
            }
            $words = preg_split('/\s+/', $q) ?: [];
            foreach ($words as $w) {
                if (mb_strlen($w) < 3) {
                    continue;
                }
                if (str_contains($hay, $w)) {
                    $score += 0.5;
                }
            }
            if ($score > 0) {
                $item['score'] = $score;
                $scored[] = $item;
            }
        }

        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /** Compact text block for LLM system context */
    public static function asPromptBlock(int $max = 40): string
    {
        $lines = [];
        foreach (array_slice(self::all(), 0, $max) as $item) {
            $lines[] = "- {$item['label']}: {$item['path']} ({$item['description']})";
        }
        return "HQ navigation map:\n" . implode("\n", $lines);
    }
}
