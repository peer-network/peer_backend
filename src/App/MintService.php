<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Models\MintAccount;
use Fawaz\App\Repositories\MintAccountRepository;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

class MintService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected MintAccountRepository $mintAccountRepository,
        protected UserMapper $userMapper
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

            $payload = [
                'status' => 'success',
                'ResponseCode' => 200,
                'affectedRows' => $account->getArrayCopy(),
            ];

            $this->logger->info('MintService.getMintAccount completed');
            return $payload;
        } catch (\Throwable $e) {
            $this->logger->error('MintService.getMintAccount failed', [
                'error' => $e->getMessage(),
            ]);
            return self::respondWithError(40301);
        }
    }
}
