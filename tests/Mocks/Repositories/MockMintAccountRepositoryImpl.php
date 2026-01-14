<?php

declare(strict_types=1);

namespace Tests\Mocks\Repositories;

use DateTimeImmutable;
use Fawaz\App\Models\MintAccount;
use Fawaz\App\Repositories\MintAccountRepository;
use RuntimeException;

final class MockMintAccountRepositoryImpl implements MintAccountRepository
{
    private ?array $accountData;

    private ?string $lockedWalletId = null;

    public function __construct(?array $accountData = null)
    {
        $this->accountData = $accountData ?? $this->buildDefaultAccount();
    }

    public function getDefaultAccount(): ?MintAccount
    {
        if ($this->accountData === null) {
            return null;
        }

        return new MintAccount($this->accountData, [], false);
    }

    public function debitIfSufficient(string $userId, string $amount): string
    {
        if (!is_numeric($amount)) {
            throw new RuntimeException('Amount must be numeric');
        }

        $value = (float) $amount;
        if ($value <= 0) {
            throw new RuntimeException('Amount must be greater than zero');
        }
        if ($value > 5000) {
            throw new RuntimeException('Amount must not exceed 5000');
        }

        if ($this->accountData === null || $this->accountData['accountid'] !== $userId) {
            throw new RuntimeException('Account not found');
        }

        $currentBalance = (float) $this->accountData['current_balance'];
        if ($currentBalance - $value < 0) {
            throw new RuntimeException('Insufficient balance');
        }

        $this->accountData['current_balance'] = $currentBalance - $value;
        $this->accountData['updatedat'] = $this->timestamp();

        return $this->formatAmount($this->accountData['current_balance']);
    }

    public function lockWalletBalance(string $walletId): void
    {
        $this->lockedWalletId = $walletId;
    }

    public function getLockedWalletId(): ?string
    {
        return $this->lockedWalletId;
    }

    public function withNoAccount(): void
    {
        $this->accountData = null;
    }

    public function seedAccount(array $data): void
    {
        $now = $this->timestamp();
        $this->accountData = array_merge(
            $this->buildDefaultAccount(),
            ['createdat' => $now, 'updatedat' => $now],
            $data,
        );
    }

    private function buildDefaultAccount(): array
    {
        $now = $this->timestamp();

        return [
            'accountid' => 'mock-account-id',
            'initial_balance' => 10000.0,
            'current_balance' => 10000.0,
            'createdat' => $now,
            'updatedat' => $now,
        ];
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 10, '.', '');
    }
}
