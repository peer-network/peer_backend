<?php

namespace Fawaz\App;

use Psr\Log\LoggerInterface;
use \Exception;
use Fawaz\Database\LogWinMapper;

class LogWinService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected LogWinMapper $logWinMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function respondWithError(int $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function createSuccessResponse(int $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
    }

    /**
     * This will be Recurring function to migrate logwin data
     * It will keep migrating data until all logwin records are processed
     * 
     */
    public function logWinMigration(): ?array
    {
        $this->logger->info('LogWinService.logWinMigration started');

        set_time_limit(0);
        try {

            // PENDING: Migrate Gems to LogWins
            $this->logWinMapper->migrateGemsToLogWins();


            $response = $this->logWinMapper->migratePaidActions();

            if(!$response){

                // Free memory between batches
                gc_collect_cycles();

                // Small delay helps avoid TLS exhaustion on some PHP builds
                usleep(200); // 200 ms

                $this->logWinMigration();
            }

            $response = $this->logWinMapper->migrateTokenTransfer();

            if(!$response){
                // Free memory between batches
                gc_collect_cycles();

                // Small delay helps avoid TLS exhaustion on some PHP builds
                usleep(200); // 200 ms

                $this->logWinMigration();
            }
            

            return [
                'status' => 'success',
                'ResponseCode' => 200
            ];

        }catch (\RuntimeException $e) {
            $this->logger->error('RuntimeException in LogWinService.logWinMigration: ' . $e->getMessage());
            return [
                'status' => 'error - ' . $e->getMessage(),
                'ResponseCode' => $e->getCode()
            ];
        }catch (\Exception $e) {
            return [
                'status' => 'error - ' . $e->getMessage(),
                'ResponseCode' => 41205
            ];
        }
    }

    /**
     * Generate logwin entries for paid actions that were not generated previously between March and 02 April 2025
     * 
     */
    public function logwinsPaidActionForMarchApril(): ?array
    {
        $this->logger->info('LogWinService.logWinMigration started');

        try {

            // Generate logwin entries for like paid actions
            $this->logWinMapper->generateLikePaidActionToLogWins();

            // Generate logwin entries for dislike paid actions
            $this->logWinMapper->generateDislikePaidActionToLogWins();

            // Generate logwin entries for post paid actions
            $this->logWinMapper->generatePostPaidActionToLogWins();

            //Generate logwin entries for comment paid actions
            $this->logWinMapper->generateCommentPaidActionToLogWins();

            return [
                'status' => 'success',
                'ResponseCode' => 200
            ];

        }catch (\RuntimeException $e) {
            $this->logger->error('RuntimeException in LogWinService.logWinMigration: ' . $e->getMessage());
            return [
                'status' => 'error - ' . $e->getMessage(),
                'ResponseCode' => $e->getCode()
            ];
        }catch (\Exception $e) {
            return [
                'status' => 'error - ' . $e->getMessage(),
                'ResponseCode' => 41205
            ];
        }
    }

    

}
