<?php

declare(strict_types=1);

namespace Fawaz\Database;


use PDO;
use Fawaz\App\DTO\MintLogItem;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Repositories\MintAccountRepository;

const TABLESTOGEMS = true;

class MintRepositoryImpl implements MintRepository
{
    use ResponseHelper;
    private string $burnWallet;
    private string $peerWallet;

    public function __construct(
        protected PeerLoggerInterface $logger, 
        protected PDO $db, 
        protected MintAccountRepository $mintAccountRepository,
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

    public function insertMintLog(MintLogItem $item): void
    {
        $this->insertMintLogs([$item]);
    }

    public function insertMintLogs(array $items): void
    {
        \ignore_user_abort(true);

        $this->logger->debug('MintRepositoryImpl.insertMintLogs started', ['count' => count($items)]);

        if (empty($items)) {
            return;
        }

        $sqlWithCreated = 'INSERT INTO mint_info (gemid, operationid, transactionid, tokenamount, createdat) VALUES (:gemid, :operationid, :transactionid, :tokenamount, :createdat)';
        
        $stmtWith = $this->db->prepare($sqlWithCreated);

        foreach ($items as $item) {
            if (!$item instanceof MintLogItem) {
                throw new \InvalidArgumentException('All items must be instances of MintLogItem');
            }

            $stmt = $stmtWith;

            $stmt->bindValue(':gemid', $item->gemid, PDO::PARAM_STR);
            $stmt->bindValue(':operationid', $item->operationid, PDO::PARAM_STR);
            $stmt->bindValue(':transactionid', $item->transactionid, PDO::PARAM_STR);
            $stmt->bindValue(':tokenamount', $item->tokenamount, PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $item->createdat ?? (new \DateTime())->format('Y-m-d H:i:s.u'), PDO::PARAM_STR);

            $stmt->execute();
        }

        $this->logger->info('MintRepositoryImpl.insertMintLogs succeeded', [
            'count' => count($items),
        ]);
    }
}
