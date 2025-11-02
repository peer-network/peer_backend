<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use PDO;
use Fawaz\App\User;
use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\App\UserAdvanced;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Status;

class ProfileRepositoryImpl implements ProfileRepository
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db) {
    }

    public function fetchAll(string $currentUserId, array $args = []): array
    {
        $this->logger->debug("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $whereClauses = ["verified = :verified"];
        $whereClauses[] = 'status = 0 AND roles_mask = 0 OR roles_mask = 16';
        $whereClausesString = implode(" AND ", $whereClauses);

        $sql = sprintf(
            "
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
                ui.reports AS user_reports
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
        $queryParams[':status'] = Status::DELETED;

        $sql .= " AND " . implode(" AND ", $conditions);

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
                $this->logger->debug("UserMapper.fetchAll.row started");
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

    public function fetchAllAdvance(array $args = [], ?string $currentUserId = null, ?string $contentFilterBy = null): array
    {
        $this->logger->debug("UserMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $trendlimit = 4;
        $trenddays = 7;

        $whereClauses = ["verified = :verified"];
        $includeDeleted = !empty($args['includeDeleted']);
        if ($includeDeleted) {
            unset($args['includeDeleted']);
        }
        // $whereClauses[] = 'status = 0 AND roles_mask = 0 OR roles_mask = 16';
        $whereClausesString = implode(" AND ", $whereClauses);

        $sql = sprintf(
            "
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

        if (!$includeDeleted) {
            $conditions[] = 'u.status != :statusExcluded';
            $queryParams[':statusExcluded'] = Status::DELETED;
        }

        if (!empty($conditions)) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
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
                $this->logger->debug("UserMapper.fetchAll.row started");
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

    public function fetchProfileData(string $userid, string $currentUserId, array $specifications): ?Profile {
        $specsSQL = array_map(fn(Specification $spec) => $spec->toSql(ContentType::user), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;
        $whereClauses[] = "u.uid = :userid";
        $whereClausesString = implode(" AND ", $whereClauses);
        $params = $allSpecs->paramsToPrepare;

        $sql = sprintf("
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.biography,
                u.visibility_status,
                ui.amountposts,
                ui.amountfollower,
                ui.amountfollowed,
                ui.amountfriends,
                ui.amountblocked,
                ui.reports AS user_reports,
                COALESCE((SELECT COUNT(*) FROM post_info pi WHERE pi.userid = u.uid AND pi.likes > 4 AND pi.createdat >= NOW() - INTERVAL '7 days'), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM follows WHERE followedid = u.uid AND followerid = :currentUserId) AS isfollowing,
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid) AS isfollowed
            FROM users u
            LEFT JOIN users_info ui ON ui.userid = u.uid
            WHERE %s",
            $whereClausesString
         );

        $stmt = $this->db->prepare($sql);
        $params['userid'] = $userid;
        $params['currentUserId'] = $currentUserId;

        $stmt->execute($params);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);


        if ($data === false) {
            $this->logger->warning("No user found with ID", ['userid' => $userid]);
            return null;
        }
        return new Profile($data);
    }

    /**
     * Fetch multiple profiles by IDs with optional specifications merged into WHERE clause.
     * Returns an array of Profile objects; empty if none found or on error.
     *
     * @param array<int, string> $userIds
     * @param array<int, Specification> $specifications
     * @return array<string,Profile>
     */
    public function fetchByIds(array $userIds, string $currentUserId, array $specifications = []): array
    {
        $this->logger->debug('ProfileRepository.fetchByIds started', [
            'count' => count($userIds),
        ]);

        if (empty($userIds)) {
            return [];
        }
        
        // Merge specification SQL parts (WHERE/params) similar to fetchProfileData
        $specsSQL = array_map(fn(Specification $spec) => $spec->toSql(ContentType::user), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;

        // Build positional placeholders for the IN clause
        $params = $allSpecs->paramsToPrepare;
        $userIdsForInStatement = array_map(fn($id) => "'$id'", $userIds);

        $userIdsString = implode(',', $userIdsForInStatement);
        $whereClauses[] = "u.uid IN ($userIdsString)";
        $whereClausesString = implode(' AND ', $whereClauses);
        $sql = sprintf(
            "
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.biography,
                u.visibility_status,
                ui.amountposts,
                ui.amountfollower,
                ui.amountfollowed,
                ui.amountfriends,
                ui.amountblocked,
                ui.reports AS user_reports,
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
            $params['currentUserId'] = $currentUserId;
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!$rows) {
                return [];
            }

            // Map to Profile objects
            $profiles = [];
            foreach ($rows as $row) {
                try {
                    $profile = new Profile($row);
                    $profiles[$profile->getUserId()] = $profile;
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to map profile row', ['error' => $e->getMessage(), 'row' => $row]);
                }
            }
            return $profiles;
        } catch (\Throwable $e) {
            $this->logger->error('Database error in fetchByIds', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
