<?php

namespace Fawaz\App\Repositories;

use Fawaz\App\Models\Notification;
use Fawaz\App\Models\NotificationRecipients;
use PDO;
use Psr\Log\LoggerInterface;

class NotificationRepository
{


    /**
     * Assign Notification object while instantiated
     */
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }
    
  
    /**
     * get notifications data
     */
    public function getNotification(Notification $notification)
    {
        $this->logger->info("NotificationRepository.saveNotification started");

        $query = "INSERT INTO notifications 
                  (notificationId, notificationClass, data, createdat)
                  VALUES 
                  (:notificationId, :notificationClass, :data, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':notificationId', $notification->getNotificationId(), \PDO::PARAM_STR);
            $stmt->bindValue(':notificationClass', $notification->getNotificationClass(), \PDO::PARAM_STR);
            $stmt->bindValue(':data', $notification->getData(), \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $notification->getCreatedat(), \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new notification into database");

            return $notification;
        } catch (\PDOException $e) {
            $this->logger->error(
                "NotificationRepository.saveNotification: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "NotificationRepository.saveNotification: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        }
    }


    /**
     * Save notification data
     */
    public function saveNotification(Notification $notification)
    {
        $this->logger->info("NotificationRepository.saveNotification started");

        $query = "INSERT INTO notifications 
                  (notificationId, notificationClass, data, createdat)
                  VALUES 
                  (:notificationId, :notificationClass, :data, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':notificationId', $notification->getNotificationId(), \PDO::PARAM_STR);
            $stmt->bindValue(':notificationClass', $notification->getNotificationClass(), \PDO::PARAM_STR);
            $stmt->bindValue(':data', $notification->getData(), \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $notification->getCreatedat(), \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new notification into database");

            return $notification;
        } catch (\PDOException $e) {
            $this->logger->error(
                "NotificationRepository.saveNotification: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "NotificationRepository.saveNotification: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        }
    }

    
    /**
     * Assign Receipient
     */
    public function assignReceipient(Notification $notification, NotificationRecipients $receipients)
    {
        $this->logger->info("NotificationRepository.assignReceipients started");

        $query = "INSERT INTO notification_recipients 
                  (notificationId, senderId, receiverId, createdat)
                  VALUES 
                  (:notificationId, :senderId, :receiverId, :createdat)";


        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':notificationId', $notification->getNotificationId(), \PDO::PARAM_STR);
            $stmt->bindValue(':senderId', $receipients->getSenderId(), \PDO::PARAM_STR);
            $stmt->bindValue(':receiverId', $receipients->getReceiverId(), \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $receipients->getCreatedat(), \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted Receipients into database");

            return $notification;
        } catch (\PDOException $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        }
    }

    /**
     * Save Multiple Recipients
     */
    public function assignReceipients(Notification $notification, array $receipientsList)
    {
        $this->logger->info("NotificationRepository.assignReceipients started");

        $query = "INSERT INTO notification_recipients 
                (notificationId, senderId, receiverId, createdat)
                VALUES 
                (:notificationId, :senderId, :receiverId, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            foreach ($receipientsList as $recipient) {
                if (!$recipient instanceof NotificationRecipients) {
                    throw new \InvalidArgumentException("Expected instance of NotificationRecipients.");
                }

                $stmt->bindValue(':notificationId', $notification->getNotificationId(), \PDO::PARAM_STR);
                $stmt->bindValue(':senderId', $recipient->getSenderId(), \PDO::PARAM_STR);
                $stmt->bindValue(':receiverId', $recipient->getReceiverId(), \PDO::PARAM_STR);
                $stmt->bindValue(':createdat', $recipient->getCreatedat(), \PDO::PARAM_STR);

                $stmt->execute();
            }

            $this->logger->info("Inserted multiple recipients into database");

            return $notification;
        } catch (\PDOException $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: PDOException occurred",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to insert notification recipients into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: Exception occurred",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to insert notification recipients into database: " . $e->getMessage());
        }
    }


    // REMOVE AFTER IMPLEMENTED
    // Can be called from Services or Mapper where it needed
    // public function notifyUsers(array $userIds, $senderId)
    // {
    //     $notification = new Notification();
    //     $notification->setNotificationId(uniqid('notif_'));
    //     $recipients = [];
    //     foreach ($userIds as $userId) {
    //         $recipient = new NotificationRecipients();
    //         $recipient->setSenderId($senderId);
    //         $recipient->setReceiverId($userId);
    //         $recipient->setCreatedat(date('Y-m-d H:i:s'));
    //         $recipients[] = $recipient;
    //     }
    //     $this->notificationRepository->assignReceipients($notification, $recipients);
    // }

    
    /**
     * Read a particular notification
     */
    public function readNotification(NotificationRecipients $receipients)
    {
        $this->logger->info("NotificationRepository.assignReceipients started");

        $query = "UPDATE notification_recipients SET isRead = :isRead WHERE notificationId = :notificationId";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':isRead', 1, \PDO::PARAM_INT);
            $stmt->bindValue(':notificationId', $receipients->getNotificationId(), \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted Receipients into database");

            return $receipients;
        } catch (\PDOException $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "NotificationRepository.assignReceipients: Exception occurred while inserting notification",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert notification into database: " . $e->getMessage());
        }
    }

}