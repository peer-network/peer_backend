<?php

declare(strict_types=1);

namespace Tests\Mocks\Database;

use Fawaz\App\DTO\Gems;
use Fawaz\App\DTO\GemsRow;
use Fawaz\Database\GemsRepository;

use function count;
use function number_format;
use function sprintf;
use function substr;

/**
 * Lightweight in-memory implementation of the GemsRepository contract for tests.
 */
final class MockGemsRepository implements GemsRepository
{
    /**
     * @var callable(string $day): array<int, GemsRow>
     */
    private $seedFactory;

    public ?string $lastMintId = null;
    public ?Gems $lastAppliedGems = null;
    public ?array $lastMintLogItems = null;

    public function __construct(?callable $seedFactory = null)
    {
        $this->seedFactory = $seedFactory ?? require __DIR__ . '/../../seed/gems_rows_seed.php';
    }

    public function fetchUncollectedGemsStats(): array
    {
        $rows = $this->loadSeedRows();
        $count = count($rows);

        return [
            'status' => 'success',
            'ResponseCode' => '11207',
            'affectedRows' => [
                'data' => [
                    'd0' => $count,
                    'd1' => 0,
                    'd2' => 0,
                    'd3' => 0,
                    'd4' => 0,
                    'd5' => 0,
                    'd6' => 0,
                    'd7' => 0,
                    'w0' => $count,
                    'm0' => $count,
                    'y0' => $count,
                ],
            ],
        ];
    }

    public function fetchAllGemsForDay(string $day = 'D0'): array
    {
        $rows = $this->loadSeedRows($day);

        if ($rows === []) {
            return [
                'status' => 'success',
                'counter' => 0,
                'ResponseCode' => '21202',
                'affectedRows' => ['data' => [], 'totalGems' => '0.0000000000'],
            ];
        }

        $summary = $this->summarizeRows($rows);

        return [
            'status' => 'success',
            'counter' => count($summary['data']),
            'ResponseCode' => '11208',
            'affectedRows' => [
                'data' => $summary['data'],
                'totalGems' => $summary['totalGems'],
            ],
        ];
    }

    public function fetchUncollectedGemsForMintResult(string $day = 'D0'): ?Gems
    {
        $rows = $this->loadSeedRows($day);

        if ($rows === []) {
            return null;
        }

        return new Gems($rows);
    }

    public function applyMintInfo(string $mintId, Gems $uncollectedGems, array $mintLogItems)
    {
        $this->lastMintId = $mintId;
        $this->lastAppliedGems = $uncollectedGems;
        $this->lastMintLogItems = $mintLogItems;
    }

    public function setGlobalWins(string $tableName, int $winType, float $factor)
    {
        return [
            'status' => 'success',
            'table' => $tableName,
            'winType' => $winType,
            'factor' => $factor,
            'insertCount' => count($this->loadSeedRows()),
        ];
    }

    /**
     * @return GemsRow[]
     */
    private function loadSeedRows(string $day = 'D0'): array
    {
        $factory = $this->seedFactory;

        return $factory($day);
    }

    /**
     * @param GemsRow[] $rows
     * @return array{data: array<int, array{userid: string, gems: string, pkey: string}>, totalGems: string}
     */
    private function summarizeRows(array $rows): array
    {
        $grouped = [];
        $overall = 0.0;

        foreach ($rows as $row) {
            $userId = $row->userid;
            $amount = (float) $row->gems;
            $overall += $amount;

            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'userid' => $userId,
                    'gems' => 0.0,
                    'pkey' => sprintf('mock-%s', substr($userId, 0, 8)),
                ];
            }

            $grouped[$userId]['gems'] += $amount;
        }

        $data = array_map(function (array $payload): array {
            return [
                'userid' => $payload['userid'],
                'gems' => $this->formatGemsAmount($payload['gems']),
                'pkey' => $payload['pkey'],
            ];
        }, array_values($grouped));

        return [
            'data' => $data,
            'totalGems' => $this->formatGemsAmount($overall),
        ];
    }

    private function formatGemsAmount(float $value): string
    {
        return number_format($value, 10, '.', '');
    }
}
