<?php

namespace Fawaz\App;

use Fawaz\Database\McapMapper;
use Psr\Log\LoggerInterface;

class McapService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected McapMapper $mcapMapper
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

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    public function loadLastId(): array
    {

        $this->logger->info('McapService.loadLastId started');

        try {
            $this->mcapMapper->fetchAndUpdateMarketPrices();
			$results = $this->mcapMapper->loadLastId();

            if ($results !== false) {
				$affectedRows = $results->getArrayCopy();
				$this->logger->info("McapService.loadLastId mcap found", ['affectedRows' => $affectedRows]);
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 'Mcap data prepared successfully',
                    'affectedRows' => $affectedRows,
                ];
				return $success;
            }

            return $this->respondWithError('No mcaps found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve mcaps list.');
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->info('McapService.fetchAll started');

        try {
            $users = $this->mcapMapper->fetchAll($args, $this->currentUserId);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                $success = [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 'Users data prepared successfully',
                    'affectedRows' => $fetchAll,
                ];
				return $success;
            }

            return $this->respondWithError('No users found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve users list.');
        }
    }
}


