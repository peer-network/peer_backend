<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;
use Fawaz\App\Models\MintAccount;
use Fawaz\Utils\PeerLoggerInterface;
use PDO;

class MintAccountRepository implements WalletDebitable
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db) {}

    /**
     * Get the default (first) mint account entity or null.
     */
    public function getDefaultAccount(): ?MintAccount
    {
        $this->logger->debug('MintAccountRepository.getDefaultAccount started');
        $sql = 'SELECT accountid, initial_balance, current_balance, createdat, updatedat
                FROM mint_account
                ORDER BY createdat ASC
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->logger->warning('MintAccountRepository.getDefaultAccount: no rows');
            return null;
        }
        return new MintAccount($row, [], false);
    }

    /**
     * Deduct an amount from current_balance for a given account.
     *
     * Rules:
     *  - amount must be > 0 and <= 5000
     *  - resulting current_balance must not be negative
     *  - current_balance must not exceed initial_balance (handled by DB CHECK)
     *
     * Returns the new current_balance on success.
     */
    public function debitIfSufficient(string $userId, string $amount): ?string
    // public function deductCurrentBalance(string $accountId, string $amount): string
    {
        $this->logger->debug('MintAccountRepository.deductCurrentBalance started', [
            'accountId' => $userId,
            'amount' => $amount,
        ]);

        // Basic input validation
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Amount must be numeric');
        }

        $amt = (float)$amount;
        if ($amt <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0');
        }
        if ($amt > 5000) {
            throw new \InvalidArgumentException('Amount must not exceed 5000');
        }

        try {
            // Single atomic update preventing negative balances, no explicit transactions
            $updateSql = 'UPDATE mint_account
                          SET current_balance = current_balance - :amount,
                              updatedat = CURRENT_TIMESTAMP
                          WHERE accountid = :accountId
                            AND current_balance - :amount >= 0
                          RETURNING current_balance';

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindValue(':amount', (string)$amount, PDO::PARAM_STR);
            $updateStmt->bindValue(':accountId', $userId, PDO::PARAM_STR);
            $updateStmt->execute();
            $updated = $updateStmt->fetch(PDO::FETCH_ASSOC);

            if (!$updated) {
                // Could be account missing or insufficient balance
                // Check if account exists to give a clearer error
                $checkStmt = $this->db->prepare('SELECT 1 FROM mint_account WHERE accountid = :accountId');
                $checkStmt->bindValue(':accountId', $userId, PDO::PARAM_STR);
                $checkStmt->execute();
                if (!$checkStmt->fetchColumn()) {
                    throw new \RuntimeException('Account not found');
                }
                throw new \RuntimeException('Insufficient balance');
            }

            $newBalance = (string)$updated['current_balance'];
            $this->logger->info('MintAccountRepository.deductCurrentBalance succeeded', [
                'accountId' => $userId,
                'amount' => $amount,
                'newBalance' => $newBalance,
            ]);
            return $newBalance;
        } catch (\Throwable $e) {
            $this->logger->error('MintAccountRepository.deductCurrentBalance failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Lock a single wallet balance row for update.
     */
    public function lockWalletBalance(string $walletId): void
    {
        $this->logger->debug('MintAccountRepository.lockWalletBalance started', [
            'walletId' => $walletId,
        ]);

        $query = 'SELECT current_balance FROM mint_account WHERE accountid = :accountid FOR UPDATE';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':accountid', $walletId, PDO::PARAM_STR);
        $stmt->execute();
        // Fetch a row to ensure the lock is taken
        $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
