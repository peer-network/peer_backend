<?php

declare(strict_types=1);

namespace Fawaz\Database;


use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\App\Repositories\MintAccountRepository;
use Fawaz\Database\UserMapper;

class UserActionsRepositoryImpl implements UserActionsRepository
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
}
