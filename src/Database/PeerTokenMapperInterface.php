<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Services\ContentFiltering\Capabilities\HasWalletId;
use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;

interface PeerTokenMapperInterface
{
    public function hasExistingTransfer(string $senderId, string $recipientId, string $amount): bool;

    public function initializeLiquidityPool(): array;

    public function recipientShouldNotBeFeesAccount(string $recipientId): bool;

    public function getLpToken(): string;

    public function validateFeesWalletUUIDs(): bool;

    public function setSenderId(string $senderId): void;

    public function calculateRequiredAmount(string $senderId, string $numberOfTokens): string;

    public function calculateEachFeesAmount(string $numberOfTokens): array;

    public function transferToken(
        string $numberOfTokens,
        TransferStrategy $strategy,
        HasWalletId $sender,
        HasWalletId $recipient,
        ?string $message = null
    ): ?array;

    public function calculateRequiredAmountByMode(string $senderId, string $inputAmount, FeePolicyMode $mode): string;

    public function getUserWalletBalance(string $userId): string;

    public function getTransactions(string $userId, array $args): ?array;

    public function getTransactionHistoryItems(string $userId, array $args, array $specs): array;
}
