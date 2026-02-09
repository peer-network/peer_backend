<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\DailyFree;
use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;

class DailyFreeService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected DailyFreeMapper $dailyFreeMapper, protected TransactionManager $transactionManager)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public function getUserDailyAvailability(string $userId): array
    {
        $this->logger->debug('DailyFreeService.getUserDailyAvailability started', ['userId' => $userId]);

        try {
            $affectedRows = $this->dailyFreeMapper->getUserDailyAvailability($userId);

            if ($affectedRows === false) {
                $this->logger->error('DailyFreeService.getUserDailyAvailability: DailyFree availability not found', ['userId' => $userId]);
                return $this::respondWithError(40301);
            }

            return $this::createSuccessResponse(11303, $affectedRows, false);

        } catch (\Throwable $e) {
            $this->logger->error('DailyFreeService.getUserDailyAvailability: Error in getUserDailyAvailability', ['exception' => $e->getMessage(), 'userId' => $userId]);
            return $this::respondWithError(40301);
        }
    }

    public function getUserDailyUsage(string $userId, int $artType): int
    {
        $this->logger->debug('DailyFreeService.getUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        try {
            $results = $this->dailyFreeMapper->getUserDailyUsage($userId, $artType);
            $this->logger->info('DailyFreeService.getUserDailyUsage results', ['results' => $results]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('DailyFreeService.getUserDailyUsage: Error in getUserDailyUsage', ['exception' => $e->getMessage()]);
            return 0;
        }
    }

    public function incrementUserDailyUsage(string $userId, int $artType): bool
    {
        $this->logger->debug('DailyFreeService.incrementUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        try {
            $response =  $this->dailyFreeMapper->incrementUserDailyUsage($userId, $artType);

            if (!$response) {
                $this->logger->error('Failed to increment daily usage', ['userId' => $userId, 'artType' => $artType]);
                return false;
            }
            $this->logger->info('DailyFreeService.incrementUserDailyUsage: Daily usage incremented successfully', ['userId' => $userId, 'artType' => $artType]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('DailyFreeService.incrementUserDailyUsage: Error in incrementUserDailyUsage', ['exception' => $e->getMessage(), 'userId' => $userId, 'artType' => $artType]);
            return false;
        }
    }
}
