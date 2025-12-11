<?php

declare(strict_types=1);

namespace Tests\Support;

use Fawaz\App\DTO\UncollectedGemsResult;
use Fawaz\App\DTO\UncollectedGemsRow;

final class UncollectedGemsFactory
{
    /**
     * Build an UncollectedGemsResult from the given users/gems map.
     *
     * Input arg description (in-out context):
     * - `$usersWithGems` is an associative array where each key is a user id
     *   string (e.g., 'u1') and each value is a numerically indexed array of
     *   gem amounts (floats) for that user. Example:
     *   [
     *     'u1' => [0.25],
     *     'u2' => [0.25],
     *     'u3' => [2.0, 5.0, 0.25],
     *     'u4' => [-3.0],
     *     'u5' => [2.0, 5.0, -3.0],
     *   ]
     *
     * The function does not mutate the input; it computes per-user totals,
     * overall totals, and percentage contributions, returning a structured
     * result with formatted string values for totals and percentages.
     *
     * @param array<string, list<float|int>> $usersWithGems Map of user id to gem amounts
     * @return UncollectedGemsResult Structured rows and overall total string
     */
    public static function makeFiveUsersSample(array $usersWithGems): UncollectedGemsResult
    {
        $users = $usersWithGems;

        // Compute totals per user and overall
        $totals = [];
        $overall = 0.0;
        foreach ($users as $uid => $entries) {
            $totals[$uid] = array_sum($entries);
            $overall += $totals[$uid];
        }

        $overallStr = rtrim(rtrim(sprintf('%.10F', $overall), '0'), '.');

        $rows = [];
        $i = 1;
        foreach ($users as $uid => $entries) {
            $totalNumbersStr = rtrim(rtrim(sprintf('%.10F', $totals[$uid]), '0'), '.');
            $percentage = $overall != 0.0 ? ($totals[$uid] / $overall) * 100.0 : 0.0;
            $percentageStr = rtrim(rtrim(sprintf('%.10F', $percentage), '0'), '.');
            foreach ($entries as $g) {
                $rows[] = new UncollectedGemsRow(
                    userid: $uid,
                    gemid: 'g'.$i,
                    postid: 'p'.$i,
                    fromid: 'f'.$i,
                    gems: rtrim(rtrim(sprintf('%.10F', $g), '0'), '.'),
                    whereby: 0,
                    createdat: '2025-01-01 00:00:00',
                    totalNumbers: $totalNumbersStr,
                    overallTotal: $overallStr,
                    percentage: $percentageStr
                );
                $i++;
            }
        }

        return new UncollectedGemsResult(rows: $rows, overallTotal: $overallStr);
    }
}
