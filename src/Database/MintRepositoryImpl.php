<?php

declare(strict_types=1);

namespace Fawaz\Database;


use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\App\Repositories\MintAccountRepository;
use Fawaz\Database\UserMapper;
use Fawaz\Services\TokenTransfer\Strategies\MintTransferStrategy;

const TABLESTOGEMS = true;

class MintRepositoryImpl implements MintRepository
{
    use ResponseHelper;
    private string $burnWallet;
    private string $peerWallet;

    public function __construct(
        protected PeerLoggerInterface $logger, 
        protected PDO $db, 
        protected LiquidityPool $pool,
        protected WalletMapper $walletMapper,
        protected PeerTokenMapper $peerTokenMapper,
        protected MintAccountRepository $mintAccountRepository,
        protected UserMapper $userMapper,
    ){} 

    public function generateGemsFromActions(): array
    {
        if (!TABLESTOGEMS) {
            return self::respondWithError(41215);
        }

        $tokenomics = ConstantsConfig::tokenomics();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        $actionGemsReturns = $tokenomics['ACTION_GEMS_RETURNS'];

        $wins = [
            ['table' => 'user_post_views', 'winType' => (int)$actions['VIEW'], 'factor' => (float)$actionGemsReturns['view']],
            ['table' => 'user_post_likes', 'winType' => (int)$actions['LIKE'], 'factor' => (float)$actionGemsReturns['like']],
            ['table' => 'user_post_dislikes', 'winType' => (int)$actions['DISLIKE'], 'factor' => (float)$actionGemsReturns['dislike']],
            ['table' => 'user_post_comments', 'winType' => (int)$actions['COMMENT'], 'factor' => (float)$actionGemsReturns['comment']]
        ];

        $totalInserts = 0;
        $winSources = [];

        foreach ($wins as $win) {
            $result = $this->setGlobalWins($win['table'], $win['winType'], $win['factor']);

            if ($result['status'] === 'error') {
                $this->logger->error("Failed to set global wins for {$win['table']}");
            }

            if (isset($result['insertCount']) && $result['insertCount'] > 0) {
                $totalInserts += $result['insertCount'];

                $tablePart = strtolower(substr($win['table'], strrpos($win['table'], '_') + 1));
                $winSources[] = $tablePart;
            }
        }

        if ($totalInserts > 0) {
            $sourceList = implode(', ', $winSources);
            $success = ['status' => 'success', 'ResponseCode' => "11206"];
            return $success;
        }

        $success = ['status' => 'success', 'ResponseCode' => "21205"];
        return $success;
    }

    protected function setGlobalWins(string $tableName, int $winType, float $factor): array
    {
        \ignore_user_abort(true);

        $this->logger->debug('WalletMapper.setGlobalWins started');

        try {
            $sql = "SELECT s.userid, s.postid, s.createdat, p.userid as poster 
                    FROM $tableName s 
                    INNER JOIN posts p ON s.postid = p.postid AND s.userid != p.userid 
                    WHERE s.collected = 0";
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching entries for ' . $tableName, ['exception' => $e]);
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

            $this->db->beginTransaction();

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
                $this->db->commit();

            } catch (\Throwable $e) {
                $this->db->rollback();
                $this->logger->error('Error inserting into gems for ' . $tableName, ['exception' => $e]);
                return self::respondWithError(41210);
            }
        }

        return [
            'status' => 'success',
            'insertCount' => $insertCount
        ];
    }

    public function listTodaysInteractions(string $userId): array
    {
        $tokenomics = ConstantsConfig::tokenomics();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        $actionGemsReturns = $tokenomics['ACTION_GEMS_RETURNS'];

        try {
            $wins = [
                ['table' => 'user_post_views', 'winType' => (int)$actions['VIEW'], 'factor' => (float)$actionGemsReturns['view'], 'key' => 'views'],
                ['table' => 'user_post_likes', 'winType' => (int)$actions['LIKE'], 'factor' => (float)$actionGemsReturns['like'], 'key' => 'likes'],
                ['table' => 'user_post_dislikes', 'winType' => (int)$actions['DISLIKE'], 'factor' => -(float)$actionGemsReturns['dislike'], 'key' => 'dislikes'],
                ['table' => 'user_post_comments', 'winType' => (int)$actions['COMMENT'], 'factor' => (float)$actionGemsReturns['comment'], 'key' => 'comments']
            ];

            $totalInteractions = 0;
            $totalScore = 0.0;
            $winSources = [];
            $interactionDetails = [
                'views' => 0,
                'likes' => 0,
                'dislikes' => 0,
                'comments' => 0,
                'viewsScore' => 0.0,
                'likesScore' => 0.0,
                'dislikesScore' => 0.0,
                'commentsScore' => 0.0
            ];

            foreach ($wins as $win) {
                $result = $this->GetUserMove($win['table'], $win['winType'], $win['factor'], $userId);

                if ($result['status'] === 'error') {
                    $this->logger->error("Failed to process {$win['table']}");
                    continue;
                }

                if (!empty($result['insertCount'])) {
                    $totalInteractions += $result['insertCount'];
                    $totalScore += $result['totalFactor'];
                    $winSources[] = strtolower($win['key']);

                    $interactionDetails[$win['key']] = $result['insertCount'];
                    $interactionDetails[$win['key'] . 'Score'] = $result['totalFactor'];
                }
            }

            return [
                'status' => 'success',
                'ResponseCode' => $totalInteractions > 0
                    ? 11205
                    : 21204,
                'affectedRows' => array_merge(['totalInteractions' => $totalInteractions, 'totalScore' => $totalScore, 'totalDetails' => $interactionDetails])
            ];
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred while processing user move', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'ResponseCode' => "41205",
                'affectedRows' => []
            ];
        }
    }

    protected function GetUserMove(string $tableName, int $winType, float $factor, string $userId): array
    {
        \ignore_user_abort(true);
        $this->logger->info("Fetching interactions for user $userId from $tableName");

        try {
            $sql = "
                SELECT COUNT(*) as interaction_count
                FROM $tableName s
                INNER JOIN posts p ON s.postid = p.postid
                WHERE p.userid = :userId 
                  AND s.createdat >= CURRENT_DATE
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['userId' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $interactionCount = (int)($result['interaction_count'] ?? 0);

            $totalFactor = $interactionCount * $factor;

        } catch (\PDOException $e) {
            $this->logger->error("Database error fetching entries for $tableName", [
                'userId' => $userId,
                'tableName' => $tableName,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => "Database error: " . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error fetching entries for $tableName", [
                'userId' => $userId,
                'tableName' => $tableName,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => "Unexpected error: " . $e->getMessage()
            ];
        }

        return [
            'status' => 'success',
            'insertCount' => $interactionCount,
            'totalFactor' => $totalFactor
        ];
    }

    /**
     * Determine if a mint was performed for a specific day action.
     *
     * Day actions supported (same semantics as getTimeSortedMatch):
     *  - D0..D7: specific day offsets from today
     *  - W0: current week
     *  - M0: current month
     *  - Y0: current year
     *
     * Returns true if at least one transaction with
     * transactiontype = 'transferMintAccountToRecipient' exists for that period
     * where the sender is the MintAccount.
     */
    public function mintWasPerformedForDay(string $dayAction): bool
    {
        // Resolve the time window condition for transactions.createdat
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

        if (!array_key_exists($dayAction, $dayOptionsRaw)) {
            throw new \InvalidArgumentException('Invalid dayAction');
        }

        // Identify the MintAccount (sender of mint transactions)
        $mintAccount = $this->mintAccountRepository->getDefaultAccount();
        if ($mintAccount === null) {
            throw new \RuntimeException('No MintAccount available');
        }

        $whereCondition = $dayOptionsRaw[$dayAction];
        $sql = "SELECT 1 FROM transactions
                WHERE senderid = :sender
                  AND transactiontype = 'transferMintAccountToRecipient'
                  AND $whereCondition
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sender', $mintAccount->getWalletId(), \PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
