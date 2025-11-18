<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Services\ContentFiltering\Types\ContentType;
use PDO;
use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\PeerLoggerInterface;

class ProfileRepositoryImpl implements ProfileRepository
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchProfileData(string $userid, string $currentUserId, array $specifications): ?Profile
    {
        $specsSQL = array_map(fn (Specification $spec) => $spec->toSql(ContentType::user), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;
        $whereClauses[] = "u.uid = :userid";
        $whereClausesString = implode(" AND ", $whereClauses);
        $params = $allSpecs->paramsToPrepare;

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
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid) AS isfollowed,
                EXISTS (SELECT 1 FROM user_reports  WHERE targetid = u.uid AND reporter_userid = :currentUserId) AS isreported
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
        $specsSQL = array_map(fn (Specification $spec) => $spec->toSql(ContentType::user), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;

        // Build positional placeholders for the IN clause
        $params = $allSpecs->paramsToPrepare;
        $userIdsForInStatement = array_map(fn ($id) => "'$id'", $userIds);

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
                EXISTS (SELECT 1 FROM follows WHERE followedid = :currentUserId AND followerid = u.uid) AS isfollowed,
                EXISTS (SELECT 1 FROM user_reports  WHERE targetid = u.uid AND reporter_userid = :currentUserId) AS isreported
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
