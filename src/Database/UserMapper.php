<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\User;
use Fawaz\App\UserInfo;
use Fawaz\App\Profile;
use Fawaz\App\ProfilUser;
use Fawaz\App\UserAdvanced;
use Psr\Log\LoggerInterface;

class UserMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function logLoginData(string $userId, ?string $actionType = 'login'): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $actionType = $actionType ?? 'login';

            $sql = "INSERT INTO logdata (userid, ip, browser, action_type) VALUES (:userid, :ip, :browser, :action_type)";
            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
            $stmt->bindValue(':browser', $browser, \PDO::PARAM_STR);
            $stmt->bindValue(':action_type', $actionType, \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Login data successfully logged', [
                'userId' => $userId,
                'ip' => $ip,
                'browser' => $browser,
                'actionType' => $actionType
            ]);

        } catch (\PDOException $e) {
            $this->logger->error('Database error occurred while logging login data', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip,
                'browser' => $browser,
                'actionType' => $actionType
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error occurred while logging login data', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip,
                'browser' => $browser,
                'actionType' => $actionType
            ]);
        }
    }

    public function logLoginDaten(string $userId, ?string $actionType = 'login'): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $url = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
            $statusCode = http_response_code();
            $responseTime = round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2);
            $location = $this->getLocationFromIP($ip) ?? 'unknown';
            $actionType = $actionType ?? 'login';

            $rawInput = \file_get_contents('php://input');
            $input = \json_decode($rawInput, true);

            $requestPayload = null;
            if ($input !== null) {
                try {
                    $requestPayload = json_encode($input, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $this->logger->error('JSON encoding error during request payload processing', [
                        'error' => $e->getMessage(),
                        'rawInput' => $rawInput
                    ]);
                }
            }

            $authStatus = 'success';

            $sql = "
                INSERT INTO logdaten 
                (userid, ip, browser, url, http_method, status_code, response_time, location, action_type, request_payload, auth_status)
                VALUES 
                (:userid, :ip, :browser, :url, :http_method, :status_code, :response_time, :location, :action_type, :request_payload, :auth_status)
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
            $stmt->bindValue(':browser', $browser, \PDO::PARAM_STR);
            $stmt->bindValue(':url', $url, \PDO::PARAM_STR);
            $stmt->bindValue(':http_method', $httpMethod, \PDO::PARAM_STR);
            $stmt->bindValue(':status_code', $statusCode, \PDO::PARAM_INT);
            $stmt->bindValue(':response_time', $responseTime, \PDO::PARAM_INT);
            $stmt->bindValue(':location', $location, \PDO::PARAM_STR);
            $stmt->bindValue(':action_type', $actionType, \PDO::PARAM_STR);
            $stmt->bindValue(':request_payload', $requestPayload, \PDO::PARAM_STR);
            $stmt->bindValue(':auth_status', $authStatus, \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Login data successfully logged', [
                'userId' => $userId,
                'ip' => $ip,
                'browser' => $browser,
                'actionType' => $actionType
            ]);

        } catch (\PDOException $e) {
            $this->logger->error('Database error occurred while logging login data', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip,
                'url' => $url
            ]);
        } catch (\JsonException $e) {
            $this->logger->error('JSON encoding error occurred while logging login data', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            $this->logger->error('An unexpected error occurred while logging login data', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip,
                'url' => $url
            ]);
        }
    }

    private function getLocationFromIP(string $ip): ?string
    {
        $url = "http://ip-api.com/json/{$ip}";

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->error('Failed to get location from IP, HTTP error', [
                    'ip' => $ip,
                    'httpCode' => $httpCode
                ]);
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && $data['status'] === 'success') {
                return "{$data['city']}, {$data['country']}";
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get location from IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function fetchAll(array $args = [], ?string $currentUserId = null): array
    {
        $this->logger->info("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $sql = "
            SELECT 
                uid,
                email,
                username,
                password,
                status,
                verified,
                slug,
                roles_mask,
                ip,
                img,
                biography,
                createdat,
                updatedat
            FROM 
                users
            WHERE 
                verified = :verified
        ";

        $conditions = [];
        $queryParams = [':verified' => 1];

        foreach ($args as $field => $value) {
            if (in_array($field, ['uid', 'email', 'status', 'verified', 'ip'], true)) {
                $conditions[] = "u.$field = :$field";
                $queryParams[":$field"] = $value;
            }

            if ($field === 'username') {
                $conditions[] = "username ILIKE :username";
                $queryParams[':username'] = '%' . $value . '%';
            }
        }

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY uid LIMIT :limit OFFSET :offset";
        $queryParams[':limit'] = $limit;
        $queryParams[':offset'] = $offset;

        $this->logger->info("Executing SQL query", ['sql' => $sql, 'params' => $queryParams]);

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($queryParams as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue($key, $value, \PDO::PARAM_STR);
                }
            }

            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->logger->info("UserMapper.fetchAll.row started");
                try {
                    $results[] = new User([
                        'uid' => $row['uid'],
                        'email' => $row['email'],
                        'username' => $row['username'],
                        'password' => $row['password'],
                        'status' => $row['status'],
                        'verified' => $row['verified'],
                        'slug' => $row['slug'],
                        'roles_mask' => $row['roles_mask'],
                        'ip' => $row['ip'],
                        'img' => $row['img'],
                        'biography' => $row['biography'],
                        'createdat' => $row['createdat'],
                        'updatedat' => $row['updatedat'],
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
                }
            }

            if ($results) {
                $this->logger->info("Fetched all users from database", ['count' => count($results)]);
            } else {
                $this->logger->warning("No users found in database");
            }

            return $results;

        } catch (\PDOException $e) {
            $this->logger->error("Database error occurred", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function fetchAllAdvance(array $args = [], ?string $currentUserId = null): array
    {
        $this->logger->info("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $trendlimit = 4;
        $trenddays = 7;

        $sql = "
            SELECT 
                u.uid,
                u.email,
                u.username,
                u.password,
                u.status,
                u.verified,
                u.slug,
                u.roles_mask,
                u.ip,
                u.img,
                u.biography,
                u.createdat,
                u.updatedat,
                COALESCE((
                    SELECT COUNT(p.postid)
                    FROM posts p
                    WHERE p.userid = u.uid
                ), 0) AS amountposts,
                COALESCE((
                    SELECT COUNT(*) 
                    FROM post_info pi 
                    WHERE pi.userid = u.uid 
                      AND pi.likes > :trendlimit 
                      AND pi.createdat >= NOW() - INTERVAL '7 days'
                ), 0) AS amounttrending,
                CASE WHEN f1.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowed,
                CASE WHEN f2.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowing,
                COALESCE((
                    SELECT COUNT(*)
                    FROM follows fr
                    WHERE fr.followerid = u.uid
                ), 0) AS amountfollowed,
                COALESCE((
                    SELECT COUNT(*)
                    FROM follows fd
                    WHERE fd.followedid = u.uid
                ), 0) AS amountfollower,
                COALESCE((
                    SELECT SUM(numbers)
                    FROM wallet w
                    WHERE w.userid = u.uid
                ), 0) AS liquidity
            FROM 
                users u
            LEFT JOIN 
                follows f1 ON u.uid = f1.followerid AND f1.followedid = :currentUserId -- Is the current user followed by this user?
            LEFT JOIN 
                follows f2 ON u.uid = f2.followedid AND f2.followerid = :currentUserId -- Is the current user following this user?
            WHERE 
                u.verified = :verified
        ";

        $conditions = [];
        $queryParams = [':verified' => 1, ':currentUserId' => $currentUserId];

        foreach ($args as $field => $value) {
            if (in_array($field, ['uid', 'email', 'status', 'verified', 'ip'], true)) {
                $conditions[] = "u.$field = :$field";
                $queryParams[":$field"] = $value;
            }

            if ($field === 'username') {
                $conditions[] = "u.username ILIKE :username";
                $queryParams[':username'] = '%' . $value . '%';
            }
        }

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY u.uid LIMIT :limit OFFSET :offset";
        $queryParams[':limit'] = $limit;
        $queryParams[':offset'] = $offset;
        $queryParams[':trendlimit'] = $trendlimit;

        $this->logger->info("Executing SQL query", ['sql' => $sql, 'params' => $queryParams]);

        try {
            $stmt = $this->db->prepare($sql);
            
            foreach ($queryParams as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue($key, $value, \PDO::PARAM_STR);
                }
            }

            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->logger->info("UserMapper.fetchAll.row started");
                try {
                    $results[] = new UserAdvanced([
                        'uid' => $row['uid'],
                        'email' => $row['email'],
                        'username' => $row['username'],
                        'password' => $row['password'],
                        'status' => $row['status'],
                        'verified' => $row['verified'],
                        'slug' => $row['slug'],
                        'roles_mask' => $row['roles_mask'],
                        'ip' => $row['ip'],
                        'img' => $row['img'],
                        'biography' => $row['biography'],
                        'amountposts' => (int)$row['amountposts'],
                        'amounttrending' => (int)$row['amounttrending'],
                        'isfollowed' => (bool)$row['isfollowed'],
                        'isfollowing' => (bool)$row['isfollowing'],
                        'amountfollower' => (int)$row['amountfollower'],
                        'amountfollowed' => (int)$row['amountfollowed'],
                        'liquidity' => (float)$row['liquidity'],
                        'createdat' => $row['createdat'],
                        'updatedat' => $row['updatedat'],
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
                }
            }

            if ($results) {
                $this->logger->info("Fetched all users from database", ['count' => count($results)]);
            } else {
                $this->logger->warning("No users found in database");
            }

            return $results;

        } catch (\PDOException $e) {
            $this->logger->error("Database error occurred", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function loadByName(string $username): array
    {
        $this->logger->info("UserMapper.loadByName started");

        try {
            $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat 
                    FROM users 
                    WHERE username = :username";
            
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':username', $username, \PDO::PARAM_STR);
            
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results[] = new User($row);
            }

            if ($results) {
                $this->logger->info("Fetched all users from database", ['count' => count($results)]);
            } else {
                $this->logger->warning("No users found in database");
            }

            return $results;

        } catch (\PDOException $e) {
            $this->logger->error("Database error occurred", ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function loadById(string $id): User|false
    {
        $this->logger->info("UserMapper.loadById started");

        try {
            $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat 
                    FROM users 
                    WHERE uid = :id";
            
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new User($data);
            }

            $this->logger->warning("No user found with id", ['id' => $id]);
            return false;

        } catch (\PDOException $e) {
            $this->logger->error("Database error occurred", ['error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function loadByEmail(string $email): User|false
    {
        $this->logger->info("UserMapper.loadByEmail started");

        try {
            $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new User($data);
            }

            $this->logger->warning("No user found with email", ['email' => $email]);
            return false;

        } catch (\PDOException $e) {
            $this->logger->error("Database error occurred", ['error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function loadUserInfoById(string $id): array|false
    {
        $this->logger->info("UserMapper.loadUserInfoById started", ['id' => $id]);

        try {
            $sql = "SELECT uid, username, img, biography, updatedat FROM users WHERE uid = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);  // Use bindValue here
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                $this->logger->info("User info fetched successfully", ['id' => $id]);
                return $data;
            }

            $this->logger->warning("No user found with id", ['id' => $id]);
            return false;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while loading user info", ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("General error while loading user info", ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function isUserExistById(string $id): bool
    {
        $this->logger->info("UserMapper.isUserExistById started", ['id' => $id]);

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;

            $this->logger->info("User existence check", ['id' => $id, 'exists' => $exists]);
            return $exists;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while checking if user exists", ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("General error while checking if user exists", ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function isEmailTaken(string $email): bool
    {
        $this->logger->info("UserMapper.isEmailTaken started", ['email' => $email]);

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $isTaken = $stmt->fetchColumn() > 0;

            $this->logger->info("Email availability check", ['email' => $email, 'isTaken' => $isTaken]);
            return $isTaken;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while checking email", ['email' => $email, 'error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("General error while checking email", ['email' => $email, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function checkIfNameAndSlugExist(string $username, int $slug): bool
    {
        $this->logger->info("UserMapper.checkIfNameAndSlugExist started", ['username' => $username, 'slug' => $slug]);

        try {
            $sql = "SELECT 1 FROM users WHERE username = :username AND slug = :slug";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $username, 'slug' => $slug]);
            $exists = $stmt->fetchColumn() !== false;

            $this->logger->info("Name and slug existence check", ['username' => $username, 'slug' => $slug, 'exists' => $exists]);
            return $exists;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while checking name and slug", ['username' => $username, 'slug' => $slug, 'error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("General error while checking name and slug", ['username' => $username, 'slug' => $slug, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function verifyAccount(string $uid): bool
    {
        $this->logger->info("UserMapper.verifyAccount started", ['uid' => $uid]);

        try {
            if (!$this->isUserExistById($uid)) {
                $this->logger->warning("User not found", ['uid' => $uid]);
                return false;
            }

            $sql = "UPDATE users SET verified = 1 WHERE uid = :uid AND verified = 0";
            $stmt = $this->db->prepare($sql);

            $stmt->execute(['uid' => $uid]);

            $isVerified = $stmt->rowCount() > 0;

            if ($isVerified) {
                $this->logger->info("User account verified", ['uid' => $uid]);
            } else {
                $this->logger->warning("User account already verified or not found", ['uid' => $uid]);
            }

            return $isVerified;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while verifying account", ['uid' => $uid, 'error' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("General error while verifying account", ['uid' => $uid, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function deactivateAccount(string $uid): bool
    {
        $this->logger->info("UserMapper.deactivateAccount started", ['uid' => $uid]);

        try {
            if (!$this->isUserExistById($uid)) {
                $this->logger->warning("User does not exist", ['uid' => $uid]);
                return false;
            }

            $sql = "UPDATE users SET verified = 0 WHERE uid = :uid";
            $stmt = $this->db->prepare($sql);
            
            $stmt->execute(['uid' => $uid]);

            $isDeactivated = $stmt->rowCount() > 0;

            if ($isDeactivated) {
                $this->logger->info("User account deactivated", ['uid' => $uid]);
            } else {
                $this->logger->warning("No rows affected when deactivating account", ['uid' => $uid]);
            }

            return $isDeactivated;
        } catch (\Exception $e) {
            $this->logger->error("Error deactivating account", ['uid' => $uid, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function fetchCountPosts(string $userid): int
    {
        $this->logger->info("UserMapper.fetchCountPosts started", ['userid' => $userid]);

        try {
            $sql = "SELECT COUNT(*) FROM posts WHERE userid = :userid";
            $stmt = $this->db->prepare($sql);
            
            $stmt->execute(['userid' => $userid]);

            $postCount = (int) $stmt->fetchColumn();

            $this->logger->info("Fetched post count", ['userid' => $userid, 'postCount' => $postCount]);

            return $postCount;
        } catch (\Exception $e) {
            $this->logger->error("Error fetching post count", ['userid' => $userid, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public function fetchFriends(
        string $userId, 
        int $offset = 0, 
        int $limit = 10
    ): ?array {
        $this->logger->info("UserMapper.fetchFriends started", ['userId' => $userId]);

        try {
            $sql = "
                SELECT u.uid, u.username, u.updatedat, u.biography, u.img, u.slug 
                FROM follows f1 
                INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                INNER JOIN users u ON f1.followedid = u.uid 
                WHERE f1.followerid = :userId 
                AND f2.followedid = :userId
                ORDER BY u.username ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':userId', $userId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();

            $friends = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($friends) {
                $this->logger->info("fetchFriends retrieved friends", ['count' => count($friends)]);
            } else {
                $this->logger->warning("No friends found for user", ['userId' => $userId]);
            }

            return $friends ?: null;
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchFriends", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function fetchFollowers(
        string $userId, 
        string $currentUserId, 
        int $offset = 0, 
        int $limit = 10
    ): array {
        $this->logger->info("UserMapper.fetchFollowers started", ['userId' => $userId]);

        try {
            $sql = "
                SELECT 
                    f.followerid AS uid, 
                    u.username, 
                    u.img,
                    u.slug,
                    EXISTS (
                        SELECT 1 
                        FROM follows ff 
                        WHERE ff.followerid = :currentUserId AND ff.followedid = f.followerid
                    ) AS isfollowed,
                    EXISTS (
                        SELECT 1 
                        FROM follows ff 
                        WHERE ff.followerid = f.followerid AND ff.followedid = :currentUserId
                    ) AS isfollowing
                FROM follows f
                JOIN users u ON u.uid = f.followerid
                WHERE f.followedid = :userId
                ORDER BY f.createdat DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $uniqueResults = array_map('unserialize', array_unique(array_map('serialize', $results)));

            $users = array_map(fn($row) => new ProfilUser($row), $uniqueResults);

            $this->logger->info(
                count($users) > 0 ? "fetchFollowers retrieved users" : "No users found",
                ['count' => count($users)]
            );

            return $users;
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchFollowers", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function fetchFollowing(
        string $userId, 
        string $currentUserId, 
        int $offset = 0, 
        int $limit = 10
    ): array {
        $this->logger->info("UserMapper.fetchFollowing started", ['userId' => $userId]);

        try {
            $sql = "
                SELECT 
                    f.followedid AS uid, 
                    u.username, 
                    u.img,
                    u.slug,
                    EXISTS (
                        SELECT 1 
                        FROM follows ff 
                        WHERE ff.followerid = :currentUserId AND ff.followedid = f.followedid
                    ) AS isfollowed,
                    EXISTS (
                        SELECT 1 
                        FROM follows ff 
                        WHERE ff.followerid = f.followedid AND ff.followedid = :currentUserId
                    ) AS isfollowing
                FROM follows f
                JOIN users u ON u.uid = f.followedid
                WHERE f.followerid = :userId
                ORDER BY f.createdat DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $uniqueResults = array_map('unserialize', array_unique(array_map('serialize', $results)));

            $users = array_map(fn($row) => new ProfilUser($row), $uniqueResults);

            $this->logger->info(
                count($users) > 0 ? "fetchFollowing retrieved users" : "No users found",
                ['count' => count($users)]
            );

            return $users;
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchFollowing", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function fetchFollowRelations(
        string $userId, 
        string $currentUserId, 
        int $offset = 0, 
        int $limit = 10, 
        string $relationType = 'followers'
    ): array {
        $this->logger->info("UserMapper.fetchFollowRelations started", ['relationType' => $relationType]);

        try {
            $isFollowers = $relationType === 'followers';
            $isFollowing = $relationType === 'following';
            $isFriends = $relationType === 'friends';

            if ($isFollowers) {
                $sql = "
                    SELECT 
                        f.followerid AS uid, 
                        u.username, 
                        u.img,
                        u.slug,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = :currentUserId AND ff.followedid = f.followerid
                        ) AS isfollowed,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = f.followerid AND ff.followedid = :currentUserId
                        ) AS isfollowing
                    FROM follows f
                    JOIN users u ON u.uid = f.followerid
                    WHERE f.followedid = :userId
                    ORDER BY f.createdat DESC
                    LIMIT :limit OFFSET :offset
                ";
            } elseif ($isFollowing) {
                $sql = "
                    SELECT 
                        f.followedid AS uid, 
                        u.username, 
                        u.img,
                        u.slug,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = :currentUserId AND ff.followedid = f.followedid
                        ) AS isfollowed,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = f.followedid AND ff.followedid = :currentUserId
                        ) AS isfollowing
                    FROM follows f
                    JOIN users u ON u.uid = f.followedid
                    WHERE f.followerid = :userId
                    ORDER BY f.createdat DESC
                    LIMIT :limit OFFSET :offset
                ";
            } elseif ($isFriends) {
                $sql = "
                    SELECT 
                        f1.followerid AS uid, 
                        u.username, 
                        u.img,
                        u.slug,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = :currentUserId AND ff.followedid = f1.followerid
                        ) AS isfollowed,
                        EXISTS (
                            SELECT 1 
                            FROM follows ff 
                            WHERE ff.followerid = f1.followerid AND ff.followedid = :currentUserId
                        ) AS isfollowing
                    FROM follows f1
                    JOIN follows f2 ON f1.followerid = f2.followedid
                    JOIN users u ON u.uid = f1.followerid
                    WHERE f1.followedid = :userId AND f2.followerid = :userId
                    ORDER BY f1.createdat DESC
                    LIMIT :limit OFFSET :offset
                ";
            } else {
                throw new InvalidArgumentException('Invalid relationType. Use "followers", "following", or "friends".');
            }

            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $uniqueResults = array_map('unserialize', array_unique(array_map('serialize', $results)));

            $users = array_map(fn($row) => new ProfilUser($row), $uniqueResults);

            $this->logger->info("fetchFollowRelations completed", ['count' => count($users)]);

            return $users;
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchFollowRelations", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function isFollowing(string $userid, string $currentUserId): bool
    {
        $this->logger->info("UserMapper.isFollowing started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedid = :userid AND followerid = :currentUserId";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->logger->error("Database error in isFollowing", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function isFollowed(string $userid, string $currentUserId): bool
    {
        $this->logger->info("UserMapper.isFollowed started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedid = :currentUserId AND followerid = :userid";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->logger->error("Database error in isFollowed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function fetchFollowCounts(string $userid, string $currentUserId): array
    {
        $sql = "
            SELECT 
                SUM(CASE WHEN f.followedid = :userid THEN 1 ELSE 0 END) AS amountfollower,
                SUM(CASE WHEN f.followerid = :userid THEN 1 ELSE 0 END) AS amountfollowed,
                EXISTS (SELECT 1 FROM follows WHERE followedid = :userid AND followerid = :currentUserId) AS isfollowing,
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = :userid) AS isfollowed
            FROM follows f
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $data ?: ['amountfollower' => 0, 'amountfollowed' => 0, 'isfollowing' => false, 'isfollowed' => false];
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchFollowCounts", ['error' => $e->getMessage()]);
            return ['amountfollower' => 0, 'amountfollowed' => 0, 'isfollowing' => false, 'isfollowed' => false];
        }
    }

    public function fetchProfileData(string $userid, string $currentUserId): Profile|false 
    {
        $sql = "
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.biography,
                COALESCE((SELECT COUNT(p.postid) FROM posts p WHERE p.userid = u.uid), 0) AS amountposts,
                COALESCE((SELECT COUNT(*) FROM post_info pi WHERE pi.userid = u.uid AND pi.likes > 4 AND pi.createdat >= NOW() - INTERVAL '7 days'), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM follows WHERE followedid = u.uid AND followerid = :currentUserId) AS isfollowing,
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid) AS isfollowed,
                (SELECT COUNT(*) FROM follows WHERE followedid = u.uid) AS amountfollower,
                (SELECT COUNT(*) FROM follows WHERE followerid = u.uid) AS amountfollowed
            FROM users u
            WHERE u.uid = :userid AND u.verified = :verified
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':verified', 1, \PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new Profile($data);
            }

            $this->logger->warning("No user found with ID", ['userid' => $userid]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Database error in fetchProfileData", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function setPassword(string $password): string
    {
        $this->logger->info("UserMapper.setPassword started");

        if (defined('PASSWORD_ARGON2ID')) {
            $hash = password_hash($password, PASSWORD_ARGON2ID, [
                'time_cost' => 3,
                'memory_cost' => 65536, // Memory usage (64MB)
                'threads' => 2
            ]);
            $algorithm = 'ARGON2ID';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, [
                'cost' => 12
            ]);
            $algorithm = 'BCRYPT';
        }

        $this->logger->info("Password hashed using {$algorithm}");

        return $hash;
    }

    public function createUser(array $userData): ?string
    {
        $this->logger->info("UserMapper.createUser started");

        try {
            $userid = $userData['uid'] ?? \bin2hex(\random_bytes(16));

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            $createdat = $updatedat = (new \DateTime())->format('Y-m-d H:i:s.u');

            $hashedPassword = method_exists($this, 'setPassword') ? $this->setPassword($userData['password']) : \password_hash($userData['password'], \PASSWORD_BCRYPT, ['time_cost' => 4, 'memory_cost' => 2048, 'threads' => 1]);

            $userData = array_merge($userData, [
                'uid' => $userid,
                'password' => $hashedPassword,
                'status' => 0,
                'verified' => 0,
                'roles_mask' => 0,
                'ip' => $ip,
                'createdat' => $createdat,
                'updatedat' => $updatedat
            ]);

            $user = new User($userData);
            $this->insert($user);

            $this->logger->info("Inserted new user into database", ['uid' => $userid]);

            return $userid;
        } catch (\Exception $e) {
            $this->logger->error("Error creating user", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function insert(User $user): User
    {
        $this->logger->info("UserMapper.insert started");

        $data = $user->getArrayCopy();

        $query = "INSERT INTO users 
                  (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat)
                  VALUES 
                  (:uid, :email, :username, :password, :status, :verified, :slug, :roles_mask, :ip, :img, :biography, :createdat, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);
            $stmt->bindValue(':username', $data['username'], \PDO::PARAM_STR);
            $stmt->bindValue(':password', $data['password'], \PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], \PDO::PARAM_INT);
            $stmt->bindValue(':verified', $data['verified'], \PDO::PARAM_INT);
            $stmt->bindValue(':slug', $data['slug'], \PDO::PARAM_INT);
            $stmt->bindValue(':roles_mask', $data['roles_mask'], \PDO::PARAM_INT);
            $stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
            $stmt->bindValue(':img', $data['img'], \PDO::PARAM_STR);
            $stmt->bindValue(':biography', $data['biography'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new user into database", ['uid' => $data['uid']]);

            return new User($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.insert: Exception occurred while inserting user",
                [
                    'uid' => $data['uid'] ?? null,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to insert user into database: " . $e->getMessage());
        }
    }

    public function insertinfo(UserInfo $user): UserInfo
    {
        $this->logger->info("UserMapper.insertinfo started");

        $data = $user->getArrayCopy();

        $query = "INSERT INTO users_info 
                  (userid, liquidity, amountposts, amountblocked, amountfollower, amountfollowed, isprivate, invited, updatedat)
                  VALUES 
                  (:userid, :liquidity, :amountposts, :amountblocked, :amountfollower, :amountfollowed, :isprivate, :invited, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
            $stmt->bindValue(':amountposts', $data['amountposts'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountblocked', $data['amountblocked'], \PDO::PARAM_INT); 
            $stmt->bindValue(':amountfollower', $data['amountfollower'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfollowed', $data['amountfollowed'], \PDO::PARAM_INT);
            $stmt->bindValue(':isprivate', $data['isprivate'], \PDO::PARAM_INT);
            $stmt->bindValue(':invited', $data['invited'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new user into database", ['userid' => $data['userid']]);

            return new UserInfo($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.insert: Exception occurred while inserting UserInfo",
                [
                    'userid' => $data['userid'] ?? null,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to insert UserInfo into database: " . $e->getMessage());
        }
    }

    public function update(User $user): User
    {
        $this->logger->info("UserMapper.update started");

        $data = $user->getArrayCopy();

        $query = "UPDATE users
                  SET username = :username,
                      password = :password,
                      email = :email,
                      status = :status,
                      verified = :verified,
                      roles_mask = :roles_mask,
                      ip = :ip,
                      img = :img,
                      biography = :biography,
                      updatedat = :updatedat
                  WHERE uid = :uid";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':username', $data['username'], \PDO::PARAM_STR);
            $stmt->bindValue(':password', $data['password'], \PDO::PARAM_STR); 
            $stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], \PDO::PARAM_INT);
            $stmt->bindValue(':verified', $data['verified'], \PDO::PARAM_INT);
            $stmt->bindValue(':roles_mask', $data['roles_mask'], \PDO::PARAM_INT);
            $stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
            $stmt->bindValue(':img', $data['img'], \PDO::PARAM_STR);
            $stmt->bindValue(':biography', $data['biography'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Updated user in database", ['uid' => $data['uid']]);

            return new User($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.update: Exception occurred while updating user",
                [
                    'uid' => $data['uid'] ?? null,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to update user in database: " . $e->getMessage());
        }
    }

    public function updatePass(User $user): User
    {
        $this->logger->info("UserMapper.updatePass started");

        $data = $user->getArrayCopy(); 
        $passwordHash = $this->setPassword($data['password']); 

        $query = "UPDATE users
                  SET password = :password,
                      ip = :ip,
                      updatedat = :updatedat
                  WHERE uid = :uid";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':password', $passwordHash, \PDO::PARAM_STR);
            $stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Updated password in database", ['uid' => $data['uid']]);

            return new User($data); 
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.updatePass: Exception occurred while updating password",
                [
                    'uid' => $data['uid'] ?? null,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to update password in database: " . $e->getMessage());
        }
    }

    public function updateProfil(User $user): User
    {
        $this->logger->info("UserMapper.updateProfil started");

        $data = $user->getArrayCopy(); 

        $query = "UPDATE users
                  SET username = :username,
                      email = :email,
                      status = :status,
                      verified = :verified,
                      slug = :slug,
                      roles_mask = :roles_mask,
                      ip = :ip,
                      img = :img,
                      biography = :biography,
                      updatedat = :updatedat
                  WHERE uid = :uid";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':username', $data['username'], \PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'], \PDO::PARAM_INT);
            $stmt->bindValue(':verified', $data['verified'], \PDO::PARAM_INT);
            $stmt->bindValue(':slug', $data['slug'], \PDO::PARAM_INT);
            $stmt->bindValue(':roles_mask', $data['roles_mask'], \PDO::PARAM_INT);
            $stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
            $stmt->bindValue(':img', $data['img'], \PDO::PARAM_STR);
            $stmt->bindValue(':biography', $data['biography'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 
            $stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Updated profile in database", ['uid' => $data['uid']]);

            return new User($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.updateProfil: Exception occurred while updating profile",
                [
                    'uid' => $data['uid'] ?? null,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to update profile in database: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        $this->logger->info("UserMapper.delete started");

        $query = "DELETE FROM users WHERE uid = :uid";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':uid', $id, \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Deleted user in database", ['id' => $id]);

            return (bool)$stmt->rowCount(); 
        } catch (\PDOException $e) {
            $this->logger->error(
                "UserMapper.delete: Exception occurred while deleting user",
                [
                    'id' => $id,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to delete user from database: " . $e->getMessage());
        }
    }

    public function deleteUnverifiedUsers(): void
    {
        $this->logger->info("UserMapper.deleteUnverifiedUsers started");

        try {
            $sql = "SELECT uid FROM users WHERE verified IS NULL";
            $stmt = $this->db->query($sql);
            $unverifiedUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($unverifiedUsers) {
                $this->logger->info("Fetched all unverified users from database", ['count' => count($unverifiedUsers)]);

                foreach ($unverifiedUsers as $user) {
                    $uid = $user['uid'] ?? null;

                    if ($uid !== null && is_string($uid)) {
                        $this->delete($uid);
                    } else {
                        $this->logger->warning("Skipping deletion for user with invalid UID", ['uid' => $uid]);
                    }
                }

                $this->logger->info("Deleted all unverified users from database");
            } else {
                $this->logger->info("No unverified users found in database");
            }
        } catch (\Exception $e) {
            $this->logger->error("Error deleting unverified users", ['error' => $e]);
        }
    }

    public function saveOrUpdateAccessToken(string $userid, string $accessToken): void
    {
        $this->logger->info("UserMapper.saveOrUpdateAccessToken started");

        $accessTokenValidity = 604800; // 7 Tage
        $createdat = time();
        $expirationTime = $createdat + $accessTokenValidity;

        try {
            $query = "SELECT COUNT(*) FROM access_tokens WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $query = "UPDATE access_tokens 
                          SET access_token = :access_token, createdat = :createdat, expiresat = :expiresat 
                          WHERE userid = :userid";
            } else {
                $query = "INSERT INTO access_tokens (userid, access_token, createdat, expiresat) 
                          VALUES (:userid, :access_token, :createdat, :expiresat)";
            }

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':access_token', $accessToken, \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_INT);
            $stmt->bindValue(':expiresat', $expirationTime, \PDO::PARAM_INT);
            $stmt->execute();

            $this->logger->info("Access token successfully saved or updated for user: $userid");
        } catch (\PDOException $e) {
            $this->logger->error("Database error in saveOrUpdateAccessToken: " . $e->getMessage());
        }
    }

    public function saveOrUpdateRefreshToken(string $userid, string $refreshToken): void
    {
        $this->logger->info("UserMapper.saveOrUpdateRefreshToken started");

        $refreshTokenValidity = 604800; // 7 Tage
        $createdat = time();
        $expirationTime = $createdat + $refreshTokenValidity;

        try {
            $query = "SELECT COUNT(*) FROM refresh_tokens WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $query = "UPDATE refresh_tokens 
                          SET refresh_token = :refresh_token, createdat = :createdat, expiresat = :expiresat 
                          WHERE userid = :userid";
            } else {
                $query = "INSERT INTO refresh_tokens (userid, refresh_token, createdat, expiresat) 
                          VALUES (:userid, :refresh_token, :createdat, :expiresat)";
            }

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':refresh_token', $refreshToken, \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_INT);
            $stmt->bindValue(':expiresat', $expirationTime, \PDO::PARAM_INT);
            $stmt->execute();

            $this->logger->info("Refresh token successfully saved or updated for user: $userid");
        } catch (\PDOException $e) {
            $this->logger->error("Database error in saveOrUpdateRefreshToken: " . $e->getMessage());
        }
    }

    public function toggleUserBlock(string $blockerid, string $blockedid): array
    {
        $this->logger->info('UserInfoMapper.toggleUserBlock started', [
            'blockerid' => $blockerid,
            'blockedid' => $blockedid
        ]);

        try {
            $this->db->beginTransaction();
            
            $query = "SELECT COUNT(*) FROM user_block_user WHERE blockerid = :blockerid AND blockedid = :blockedid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
            $stmt->bindValue(':blockedid', $blockedid, \PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $query = "DELETE FROM user_block_user WHERE blockerid = :blockerid AND blockedid = :blockedid";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->bindValue(':blockedid', $blockedid, \PDO::PARAM_STR);
                $stmt->execute();

                $action = false;
                $response = 'User unblocked successfully.';
            } else {
                $query = "INSERT INTO user_block_user (blockerid, blockedid) VALUES (:blockerid, :blockedid)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->bindValue(':blockedid', $blockedid, \PDO::PARAM_STR);
                $stmt->execute();

                $action = true;
                $response = 'User blocked successfully.';
            }

            $this->db->commit();
            $this->logger->info($response, ['blockerid' => $blockerid, 'blockedid' => $blockedid]);

            return ['status' => 'success', 'ResponseCode' => $response, 'isBlocked' => $action];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to toggle user block', ['exception' => $e->getMessage()]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user block'];
        }
    }

    public function fetchAllFriends(int $offset = 0, int $limit = 20): ?array
    {
        $this->logger->info("UserMapper.fetchAllFriends started");

        $sql = "SELECT DISTINCT u1.uid AS follower, u1.username AS followername, u1.slug AS followerslug, 
                                u2.uid AS followed, u2.username AS followedname, u2.slug AS followedslug
                FROM follows f1
                INNER JOIN follows f2 ON f1.followerid = f2.followedid 
                                     AND f1.followedid = f2.followerid
                INNER JOIN users u1 ON u1.uid = f1.followerid
                INNER JOIN users u2 ON u2.uid = f1.followedid
                ORDER BY follower
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->logger->error("Database error in fetchFriends: " . $e->getMessage());
            return null;
        }
    }


}
