<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use PDO;
use Fawaz\App\User;
use Fawaz\App\UserInfo;
use Fawaz\Utils\PeerLoggerInterface;

class UserInfoMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userId, string $currentUserId): bool
    {
        return $userId === $currentUserId;
    }

    public function loadInfoById(string $id): UserInfo|false
    {
        $this->logger->debug('UserInfoMapper.loadInfoById started', ['id' => $id]);

        try {
            $stmt = $this->db->prepare(
                'SELECT userid, liquidity, amountposts, amountblocked, amountfollower, reports, 
                        amountfollowed, amountfriends, isprivate, invited, updatedat 
                 FROM users_info 
                 WHERE userid = :id'
            );

            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                $this->logger->info('User info loaded successfully', ['id' => $id, 'data' => $data]);
                return new UserInfo($data);
            } else {
                $this->logger->warning("No user found with given ID", ['id' => $id]);
                return false;
            }
        } catch (\PDOException $e) {
            $this->logger->error("Database error in loadInfoById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in loadInfoById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function update(UserInfo $user): UserInfo
    {
        $userid = $user->getUserId();
        $this->logger->debug('UserInfoMapper.update started', ['userid' => $userid]);

        try {
            $user->setUpdatedAt();
            $data = $user->getArrayCopy();

            $query = "UPDATE users_info 
                      SET liquidity = :liquidity, 
                          amountposts = :amountposts, 
                          amountfollower = :amountfollower, 
                          amountfollowed = :amountfollowed, 
                          amountfriends = :amountfriends, 
                          amountblocked = :amountblocked, 
                          isprivate = :isprivate, 
                          reports = :reports, 
                          totalreports = :totalreports, 
                          invited = :invited,
                          phone = :phone,                          
                          pkey = :pkey,
                          updatedat = :updatedat 
                      WHERE userid = :userid";

            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
            $stmt->bindValue(':amountposts', $data['amountposts'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfollower', $data['amountfollower'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfollowed', $data['amountfollowed'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountfriends', $data['amountfriends'], \PDO::PARAM_INT);
            $stmt->bindValue(':amountblocked', $data['amountblocked'], \PDO::PARAM_INT);
            $stmt->bindValue(':isprivate', $data['isprivate'], \PDO::PARAM_INT);
            $stmt->bindValue(':totalreports', $data['totalreports'], \PDO::PARAM_INT);
            $stmt->bindValue(':reports', $data['reports'], \PDO::PARAM_INT);
            $stmt->bindValue(':invited', $data['invited'], \PDO::PARAM_STR);
            $stmt->bindValue(':phone', $data['phone'], \PDO::PARAM_STR);
            $stmt->bindValue(':pkey', $data['pkey'], \PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->logger->info("User info updated successfully", ['userid' => $data['userid']]);
            } else {
                $this->logger->warning("No changes made to user info", ['userid' => $data['userid']]);
            }

            return new UserInfo($data);
        } catch (\PDOException $e) {
            $this->logger->error("Database error in update", [
                'userid' => $userid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in update", [
                'userid' => $userid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function fetchFriends(string $userid): array
    {
        $this->logger->debug("UserInfoMapper.fetchFriends started", ['userid' => $userid]);

        try {
            $sql = "SELECT u.uid, u.username, u.slug, u.updatedat, u.biography, u.img 
                    FROM follows f1 
                    INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                    INNER JOIN users u ON f1.followedid = u.uid 
                    WHERE f1.followerid = :userid 
                    AND f2.followedid = :userid";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error("Database error in fetchFriends: " . $e->getMessage(), ['userid' => $userid]);
            return [];
        }
    }

    private function getFriends(string $currentUserId): array|null
    {
        $this->logger->debug('UserInfoMapper.getFriends started');
        $users = $this->fetchFriends($currentUserId);

        if ($users) {
            return $users;
        }

        return null;
    }

    public function toggleUserFollow(string $followerid, string $followeduserid): array
    {
        $this->logger->info('UserInfoMapper.toggleUserFollow started', ['follower_id' => $followerid,
        'followed_user_id' => $followeduserid]);

        try {

            $insertQuery = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid) ON CONFLICT (followerid, followedid) DO NOTHING";
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->updateFollowCounts($followerid, 1, "amountfollowed");
                $this->updateFollowCounts($followeduserid, 1, "amountfollower");

                $action = true;
                $response = "11104";

                $this->logger->info('Follow relationship created', [
                    'followerid' => $followerid,
                    'followeduserid' => $followeduserid
                ]);
            } else {
                $deleteQuery = "DELETE FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
                $stmt = $this->db->prepare($deleteQuery);
                $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
                $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $this->updateFollowCounts($followerid, -1, "amountfollowed");
                    $this->updateFollowCounts($followeduserid, -1, "amountfollower");

                    $action = false;
                    $response = "11103";

                    $this->logger->info('Follow relationship removed', [
                        'followerid' => $followerid,
                        'followeduserid' => $followeduserid
                    ]);
                } else {
                    $this->logger->warning('Follow relationship disappeared during toggle', [
                        'followerid' => $followerid,
                        'followeduserid' => $followeduserid
                    ]);

                    $action = false;
                    $response = "11103";
                }
            }

            $this->updateFriendsCount($followerid);
            $this->updateFriendsCount($followeduserid);


            return ['status' => 'success', 'ResponseCode' => $response, 'isfollowing' => $action];

        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle user follow', ['exception' => $e]);
            return ['status' => 'error', 'ResponseCode' => "41103"];
        }
    }

    private function updateFollowCounts(string $userId, int $change, string $column): void
    {
        $allowedColumns = ['amountfollowed', 'amountfollower'];

        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid column name: $column");
        }

        $query = "UPDATE users_info SET $column = GREATEST($column + :change, 0) WHERE userid = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':change', $change, \PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
        $stmt->execute();
    }

    private function updateFriendsCount(string $userId): void
    {
        $query = "
            UPDATE users_info 
            SET amountfriends = (
                SELECT COUNT(*) 
                FROM follows f1 
                INNER JOIN follows f2 ON f1.followedid = f2.followerid 
                WHERE f1.followerid = :userId 
                AND f2.followedid = :userId
            ) 
            WHERE userid = :userId
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
        $stmt->execute();
    }

    public function isUserExistById(string $userId): bool
    {
        $this->logger->debug('UserInfoMapper.isUserExistById started', ['userId' => $userId]);

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :userId");
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();

            $exists = $stmt->fetchColumn() > 0;

            $this->logger->info('Checked user existence', ['userId' => $userId, 'exists' => $exists]);

            return $exists;
        } catch (\PDOException $e) {
            $this->logger->error('Database error in isUserExistById', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in isUserExistById', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function toggleUserBlock(string $blockerid, string $blockedid): array
    {
        $this->logger->debug('UserInfoMapper.toggleUserBlock started', [
            'blockerid' => $blockerid,
            'blockedid' => $blockedid
        ]);

        try {

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

                $queryUpdateBlocker = "UPDATE users_info SET amountblocked = GREATEST(amountblocked - 1, 0) WHERE userid = :blockerid";
                $stmt = $this->db->prepare($queryUpdateBlocker);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->execute();

                $action = false;
                $response = "11106";
            } else {
                // Block the user
                $query = "INSERT INTO user_block_user (blockerid, blockedid) VALUES (:blockerid, :blockedid)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->bindValue(':blockedid', $blockedid, \PDO::PARAM_STR);
                $stmt->execute();

                $queryUpdateBlocker = "UPDATE users_info SET amountblocked = amountblocked + 1 WHERE userid = :blockerid";
                $stmt = $this->db->prepare($queryUpdateBlocker);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->execute();

                $action = true;
                $response = "11105";
            }

            return ['status' => 'success', 'ResponseCode' => $response, 'isBlocked' => $action];

        } catch (\PDOException $e) {
            $this->logger->error('Database error in toggleUserBlock', [
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'ResponseCode' => "41106"];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in toggleUserBlock', [
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'ResponseCode' => "41106"];
        }
    }

    public function getBlockRelations(
        string $myUserId,
        array $specifications,
    ): array {
        $this->logger->info('Fetching block relationships', [
            'myUserId' => $myUserId,
        ]);

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        // Build WHERE/params from specs, targeting users as alias `u`
        $specsSQL = array_map(fn (Specification $spec) => $spec->toSql(\Fawaz\Services\ContentFiltering\Types\ContentType::user), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);

        // BlockedBy: who blocked me -> select blocker user as `u`
        $blockedByWhere = $allSpecs->whereClauses;
        $blockedByWhere[] = 'ub.blockedid = :myUserId';
        $blockedByWhereStr = implode(' AND ', $blockedByWhere);
        $blockedByParams = $allSpecs->paramsToPrepare;
        $blockedByParams['myUserId'] = $myUserId;
        $blockedByParams['limit'] = $limit;
        $blockedByParams['offset'] = $offset;

        // IBlocked: whom I blocked -> select blocked user as `u`
        $iBlockedWhere = $allSpecs->whereClauses;
        $iBlockedWhere[] = 'ub.blockerid = :myUserId';
        $iBlockedWhereStr = implode(' AND ', $iBlockedWhere);
        $iBlockedParams = $allSpecs->paramsToPrepare;
        $iBlockedParams['myUserId'] = $myUserId;
        $iBlockedParams['limit'] = $limit;
        $iBlockedParams['offset'] = $offset;

        // Query for users who blocked me
        $sqlBlockedBy = "
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.roles_mask,
                u.verified,
                ui.reports AS user_reports,
                u.visibility_status
            FROM user_block_user ub
            JOIN users u ON ub.blockerid = u.uid
            LEFT JOIN users_info ui ON ui.userid = u.uid
            WHERE $blockedByWhereStr
            ORDER BY ub.createdat DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sqlBlockedBy);
        $stmt->execute($blockedByParams);
        $blockedByRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Query for users I blocked
        $sqlIBlocked = "
            SELECT 
                u.uid,
                u.username,
                u.slug,
                u.status,
                u.img,
                u.roles_mask,
                u.verified,
                ui.reports AS user_reports,
                u.visibility_status
            FROM user_block_user ub
            JOIN users u ON ub.blockedid = u.uid
            LEFT JOIN users_info ui ON ui.userid = u.uid
            WHERE $iBlockedWhereStr
            ORDER BY ub.createdat DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sqlIBlocked);
        $stmt->execute($iBlockedParams);
        $iBlockedRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $blockedBy = array_map(fn ($row) => new Profile($row), $blockedByRows);

        $iBlocked = array_map(fn ($row) => new Profile($row), $iBlockedRows);
        $this->logger->info('Fetched block relationships', [
            'blockedByCount' => count($blockedBy),
            'iBlockedCount' => count($iBlocked),
            'total' => count($blockedBy) + count($iBlocked)
        ]);

        return [
            'blockedBy' => $blockedBy,
            'iBlocked' => $iBlocked,
        ];
    }
}
