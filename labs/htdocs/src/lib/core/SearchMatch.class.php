<?php

/**
 * Multi-token scoring for the global search palette (/api/search).
 *
 * Matching is advanced-filter style: the query is split into tokens and an
 * item matches when AT LEAST one token is found (order-insensitive, plain
 * case-insensitive substring — no regex, so user input is always safe).
 * Ranking (lower = better):
 *      0  exact match
 *     10  starts with the full query
 *     20  full query found contiguously
 *     30  all tokens found, each at a word start (scattered)
 *     40  all tokens found (scattered)
 *  50 - n  partial: only n of the tokens found — still suggested, ranked below
 *          every full match (so "My Device" suggests "Devices" at the bottom,
 *          while an item literally named "My Device" ranks at the top)
 */
class SearchMatch
{
    /** Split a query into unique, lowercased tokens. */
    public static function tokenize(string $q): array
    {
        $parts = preg_split('/[\s,]+/u', mb_strtolower(trim($q)), -1, PREG_SPLIT_NO_EMPTY);
        return $parts === false ? [] : array_values(array_unique($parts));
    }

    /** Score one haystack against a lowercased query + its tokens. null = no match. */
    public static function score(string $text, string $qLower, array $tokens): ?int
    {
        if ($tokens === [] || $qLower === '') {
            return null;
        }

        $h = mb_strtolower($text);
        $matched = 0;
        $allWordStart = true;

        foreach ($tokens as $t) {
            $pos = mb_strpos($h, $t);
            if ($pos === false) {
                continue;
            }
            $matched++;
            if ($pos > 0 && !preg_match('/[\s_\-\/.·:&()\[\]]/', $h[$pos - 1])) {
                $allWordStart = false;
            }
        }

        if ($matched === 0) {
            return null;
        }
        if ($matched < count($tokens)) {
            return 50 - $matched;
        }
        if ($h === $qLower) {
            return 0;
        }
        if (str_starts_with($h, $qLower)) {
            return 10;
        }
        if (mb_strpos($h, $qLower) !== false) {
            return 20;
        }
        return $allWordStart ? 30 : 40;
    }

    /** Score across several fields concatenated into one haystack. */
    public static function scoreFields(array $fields, string $qLower, array $tokens): ?int
    {
        $parts = [];
        foreach ($fields as $f) {
            if ($f !== null && $f !== '') {
                $parts[] = (string) $f;
            }
        }
        return self::score(implode(' · ', $parts), $qLower, $tokens);
    }

    /**
     * Sort scored rows ascending by _score (stable), then strip _score.
     * Rows without _score sort last.
     */
    public static function sortItems(array $items): array
    {
        usort($items, fn ($a, $b) => ($a['_score'] ?? PHP_INT_MAX) <=> ($b['_score'] ?? PHP_INT_MAX));
        foreach ($items as &$item) {
            unset($item['_score']);
        }
        return $items;
    }
}
