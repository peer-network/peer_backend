<?php

namespace Fawaz\App\Repositories;

use Fawaz\App\Models\BtcSwapTransaction;
use PDO;
use Psr\Log\LoggerInterface;

class BtcSwapTransactionRepository
{

    /**
     * Assign BtcSwapTransaction object while instantiated
     */
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
        
    }

    /**
     * Save transaction data
     */
    public function saveTransaction(BtcSwapTransaction $transaction)
    {
        $this->logger->info("TransactionRepository.saveTransaction started");

        $query = "INSERT INTO btc_swap_transactions 
                  (swapid, transuniqueid, transactiontype, userid, btcaddress,  tokenamount, btcamount, status, message, createdat)
                  VALUES 
                  (:swapId, :transUniqueId, :transactionType, :userId, :btcAddress, :tokenAmount, :btcAmount, :status, :message, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':swapId', $transaction->getSwapId(), \PDO::PARAM_STR);
            $stmt->bindValue(':transUniqueId', $transaction->getTransUniqueId(), \PDO::PARAM_STR);
            $stmt->bindValue(':transactionType', $transaction->getTransactionType(), \PDO::PARAM_STR);
            $stmt->bindValue(':userId', $transaction->getUserId(), \PDO::PARAM_STR);
            $stmt->bindValue(':btcAddress', $transaction->getBtcAddress(), \PDO::PARAM_STR);
            $stmt->bindValue(':tokenAmount', $transaction->getTokenAmount(), \PDO::PARAM_STR);
            $stmt->bindValue(':btcAmount', $transaction->getBtcAmount(), \PDO::PARAM_STR);
            $stmt->bindValue(':status', $transaction->getStatus(), \PDO::PARAM_STR);
            $stmt->bindValue(':message', $transaction->getMessage(), \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $transaction->getCreatedat(), \PDO::PARAM_STR);
            $stmt->execute();

            $this->logger->info("Inserted new transaction into database");

            return $transaction;
        } catch (\PDOException $e) {
            $this->logger->error(
                "TransactionRepository.saveTransaction: Exception occurred while inserting transaction",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to insert transaction into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "TransactionRepository.saveTransaction: Exception occurred while inserting transaction",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to insert transaction into database: " . $e->getMessage());
        }
    }

    
}