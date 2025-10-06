<?php
declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\App\UserPreferences;
use Psr\Log\LoggerInterface;
use Fawaz\Utils\JsonHelper;

class UserPreferencesMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function loadPreferencesById(string $id): UserPreferences|false
    {
        $this->logger->debug('UserPreferencesMapper.loadPreferencesById started', ['id' => $id]);

        try {
            $stmt = $this->db->prepare(
                'SELECT userid, content_filtering_severity_level, updatedat, onboardingswereshown
                 FROM user_preferences 
                 WHERE userid = :id'
            );

            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->logger->info('User preferences loaded successfully', ['id' => $id, 'data' => $data]);
                $data['contentFilteringSeverityLevel'] = $data['content_filtering_severity_level'];
                $data['onboardingsWereShown'] = JsonHelper::decode($data['onboardingswereshown'] ?? '[]') ?? [];
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

    public function insert(UserPreferences $user): UserPreferences
    {
        $this->logger->debug("UserPreferencesMapper.insert started");

        $data = $user->getArrayCopy();

        $query = "INSERT INTO user_preferences
                  (userid, content_filtering_severity_level, updatedat, onboardingswereshown)
                  VALUES 
                  (:userid, :content_filtering_severity_level , :updatedat, :onboardings::jsonb)";
        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':content_filtering_severity_level', $data['contentFilteringSeverityLevel'], \PDO::PARAM_INT);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':onboardings', json_encode($data['onboardingsWereShown'] ?? [], JSON_UNESCAPED_UNICODE), \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new user preferences into database", ['userid' => $data['userid']]);

            return new UserPreferences($data);
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.insert: Exception occurred while inserting user preferences", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to insert UserPreferences into database: " . $e->getMessage());
        }
    }

    public function update(UserPreferences $userPreferences): UserPreferences
    {
        $this->logger->debug('UserPreferences.update started', ['userid' => $userPreferences->getUserId()]);

        $userPreferences->setUpdatedAt();
        $data = $userPreferences->getArrayCopy();
        
        try {
            $query = "UPDATE user_preferences 
                      SET content_filtering_severity_level = :content_filtering_severity_level, 
                          updatedat = :updatedat,
                          onboardingswereshown = :onboardings::jsonb
                      WHERE userid = :userid";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':content_filtering_severity_level', $data['contentFilteringSeverityLevel'], \PDO::PARAM_INT);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':onboardings', json_encode($data['onboardingsWereShown'] ?? [], JSON_UNESCAPED_UNICODE), \PDO::PARAM_STR);

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->logger->info("UserPreferences updated successfully", ['data' => $data]);
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
