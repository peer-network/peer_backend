<?php

namespace Fawaz\App\Models;

class NotificationRecipients
{
    protected string $notificationId;
    protected string $senderId;
    protected string $receiverId;
    protected int $isRead;
    protected string $createdat;


    /**
     * Assign NotificationRecipients object while instantiated
     */
    public function __construct(array $data = [])
    {
        $this->notificationId = $data['notificationId'] ?? null;
        $this->senderId = $data['senderId'] ?? null;
        $this->receiverId = $data['receiverId'] ?? null;
        $this->isRead = $data['isRead'] ?? null;
        $this->createdat = $data['createdat'] ?? date('Y-m-d H:i:s.u');
    }

     
    /**
     * Getter and Setter methods for notificationId
     */
    public function getNotificationId(): string
    {
        return $this->notificationId;
    }
    public function setNotificationId(string $notificationId): void
    {
        $this->notificationId = $notificationId;
    }


    /**
     * Getter and Setter methods for senderId
     */
    public function getSenderId(): string
    {
        return $this->senderId;
    }
    public function setSenderId(string $senderId): void
    {
        $this->senderId = $senderId;
    }

    
    /**
     * Getter and Setter methods for receiverId
     */
    public function getReceiverId(): string
    {
        return $this->receiverId;
    }
    public function setReceiverId(string $receiverId): void
    {
        $this->receiverId = $receiverId;
    }

    /**
     * Getter and Setter methods for isRead
     */
    public function getIsRead(): int
    {
        return $this->isRead;
    }
    public function setIsRead(int $isRead): void
    {
        $this->isRead = $isRead;
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