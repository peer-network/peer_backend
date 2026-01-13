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
    ){}

    /**
     * Determine if a mint was performed for a specific day action.
     *
     * Day actions supported (same semantics as getTimeSortedMatch):
     *  - D0..D7: specific day offsets from today
     *  - W0: current week
     *  - M0: current month
     *  - Y0: current year
     *
     * Returns true if at least one transaction with
     * transactiontype = 'transferMintAccountToRecipient' exists for that period
     * where the sender is the MintAccount.
     */
    public function mintWasPerformedForDay(string $dayAction): bool
    {
        // Resolve the time window condition for transactions.createdat
        $dayOptionsRaw = [
            'D0' => "createdat::date = CURRENT_DATE",
            'D1' => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            'D2' => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            'D3' => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            'D4' => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            'D5' => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            'D6' => "createdat::date = CURRENT_DATE - INTERVAL '6 day'",
            'D7' => "createdat::date = CURRENT_DATE - INTERVAL '7 day'",
            'W0' => "DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
            'M0' => "TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
            'Y0' => "EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
        ];

        if (!array_key_exists($dayAction, $dayOptionsRaw)) {
            throw new \InvalidArgumentException('Invalid dayAction');
        }

        // Identify the MintAccount (sender of mint transactions)
        $mintAccount = $this->mintAccountRepository->getDefaultAccount();
        if ($mintAccount === null) {
            throw new \RuntimeException('No MintAccount available');
        }

        $whereCondition = $dayOptionsRaw[$dayAction];
        $sql = "SELECT 1 FROM transactions
                WHERE senderid = :sender
                  AND transactiontype = 'transferMintAccountToRecipient'
                  AND $whereCondition
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sender', $mintAccount->getWalletId(), \PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
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
