<?php

namespace Fawaz\App\Repositories;

use Fawaz\App\Models\Transaction;
use PDO;
use Fawaz\Utils\PeerLoggerInterface;

class TransactionRepository
{

    /**
     * Assign Transaction object while instantiated
     */
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }
    

    /**
     * Save transaction data
     */
    public function saveTransaction(Transaction $transaction)
    {
        $this->logger->debug("TransactionRepository.saveTransaction started");

        $query = "INSERT INTO transactions 
                  (transactionid, operationid, transactiontype, senderid, recipientid, tokenamount, transferaction, message, createdat)
                  VALUES 
                  (:transactionId, :operationId, :transactionType, :senderId, :recipientId, :tokenAmount, :transferAction, :message, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':transactionId', $transaction->getTransactionId(), \PDO::PARAM_STR);
            $stmt->bindValue(':operationId', $transaction->getOperationId(), \PDO::PARAM_STR);
            $stmt->bindValue(':transactionType', $transaction->getTransactionType(), \PDO::PARAM_STR);
            $stmt->bindValue(':senderId', $transaction->getSenderId(), \PDO::PARAM_STR);
            $stmt->bindValue(':recipientId', $transaction->getRecipientId(), \PDO::PARAM_STR);
            $stmt->bindValue(':tokenAmount', $transaction->getTokenAmount(), \PDO::PARAM_STR);
            $stmt->bindValue(':transferAction', $transaction->geTtransferAction(), \PDO::PARAM_STR);
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