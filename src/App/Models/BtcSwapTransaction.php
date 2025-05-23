<?php

namespace Fawaz\App\Models;


class BtcSwapTransaction
{
    protected string $swapId;
    protected string $transUniqueId;
    protected string $userId;
    protected string $btcAddress;
    protected string $transactionType;
    protected string $tokenAmount;
    protected string $btcAmount;
    protected ?string $message;
    protected ?string $status;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [])
    {
        $this->swapId = $data['swapId'] ?? self::generateUUID();
        $this->transUniqueId = $data['transUniqueId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->btcAddress = $data['btcAddress'] ?? null;
        $this->transactionType = $data['transactionType'] ?? null;
        $this->tokenAmount = $data['tokenAmount'] ?? null;
        $this->btcAmount = $data['btcAmount'] ?? null;
        $this->status = $data['status'] ?? 'PENDING';
        $this->message = $data['message'] ?? null;
        $this->createdat = $data['createdat'] ?? date('Y-m-d H:i:s.u');
    }
    
    /**
     * Generate UUID
     */ 
    private static function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    /**
     * Getter and Setter methods for swapId
     */
    public function getSwapId(): string
    {
        return $this->swapId;
    }
    public function setSwapId(string $swapId): void
    {
        $this->swapId = $swapId;
    }

    
    /**
     * Getter and Setter methods for transUniqueId
     */
    public function getTransUniqueId(): string
    {
        return $this->transUniqueId;
    }
    public function setTransUniqueId(string $transUniqueId): void
    {
        $this->transUniqueId = $transUniqueId;
    }


    /**
     * Getter and Setter methods for userId
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }


    
    /**
     * Getter and Setter methods for btcAddress
     */
    public function getBtcAddress(): string
    {
        return $this->btcAddress;
    }
    public function setBtcAddress(string $btcAddress): void
    {
        $this->btcAddress = $btcAddress;
    }


    /**
     * Getter and Setter methods for status
     */
    public function getStatus(): string|null
    {
        return $this->status;
    }
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }


    /**
     * Getter and Setter methods for transactionType
     */
    public function getTransactionType(): string|null
    {
        return $this->transactionType;
    }
    public function setTransactionType(string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    
    /**
     * Getter and Setter methods for tokenAmount
     */
    public function getTokenAmount(): float
    {
        return $this->tokenAmount;
    }
    public function setTokenAmount(float $tokenAmount): void
    {
        $this->tokenAmount = $tokenAmount;
    }
    
        
    /**
     * Getter and Setter methods for btcAmount
     */
    public function getBtcAmount(): float
    {
        return $this->btcAmount;
    }
    public function setBtcAmount(float $btcAmount): void
    {
        $this->btcAmount = $btcAmount;
    }
    

    /**
     * Getter and Setter methods for message
     */
    public function getMessage(): string|null
    {
        return $this->message;
    }
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Getter and Setter methods for createdat
     */
    public function getCreatedat(): string
    {
        return $this->createdat;
    }
    public function setCreatedat(string $createdat): void
    {
        $this->createdat = $createdat;
    }

}