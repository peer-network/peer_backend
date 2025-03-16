<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\DailyFree;
use Psr\Log\LoggerInterface;

class DailyFreeMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("DailyFreeMapper.fetchAll started");

        $sql = "SELECT * FROM dailyfree ORDER BY createdat DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new DailyFree($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched dailyfree successfully" : "No dailyfree found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching dailyfree from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadById(string $userid): DailyFree|false
    {
        $this->logger->info("DailyFreeMapper.loadById started");

        $sql = "SELECT * FROM dailyfree WHERE userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            $this->logger->info("User found with userid", ['data' => $data]);
            return new DailyFree($data);
        }

        $this->logger->warning("No user found with userid", ['userid' => $userid]);

        try {
            $dailyData = [
                'userid' => $userid,
                'liken' => 0,
                'comments' => 0,
                'posten' => 0,
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u')
            ];

            $newUser = new DailyFree($dailyData);
            $insertedUser = $this->insert($newUser);

            if ($insertedUser === false) {
                $this->logger->error("Failed to create new user", ['userid' => $userid]);
                return false;
            }

            $this->logger->info("New user created successfully", ['userid' => $userid]);
            return $insertedUser;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during user creation", [
                'userid' => $userid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function insert(DailyFree $user): DailyFree|false
    {
        $this->logger->info("DailyFree.insert started");

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
        $this->logger->info("DailyFree.update started");

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

    public function delete(string $userid): bool
    {
        $this->logger->info("DailyFree.delete started");

        try {
            $query = "DELETE FROM dailyfree WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();

            $deleted = (bool)$stmt->rowCount();

            if ($deleted) {
                $this->logger->info("User successfully deleted from database", ['userid' => $userid]);
            } else {
                $this->logger->warning("No user found to delete in database", ['userid' => $userid]);
            }

            return $deleted;
        } catch (\PDOException $e) {
            $this->logger->error("Database error during user deletion", [
                'error' => $e->getMessage(),
                'userid' => $userid
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during user deletion", [
                'error' => $e->getMessage(),
                'userid' => $userid
            ]);
            return false;
        }
    }

    public function getUserDailyUsage(string $userId, string $artType): int
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

        $query = "SELECT {$column} 
                  FROM dailyfree 
                  WHERE userid = :userId 
                  AND createdat::date = CURRENT_DATE";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int)($result[$column] ?? 0);
    }

    public function getUserDailyUsageWithColumnNames(string $userId): array
    {
        $columnMap = [
            'liken' => 'Likes',
            'comments' => 'Comments',
            'posten' => 'Posts',
        ];

        $query = "SELECT liken, comments, posten 
                  FROM dailyfree 
                  WHERE userid = :userId 
                  AND createdat::date = CURRENT_DATE";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return array_map(fn($label) => ['name' => $label, 'value' => 0], $columnMap);
        }

        return array_map(fn($column, $label) => [
            'name' => $label,
            'value' => (int)($result[$column] ?? 0),
        ], array_keys($columnMap), $columnMap);
    }

    public function getUserDailyAvailability(string $userId): array
    {
        $dailyLimits = [
            'liken' => 3,      // Daily limit for likes
            'comments' => 4,   // Daily limit for comments
            'posten' => 1,     // Daily limit for posts
        ];

        $columnMap = [
            'liken' => 'Likes',
            'comments' => 'Comments',
            'posten' => 'Posts',
        ];

        $query = "SELECT liken, comments, posten 
                  FROM dailyfree 
                  WHERE userid = :userId 
                  AND createdat::date = CURRENT_DATE";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $userId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return array_map(fn($column, $label) => [
                'name' => $label,
                'used' => 0,
                'available' => $dailyLimits[$column],
            ], array_keys($columnMap), $columnMap);
        }

        return array_map(fn($column, $label) => [
            'name' => $label,
            'used' => (int)($result[$column] ?? 0),
            'available' => max($dailyLimits[$column] - (int)($result[$column] ?? 0), 0),
        ], array_keys($columnMap), $columnMap);
    }

    public function incrementUserDailyUsage(string $userId, string $artType): bool
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
            $existsQuery = "SELECT createdat::date 
                            FROM dailyfree 
                            WHERE userid = :userId";

            $existsStmt = $this->db->prepare($existsQuery);
            $existsStmt->execute(['userId' => $userId]);

            $result = $existsStmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                $recordDate = $result['createdat'];
                $isToday = $recordDate === date('Y-m-d');

                if ($isToday) {
                    $updateQuery = "UPDATE dailyfree 
                                    SET $column = $column + 1 
                                    WHERE userid = :userId 
                                    AND createdat::date = CURRENT_DATE";

                    $updateStmt = $this->db->prepare($updateQuery);
                    return $updateStmt->execute(['userId' => $userId]);
                } else {
                    $resetQuery = "UPDATE dailyfree 
                                   SET liken = :liken, comments = :comments, posten = :posten, createdat = NOW()
                                   WHERE userid = :userId";

                    $resetParams = [
                        'liken' => $artType === LIKE_ ? 1 : 0,
                        'comments' => $artType === COMMENT_ ? 1 : 0,
                        'posten' => $artType === POST_ ? 1 : 0,
                        'userId' => $userId,
                    ];

                    $resetStmt = $this->db->prepare($resetQuery);
                    return $resetStmt->execute($resetParams);
                }
            } else {
                $insertQuery = "INSERT INTO dailyfree (userid, liken, comments, posten, createdat)
                                VALUES (:userId, :liken, :comments, :posten, NOW())";

                $insertParams = [
                    'userId' => $userId,
                    'liken' => $artType === LIKE_ ? 1 : 0,
                    'comments' => $artType === COMMENT_ ? 1 : 0,
                    'posten' => $artType === POST_ ? 1 : 0,
                ];

                $insertStmt = $this->db->prepare($insertQuery);
                return $insertStmt->execute($insertParams);
            }
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
