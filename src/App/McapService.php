<?php
declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\McapMapper;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class McapService
{
    use ResponseHelper;
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

    public function loadLastId(): array
    {

        $this->logger->debug('McapService.loadLastId started');

        try {
            $fetchUpdate = $this->mcapMapper->fetchAndUpdateMarketPrices();
            if (isset($fetchUpdate['status']) && $fetchUpdate['status'] === 'error') {
                return $fetchUpdate;
            }

            $results = $this->mcapMapper->loadLastId();

            if ($results !== false) {
                $affectedRows = $results->getArrayCopy();
                $this->logger->info("McapService.loadLastId mcap found", ['affectedRows' => $affectedRows]);
                return $this::createSuccessResponse(
                    11021,
                    $affectedRows,
                    false
                );
            }

            return $this::respondWithError(31201);
        } catch (\Exception $e) {
            return $this::respondWithError(41206);
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->debug('McapService.fetchAll started');

        try {
            $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
            $limit = isset($args['limit']) ? (int) $args['limit'] : null;

            $users = $this->mcapMapper->fetchAll($offset, $limit);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                $success = [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => "11009",
                    'affectedRows' => $fetchAll,
                ];
                return $success;
            }

            return $this::createSuccessResponse(21001);
        } catch (\Exception $e) {
            return $this::respondWithError(41207);
        }
    }
}
