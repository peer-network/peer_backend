<?php

namespace Fawaz\App;

use \Exception;
use Fawaz\Database\LogWinMapper;
use Fawaz\Utils\PeerLoggerInterface;

class LogWinService
{
    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected LogWinMapper $logWinMapper)
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
    public function logWinMigration04(): ?array
    {
        $this->logger->info('LogWinService.logWinMigration04 started');

        set_time_limit(0);
        try {


            $response = $this->logWinMapper->migrateTokenTransfer();

            if(!$response){
                // Free memory between batches
                gc_collect_cycles();

                // Small delay helps avoid TLS exhaustion on some PHP builds
                usleep(200); // 200 ms

                $this->logWinMigration04();

                // $message = 'Token transfer migration batch has more records to process. Wait for 20 seconds and call again.';

            }
            
            $message = 'Token transfer migration batch processed.';

            return [
                'status' => $message,
                'ResponseCode' => 200
            ];

        }catch (\RuntimeException $e) {
            $this->logger->error('RuntimeException in LogWinService.logWinMigration04: ' . $e->getMessage());
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
     * This will be Recurring function to migrate logwin data
     * It will keep migrating data until all logwin records are processed
     * 
     */
    public function logWinMigration05(): ?array
    {
        $this->logger->info('LogWinService.logWinMigration04 started');

        set_time_limit(0);
        try {


            $response = $this->logWinMapper->migrateTokenTransfer01();

            if(!$response){
                // Free memory between batches
                gc_collect_cycles();

                // Small delay helps avoid TLS exhaustion on some PHP builds
                usleep(200); // 200 ms

                $this->logWinMigration05();

                // $message = 'Token transfer migration batch has more records to process. Wait for 20 seconds and call again.';

            }

            $response = $this->logWinMapper->checkForUnmigratedRecords();
            
            if(!$response){
                // Free memory between batches
                gc_collect_cycles();

                // Small delay helps avoid TLS exhaustion on some PHP builds
                usleep(200); // 200 ms

                $this->logWinMigration05();

                // $message = 'Token transfer migration batch has more records to process. Wait for 20 seconds and call again.';

            }
            
            $message = 'Token transfer migration batch processed.';

            return [
                'status' => $message,
                'ResponseCode' => 200
            ];

        }catch (\RuntimeException $e) {
            $this->logger->error('RuntimeException in LogWinService.logWinMigration04: ' . $e->getMessage());
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