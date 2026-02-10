<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\DTO\Gems;
use Fawaz\App\DTO\GemsRow;
use Fawaz\App\DTO\UncollectedGemsResult;
use PDO;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

class GemsRepositoryImpl implements GemsRepository
{
    use ResponseHelper;

    /**
     * @param PeerLoggerInterface $logger Logger instance
     * @param PDO                 $db     Database connection
     */
    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PDO $db,
    ) {
    }

    /**
     * Aggregated stats for uncollected gems across common time windows.
     *
     * @return array Associative array with keys like d0..d7, w0, m0, y0
     */
    public function fetchUncollectedGemsStats(): array
    {
        $this->logger->debug('GemsRepositoryImpl.fetchUncollectedGemsStats started');
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

    /**
     * Aggregated per-user gems for a given day filter.
     *
     * Supported filters: D0..D5, W0, M0, Y0
     *
     * @param string $day Day filter (e.g., 'D0', 'W0', 'M0', 'Y0')
     * @return array List of per-user aggregates and totals
     */
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
            $this->logger->debug('GemsRepositoryImpl.fetchAllGemsForDay failed', ['exception' => "Invalid day filter: $day"]);
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
                $this->logger->error('GemsRepositoryImpl.fetchAllGemsForDay: Invalid whereby value', ['whereby' => $whereby]);
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

    /**
     * Raw uncollected gems rows for a given date, suitable for minting.
     *
     * @param string $dateYYYYMMDD Date filter in YYYY-MM-DD format.
     * @return array List of associative rows including userid, gemid, postid, etc.
     */
    private function fetchUncollectedGemsForMint(string $dateYYYYMMDD): array
    {
        $sql = "
            SELECT 
                g.userid,
                g.gemid,
                g.postid,
                g.fromid,
                g.gems,
                g.whereby,
                g.createdat
            FROM gems g
            WHERE g.collected = 0 AND g.createdat::date = :mintDate;
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mintDate' => $dateYYYYMMDD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Immutable DTO result for uncollected gems over a date.
     *
     * @param string $dateYYYYMMDD Date filter in YYYY-MM-DD format.
     * @return Gems DTO containing rows
     */
    public function fetchUncollectedGemsForMintResult(string $dateYYYYMMDD): ?Gems
    {
        $this->logger->debug('GemsRepositoryImpl.fetchUncollectedGemsForMintResult started', ['date' => $dateYYYYMMDD]);
        $rows = $this->fetchUncollectedGemsForMint($dateYYYYMMDD);
        if (empty($rows)) {
            return null;
        }

        $mapped = [];
        foreach ($rows as $r) {
            $mapped[] = new GemsRow(
                userid: (string)$r['userid'],
                gemid: (string)$r['gemid'],
                postid: isset($r['postid']) ? (string)$r['postid'] : null,
                fromid: isset($r['fromid']) ? (string)$r['fromid'] : null,
                gems: (string)$r['gems'],
                whereby: (int)$r['whereby'],
                createdat: (string)$r['createdat']
            );
        }
        return new Gems($mapped);
    }

    /**
     * Insert global win entries into gems and mark source rows as collected.
     *
     * @param string $tableName Source table name (e.g., 'likes', 'views')
     * @param int    $winType   Win type code to store in whereby
     * @param float  $factor    Gems amount factor to insert
     * @return array Result payload including insertCount
     */
    public function setGlobalWins(string $tableName, int $winType, float $factor): array
    {
        \ignore_user_abort(true);

        $this->logger->debug('GemsRepositoryImpl.setGlobalWins started', [
            'tableName' => $tableName,
            'winType' => $winType,
        ]);

        try {
            $sql = "SELECT s.userid, s.postid, s.createdat, p.userid as poster 
                    FROM $tableName s 
                    INNER JOIN posts p ON s.postid = p.postid AND s.userid != p.userid 
                    WHERE s.collected = 0";
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error('GemsRepositoryImpl.setGlobalWins: Error fetching entries for ' . $tableName, ['exception' => $e->getMessage()]);
            return self::respondWithError(41208);
        }

        if (empty($entries)) {
            return ['status' => 'success', 'insertCount' => 0];
        }

        $insertCount = 0;
        $entry_ids = [];

        if (!empty($entries)) {
            $entry_ids = array_map(fn ($row) => isset($row['userid']) && is_string($row['userid']) ? $row['userid'] : null, $entries);
            $entry_ids = array_filter($entry_ids);


            $sql = "INSERT INTO gems (gemid, userid, postid, fromid, gems, whereby, createdat) 
                    VALUES (:gemid, :userid, :postid, :fromid, :gems, :whereby, :createdat)";
            $stmt = $this->db->prepare($sql);

            try {
                foreach ($entries as $row) {
                    $id = self::generateUUID();

                    $stmt->execute([
                        ':gemid' => $id,
                        ':userid' => $row['poster'],
                        ':postid' => $row['postid'],
                        ':fromid' => $row['userid'],
                        ':gems' => $factor,
                        ':whereby' => $winType,
                        ':createdat' => $row['createdat']
                    ]);

                    $insertCount++;
                }

                if (!empty($entry_ids)) {
                    $placeholders = implode(',', array_fill(0, count($entry_ids), '?'));
                    $sql = "UPDATE $tableName SET collected = 1 WHERE collected = 0 AND userid IN ($placeholders)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($entry_ids);
                }

            } catch (\Throwable $e) {
                $this->logger->error('GemsRepositoryImpl.setGlobalWins: Error inserting into gems for ' . $tableName, ['exception' => $e->getMessage()]);
                return self::respondWithError(41210);
            }
        }

        return [
            'status' => 'success',
            'insertCount' => $insertCount
        ];
    }

    /**
     * Apply mint metadata and mark corresponding gems as collected.
     *
     * @param string                $mintId          The mint operation identifier
     * @param Gems $uncollectedGems The result set to apply
     * @param array<string,array<string, mixed>>    $mintLogItems     Map of userId => log item (contains transactionId)
     * @return void
     */
    public function applyMintInfo(
        string $mintId,
        Gems $uncollectedGems,
        array $mintLogItems
    ): void {

        $this->logger->debug('GemsRepositoryImpl.applyMintInfo started', [
            'mintId' => $mintId,
            'rows' => count($uncollectedGems->rows),
            'users' => count($mintLogItems),
        ]);

        if (empty($uncollectedGems->rows)) {
            $this->logger->error('list of uncollected gems is empty');
            throw new \RuntimeException("list of uncollected gems is empty", 40301);
        }


        $sql = 'UPDATE gems SET mintid = :mintid, transactionid = :tx, collected = 1 WHERE gemid = :gemid';
        $stmt = $this->db->prepare($sql);

        foreach ($uncollectedGems->rows as $row) {
            $userId = (string)$row->userid;
            $transactionId = $mintLogItems[$userId]['logItem']->transactionid ?? null;

            if (!$transactionId) {
                $this->logger->info('GemsRepositoryImpl.applyMintInfo: No mint transaction found for userid. Due to thing, that User has 0 tokens to distribute(because of less than 0 gems). Transactionid will remain empty', ['userId' => $userId]);
                // $this->logger->info('mintlogItem for userid not found. Revert.', ['userId' => $userId]);
            }
            $stmt->execute([
                ':mintid' => $mintId,
                ':tx'     => $transactionId,
                ':gemid'  => (string)$row->gemid,
            ]);
        }

        $this->logger->info('GemsRepositoryImpl.applyMintInfo succeeded');
    }
}
