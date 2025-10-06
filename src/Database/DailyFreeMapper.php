<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\DailyFree;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DailyFreeMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function insert(DailyFree $user): DailyFree|false
    {
        $this->logger->debug("DailyFree.insert started");

        try {
            $data = $user->getArrayCopy();

            $query = "INSERT INTO dailyfree (userid, liken, comments, posten, createdat) VALUES (:userid, :liken, :comments, :posten, :createdat)";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':liken', $data['liken'], \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $data['comments'], \PDO::PARAM_INT);
            $stmt->bindValue(':posten', $data['posten'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);
            $stmt->execute();

            $this->logger->info("Inserted new record into database", ['record' => $data]);

            return new DailyFree($data);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23505') {
                $this->logger->warning("Duplicate record detected", [
                    'userid' => $user->getArrayCopy()['userid'],
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            $this->logger->error("Error inserting record into database", [
                'error' => $e->getMessage(),
                'data' => $user->getArrayCopy(),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during record creation", [
                'error' => $e->getMessage(),
                'data' => $user->getArrayCopy(),
            ]);
            return false;
        }
    }

    public function update(DailyFree $user): DailyFree|false
    {
        $this->logger->debug("DailyFree.update started");

        try {
            $data = $user->getArrayCopy();

            $query = "UPDATE dailyfree SET liken = :liken, comments = :comments, posten = :posten, createdat = :createdat WHERE userid = :userid";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':liken', $data['liken'], \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $data['comments'], \PDO::PARAM_INT);
            $stmt->bindValue(':posten', $data['posten'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("User updated successfully in database", ['user' => $data]);

            return new DailyFree($data);
        } catch (\PDOException $e) {
            $this->logger->error("Database error during user update", [
                'error' => $e->getMessage(),
                'user' => $user->getArrayCopy(),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during user update", [
                'error' => $e->getMessage(),
                'user' => $user->getArrayCopy(),
            ]);
            return false;
        }
    }

    public function getUserDailyUsage(string $userId, int $artType): int
    {
        $columnMap = [
            LIKE_ => 'liken',
            COMMENT_ => 'comments',
            POST_ => 'posten',
        ];

        $column = $columnMap[$artType] ?? null;

        if ($column === null) {
            throw new InvalidArgumentException('Invalid art type provided.');
        }

        try {
            $query = "SELECT COALESCE($column, 0) AS usage
                      FROM dailyfree 
                      WHERE userid = :userId 
                      AND createdat::date = CURRENT_DATE";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int)($result['usage'] ?? 0);

        } catch (\PDOException $e) {
            $this->logger->error('Database error in getUserDailyUsage', ['exception' => $e->getMessage()]);

            return 0;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in getUserDailyUsage', ['exception' => $e->getMessage()]);
            return 0;
        }
    }

    public function getUserDailyAvailability(string $userId): array
    {
        $this->logger->debug('DailyFreeMapper.getUserDailyAvailability started', ['userId' => $userId]);

        $dailyLimits = [
            'liken' => 3,
            'comments' => 4,
            'posten' => 1,
        ];

        $columnMap = [
            'liken' => 'Likes',
            'comments' => 'Comments',
            'posten' => 'Posts',
        ];

        try {
            $query = "SELECT liken, comments, posten 
                      FROM dailyfree 
                      WHERE userid = :userId 
                      AND createdat::date = CURRENT_DATE";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return array_map(fn ($column, $label) => [
                    'name' => $label,
                    'used' => 0,
                    'available' => $dailyLimits[$column],
                ], array_keys($columnMap), $columnMap);
            }

            return array_map(fn ($column, $label) => [
                'name' => $label,
                'used' => (int)($result[$column] ?? 0),
                'available' => max($dailyLimits[$column] - (int)($result[$column] ?? 0), 0),
            ], array_keys($columnMap), $columnMap);
        } catch (\PDOException $e) {
            $this->logger->error('Database error in getUserDailyAvailability', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return [];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in getUserDailyAvailability', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function incrementUserDailyUsage(string $userId, int $artType): bool
    {
        $this->logger->debug('DailyFreeMapper.incrementUserDailyUsage started', ['userId' => $userId, 'artType' => $artType]);

        $columnMap = [
            LIKE_ => 'liken',
            COMMENT_ => 'comments',
            POST_ => 'posten',
        ];

        if (!isset($columnMap[$artType])) {
            $this->logger->error('Invalid art type provided', ['artType' => $artType]);
            throw new InvalidArgumentException('Invalid art type provided.');
        }

        $column = $columnMap[$artType];

        try {

            $query = "
                INSERT INTO dailyfree (userid, liken, comments, posten, createdat)
                VALUES (:userId, :liken, :comments, :posten, NOW())
                ON CONFLICT (userid) 
                DO UPDATE 
                SET 
                    liken = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.liken ELSE 0 END + :liken,
                    comments = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.comments ELSE 0 END + :comments,
                    posten = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.posten ELSE 0 END + :posten,
                    createdat = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.createdat ELSE NOW() END
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':liken', $artType === LIKE_ ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $artType === COMMENT_ ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue(':posten', $artType === POST_ ? 1 : 0, \PDO::PARAM_INT);

            $success = $stmt->execute();

            return $success;
        } catch (\PDOException $e) {
            $this->logger->error('Database error in incrementUserDailyUsage', ['exception' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in incrementUserDailyUsage', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function incrementUserDailyUsagee(string $userId, int $artType): bool
    {
        $columnMap = [
            LIKE_ => 'liken',
            COMMENT_ => 'comments',
            POST_ => 'posten',
        ];

        $column = $columnMap[$artType] ?? null;
        if ($column === null) {
            throw new InvalidArgumentException('Invalid action type provided.');
        }

        try {

            $updateQuery = "
                INSERT INTO dailyfree (userid, liken, comments, posten, createdat)
                VALUES (:userId, :liken, :comments, :posten, NOW())
                ON CONFLICT (userid) 
                DO UPDATE SET 
                    liken = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.liken + :liken ELSE :liken END,
                    comments = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.comments + :comments ELSE :comments END,
                    posten = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.posten + :posten ELSE :posten END,
                    createdat = CASE WHEN dailyfree.createdat::date = CURRENT_DATE THEN dailyfree.createdat ELSE NOW() END
                WHERE dailyfree.userid = :userId
            ";

            $stmt = $this->db->prepare($updateQuery);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':liken', $artType === LIKE_ ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $artType === COMMENT_ ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue(':posten', $artType === POST_ ? 1 : 0, \PDO::PARAM_INT);

            return $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('Error incrementing user daily usage', [
                'userId' => $userId,
                'artType' => $artType,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
