<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Interfaces\GemsService;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\GemsRepository;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

const TABLESTOGEMS = true;

class GemsServiceImpl implements GemsService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected GemsRepository $gemsRepository,
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function gemsStats(): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        return $this->gemsRepository->fetchUncollectedGemsStats();
    }

    public function allGemsForDay(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];

        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30223);
        }

        return $this->gemsRepository->fetchAllGemsForDay($day);
    }

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
            // ['table' => 'user_post_dislikes', 'winType' => (int)$actions['DISLIKE'], 'factor' => (float)$actionGemsReturns['dislike']],
            ['table' => 'user_post_comments', 'winType' => (int)$actions['COMMENT'], 'factor' => (float)$actionGemsReturns['comment']]
        ];

        $totalInserts = 0;
        $winSources = [];

        foreach ($wins as $win) {
            $result = $this->gemsRepository->setGlobalWins($win['table'], $win['winType'], $win['factor']);

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
}

