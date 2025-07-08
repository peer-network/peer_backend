<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\User;
use Fawaz\App\UserInfo;
use Fawaz\App\Profile;
use Fawaz\App\ProfilUser;
use Fawaz\App\UserAdvanced;
use Fawaz\App\Tokenize;
use Fawaz\config\constants\ConstantsConfig;
use Psr\Log\LoggerInterface;
use Fawaz\Mail\PasswordRestMail;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\GetProfileContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\DateService;

class UserMapper
{
    const STATUS_DELETED = 6;
    private string $poolWallet;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool)
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

        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
            $this->logger->error('Failed to get location from IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function fetchAll(string $currentUserId, array $args = []): array
    {
        $this->logger->info("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        
        $whereClauses = ["verified = :verified"];
        $whereClauses[] = 'status = 0 AND roles_mask = 0 OR roles_mask = 16';
        $whereClausesString = implode(" AND ", $whereClauses);

        $contentFilterService = new ContentFilterServiceImpl(
            new ListPostsContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        $sql = sprintf("
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
                ui.reports AS user_reports,
                ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed
            FROM 
                users u
            LEFT JOIN users_info ui ON uid = ui.userid
            WHERE %s",
            $whereClausesString,
        );

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
        $conditions[] = "status != :status";
        $queryParams[':status'] = self::STATUS_DELETED;

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
                    $user_reports = (int)$row['user_reports'];
                    $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];
                    if ($contentFilterService->getContentFilterAction(
                        ContentType::user,
                        ContentType::user,
                        $user_reports,$user_dismiss_moderation_amount,
                        $currentUserId,$row['uid']
                    ) == ContentFilteringAction::replaceWithPlaceholder) {
                        $replacer = ContentReplacementPattern::flagged;
                        $row['username'] = $replacer->username($row['username']);
                        $row['img'] = $replacer->profilePicturePath($row['img']);
                    }

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
                        'updatedat' => $row['updatedat']
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
                }
            }

            if ($results) {
                $this->logger->info("Fetched all users from database", ['count' => count($results)]);
            } else {
                $this->logger->warning("No users found in database");
            }

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function fetchAllAdvance(array $args = [], ?string $currentUserId = null,?string $contentFilterBy = null): array
    {
        $this->logger->info("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $trendlimit = 4;
        $trenddays = 7;
        
        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        $whereClauses = ["verified = :verified"];
        // $whereClauses[] = 'status = 0 AND roles_mask = 0 OR roles_mask = 16';
        $whereClausesString = implode(" AND ", $whereClauses);
        
        $sql = sprintf("
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
                ui.reports AS user_reports,
                ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed,
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
            LEFT JOIN users_info ui ON ui.userid = u.uid
            WHERE %s",
            $whereClausesString
        );

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

        $conditions[] = "u.status != :status";
        $queryParams[':status'] = self::STATUS_DELETED;

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY u.createdat DESC LIMIT :limit OFFSET :offset";
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
                    $user_reports = (int)$row['user_reports'];
                    $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];
                    if ($contentFilterService->getContentFilterAction(
                        ContentType::user,
                        ContentType::user,
                        $user_reports,$user_dismiss_moderation_amount,
                        $currentUserId,$row['uid']
                    ) == ContentFilteringAction::replaceWithPlaceholder) {
                        $replacer = ContentReplacementPattern::flagged;
                        $row['username'] = $replacer->username($row['username']);
                        $row['img'] = $replacer->profilePicturePath($row['img']);
                    }

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
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to map user data", ['error' => $e->getMessage(), 'data' => $row]);
                }
            }

            if ($results) {
                $this->logger->info("Fetched all users from database", ['count' => count($results)]);
            } else {
                $this->logger->warning("No users found in database");
            }

            return $results;

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function loadByIdMAin(string $id, int $roles_mask = 0): User|false
    {
        $this->logger->info("UserMapper.loadById started");

        try {
            $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat 
                    FROM users 
                    WHERE uid = :id AND roles_mask = :roles_mask AND status = 0";
            
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->bindValue(':roles_mask', $roles_mask, \PDO::PARAM_INT);
            
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new User($data);
            }

            $this->logger->warning("No user found with id", ['id' => $id]);
            return false;

        } catch (\Throwable $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function loadById(string $id): User|false
    {
        $this->logger->info("UserMapper.loadById started");

        try {
            $sql = "SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, createdat, updatedat 
                    FROM users 
                    WHERE uid = :id AND status != :status";
            
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->bindValue(':status', self::STATUS_DELETED, \PDO::PARAM_STR);
            
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new User($data);
            }

            $this->logger->warning("No user found with id", ['id' => $id]);
            return false;

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
            $this->logger->error("An error occurred", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function loadUserInfoById(string $id): array|false
    {
        $this->logger->info("UserMapper.loadUserInfoById started", ['id' => $id]);

        try {
            $sql = "SELECT uid, username, status, slug, img, biography, updatedat FROM users WHERE uid = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);  // Use bindValue here
            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                $this->logger->info("User info fetched successfully", ['id' => $id]);
                return (new User($data, [], false))->getArrayCopy();
            }

            $this->logger->warning("No user found with id", ['id' => $id]);
            return false;
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching post count", ['userid' => $userid, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public function fetchFriends(
        string $userId, 
        int $offset = 0, 
        int $limit = 10,
        ?string $contentFilterBy = null
    ): ?array {
        $this->logger->info("UserMapper.fetchFriends started", ['userId' => $userId]);

        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        try {
            $sql = "
                SELECT 
                    u.uid, 
                    u.username, 
                    u.slug, 
                    u.status,
                    u.updatedat, 
                    u.biography, 
                    u.img,
                    ui.reports AS user_reports,
                    ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed
                FROM follows f1 
                INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                INNER JOIN users u ON f1.followedid = u.uid 
                LEFT JOIN users_info ui ON ui.userid = u.uid
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
            $filtered_friends = [];


            foreach ($friends as $row) {
                $user_reports = (int)$row['user_reports'];
                $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];

                $frdObj = (new User($row, [], false))->getArrayCopy();

                $row['username'] = $frdObj['username'];
                $row['img'] = $frdObj['img'];
                $row['biography'] = $frdObj['biography'];

                if ($contentFilterService->getContentFilterAction(
                    ContentType::user,
                    ContentType::user,
                    $user_reports,$user_dismiss_moderation_amount
                ) == ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer = ContentReplacementPattern::flagged;
                    $row['username'] = $replacer->username($row['username']);
                    $row['img'] = $replacer->profilePicturePath($row['img']);
                }
                $filtered_friends[] = $row;
            }

            if ($filtered_friends) {
                $this->logger->info("fetchFriends retrieved friends", ['count' => count($filtered_friends)]);
            } else {
                $this->logger->warning("No friends found for user", ['userId' => $userId]);
            }

            return $filtered_friends ?: null;
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFriends", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function fetchFollowers(
        string $userId, 
        string $currentUserId, 
        int $offset = 0, 
        int $limit = 10,
        ?string $contentFilterBy = null
    ): array {
        $this->logger->info("UserMapper.fetchFollowers started", ['userId' => $userId]);

        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        try {
            $sql = "
                SELECT 
                    f.followerid AS uid, 
                    u.username, 
                    u.slug,
                    u.status,
                    u.img,
                    ui.reports AS user_reports,
                    ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed,
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
                LEFT JOIN users_info ui ON ui.userid = u.uid
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
            $filtered_results = [];
            
            foreach ($uniqueResults as $row) {
                $user_reports = (int)$row['user_reports'];
                $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];
                
                if ($contentFilterService->getContentFilterAction(
                    ContentType::user,
                    ContentType::user,
                    $user_reports,$user_dismiss_moderation_amount,
                    $currentUserId,$row['uid']
                ) == ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer = ContentReplacementPattern::flagged;
                    $row['username'] = $replacer->username($row['username']);
                    $row['img'] = $replacer->profilePicturePath($row['img']);
                }
                $filtered_results[] = $row;
            }

            $users = array_map(fn($row) => new ProfilUser($row), $filtered_results);

            $this->logger->info(
                count($users) > 0 ? "fetchFollowers retrieved users" : "No users found",
                ['count' => count($users)]
            );

            return $users;
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFollowers", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function fetchFollowing(
        string $userId, 
        string $currentUserId, 
        int $offset = 0, 
        int $limit = 10,
        ?string $contentFilterBy = null
    ): array {
        $this->logger->info("UserMapper.fetchFollowing started", ['userId' => $userId]);

        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        try {
            $sql = "
                SELECT 
                    f.followedid AS uid, 
                    u.username, 
                    u.slug,
                    u.img,
                    u.status,
                    ui.reports AS user_reports,
                    ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed,
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
                LEFT JOIN users_info ui ON ui.userid = u.uid
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
            $filtered_results = [];
            
            foreach ($uniqueResults as $row) {
                $user_reports = (int)$row['user_reports'];
                $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];
                
                if ($contentFilterService->getContentFilterAction(
                    ContentType::user,
                    ContentType::user,
                    $user_reports,$user_dismiss_moderation_amount,
                    $currentUserId,$row['uid']
                ) == ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer = ContentReplacementPattern::flagged;
                    $row['username'] = $replacer->username($row['username']);
                    $row['img'] = $replacer->profilePicturePath($row['img']);
                }
                $filtered_results[] = $row;
            }

            $users = array_map(fn($row) => new ProfilUser($row), $filtered_results);

            $this->logger->info(
                count($users) > 0 ? "fetchFollowing retrieved users" : "No users found",
                ['count' => count($users)]
            );

            return $users;
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFollowing", ['error' => $e->getMessage()]);
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFollowCounts", ['error' => $e->getMessage()]);
            return ['amountfollower' => 0, 'amountfollowed' => 0, 'isfollowing' => false, 'isfollowed' => false];
        }
    }

    public function fetchProfileData(string $userid, string $currentUserId, ?string $contentFilterBy): Profile|false 
    {
        $whereClauses = ["u.uid = :userid AND u.verified = :verified"];
        // $whereClauses[] = 'u.status = 0';
        $whereClausesString = implode(" AND ", $whereClauses);

        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        $sql = sprintf("
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.biography,
                ui.amountposts,
                ui.amountfollower,
                ui.amountfollowed,
                ui.amountfriends,
                ui.amountblocked,
                ui.reports AS user_reports,
                ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed,
                COALESCE((SELECT COUNT(*) FROM post_info pi WHERE pi.userid = u.uid AND pi.likes > 4 AND pi.createdat >= NOW() - INTERVAL '7 days'), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM follows WHERE followedid = u.uid AND followerid = :currentUserId) AS isfollowing,
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid) AS isfollowed
            FROM users u
            LEFT JOIN users_info ui ON ui.userid = u.uid
            WHERE %s",
            $whereClausesString
         );

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':verified', 1, \PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);


            if ($data !== false) {
                $user_reports = (int)$data['user_reports'];
                $user_dismiss_moderation_amount = (int)$data['user_count_content_moderation_dismissed'];

                if ($contentFilterService->getContentFilterAction(
                    ContentType::user,
                    ContentType::user,
                    $user_reports,$user_dismiss_moderation_amount,
                    $currentUserId,$data['uid']
                ) == ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer = ContentReplacementPattern::flagged;
                    $data['username'] = $replacer->username($data['username']);
                    $data['img'] = $replacer->profilePicturePath($data['img']);
                }

                return new Profile($data);
            }

            $this->logger->warning("No user found with ID", ['userid' => $userid]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchProfileData", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function setPassword(string $password): string
    {
        $this->logger->info("UserMapper.setPassword started");

        if (defined('PASSWORD_ARGON2ID')) {
            $hash = \password_hash($password, PASSWORD_ARGON2ID, [
                'time_cost' => 3,
                'memory_cost' => 65536, // Memory usage (64MB)
                'threads' => 2
            ]);
            $algorithm = 'ARGON2ID';
        } else {
            $hash = \password_hash($password, \PASSWORD_BCRYPT, [
                'cost' => 12
            ]);
            $algorithm = 'BCRYPT';
        }

        $this->logger->info("Password hashed using {$algorithm}");

        return $hash;
    }

    public function createUser(User $userData): ?string
    {
        $this->logger->info("UserMapper.createUser started");

        try {
            $userid = $userData->getUserId();
            $password = $userData->getPassword();

            $hashedPassword = method_exists($this, 'setPassword') ? $this->setPassword($password) : \password_hash($password, \PASSWORD_BCRYPT, ['time_cost' => 4, 'memory_cost' => 2048, 'threads' => 1]);
            $userData->setPassword($hashedPassword);

            if (!$userData->getReferralUuid()) {
                $userData->setReferralUuid($userData->getUserId());
            }
            $this->insert($userData);

            $this->logger->info("Inserted new user into database", ['uid' => $userid]);

            return $userid;
        } catch (\Throwable $e) {
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
        $this->logger->info('UserMapper.insert second', ['data' => $data]);

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
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.insert: Exception occurred while inserting user", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to insert user into database: " . $e->getMessage());
        }
    }
    
    public function insertReferralInfo(string $userId, string $link): void
    {
        $this->logger->info("UserMapper.insertReferralInfo started", [
            'userId' => $userId,
            'link' => $link,
        ]);
    
        try {
            $query = "SELECT 1 FROM user_referral_info WHERE uid = :uid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':uid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
    
            if ($stmt->fetch()) {
                $this->logger->info("Referral link already exists, skipping insert.", ['userId' => $userId]);
                return;
            }
    
            $referralUuid = $userId;
    
            $query = "INSERT INTO user_referral_info (uid, referral_link, referral_uuid)
                      VALUES (:uid, :referral_link, :referral_uuid)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':uid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':referral_link', $link, \PDO::PARAM_STR);
            $stmt->bindValue(':referral_uuid', $referralUuid, \PDO::PARAM_STR);
            $stmt->execute();
    
            $this->logger->info("Referral link inserted successfully.", ['userId' => $userId]);
        } catch (\PDOException $e) {
            $this->logger->error("UserMapper.insertReferralInfo: PDOException", ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->error("UserMapper.insertReferralInfo: Exception", ['error' => $e->getMessage()]);
        }
    }

    public function getReferralInfoByUserId(string $userId): ?array
    {
        $this->logger->info("UserMapper.getReferralInfoByUserId started", [
            'userId' => $userId,
        ]);
    
        $query = "SELECT referral_uuid, referral_link FROM user_referral_info WHERE uid = :uid";
    
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_STR);
        $stmt->execute();
    
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        if (!$result || empty($result['referral_uuid']) || empty($result['referral_link'])) {
            $this->logger->info("No referral info found. Generating new referral for user.", [
                'userId' => $userId,
            ]);
    
            $referralLink = $this->generateReferralLink($userId);
            $this->insertReferralInfo($userId, $referralLink);
    
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':uid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
    
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
    
        $this->logger->info("Referral info query result", ['result' => $result]);
    
        return $result ?: null;
    }

    public function getInviterByInvitee(string $userId): ?array
    {
        $this->logger->info("UserMapper.getInviterByInvitee started", [
            'invitee_uuid' => $userId,
        ]);

        $query = "
        SELECT u.uid, u.status, u.username, u.slug, u.img
        FROM users_info ui
        JOIN users u ON ui.invited = u.uid
        WHERE ui.userid = :invitee_uuid
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':invitee_uuid', $userId, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? ((new User($result, [], false))->getArrayCopy()) : null;
    }

    public function getReferralRelations(string $userId, int $offset = 0, int $limit = 20): array 
    {
        $query = "
            SELECT u.uid, u.status, u.username, u.slug, u.img
            FROM users_info ui
            JOIN users u ON ui.userid = u.uid
            WHERE ui.invited = :userId
            ORDER BY ui.updatedat DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userId', $userId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return [
            'iInvited' => array_map(fn($user) => (new User($user, [], false))->getArrayCopy(), $data)
        ];
    }

    public function generateReferralLink(string $referralUuid): string
    {
        return 'https://frontend.getpeer.eu/register.php?referralUuid=' . $referralUuid;
    }

    public function insertinfo(UserInfo $user): UserInfo
    {
        $this->logger->info("UserMapper.insertinfo started");

        $data = $user->getArrayCopy();

        $query = "INSERT INTO users_info 
                  (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, pkey, updatedat)
                  VALUES 
                  (:userid, :liquidity, :amountposts, :amountfollower, :amountfollowed, :amountfriends, :amountblocked, :isprivate, :invited, :pkey, :updatedat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
            $stmt->bindValue(':amountposts', $data['amountposts'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfollower', $data['amountfollower'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfollowed', $data['amountfollowed'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfriends', $data['amountfriends'], \PDO::PARAM_INT); 
            $stmt->bindValue(':amountblocked', $data['amountblocked'], \PDO::PARAM_INT); 
            $stmt->bindValue(':isprivate', $data['isprivate'], \PDO::PARAM_INT);
            $stmt->bindValue(':invited', $data['invited'], \PDO::PARAM_STR);
            $stmt->bindValue(':pkey', $data['pkey'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR); 

            $stmt->execute();

            $this->logger->info("Inserted new user into database", ['userid' => $data['userid']]);

            return new UserInfo($data);
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.insert: Exception occurred while inserting user", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.update: Exception occurred while updating user", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to update user into database: " . $e->getMessage());
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
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.updatePass: Exception occurred while updating password", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to update password into database: " . $e->getMessage());
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
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.updateProfil: Exception occurred while updating profile", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to update profile in database: " . $e->getMessage());
        }
    }

    /**
     * Delete User Account.
     * Flags the account as deleted by setting status to STATUS_DELETED.
     *
     * Usage of constant improves readability:
     * const STATUS_DELETED = 6;
     *
     * @param string $id User unique identifier (uid).
     * @return bool True if user was flagged as deleted, false otherwise.
     * @throws \RuntimeException if database operation fails.
     */
    public function delete(string $id): bool
    {
        $this->logger->info("UserMapper.delete started");

        $query = "UPDATE users SET status = :status WHERE uid = :uid";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':status', self::STATUS_DELETED, \PDO::PARAM_STR);
            $stmt->bindValue(':uid', $id, \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Deleted user in database", ['id' => $id]);

            return (bool)$stmt->rowCount(); 
        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.delete: Exception occurred while deleting user", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error("Database error in saveOrUpdateAccessToken: ", ['error' => $e]);
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
        } catch (\Throwable $e) {
            $this->logger->error("Database error in saveOrUpdateRefreshToken: ", ['error' => $e]);
        }
    }

    public function fetchAllFriends(int $offset = 0, int $limit = 20): ?array
    {
        $this->logger->info("UserMapper.fetchAllFriends started");

        $sql = "SELECT DISTINCT u1.uid AS follower, u1.username AS followername, u1.slug AS followerslug, u1.status as followerstatus, 
                                u2.uid AS followed, u2.username AS followedname, u2.slug AS followedslug, u2.status as followedstatus
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

            $userResults =  $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $userResultObj = [];
            foreach($userResults as $key => $prt){
                $userObj = [
                        'status' => $prt['followerstatus'],
                        'username' => $prt['followername'],
                    ];
                $userObj = (new User($userObj, [], false))->getArrayCopy();

                $userResultObj[$key] = $prt;
                $userResultObj[$key]['followername'] = $userObj['username'];

                $userObj = [
                        'status' => $prt['followedstatus'],
                        'username' => $prt['followedname'],
                    ];
                $userObj = (new User($userObj, [], false))->getArrayCopy();
                $userResultObj[$key]['followedname'] = $userObj['username'];

            }   
            
            return $userResultObj;
        } catch (\Throwable $e) {
            $this->logger->error('Database error in fetchFriends: ', ['exception' => $e->getMessage()]);
            return null;
        }
    }


    /**
     * Send Actual email to Email.
    */
    public function sendPasswordResetEmail(string $email, array $data): void {
        (new PasswordRestMail($data))->send($email);
    }

    /**
     * Inserts a new password reset request.
     */
    public function createResetRequest(string $userId, string $token, string $updatedAt, string $expiresAt): array
    {
        $sql = "
            INSERT INTO password_reset_requests 
            (user_id, token, attempt_count, updatedat, last_attempt, expires_at)  
            VALUES (:user_id, :token, :attempt_count, :updatedat, :last_attempt, :expires_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':attempt_count', 1);
        $stmt->bindValue(':updatedat', $updatedAt);
        $stmt->bindValue(':last_attempt', $updatedAt);
        $stmt->bindValue(':expires_at', $expiresAt);
        $stmt->execute();

        return [];
    }

    /**
     * Updates an existing reset attempt, incrementing the attempt count.
     */
    public function updateAttempt(array $attempt): bool
    {
        $sql = "
            UPDATE password_reset_requests 
            SET attempt_count = :attempt_count, last_attempt = :last_attempt 
            WHERE token = :token";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':attempt_count', $attempt['attempt_count'] + 1, \PDO::PARAM_INT);
        $stmt->bindValue(':last_attempt', $this->getCurrentTimestamp());
        $stmt->bindValue(':token', $attempt['token']);
        $stmt->execute();

        return true;
    }

    /**
     * Checks for an active (unexpired and unused) password reset request.
     */
    public function checkForPasswordResetExpiry(string $userId): array|bool
    {
        $this->logger->info("UserMapper.checkForPasswordResetExpiry started");

        try {
            $sql = "
                SELECT * FROM password_reset_requests 
                WHERE user_id = :user_id AND expires_at >= :now AND collected = false";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':now', $this->getCurrentTimestamp());
            $stmt->execute();

            $data =  $stmt->fetch(\PDO::FETCH_ASSOC);
            return $data;
        } catch (\Exception $e) {
            $this->logger->error("Error checking reset request", ['error' => $e->getMessage()]);
        }
        return [];
    }
    
    public function loadTokenById(string $id): bool
    {
        $this->logger->info("UserMapper.loadTokenById started");
        $time = (int)\time();

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM refresh_tokens WHERE userid = :id AND expiresat > :expiresat");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':expiresat', $time, \PDO::PARAM_INT);
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;

            $this->logger->info("Refresh_token existence check", ['exists' => $exists]);
            return $exists;
        } catch (\Throwable $e) {
            $this->logger->error("General error while checking if refresh_token exists", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Determines if the first request is being retried too soon.
     */
    public function isFirstAttemptTooSoon(array $attempt): bool
    {
        return $attempt['attempt_count'] === 1 
            && !$attempt['collected'] 
            && time() < strtotime($attempt['updatedat'] . ' +1 minute');
    }

    /**
     * Determines if the second request is being retried too soon.
     */
    public function isSecondAttemptTooSoon(array $attempt): bool
    {
        return $attempt['attempt_count'] === 2 
            && !$attempt['collected'] 
            && time() < strtotime($attempt['last_attempt'] . ' +10 minutes');
    }
    
    /**
     * Returns a response indicating the user should retry after a delay.
     */
    public function rateLimitResponse(int $waitMinutes, ?string $lastAttempt = null): array
    {
        $remaining = $waitMinutes;

        if ($lastAttempt) {
            $remaining = ceil((strtotime($lastAttempt . " +{$waitMinutes} minutes") - time()) / 60);
        }

        $nextAttemptAt = DateService::nowPlusMinutes($remaining);
        
        return [
            'status' => 'error',
            'ResponseCode' => 31901,
            'nextAttemptAt' => $nextAttemptAt
        ];
    }

    /**
     * Returns a response when user has made too many attempts.
     */
    public function tooManyAttemptsResponse(): array
    {
        return [
            'status' => 'error',
            'ResponseCode' => 31903
        ];
    }

    /**
     * Returns the current timestamp in microsecond precision.
     */
    private function getCurrentTimestamp(): string
    {
        return date("Y-m-d H:i:s.u");
    }


    /**
     * Fetch password reset request by token if valid and not expired.
     *
     * @param string $token
     * @return array|null
     */
    public function getPasswordResetRequest(string $token): ?array
    {
        $sql = "
            SELECT * FROM password_reset_requests 
            WHERE token = :token 
            AND expires_at >= :current_time 
            AND collected = false
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':token', $token, \PDO::PARAM_STR);
        $stmt->bindValue(':current_time', $this->getCurrentTimestamp(), \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Delete password reset token from database.
     *
     * @param string $token
     * @return void
     */
    public function deletePasswordResetToken(string $token): void
    {
        $sql = "DELETE FROM password_reset_requests WHERE token = :token";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':token', $token, \PDO::PARAM_STR);
        $stmt->execute();
    }


    // public function insertoken(array $args): void
    public function insertoken(Tokenize $data): ?Tokenize
    {
        $this->logger->info("UserMapper.insertoken started");

        $data = $data->getArrayCopy();
        $this->logger->info('UserMapper.insertoken second', ['data' => $data]);

        $query = "INSERT INTO token_holders 
                  (token, userid, expiresat)
                  VALUES 
                  (:token, :userid, :expiresat)";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':token', $data['token'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':expiresat', $data['expiresat'], \PDO::PARAM_INT);

            $stmt->execute();

            $this->logger->info("Inserted new token into database", ['userid' => $data['userid']]);
            return new Tokenize($data);

        } catch (\Throwable $e) {
            $this->logger->error("UserMapper.insertoken: Exception occurred while inserting token", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
        return null;
    }

    public function getValidReferralInfoByLink(string $referralLink): array|null
    {
        $this->logger->info("UserMapper.getValidReferralInfoByLink started", [
            'referralLink' => $referralLink,
        ]);

        $accountsResult = $this->pool->returnAccounts();


		if (isset($accountsResult['status']) && $accountsResult['status'] === 'error') {
			$this->logger->warning('Incorrect returning Accounts', ['Error' => $accountsResult['status']]);
			return null;
		}
        
        $this->poolWallet = $accountsResult['response']['pool'];
        $this->burnWallet = $accountsResult['response']['burn'];
        $this->peerWallet = $accountsResult['response']['peer'];
        // $this->btcpool = $accountsResult['response']['btcpool'];
    
        $query = "SELECT ur.referral_uuid, ur.referral_link, u.username, u.slug, u.img, u.uid FROM user_referral_info ur LEFT JOIN users u ON u.uid = ur.user_uuid  WHERE u.status = 0 AND ur.referral_uuid = :referral_uuid";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':referral_uuid', $referralLink, \PDO::PARAM_STR);
        $stmt->execute();
    
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        $this->logger->info("Referral info query result", ['result' => $result]);

        if (isset($result['referral_uuid']) && $this->poolWallet == $result['referral_uuid'] || $this->burnWallet == $result['referral_uuid'] || $this->peerWallet == $result['referral_uuid']) {
            $this->logger->warning('Unauthorized to send token');
            return null;
        }

        return $result ?: null;
    }
}
