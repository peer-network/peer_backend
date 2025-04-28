<?php

namespace Fawaz\App\Models;

use PDO;

class Notification
{
    protected string $notificationId;
    protected string $notificationClass;
    protected ?string $data = null;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [])
    {
        $this->notificationId = $data['notificationId'] ?? self::generateUUID();
        $this->notificationClass = $data['notificationClass'] ?? null;
        $this->data = $data['data'] ?? null;
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
     * Getter and Setter methods for notificationClass
     */
    public function getNotificationClass(): string
    {
        return $this->notificationClass;
    }
    public function setNotificationClass(string $notificationClass): void
    {
        $this->notificationClass = $notificationClass;
    }

    /**
     * Getter and Setter methods for data
     * 
     * It needs to be converted into serialize or json_encode
     */
    public function getData(): array
    {
        return unserialize($this->data);
    }
    public function setData(array $data): void
    {
        $this->data = serialize($data);
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