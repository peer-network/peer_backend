<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Repositories\MintAccountRepository;
use Fawaz\App\Interfaces\HasTokenWallet;
use Fawaz\Database\UserMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use PDO;

class MintService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected MintAccountRepository $mintAccountRepository,
        protected UserMapper $userMapper,
        protected WalletMapper $walletMapper,
        protected PDO $db
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
        // Admin-only: allow ADMIN and SUPER_ADMIN
        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            $this->logger->warning('User not found for admin check', ['uid' => $this->currentUserId]);
            return false;
        }
        $rolesMask = $user->getRolesmask();
        if ($rolesMask !== Role::ADMIN && $rolesMask !== Role::SUPER_ADMIN) {
            $this->logger->warning('Forbidden: admin-only endpoint', ['uid' => $this->currentUserId, 'roles' => $rolesMask]);
            return false;
        }
        return true;
    }

    /**
     * Get the single Mint Account row.
     */
    public function getMintAccount(): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        try {
            $this->logger->debug('MintService.getMintAccount started');
            $account = $this->mintAccountRepository->getDefaultAccount();
            if (!$account) {
                $this->logger->debug('MintService.getMintAccount result is empty');
                return self::respondWithError(40401);
            }

            $this->logger->info('MintService.getMintAccount completed');
            // Map to MintAccount GraphQL type and wrap in standard success response (no counter)
            return $this::createSuccessResponse(00000, $account->getArrayCopy(), false);
        } catch (\Throwable $e) {
            $this->logger->error('MintService.getMintAccount failed', [
                'error' => $e->getMessage(),
            ]);
            return self::respondWithError(40301);
        }
    }

    public function callGlobalWins(): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        return $this->walletMapper->callGlobalWins();
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        return $this->walletMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'W0', 'M0', 'Y0'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30105);
        }

        $gemsters = $this->walletMapper->getTimeSortedMatch($day);

        if (isset($gemsters['affectedRows']['data'])) {
            $winstatus = $gemsters['affectedRows']['data'][0];
            unset($gemsters['affectedRows']['data'][0]);

            $userStatus = array_values($gemsters['affectedRows']['data']);

            $affectedRows = [
                'winStatus' => $winstatus ?? [],
                'userStatus' => $userStatus,
            ];

            return [
                'status' => $gemsters['status'],
                'counter' => $gemsters['counter'] ?? 0,
                'ResponseCode' => $gemsters['ResponseCode'],
                'affectedRows' => $affectedRows
            ];
        }
        return [
            'status' => $gemsters['status'],
            'counter' => 0,
            'ResponseCode' => $gemsters['ResponseCode'],
            'affectedRows' => []
        ];
    }
}
