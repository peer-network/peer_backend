<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\User;
use Fawaz\App\UserBlock;
use Fawaz\App\UserInfo;
use Psr\Log\LoggerInterface;

class UserInfoMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userId, string $currentUserId): bool
    {
        return $userId === $currentUserId;
    }

    public function loadInfoById(string $id): UserInfo|false
    {
        $this->logger->info('UserInfoMapper.loadInfoById started', ['id' => $id]);

        try {
            $stmt = $this->db->prepare(
                'SELECT userid, liquidity, amountposts, amountblocked, amountfollower, 
                        amountfollowed, amountfriends, isprivate, updatedat 
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
        $this->logger->info('UserInfoMapper.update started', ['userid' => $user->getUserId()]);

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

    public function loadById(string $id): User|false
    {
        $this->logger->info('UserInfoMapper.loadById started', ['id' => $id]);

        try {
            $stmt = $this->db->prepare(
                'SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, updatedat, createdat 
                 FROM users 
                 WHERE uid = :id'
            );

            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                $this->logger->info('User loaded successfully', ['id' => $id, 'user' => $data]);
                return new User($data);
            } else {
                $this->logger->warning("No user found with the given ID", ['id' => $id]);
                return false;
            }
        } catch (\PDOException $e) {
            $this->logger->error("Database error in loadById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in loadById", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function updateUsers(User $user): ?User
    {
        $this->logger->info('UserInfoMapper.updateUsers started');

        try {
            $user->setUpdatedAt();
            $user->setIp();
            $data = $user->getArrayCopy();

            $query = "UPDATE users 
                      SET email = :email, 
                          username = :username, 
                          password = :password, 
                          status = :status, 
                          verified = :verified, 
                          slug = :slug, 
                          roles_mask = :roles_mask, 
                          ip = :ip, 
                          img = :img, 
                          biography = :biography, 
                          updatedat = :updatedat, 
                          createdat = :createdat 
                      WHERE uid = :uid";

            $stmt = $this->db->prepare($query);

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
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);
            $stmt->bindValue(':uid', $data['uid'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("User updated successfully", ['user' => $data]);

            return new User($data);
        } catch (\PDOException $e) {
            $this->logger->error('Database error in updateUsers', [
                'error' => $e->getMessage()
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in updateUsers', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function fetchFriends(string $userid): array
    {
        $this->logger->info("UserInfoMapper.fetchFriends started", ['userid' => $userid]);

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
        $this->logger->info('UserInfoMapper.getFriends started');
        $users = $this->fetchFriends($currentUserId);

        if ($users) {
            return $users;
        } 

        return null;
    }

    public function toggleUserFollow(string $followerid, string $followeduserid): array
    {
        $this->logger->info('UserInfoMapper.toggleUserFollow started');

        try {
            $this->db->beginTransaction();

            $query = "SELECT COUNT(*) FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {

                $query = "DELETE FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
                $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
                $stmt->execute();

                $this->updateFollowCounts($followerid, -1, "amountfollowed");
                $this->updateFollowCounts($followeduserid, -1, "amountfollower");

                $action = false;
                $response = 'Unfollow successful.';
            } else {

                $query = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
                $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
                $stmt->execute();

                $this->updateFollowCounts($followerid, 1, "amountfollowed");
                $this->updateFollowCounts($followeduserid, 1, "amountfollower");

                $action = true;
                $response = 'Follow successful.';
            }

            $this->updateChatsStatus($followerid, $followeduserid);
            $this->updateFriendsCount($followerid);
            $this->updateFriendsCount($followeduserid);

            $this->db->commit();

            return ['status' => 'success', 'ResponseCode' => $response, 'isfollowing' => $action];

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to toggle user follow', ['exception' => $e]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user follow'];
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

    private function updateChatsStatus(string $followerid, string $followeduserid): void
    {
        $this->logger->info('UserInfoMapper.fetchFriends started', ['userid' => $followerid]);

        try {
            $friends = $this->getFriends($followerid);

            if (!is_array($friends) || empty($friends)) {
                throw new \InvalidArgumentException('NO FRIENDS FOUND OR AN ERROR OCCURRED IN FETCHING FRIENDS');
            }

            $friendIds = array_column($friends, 'uid');
            $this->logger->info('Fetched friend IDs', ['friendIds' => $friendIds]);

            if (!in_array($followeduserid, $friendIds)) {
                $queryRestoreAccess = "UPDATE chats 
                    SET ispublic = 9
                    WHERE ispublic = 0 
                    AND chatid IN (
                        SELECT c.chatid FROM chats c
                        JOIN chatparticipants cp1 ON c.chatid = cp1.chatid
                        JOIN chatparticipants cp2 ON c.chatid = cp2.chatid
                        WHERE cp1.userid = :followerid 
                        AND cp2.userid = :followeduserid
                )";
                $this->logger->info('Setting chat visibility to 9 (private)', ['followerid' => $followerid, 'followeduserid' => $followeduserid]);
            } else {
                $queryRestoreAccess = "UPDATE chats 
                    SET ispublic = 0
                    WHERE ispublic = 9 
                    AND chatid IN (
                        SELECT c.chatid FROM chats c
                        JOIN chatparticipants cp1 ON c.chatid = cp1.chatid
                        JOIN chatparticipants cp2 ON c.chatid = cp2.chatid
                        WHERE cp1.userid = :followerid 
                        AND cp2.userid = :followeduserid
                )";
                $this->logger->info('Restoring chat visibility to 0 (public)', ['followerid' => $followerid, 'followeduserid' => $followeduserid]);
            }

            $stmt = $this->db->prepare($queryRestoreAccess);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();

            $this->logger->info('Query.setFollowUserResponse Resolvers', ['uid' => $followerid]);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Failed to toggle user follow', [
                'exception' => $e->getMessage(),
                'uid' => $followerid
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in updateChatsStatus', [
                'exception' => $e->getMessage(),
                'uid' => $followerid
            ]);
        }
    }

    public function isUserExistById(string $userId): bool
    {
        $this->logger->info('UserInfoMapper.isUserExistById started', ['userId' => $userId]);

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

                $queryUpdateBlocker = "UPDATE users_info SET amountblocked = GREATEST(amountblocked - 1, 0) WHERE userid = :blockerid";
                $stmt = $this->db->prepare($queryUpdateBlocker);
                $stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
                $stmt->execute();

                $action = false;
                $response = 'User unblocked successfully.';
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
                $response = 'User blocked successfully.';
            }

            $this->db->commit();
            return ['status' => 'success', 'ResponseCode' => $response, 'isBlocked' => $action];

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Database error in toggleUserBlock', [
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user block'];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Unexpected error in toggleUserBlock', [
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user block'];
        }
    }

    public function getBlockRelations(string $myUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info('Fetching block relationships', ['myUserId' => $myUserId]);

        $query = "
            SELECT 
                ub.blockerid, blocker.slug AS blocker_slug, blocker.img AS blocker_img, blocker.username AS blocker_username, 
                ub.blockedid, blocked.slug AS blocked_slug, blocked.img AS blocked_img, blocked.username AS blocked_username
            FROM user_block_user ub
            JOIN users blocker ON ub.blockerid = blocker.uid
            JOIN users blocked ON ub.blockedid = blocked.uid
            WHERE ub.blockerid = :myUserId OR ub.blockedid = :myUserId
            ORDER BY ub.createdat DESC
            LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':myUserId', $myUserId, \PDO::PARAM_STR);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $blockedBy = []; 
            $iBlocked = []; 

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($row['blockedid'] === $myUserId) {
                    $blockedBy[] = [
                        'userid' => $row['blockerid'],
                        'img' => $row['blocker_img'],
                        'username' => $row['blocker_username'],
                        'slug' => $row['blocker_slug'],
                    ];
                } elseif ($row['blockerid'] === $myUserId) {
                    $iBlocked[] = [
                        'userid' => $row['blockedid'],
                        'img' => $row['blocked_img'],
                        'username' => $row['blocked_username'],
                        'slug' => $row['blocked_slug'],
                    ];
                }
            }

            $counter = count($blockedBy) + count($iBlocked); 

            $this->logger->info("Fetched block relationships", [
                'blockedByCount' => count($blockedBy),
                'iBlockedCount' => count($iBlocked),
                'total' => $counter
            ]);

            return [
                'status' => 'success',
                'counter' => $counter,
                'ResponseCode' => "BlockRelations data prepared successfully",
                'affectedRows' => [
                    'blockedBy' => $blockedBy,
                    'iBlocked' => $iBlocked
                ]
            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching block relationships", ['error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'ResponseCode' => "Database error while fetching block relationships",
                'affectedRows' => []
            ];
        }
    }

    public function updateUserInfoStats(string $userid): array
    {
        $this->logger->info('UserInfoMapper.updateUserInfoStats started', ['userid' => $userid]);
        $trenddays = 7;

        try {
            $this->db->beginTransaction();

            $queries = [
                'amountposts' => "SELECT COUNT(*) FROM posts WHERE userid = :userid",
                'amounttrending' => "SELECT COALESCE(SUM(w.numbers), 0) 
                                     FROM logwins w
                                     INNER JOIN posts p ON w.postid = p.postid 
                                     WHERE p.userid = :userid 
                                       AND w.createdat >= NOW() - INTERVAL :trenddays DAY",
                'amountfollower' => "SELECT COUNT(*) FROM follows WHERE followedid = :userid",
                'amountfollowed' => "SELECT COUNT(*) FROM follows WHERE followerid = :userid",
                'amountchats' => "SELECT COUNT(*) FROM chats WHERE creatorid = :userid",
                'amountcomments' => "SELECT COUNT(*) FROM user_post_comments WHERE userid = :userid",
                'amountblocked' => "SELECT COUNT(*) FROM user_block_user WHERE blockerid = :userid",
                'amountlikes' => "SELECT COUNT(*) FROM user_post_likes WHERE userid = :userid",
                'amountdislikes' => "SELECT COUNT(*) FROM user_post_dislikes WHERE userid = :userid",
                'amountreports' => "SELECT COUNT(*) FROM user_post_reports WHERE userid = :userid",
                'amountsaves' => "SELECT COUNT(*) FROM user_post_saves WHERE userid = :userid",
                'amountshares' => "SELECT COUNT(*) FROM user_post_shares WHERE userid = :userid",
                'amountviews' => "SELECT COUNT(*) FROM user_post_views WHERE userid = :userid"
            ];

            $updates = [];
            foreach ($queries as $field => $query) {
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);

                if ($field === 'amounttrending') {
                    $stmt->bindValue(':trenddays', $trenddays, \PDO::PARAM_INT);
                }

                $stmt->execute();
                $updates[$field] = (int) $stmt->fetchColumn();
            }

            $updateParts = [];
            $params = [':userid' => $userid];

            foreach ($updates as $field => $value) {
                $updateParts[] = "$field = :$field";
                $params[":$field"] = $value;
            }

            $updateQuery = "UPDATE users_info SET " . implode(', ', $updateParts) . " WHERE userid = :userid";
            $stmt = $this->db->prepare($updateQuery);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            }

            $stmt->execute();
            $this->db->commit();

            return [
                'status' => 'success',
                'message' => 'User stats updated successfully',
                'updated_data' => $updates
            ];
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Database error in updateUserInfoStats', [
                'userid' => $userid,
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'message' => 'Failed to update user stats'];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Unexpected error in updateUserInfoStats', [
                'userid' => $userid,
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'message' => 'Failed to update user stats'];
        }
    }
}
