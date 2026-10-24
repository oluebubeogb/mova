<?php
/** Small line diff (LCS on the changed middle, capped) for the import preview's body diff view. */

declare(strict_types=1);

namespace Mova\Backup;

final class TextDiff
{
    /** Split HTML into readable lines (one tag boundary per line). */
    public static function lines(string $html): array
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/>\s*</', ">\n<", $html) ?? $html;
        $out = [];
        foreach (explode("\n", $html) as $l) {
            $l = rtrim($l);
            if ($l !== '') {
                $out[] = $l;
            }
        }
        return $out;
    }

    /**
     * @return list<array{0:string,1:string}> ops: [' ', line] same, ['-', line] removed, ['+', line] added
     */
    public static function diff(array $a, array $b, int $cap = 700): array
    {
        $pre = 0;
        $na = count($a);
        $nb = count($b);
        while ($pre < $na && $pre < $nb && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $na - $pre && $suf < $nb - $pre && $a[$na - 1 - $suf] === $b[$nb - 1 - $suf]) {
            $suf++;
        }
        $ma = array_slice($a, $pre, $na - $pre - $suf);
        $mb = array_slice($b, $pre, $nb - $pre - $suf);
        $ops = [];
        for ($i = 0; $i < $pre; $i++) {
            $ops[] = [' ', $a[$i]];
        }
        $x = count($ma);
        $y = count($mb);
        if ($x > $cap || $y > $cap) {
            foreach ($ma as $l) {
                $ops[] = ['-', $l];
            }
            foreach ($mb as $l) {
                $ops[] = ['+', $l];
            }
        } else {
            // LCS lengths, rows as strings of ints to keep memory low
            $L = array_fill(0, $x + 1, array_fill(0, $y + 1, 0));
            for ($i = $x - 1; $i >= 0; $i--) {
                for ($j = $y - 1; $j >= 0; $j--) {
                    $L[$i][$j] = $ma[$i] === $mb[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
                }
            }
            $i = 0;
            $j = 0;
            while ($i < $x && $j < $y) {
                if ($ma[$i] === $mb[$j]) {
                    $ops[] = [' ', $ma[$i]];
                    $i++;
                    $j++;
                } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                    $ops[] = ['-', $ma[$i++]];
                } else {
                    $ops[] = ['+', $mb[$j++]];
                }
            }
            for (; $i < $x; $i++) {
                $ops[] = ['-', $ma[$i]];
            }
            for (; $j < $y; $j++) {
                $ops[] = ['+', $mb[$j]];
            }
        }
        for ($i = $na - $suf; $i < $na; $i++) {
            $ops[] = [' ', $a[$i]];
        }
        return $ops;
    }

    /** Collapse long unchanged runs to N lines of context. */
    public static function hunks(array $ops, int $context = 3): array
    {
        $n = count($ops);
        $keep = array_fill(0, $n, false);
        foreach ($ops as $i => $op) {
            if ($op[0] !== ' ') {
                for ($k = max(0, $i - $context); $k <= min($n - 1, $i + $context); $k++) {
                    $keep[$k] = true;
                }
            }
        }
        $out = [];
        $skipped = 0;
        foreach ($ops as $i => $op) {
            if ($keep[$i]) {
                if ($skipped) {
                    $out[] = ['…', $skipped . ' unchanged line' . ($skipped === 1 ? '' : 's')];
                    $skipped = 0;
                }
                $out[] = $op;
            } else {
                $skipped++;
            }
        }
        if ($skipped) {
            $out[] = ['…', $skipped . ' unchanged line' . ($skipped === 1 ? '' : 's')];
        }
        return $out;
    }
}
