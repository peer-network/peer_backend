<?php

declare(strict_types=1);

namespace Tests\Mocks\Database;

use Fawaz\Database\PeerTokenMapperInterface;
use Fawaz\Services\ContentFiltering\Capabilities\HasWalletId;
use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;

final class MockPeerTokenMapper implements PeerTokenMapperInterface
{
    private array $transfers = [];
    private array $walletBalances = [];
    private array $transactions = [];
    private string $lpToken;
    private bool $feesWalletsValid = true;
    private array $restrictedWallets = [];
    private array $feesPercentages = [
        'peer' => '0.01',
        'burn' => '0.01',
        'invite' => '0.00',
    ];

    public function __construct(array $walletBalances = [], string $lpToken = 'mock-lp-token')
    {
        $this->walletBalances = $walletBalances;
        $this->lpToken = $lpToken;
    }

    public function hasExistingTransfer(string $senderId, string $recipientId, string $amount): bool
    {
        foreach ($this->transfers as $transfer) {
            if ($transfer['senderId'] === $senderId && $transfer['recipientId'] === $recipientId && $transfer['amount'] === $amount) {
                return true;
            }
        }

        return false;
    }

    public function initializeLiquidityPool(): array
    {
        return ['status' => 'initialized', 'lpToken' => $this->lpToken];
    }

    public function recipientShouldNotBeFeesAccount(string $recipientId): bool
    {
        return !in_array($recipientId, $this->restrictedWallets, true);
    }

    public function getLpToken(): string
    {
        return $this->lpToken;
    }

    public function validateFeesWalletUUIDs(): bool
    {
        return $this->feesWalletsValid;
    }

    public function setSenderId(string $senderId): void
    {
        return;
    }

    public function calculateRequiredAmount(string $senderId, string $numberOfTokens): string
    {
        return $numberOfTokens;
    }

    public function calculateEachFeesAmount(string $numberOfTokens): array
    {
        return [
            $this->mul($numberOfTokens, $this->feesPercentages['peer']),
            $this->mul($numberOfTokens, $this->feesPercentages['burn']),
            $this->mul($numberOfTokens, $this->feesPercentages['invite']),
        ];
    }

    public function transferToken(
        string $numberOfTokens,
        TransferStrategy $strategy,
        HasWalletId $sender,
        HasWalletId $recipient,
        ?string $message = null
    ): array {
        $senderId = $sender->getWalletId();
        $recipientId = $recipient->getWalletId();
        $this->setSenderId($senderId);

        $this->walletBalances[$senderId] = $this->formatAmount(
            $this->getNumericBalance($senderId) - (float) $numberOfTokens
        );
        $this->walletBalances[$recipientId] = $this->formatAmount(
            $this->getNumericBalance($recipientId) + (float) $numberOfTokens
        );

        $record = [
            'senderId' => $senderId,
            'recipientId' => $recipientId,
            'amount' => $numberOfTokens,
            'message' => $message,
            'operationId' => $strategy->getOperationId(),
        ];
        $this->transfers[] = $record;
        $this->transactions[] = $record;

        return ['status' => 'success', 'payload' => $record];
    }

    public function calculateRequiredAmountByMode(string $senderId, string $inputAmount, FeePolicyMode $mode): string
    {
        return $this->calculateRequiredAmount($senderId, $inputAmount);
    }

    public function getUserWalletBalance(string $userId): string
    {
        return $this->walletBalances[$userId] ?? '0';
    }

    public function getTransactions(string $userId, array $args): array
    {
        return array_values(array_filter($this->transactions, function (array $txn) use ($userId): bool {
            return $txn['senderId'] === $userId || $txn['recipientId'] === $userId;
        }));
    }

    public function getTransactionHistoryItems(string $userId, array $args, array $specs): array
    {
        return $this->getTransactions($userId, $args);
    }

    public function seedWalletBalance(string $walletId, float $amount): void
    {
        $this->walletBalances[$walletId] = $this->formatAmount($amount);
    }

    public function restrictWallet(string $walletId): void
    {
        $this->restrictedWallets[] = $walletId;
    }

    public function setFeesValidationStatus(bool $isValid): void
    {
        $this->feesWalletsValid = $isValid;
    }

    public function setFeePercentages(array $percentages): void
    {
        $this->feesPercentages = array_merge($this->feesPercentages, $percentages);
    }

    private function mul(string $numberOfTokens, string $percentage): string
    {
        return $this->formatAmount((float) $numberOfTokens * (float) $percentage);
    }

    private function getNumericBalance(string $walletId): float
    {
        return (float) ($this->walletBalances[$walletId] ?? '0');
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 8, '.', '');
    }

    public function getTransfers(): array
    {
        return $this->transfers;
    }

    public function getWalletBalances(): array
    {
        return $this->walletBalances;
    }
}
