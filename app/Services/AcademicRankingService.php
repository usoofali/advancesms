<?php

namespace App\Services;

class AcademicRankingService
{
    /**
     * Compute competition rankings for an array of items based on a numeric score key.
     * Handles ties using standard competition ranking (1, 2, 2, 4...).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function rankItems(
        array $items,
        string $scoreKey = 'total_score',
        string $rankKey = 'rank',
        string $ordinalKey = 'position'
    ): array {
        if (empty($items)) {
            return [];
        }

        // Sort descending by scoreKey
        usort($items, function (array $a, array $b) use ($scoreKey) {
            $scoreA = (float) ($a[$scoreKey] ?? 0);
            $scoreB = (float) ($b[$scoreKey] ?? 0);

            if ($scoreA === $scoreB) {
                return 0;
            }

            return ($scoreA > $scoreB) ? -1 : 1;
        });

        $currentRank = 1;
        $previousScore = null;
        $rankedItems = [];

        foreach ($items as $index => $item) {
            $score = (float) ($item[$scoreKey] ?? 0);

            if ($previousScore !== null && $score < $previousScore) {
                $currentRank = $index + 1;
            }

            $item[$rankKey] = $currentRank;
            $item[$ordinalKey] = $this->formatOrdinal($currentRank);

            $previousScore = $score;
            $rankedItems[] = $item;
        }

        return $rankedItems;
    }

    /**
     * Format a numeric rank into an English ordinal representation (e.g. 1st, 2nd, 3rd, 4th, 21st, 102nd).
     */
    public function formatOrdinal(int $number): string
    {
        if ($number <= 0) {
            return '-';
        }

        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number.'th';
        }

        return $number.$ends[$number % 10];
    }
}
