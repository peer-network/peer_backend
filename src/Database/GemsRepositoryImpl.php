<?php

declare(strict_types=1);

namespace Fawaz\Database;
use Fawaz\App\DTO\UncollectedGemsResult;
use Fawaz\App\DTO\UncollectedGemsRow;
use PDO;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

class GemsRepositoryImpl implements GemsRepository
{
    use ResponseHelper;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PDO $db,
    ) {}

    public function fetchUncollectedGemsStats(): array
    {
        try {
            $sql = "
                SELECT 
                COUNT(CASE WHEN createdat::date = CURRENT_DATE THEN 1 END) AS d0,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '1 day' THEN 1 END) AS d1,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '2 day' THEN 1 END) AS d2,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '3 day' THEN 1 END) AS d3,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '4 day' THEN 1 END) AS d4,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '5 day' THEN 1 END) AS d5,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '6 day' THEN 1 END) AS d6,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '7 day' THEN 1 END) AS d7,
                COUNT(CASE WHEN DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS w0,
                COUNT(CASE WHEN TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM') THEN 1 END) AS m0,
                COUNT(CASE WHEN EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS y0
                FROM gems WHERE collected = 0
            ";

            $stmt = $this->db->query($sql);
            $entries = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->logger->info('GemsRepositoryImpl.fetchUncollectedGemsStats', ['entries' => $entries]);
        } catch (\Throwable $e) {
            $this->logger->error('GemsRepositoryImpl.fetchUncollectedGemsStats failed', ['exception' => $e->getMessage()]);
            return self::respondWithError(41208);
        }

        return $this::createSuccessResponse(11207, $entries, false);
    }

    public function fetchAllGemsForDay(string $day = 'D0'): array
    {
        \ignore_user_abort(true);

        $this->logger->debug('GemsRepositoryImpl.fetchAllGemsForDay started', ['day' => $day]);

        $dayOptions = [
            'D0' => "createdat::date = CURRENT_DATE",
            'D1' => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            'D2' => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            'D3' => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            'D4' => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            'D5' => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            'W0' => "DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
            'M0' => "TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
            'Y0' => "EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
        ];

        if (!array_key_exists($day, $dayOptions)) {
            return $this::respondWithError(30223);
        }

        $whereCondition = $dayOptions[$day];

        $sql = "
            WITH user_sums AS (
                SELECT 
                    userid,
                    GREATEST(SUM(gems), 0) AS total_numbers
                FROM gems
                WHERE {$whereCondition}
                GROUP BY userid
            ),
            total_sum AS (
                SELECT SUM(total_numbers) AS overall_total FROM user_sums
            )
            SELECT 
                g.userid,
                ui.pkey,
                g.gemid,
                g.gems,
                g.whereby,
                g.createdat,
                us.total_numbers,
                (SELECT SUM(total_numbers) FROM user_sums) AS overall_total,
                (us.total_numbers * 100.0 / ts.overall_total) AS percentage
            FROM gems g
            JOIN user_sums us ON g.userid = us.userid
            JOIN users_info ui ON g.userid = ui.userid
            CROSS JOIN total_sum ts
            WHERE us.total_numbers > 0 AND g.{$whereCondition};
        ";

        try {
            $stmt = $this->db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error('GemsRepositoryImpl.fetchAllGemsForDay: read error', ['exception' => $e->getMessage()]);
            return $this::respondWithError(40301);
        }

        if (empty($data)) {
            return $this::createSuccessResponse(21202); // No records found for selected filter
        }

        $totalGems = isset($data[0]['overall_total']) ? (string)$data[0]['overall_total'] : '0';
        $args = [];

        foreach ($data as $row) {
            $userId = (string)$row['userid'];
            if (!isset($args[$userId])) {
                $args[$userId] = [
                    'userid' => $userId,
                    'gems' => $row['total_numbers'],
                    'pkey' => $row['pkey'] ?? '',
                ];
            }

            $whereby = (int)$row['whereby'];
            $mapping = [
                1 => ['text' => 'View'],
                2 => ['text' => 'Like'],
                3 => ['text' => 'Dislike'],
                4 => ['text' => 'Comment'],
                5 => ['text' => 'Post'],
            ];

            if (!isset($mapping[$whereby])) {
                return $this::respondWithError(41221);
            }
        }

        return [
            'status' => 'success',
            'counter' => count($args) - 1,
            'ResponseCode' => '11208',
            'affectedRows' => ['data' => array_values($args), 'totalGems' => $totalGems],
        ];
    }

    public function fetchUncollectedGemsForMint(string $day = 'D0'): array
    {
        $dayOptionsRaw = [
            'D0' => "createdat::date = CURRENT_DATE",
            'D1' => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            'D2' => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            'D3' => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            'D4' => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            'D5' => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            'D6' => "createdat::date = CURRENT_DATE - INTERVAL '6 day'",
            'D7' => "createdat::date = CURRENT_DATE - INTERVAL '7 day'",
            'W0' => "DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
            'M0' => "TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
            'Y0' => "EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
        ];

        if (!array_key_exists($day, $dayOptionsRaw)) {
            return [];
        }

        $whereConditionRaw = $dayOptionsRaw[$day];
        $whereConditionAliased = preg_replace('/\b(createdat)\b/', 'g.$1', $whereConditionRaw);

        $sql = "
            WITH user_sums AS (
                SELECT 
                    userid,
                    GREATEST(SUM(gems), 0) AS total_numbers
                FROM gems
                WHERE {$whereConditionRaw} AND collected = 0
                GROUP BY userid
            ),
            total_sum AS (
                SELECT SUM(total_numbers) AS overall_total FROM user_sums
            )
            SELECT 
                g.userid,
                g.gemid,
                g.postid,
                g.fromid,
                g.gems,
                g.whereby,
                g.createdat,
                us.total_numbers,
                (SELECT SUM(total_numbers) FROM user_sums) AS overall_total,
                (us.total_numbers * 100.0 / ts.overall_total) AS percentage
            FROM gems g
            JOIN user_sums us ON g.userid = us.userid
            CROSS JOIN total_sum ts
            WHERE us.total_numbers > 0 AND g.collected = 0 AND {$whereConditionAliased};
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Immutable DTO variant of fetchUncollectedGemsForMint result.
     * Does not alter existing callers that expect an array.
     */
    public function fetchUncollectedGemsForMintResult(string $day = 'D0'): UncollectedGemsResult
    {
        $rows = $this->fetchUncollectedGemsForMint($day);
        if (empty($rows)) {
            return new UncollectedGemsResult([], '0');
        }

        $overall = isset($rows[0]['overall_total']) ? (string)$rows[0]['overall_total'] : '0';
        $mapped = [];
        foreach ($rows as $r) {
            $mapped[] = new UncollectedGemsRow(
                userid: (string)$r['userid'],
                gemid: (string)$r['gemid'],
                postid: isset($r['postid']) ? (string)$r['postid'] : null,
                fromid: isset($r['fromid']) ? (string)$r['fromid'] : null,
                gems: (string)$r['gems'],
                whereby: (int)$r['whereby'],
                createdat: (string)$r['createdat'],
                totalNumbers: (string)$r['total_numbers'],
                overallTotal: (string)($r['overall_total'] ?? $overall),
                percentage: (string)$r['percentage'],
            );
        }

        return new UncollectedGemsResult($mapped, $overall);
    }

    public function setGemsAsCollected(UncollectedGemsResult $uncollectedGems) {
        $gemIds = array_column(
            $uncollectedGems->rows, 
            'gemid'
        );
        $quotedGemIds = array_map(
            fn ($gemId) => $this->db->quote($gemId), 
            $gemIds
        );
        $this->db->query(
            '
                UPDATE 
                    gems 
                SET 
                    collected = 1 
                WHERE gemid IN (' . \implode(',', $quotedGemIds) . ')'
        );
    }
}
