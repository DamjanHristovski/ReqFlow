<?php

namespace App\Support;

class TextDiffer
{
    /**
     * Word-level diff between two texts, rendered as safe HTML with only the
     * changed words wrapped in <mark> — unchanged text is left untouched
     * rather than highlighting the entire string.
     *
     * @return array{from: string, to: string}
     */
    public static function diffToHtml(?string $from, ?string $to): array
    {
        $fromTokens = self::tokenize($from ?? '');
        $toTokens = self::tokenize($to ?? '');

        [$fromOps, $toOps] = self::diffTokens($fromTokens, $toTokens);

        return [
            'from' => self::render($fromOps),
            'to' => self::render($toOps),
        ];
    }

    /**
     * Splits on whitespace while keeping the whitespace itself as tokens, so
     * the original spacing/line breaks are preserved when re-joined.
     */
    private static function tokenize(string $text): array
    {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Longest-common-subsequence word diff. Returns [fromOps, toOps], each a
     * list of [text, changed] pairs.
     */
    private static function diffTokens(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        $lcsLength = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $lcsLength[$i][$j] = $a[$i] === $b[$j]
                    ? $lcsLength[$i + 1][$j + 1] + 1
                    : max($lcsLength[$i + 1][$j], $lcsLength[$i][$j + 1]);
            }
        }

        $fromOps = [];
        $toOps = [];
        $i = 0;
        $j = 0;
        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $fromOps[] = [$a[$i], false];
                $toOps[] = [$b[$j], false];
                $i++;
                $j++;
            } elseif ($lcsLength[$i + 1][$j] >= $lcsLength[$i][$j + 1]) {
                $fromOps[] = [$a[$i], true];
                $i++;
            } else {
                $toOps[] = [$b[$j], true];
                $j++;
            }
        }
        while ($i < $m) {
            $fromOps[] = [$a[$i], true];
            $i++;
        }
        while ($j < $n) {
            $toOps[] = [$b[$j], true];
            $j++;
        }

        return [$fromOps, $toOps];
    }

    /**
     * Merges consecutive changed tokens into a single <mark> span instead of
     * wrapping each word individually, so adjacent highlighted words read as
     * one continuous highlight rather than a row of separate rounded boxes.
     */
    private static function render(array $ops): string
    {
        $html = '';
        $buffer = '';
        foreach ($ops as [$text, $changed]) {
            if ($changed) {
                $buffer .= e($text);

                continue;
            }

            if ($buffer !== '') {
                $html .= "<mark class=\"bg-yellow-200 rounded px-0.5\">{$buffer}</mark>";
                $buffer = '';
            }
            $html .= e($text);
        }

        if ($buffer !== '') {
            $html .= "<mark class=\"bg-yellow-200 rounded px-0.5\">{$buffer}</mark>";
        }

        return $html;
    }
}
