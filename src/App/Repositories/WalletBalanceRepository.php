<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;

use DateTime;
use PDO;
use PDOException;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Repositories\Interfaces\WalletBalanceRepositoryInterface;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\App\Repositories\Errors\RepositoryException;

/**
 * Wallet balance persistence adapter.
 *
 * Arithmetic for token amounts uses Rust-backed helpers via TokenHelper (FFI),
 * ensuring consistent precision across the codebase.
 */
class WalletBalanceRepository implements WalletBalanceRepositoryInterface
{
    public function __construct(private PeerLoggerInterface $logger, private PDO $db)
    {
    }

    public function getBalance(string $userId): float
    {
        $this->logger->debug('WalletBalanceRepository.getBalance started');

        $query = "SELECT COALESCE(liquidity, 0) AS balance 
                  FROM wallett 
                  WHERE userid = :userId";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            $this->logger->debug('Fetched wallet balance', ['balance' => $balance]);

            return (float) $balance;
        } catch (PDOException $e) {
            $this->logger->error('Database error in getBalance: ' . $e->getMessage());
            throw new RepositoryException('Unable to fetch wallet balance');
        }

    }

    /**
     * Set absolute balance and persist Q64.96 mirror.
     * Uses Rust-backed math (via TokenHelper) where arithmetic is required;
     * Q64.96 conversion uses BCMath for big integer scaling.
     */
    public function setBalance(string $userId, float $liquidity): float
    {
        $this->logger->debug('WalletBalanceRepository.setBalance started');

        try {
            $sql = "
                INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat)
                VALUES (:userid, :liquidity, :liquiditq, NOW(), NOW())
                ON CONFLICT (userid)
                DO UPDATE SET
                    liquidity = EXCLUDED.liquidity,
                    liquiditq = EXCLUDED.liquiditq,
                    updatedat = EXCLUDED.updatedat
                RETURNING liquidity
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userid'    => $userId,
                ':liquidity' => $liquidity,
                ':liquiditq' => $this->decimalToQ64_96($liquidity),
            ]);
            return (float)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error('Error setting liquidity', ['error' => $e->getMessage(), 'userid' => $userId]);
            throw new RepositoryException('Unable to setBalance');
        }
    }

    /**
     * Increment/decrement balance by delta using Rust-backed math for precision.
     * See WalletBalanceRepositoryInterface::addToBalance
     */
    public function addToBalance(string $userId, float $delta): float
    {
        \ignore_user_abort(true);
        $this->logger->debug('WalletBalanceRepository.addToBalance started');

        try {
            $stmt = $this->db->prepare("SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE");
            $stmt->execute([':userid' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $newLiquidity = abs($delta);
                $stmt = $this->db->prepare(
                    "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat)
                     VALUES (:userid, :liquidity, :liquiditq, NOW(), NOW())
                     RETURNING liquidity"
                );
                $stmt->execute([
                    ':userid'    => $userId,
                    ':liquidity' => $newLiquidity,
                    ':liquiditq' => $this->decimalToQ64_96($newLiquidity),
                ]);
                return (float)$stmt->fetchColumn();
            }

            $currentBalance = (float)$row['liquidity'];
            $newLiquidity = TokenHelper::addRc($currentBalance, $delta);
            $stmt = $this->db->prepare(
                "UPDATE wallett
                 SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = NOW()
                 WHERE userid = :userid
                 RETURNING liquidity"
            );
            $stmt->execute([
                ':userid'    => $userId,
                ':liquidity' => $newLiquidity,
                ':liquiditq' => $this->decimalToQ64_96($newLiquidity),
            ]);
            return (float)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error('Database error in addToBalance: ' . $e);
            throw new RepositoryException('Unable to addToBalance');
        }
    }

    private function decimalToQ64_96(float $value): string
    {
        $scaleFactor = \bcpow('2', '96');
        $decimalString = \number_format($value, 30, '.', '');
        return \bcmul($decimalString, $scaleFactor, 0);
    }
}
