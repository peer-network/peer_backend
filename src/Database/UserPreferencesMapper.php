<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\User;
use Fawaz\App\UserPreferences;
use Psr\Log\LoggerInterface;

class UserPreferencesMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userId, string $currentUserId): bool
    {
        return $userId === $currentUserId;
    }

    public function loadPreferencesById(string $id): UserPreferences|false
    {
        $this->logger->info('UserPreferencesMapper.loadPreferencesById started', ['id' => $id]);

        try {
            $stmt = $this->db->prepare(
                'SELECT userid, contentFilteringSeverityLevel, updatedat 
                 FROM user_preferences 
                 WHERE userid = :id'
            );

            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                $this->logger->info('User preferences loaded successfully', ['id' => $id, 'data' => $data]);
                return new UserPreferences($data);
            } else {
                $this->logger->warning("No user found with given ID", ['id' => $id]);
                return false;
            }
        } catch (\PDOException $e) {
            $this->logger->error("Database error in loadPreferencesById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in loadPreferencesById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function update(UserPreferences $userPreferences): UserPreferences
    {
        $this->logger->info('UserPreferences.update started', ['userid' => $userPreferences->getUserId()]);

        try {
            $userPreferences->setUpdatedAt();
            $data = $userPreferences->getArrayCopy();

            $query = "UPDATE user_preferences 
                      SET contentFilteringSeverityLevel = :contentFilteringSeverityLevel, 
                          updatedat = :updatedat 
                      WHERE userid = :userid";

            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':contentFilteringSeverityLevel', $data['contentFilteringSeverityLevel'], \PDO::PARAM_INT);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->logger->info("UserPreferences updated successfully", ['userid' => $data['userid']]);
            } else {
                $this->logger->warning("No changes made to UserPreferences", ['userid' => $data['userid']]);
            }

            return new UserPreferences($data);
        } catch (\PDOException $e) {
            $this->logger->error("Database error in update", [
                'userid' => $data['userid'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in update", [
                'userid' => $data['userid'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
