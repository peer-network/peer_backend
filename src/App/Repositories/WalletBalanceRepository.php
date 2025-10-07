<?php
declare(strict_types=1);

namespace Fawaz\App\Repositories;

use DateTime;
use PDO;
use PDOException;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Repositories\Interfaces\WalletBalanceRepositoryInterface;

class WalletBalanceRepository implements WalletBalanceRepositoryInterface
{
    public function __construct(private PeerLoggerInterface $logger, private PDO $db)
    {
    }

    public function getBalance(string $userId): float
    {
        $this->logger->debug('WalletBalanceRepository.getBalance started');

        $query = 'SELECT COALESCE(liquidity, 0) AS balance FROM wallett WHERE userid = :userId';
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            $this->logger->info('Fetched wallet balance', ['userId' => $userId, 'balance' => $balance]);
            return (float) $balance;
        } catch (PDOException $e) {
            $this->logger->error('DB error in getBalance', ['error' => $e->getMessage(), 'userId' => $userId]);
            throw new \RuntimeException('Unable to fetch wallet balance');
        }
    }

    public function setBalance(string $userId, float $liquidity): bool
    {
        $this->logger->debug('WalletBalanceRepository.setBalance started');

        try {
            // Try update first
            $sqlUpdate = 'UPDATE wallett SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = CURRENT_TIMESTAMP WHERE userid = :userid';
            $stmt = $this->db->prepare($sqlUpdate);
            $stmt->bindValue(':liquidity', $liquidity, PDO::PARAM_STR);
            $stmt->bindValue(':liquiditq', $this->decimalToQ64_96($liquidity), PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                // Insert if not exists
                $sqlInsert = 'INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat) VALUES (:userid, :liquidity, :liquiditq, :updatedat, :createdat)';
                $stmt = $this->db->prepare($sqlInsert);
                $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $liquidity, PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $this->decimalToQ64_96($liquidity), PDO::PARAM_STR);
                $now = (new DateTime())->format('Y-m-d H:i:s.u');
                $stmt->bindValue(':updatedat', $now, PDO::PARAM_STR);
                $stmt->bindValue(':createdat', $now, PDO::PARAM_STR);
                $stmt->execute();
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error setting liquidity', ['error' => $e->getMessage(), 'userid' => $userId]);
            return false;
        }
    }

    public function upsertAndReturn(string $userId, float $liquidity): float
    {
        $this->logger->debug('WalletBalanceRepository.upsertAndReturn started');

        try {
            // Lock row for update if exists
            $stmt = $this->db->prepare('SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE');
            $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                // Insert new
                $newLiquidity = abs($liquidity);
                $stmt = $this->db->prepare('INSERT INTO wallett (userid, liquidity, liquiditq, updatedat) VALUES (:userid, :liquidity, :liquiditq, :updatedat)');
                $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $newLiquidity, PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $this->decimalToQ64_96($newLiquidity), PDO::PARAM_STR);
                $stmt->bindValue(':updatedat', (new DateTime())->format('Y-m-d H:i:s.u'), PDO::PARAM_STR);
                $stmt->execute();
                return $newLiquidity;
            }

            // Update existing
            $newLiquidity = (float) $liquidity;
            $stmt = $this->db->prepare('UPDATE wallett SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat WHERE userid = :userid');
            $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':liquidity', $newLiquidity, PDO::PARAM_STR);
            $stmt->bindValue(':liquiditq', $this->decimalToQ64_96($newLiquidity), PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', (new DateTime())->format('Y-m-d H:i:s.u'), PDO::PARAM_STR);
            $stmt->execute();

            return $newLiquidity;
        } catch (\Throwable $e) {
            $this->logger->error('Error upserting liquidity', ['error' => $e->getMessage(), 'userid' => $userId]);
            throw new \RuntimeException('Unable to upsert wallet entry');
        }
    }

    private function decimalToQ64_96(float $value): string
    {
        $scaleFactor = \bcpow('2', '96');
        $decimalString = \number_format($value, 30, '.', '');
        return \bcmul($decimalString, $scaleFactor, 0);
    }
}

