<?php
declare(strict_types=1);

namespace Fawaz\App;

const LIKE_=2;
const COMMENT_=4;
const POST_=5;

use Fawaz\App\DailyFree;
use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class DailyFreeService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected DailyFreeMapper $dailyFreeMapper, protected TransactionManager $transactionManager)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning("Unauthorized action attempted.");
            return false;
        }
        return true;
    }

    public function getUserDailyAvailability(string $userId): array
    {
        $this->logger->debug('DailyFreeService.getUserDailyAvailability started', ['userId' => $userId]);

        try {
            $affectedRows = $this->dailyFreeMapper->getUserDailyAvailability($userId);

            if ($affectedRows === false) {
                $this->logger->warning('DailyFree availability not found', ['userId' => $userId]);
                return $this::respondWithError(40301);
            }

            return $this::createSuccessResponse(11303, $affectedRows, false);

        } catch (\Throwable $e) {
            $this->logger->error('Error in getUserDailyAvailability', ['exception' => $e->getMessage(), 'userId' => $userId]);
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
            $this->logger->error('Error in getUserDailyUsage', ['exception' => $e->getMessage()]);
            return 0;
        }
    }

    public function incrementUserDailyUsage(string $userId, int $artType): bool
    {
        $this->logger->debug('DailyFreeService.incrementUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        try {
            $this->transactionManager->beginTransaction();
            $response =  $this->dailyFreeMapper->incrementUserDailyUsage($userId, $artType);

            if (!$response) {
                $this->logger->error('Failed to increment daily usage', ['userId' => $userId, 'artType' => $artType]);
                $this->transactionManager->rollback();
                return false;
            }
            $this->transactionManager->commit();
            $this->logger->info('Daily usage incremented successfully');

            return true;
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error in incrementUserDailyUsage', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
