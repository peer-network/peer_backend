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
			$stmt->execute([
				':userid' => $userId,
				':ip' => $ip,
				':browser' => $browser,
                ':action_type' => $actionType,
			]);

			$this->logger->info('Login data logged', ['userid' => $userId, 'ip' => $ip, 'browser' => $browser]);

		} catch (\Exception $e) {
			$this->logger->error('Failed to log login data', ['ResponseCode' => $e->getMessage()]);
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
            $responseTime = round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2); // in milliseconds
            $location = $this->getLocationFromIP($ip) ?? 'unknown';
            $actionType = $actionType ?? 'login';

			$rawInput = \file_get_contents('php://input');
			$input = \json_decode($rawInput, true);

			$query = $input['query'] ?? null;
			$variableValues = $input['variables'] ?? null;

			if ($query !== null) {
				try {
					//$mediafile = $_POST;
					//unset($mediafile['img'], $mediafile['media'], $mediafile['image'], $mediafile['biography']);
					$requestPayload = !empty($input) ? json_encode($input, JSON_THROW_ON_ERROR) : 'null';

				} catch (\Exception $e) {
					echo "An error occurred: " . $e->getMessage();
				}
			} else {
				$requestPayload = null;
			}
            $authStatus = 'success';

            $sql = "
                INSERT INTO logdaten 
                (userid, ip, browser, url, http_method, status_code, response_time, location, action_type, request_payload, auth_status)
                VALUES 
                (:userid, :ip, :browser, :url, :http_method, :status_code, :response_time, :location, :action_type, :request_payload, :auth_status)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userid' => $userId,
                ':ip' => $ip,
                ':browser' => $browser,
                ':url' => $url,
                ':http_method' => $httpMethod,
                ':status_code' => $statusCode,
                ':response_time' => $responseTime,
                ':location' => $location,
                ':action_type' => $actionType,
                ':request_payload' => $requestPayload,
                ':auth_status' => $authStatus
            ]);

            $this->logger->info('Login data logged', compact(
                'userId', 'ip', 'browser', 'url', 'httpMethod', 
                'statusCode', 'responseTime', 'location', 'actionType', 
                'requestPayload', 'authStatus'
            ));

        } catch (\PDOException $e) {
            $this->logger->error('Database error occurred while logging login data', [
                'ResponseCode' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip,
                'url' => $url
            ]);
        } catch (\JsonException $e) {
            $this->logger->error('JSON encoding error occurred while logging login data', [
                'ResponseCode' => $e->getMessage(),
                'userId' => $userId,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            $this->logger->error('An unexpected error occurred while logging login data', [
                'ResponseCode' => $e->getMessage(),
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
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if ($data && $data['status'] === 'success') {
                return "{$data['city']}, {$data['country']}";
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get location from IP', [
                'ip' => $ip,
                'ResponseCode' => $e->getMessage()
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            } catch (Exception $e) {
                $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
            }
        }

        if ($results) {
            $this->logger->info("Fetched all users from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No users found in database");
        }

        return $results;
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            } catch (Exception $e) {
                $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
            }
        }

        if ($results) {
            $this->logger->info("Fetched all users from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No users found in database");
        }

        return $results;
    }

    public function loadByName(string $username): array
    {
        $this->logger->info("UserMapper.loadByName started");

        $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new User($row);
        }

        if ($results) {
            $this->logger->info("Fetched all users from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No users found in database");
        }

        return $results;
    }

    public function loadById(string $id): User|false
    {
        $this->logger->info("UserMapper.loadById started");
        $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat FROM users WHERE uid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new User($data);
        }

        $this->logger->warning("No user found with id", ['id' => $id]);
        return false;
    }

    public function loadByEmail(string $email): User|false
    {
        $this->logger->info("UserMapper.loadByEmail started");

        $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new User($data);
        }

        $this->logger->warning("No user found with email", ['email' => $email]);
        return false;
    }

    public function loadUserInfoById(string $id): array|false
    {
        $this->logger->info("UserMapper.loadUserInfoById started");

        $sql = "SELECT uid, username, img, biography, updatedat FROM users WHERE uid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return $data;
        }

        $this->logger->warning("No user found with id: " . $id);
        return false;
    }

    public function isUserExistById(string $id): bool
    {
        $this->logger->info("UserMapper.isUserExistById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function isEmailTaken(string $email): bool
    {
        $this->logger->info("UserMapper.isEmailTaken started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    public function checkIfNameAndSlugExist(string $username, int $slug): bool
    {
        $this->logger->info("UserMapper.checkIfNameAndSlugExist started");

        $sql = "SELECT 1 FROM users WHERE username = :username AND slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username, 'slug' => $slug]);

        return $stmt->fetchColumn() !== false;
    }

	public function verifyAccount(string $uid): bool
	{
		$this->logger->info("UserMapper.verifyAccount started");

		try {
			if (!$this->isUserExistById($uid)) {
				return false;
			}

			$sql = "UPDATE users SET verified = 1 WHERE uid = :uid AND verified = 0";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(['uid' => $uid]);

			return $stmt->rowCount() > 0;
		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return false;
		} catch (Exception $e) {
			error_log('General error: ' . $e->getMessage());
			return false;
		}
	}

    public function deactivateAccount(string $uid): bool
    {
        $this->logger->info("UserMapper.deactivateAccount started");

        if (!$this->isUserExistById($uid)) {
            return false;
        }

        $sql = "UPDATE users SET verified = 0 WHERE uid = :uid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $uid]);

        return $stmt->rowCount() > 0;
    }

    public function fetchCountPosts(string $userid): int
    {
        $this->logger->info("UserMapper.fetchCountPosts started");

        $sql = "SELECT COUNT(*) FROM posts WHERE userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);
        return (int) $stmt->fetchColumn();
    }

	public function fetchFriends(string $userId, int $offset = 0, int $limit = 10): ?array
	{
		$this->logger->info("UserMapper.fetchFriends started");

		$sql = "SELECT u.uid, u.username, u.updatedat, u.biography, u.img 
				FROM follows f1 
				INNER JOIN follows f2 ON f1.followedid = f2.followerid 
				INNER JOIN users u ON f1.followedid = u.uid 
				WHERE f1.followerid = :userId 
				AND f2.followedid = :userId
                ORDER BY u.username ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

    public function fetchFollowers(string $userId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $sql = "
            SELECT 
                f.followerid AS uid, 
                u.username, 
                u.img,
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
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'currentUserId' => $currentUserId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new ProfilUser($row);
        }

        if ($results) {
            $this->logger->info("fetchFollowers from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No users found in database");
        }

        return $results;
    }

    public function fetchFollowing(string $userId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $sql = "
            SELECT 
                f.followedid AS uid, 
                u.username, 
                u.img,
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
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'currentUserId' => $currentUserId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new ProfilUser($row);
        }

        if ($results) {
            $this->logger->info("fetchFollowing from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No users found in database");
        }

        return $results;
    }

    public function fetchFollowRelations(string $userId, string $currentUserId, int $offset = 0, int $limit = 10, string $relationType = 'followers'): array
    {
        $isFollowers = $relationType === 'followers';
        $isFollowing = $relationType === 'following';
        $isFriends = $relationType === 'friends';

        // Determine SQL logic for followers, following, or mutual
        if ($isFollowers) {
            $sql = "
                SELECT 
                    f.followerid AS uid, 
                    u.username, 
                    u.img,
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
        $stmt->execute([
            'userId' => $userId,
            'currentUserId' => $currentUserId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Remove exact duplicates from results if any
        $uniqueResults = array_map('unserialize', array_unique(array_map('serialize', $results)));

        // Convert to ProfilUser objects
        $users = array_map(fn($row) => new ProfilUser($row), $uniqueResults);

        $logMessage = count($users) > 0 
            ? "fetchFollowRelations from database" 
            : "No users found in database";

        $this->logger->info($logMessage, ['count' => count($users)]);

        return $users;
    }

    public function isFollowing(string $userid, string $currentUserId): bool
    {
        $this->logger->info("UserMapper.isFollowing started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedid = :userid AND followerid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function isFollowed(string $userid, string $currentUserId): bool
    {
        $this->logger->info("UserMapper.isFollowed started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedid = :currentUserId AND followerid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function fetchFollowCounts(string $userid, string $currentUserId): array
    {
        $sql = "
            SELECT 
                SUM(CASE WHEN f.followedid = :userid THEN 1 ELSE 0 END) AS amountfollower,
                SUM(CASE WHEN f.followerid = :userid THEN 1 ELSE 0 END) AS amountfollowed,
                EXISTS (
                    SELECT 1 FROM follows WHERE followedid = :userid AND followerid = :currentUserId
                ) AS isfollowing,
                EXISTS (
                    SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = :userid
                ) AS isfollowed
            FROM follows f
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userid' => $userid,
            'currentUserId' => $currentUserId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
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
				COALESCE((
                    SELECT COUNT(p.postid)
                    FROM posts p
                    WHERE p.userid = u.uid
                ), 0) AS amountposts,
				COALESCE((
					SELECT COUNT(*) 
					FROM post_info pi 
					WHERE pi.userid = u.uid 
					  AND pi.likes > 4 
					  AND pi.createdat >= NOW() - INTERVAL '7 days'
				), 0) AS amounttrending,
                EXISTS (
                    SELECT 1 FROM follows WHERE followedid = u.uid AND followerid = :currentUserId
                ) AS isfollowing,
                EXISTS (
                    SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid
                ) AS isfollowed,
                (SELECT COUNT(*) FROM follows WHERE followedid = u.uid) AS amountfollower,
                (SELECT COUNT(*) FROM follows WHERE followerid = u.uid) AS amountfollowed
            FROM users u
            WHERE u.uid = :userid AND u.verified = :verified
        ";

        $queryParams = [':verified' => 1, 'userid' => $userid, ':currentUserId' => $currentUserId];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Profile($data);
        }

        $this->logger->warning("No user found with id", ['id' => $id]);
        return false;
    }

    private function setPassword(string $password): string
    {
        $this->logger->info("UserMapper.setPassword started");

		if(defined('PASSWORD_ARGON2ID')) {
			$hash = \password_hash($password, \PASSWORD_ARGON2ID, ['time_cost' => 4, 'memory_cost' => 2048, 'threads' => 1]);
		} else {
			$hash = \password_hash($password, \PASSWORD_BCRYPT, ['time_cost' => 4, 'memory_cost' => 2048, 'threads' => 1]);
		}

        return $hash;
    }

    public function createUser(array $userData): string|null
    {
        $this->logger->info("UserMapper.createUser started");

        try {
            $userid = $userData['uid'] ?? null;

            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            $createdat = $updatedat = (new \DateTime())->format('Y-m-d H:i:s.u');

            $Password = $this->setPassword($userData['password']);

            $userData = array_merge($userData, [
                'password' => $Password,
                'status' => 0,
                'verified' => 0,
                'roles_mask' => 0,
                'ip' => $ip,
                'createdat' => $createdat,
                'updatedat' => $updatedat
            ]);

            $user = new User($userData);

            $this->insert($user);

            $this->logger->info("Inserted new user into database", ['user' => $userData]);

            return $userid;
        } catch (\Exception $e) {
            $this->logger->warning("Error creating user", ['user' => $userData, 'error' => $e->getMessage()]);
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

			// Explicitly bind each value
			$stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);
			$stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);
			$stmt->bindValue(':username', $data['username'], \PDO::PARAM_STR);
			$stmt->bindValue(':password', $data['password'], \PDO::PARAM_STR); // Password should already be hashed
			$stmt->bindValue(':status', $data['status'], \PDO::PARAM_INT);
			$stmt->bindValue(':verified', $data['verified'], \PDO::PARAM_INT);
			$stmt->bindValue(':slug', $data['slug'], \PDO::PARAM_INT);
			$stmt->bindValue(':roles_mask', $data['roles_mask'], \PDO::PARAM_INT);
			$stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
			$stmt->bindValue(':img', $data['img'], \PDO::PARAM_STR);
			$stmt->bindValue(':biography', $data['biography'], \PDO::PARAM_STR);
			$stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR); // Ensure date is formatted correctly
			$stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); // Ensure date is formatted correctly

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
				  (userid, liquidity, amountposts, amounttrending, amountfollower, amountfollowed, isprivate, invited, updatedat)
				  VALUES 
				  (:userid, :liquidity, :amountposts, :amounttrending, :amountfollower, :amountfollowed, :isprivate, :invited, :updatedat)";

		try {
			$stmt = $this->db->prepare($query);

			// Explicitly bind each value
			$stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
			$stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
			$stmt->bindValue(':amountposts', $data['amountposts'], \PDO::PARAM_INT);
			$stmt->bindValue(':amounttrending', $data['amounttrending'], \PDO::PARAM_INT); 
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

			// Bind each value explicitly
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

			// Explicitly bind only the required values
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

			// Explicitly bind each value
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

			// Explicitly bind the `id` parameter
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
            $unverifiedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        $accessTokenValidity = 604800;
        $createdat = time();
        $expirationTime = $createdat + $accessTokenValidity;
        $data = [];
        $data['userid'] = $userid;
        $data['access_token'] = $accessToken;
        $data['createdat'] = $createdat;
        $data['expiresat'] = $expirationTime;

        $query = "SELECT COUNT(*) FROM access_tokens WHERE userid = :userid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['userid' => $userid]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $query = "UPDATE access_tokens SET access_token = :access_token, createdat = :createdat, expiresat = :expiresat WHERE userid = :userid";
        } else {
            $query = "INSERT INTO access_tokens (userid, access_token, createdat, expiresat) VALUES (:userid, :access_token, :createdat, :expiresat)";
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
    }

    public function saveOrUpdateRefreshToken(string $userid, string $refreshToken): void
    {
        $this->logger->info("UserMapper.saveOrUpdateRefreshToken started");

        $refreshTokenValidity = 604800;
        $createdat = time();
        $expirationTime = $createdat + $refreshTokenValidity;
        $data = [];
        $data['userid'] = $userid;
        $data['refresh_token'] = $refreshToken;
        $data['createdat'] = $createdat;
        $data['expiresat'] = $expirationTime;

        $query = "SELECT COUNT(*) FROM refresh_tokens WHERE userid = :userid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['userid' => $userid]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $query = "UPDATE refresh_tokens SET refresh_token = :refresh_token, createdat = :createdat, expiresat = :expiresat WHERE userid = :userid";
        } else {
            $query = "INSERT INTO refresh_tokens (userid, refresh_token, createdat, expiresat) VALUES (:userid, :refresh_token, :createdat, :expiresat)";
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
    }

	public function loadByIdByAccount(string $id): User|false
	{
		$this->logger->info("UserMapper.loadById started");

		// Query to load user details
		$sqlUser = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat 
					FROM users 
					WHERE uid = :id";
		$stmtUser = $this->db->prepare($sqlUser);
		$stmtUser->execute(['id' => $id]);
		$data = $stmtUser->fetch(PDO::FETCH_ASSOC);

		if ($data === false) {
			$this->logger->warning("No user found with id", ['id' => $id]);
			return false;
		}

		// Query to load dailyfree stats
		$sqlDailyFree = "SELECT 
							COALESCE(liken, 0) AS liken, 
							COALESCE(comments, 0) AS comments, 
							COALESCE(posten, 0) AS posten 
						 FROM dailyfree 
						 WHERE userid = :id";
		$stmtDailyFree = $this->db->prepare($sqlDailyFree);
		$stmtDailyFree->execute(['id' => $id]);
		$dailyFreeData = $stmtDailyFree->fetch(PDO::FETCH_ASSOC);

		// Set default values if no dailyfree data exists
		if ($dailyFreeData === false) {
			$dailyFreeData = ['liken' => 0, 'comments' => 0, 'posten' => 0];
		}

		// Query to check the user's liquidity
		$sqlLiquidity = "SELECT 
							COALESCE(SUM(numbers), 0) AS total_liquidity 
						 FROM wallet 
						 WHERE userid = :id";
		$stmtLiquidity = $this->db->prepare($sqlLiquidity);
		$stmtLiquidity->execute(['id' => $id]);
		$liquidityData = $stmtLiquidity->fetch(PDO::FETCH_ASSOC);

		// Set default value if no wallet data exists
		$totalLiquidity = $liquidityData['total_liquidity'] ?? 0;

		// Add daily usage and liquidity data to the user object
		$user = new User($data);
		$user->dailyFree = [
			'liken' => $dailyFreeData['liken'],
			'comments' => $dailyFreeData['comments'],
			'posten' => $dailyFreeData['posten'],
			'max_likes' => 3,
			'max_comments' => 4,
			'max_posten' => 1
		];
		$user->liquidity = $totalLiquidity;

		// Log daily usage and liquidity for debug purposes
		$this->logger->info("Daily free stats", ['dailyFree' => $user->dailyFree]);
		$this->logger->info("Total liquidity", ['totalLiquidity' => $totalLiquidity]);

		return $user;
	}
}
