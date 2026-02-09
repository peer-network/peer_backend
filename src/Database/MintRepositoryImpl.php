<?php

declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Repositories\MintAccountRepositoryImpl;

class MintRepositoryImpl implements MintRepository
{
    use ResponseHelper;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PDO $db,
        protected MintAccountRepositoryImpl $mintAccountRepository,
    ) {
    }

    public function insertMint(string $mintId, string $day, string $gemsInTokenRatio): void
    {
        $this->logger->debug('MintRepositoryImpl.insertMint started', [
            'mintId' => $mintId,
            'day' => $day,
            'gemsInTokenRatio' => $gemsInTokenRatio,
        ]);

        $sql = 'INSERT INTO mints (mintid, day, gems_in_token_ratio) VALUES (:mintid, :day, :ratio)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mintid', $mintId, PDO::PARAM_STR);
        $stmt->bindValue(':day', $day, PDO::PARAM_STR);
        $stmt->bindValue(':ratio', $gemsInTokenRatio, PDO::PARAM_STR);
        $stmt->execute();

        $this->logger->info('MintRepositoryImpl.insertMint succeeded', [
            'mintId' => $mintId,
            'day' => $day,
            'ratio' => $gemsInTokenRatio
        ]);
    }

    /**
     * Get a mint row for a concrete date (YYYY-MM-DD) if it exists.
     *
     * @param string $dateYYYYMMDD Date in ISO format (e.g., 2025-12-03)
     * @return array|null Associative row of mint data or null if none
     */
    public function getMintForDate(string $dateYYYYMMDD): ?array
    {
        $this->logger->debug('MintRepositoryImpl.getMintForDate started', [
            'day' => $dateYYYYMMDD,
        ]);

        $sql = 'SELECT mintid, day, gems_in_token_ratio FROM mints WHERE day = :day LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':day', $dateYYYYMMDD, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Resolve a day token (e.g., D0..D7) to a concrete date and fetch its mint.
     *
     * Supported tokens: D0..D7. Other tokens are not supported for a single-day mint lookup.
     *
     * @param string $day Day token (default 'D0')
     * @return array|null Associative row of mint data or null if none
     */
    public function getMintForDay(string $day = 'D0'): ?array
    {
        $supported = ['D0','D1','D2','D3','D4','D5','D6','D7'];
        if (!in_array($day, $supported, true)) {
            throw new \InvalidArgumentException('Unsupported day token for single-day lookup');
        }

        // Convert token to date string using the database to ensure timezone parity
        $offset = (int) substr($day, 1);
        $sql = "SELECT TO_CHAR((CURRENT_DATE - INTERVAL '$offset day'), 'YYYY-MM-DD') AS d";
        $stmt = $this->db->query($sql);
        $date = $stmt->fetchColumn();
        if (!is_string($date) || $date === '') {
            return null;
        }

        return $this->getMintForDate($date);
    }
}
