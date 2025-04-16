<?php

namespace Fawaz\App;

const LIKE_=2;
const COMMENT_=4;
const POST_=5;

const DAILYFREEPOST=1;
const DAILYFREELIKE=3;
const DAILYFREECOMMENT=4;

use Fawaz\App\DailyFree;
use Fawaz\Database\DailyFreeMapper;
use Psr\Log\LoggerInterface;

class DailyFreeService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected DailyFreeMapper $dailyFreeMapper)
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

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
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
        $this->logger->info('DailyFreeService.getUserDailyAvailability started', ['userId' => $userId]);

        try {
            $affectedRows = $this->dailyFreeMapper->getUserDailyAvailability($userId);

            if ($affectedRows !== false) {
                return [
                    'status' => 'success',
                    'ResponseCode' =>  11303,
                    'affectedRows' => $affectedRows,
                ];
            }

            return [];
        } catch (\Throwable $e) {
            $this->logger->error('Error in getUserDailyAvailability', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    public function getUserDailyUsage(string $userId, int $artType): int
    {
        $this->logger->info('DailyFreeService.getUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        try {
            $results = $this->dailyFreeMapper->getUserDailyUsage($userId, $artType);
            $this->logger->info('DailyFreeService.getUserDailyUsage results', ['results' => $results]);

            return ($results !== false) ? (int)$results : 0;
        } catch (\Throwable $e) {
            $this->logger->error('Error in getUserDailyUsage', ['exception' => $e->getMessage()]);
            return 0;
        }
    }

    public function incrementUserDailyUsage(string $userId, int $artType): bool
    {
        $this->logger->info('DailyFreeService.incrementUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        try {
            return $this->dailyFreeMapper->incrementUserDailyUsage($userId, $artType);
        } catch (\Throwable $e) {
            $this->logger->error('Error in incrementUserDailyUsage', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
