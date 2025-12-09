<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Interfaces\GemsService as GemsServiceInterface;
use Fawaz\Database\GemsRepository;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

class GemsServiceImpl implements GemsServiceInterface
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
}

