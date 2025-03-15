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
        $this->logger->info('UserInfoMapper.loadInfoById started');

        $stmt = $this->db->prepare('SELECT userid, liquidity, amountposts, amountblocked, amountfollower, amountfollowed, isprivate, updatedat FROM users_info WHERE userid = :id');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            $this->logger->info('load Info By Id', ['data' => $data]);
            return new UserInfo($data);
        }

        $this->logger->warning("No user found with id", ['id' => $id]);

        return false;
    }

    public function update(UserInfo $user): UserInfo
    {
        $this->logger->info('UserInfoMapper.update started');

        $user->setUpdatedAt();
        $data = $user->getArrayCopy();
        $query = "UPDATE users_info SET liquidity = :liquidity, amountposts = :amountposts, amountblocked = :amountblocked, amountfollower = :amountfollower, amountfollowed = :amountfollowed, isprivate = :isprivate, updatedat = :updatedat WHERE userid = :userid";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Updated user in database", ['user' => $data]);

        return new UserInfo($data);
    }

    public function loadById(string $id): User|false
    {
        $this->logger->info('UserInfoMapper.loadById started');

        $stmt = $this->db->prepare('SELECT uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography, updatedat, createdat FROM users WHERE uid = :id');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new User($data);
        }

        $this->logger->warning("No user found with id", ['id' => $id]);

        return false;
    }

    public function updateUsers(User $user): User
    {
        $this->logger->info('UserInfoMapper.updateUsers started');

        $user->setUpdatedAt();
        $data = $user->getArrayCopy();
        $query = "UPDATE users SET email = :email, username = :username, password = :password, status = :status, verified = :verified, slug = :slug, roles_mask = :roles_mask, ip = :ip, img = :img, biography = :biography, updatedat = :updatedat, createdat = :createdat WHERE uid = :uid";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Updated user in database", ['user' => $data]);

        return new User($data);
    }

    public function toggleUserFollow(string $followerid, string $followeduserid): array
    {
        $this->logger->info('UserInfoMapper.toggleUserFollow started');

        try {
            $this->db->beginTransaction();
            
            $query = "SELECT COUNT(*) FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['followerid' => $followerid, 'followeduserid' => $followeduserid]);
            
            if ($stmt->fetchColumn() > 0) {
                $query = "DELETE FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['followerid' => $followerid, 'followeduserid' => $followeduserid]);

                $queryUpdateFollower = "UPDATE users_info SET amountfollowed = amountfollowed - 1 WHERE userid = :followerid";
                $stmt = $this->db->prepare($queryUpdateFollower);
                $stmt->execute(['followerid' => $followerid]);

                $queryUpdateFollowed = "UPDATE users_info SET amountfollower = amountfollower - 1 WHERE userid = :followeduserid";
                $stmt = $this->db->prepare($queryUpdateFollowed);
                $stmt->execute(['followeduserid' => $followeduserid]);

                $action = false;
                $response = 'UnFollow user set successful.';
            } else {
                $query = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid)";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['followerid' => $followerid, 'followeduserid' => $followeduserid]);

                $queryUpdateFollower = "UPDATE users_info SET amountfollowed = amountfollowed + 1 WHERE userid = :followerid";
                $stmt = $this->db->prepare($queryUpdateFollower);
                $stmt->execute(['followerid' => $followerid]);

                $queryUpdateFollowed = "UPDATE users_info SET amountfollower = amountfollower + 1 WHERE userid = :followeduserid";
                $stmt = $this->db->prepare($queryUpdateFollowed);
                $stmt->execute(['followeduserid' => $followeduserid]);

                $action = true;
                $response = 'Follow user set successful.';
            }

            $this->db->commit();

            return ['status' => 'success', 'ResponseCode' => $response, 'isfollowing' => $action];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to toggle user follow', ['exception' => $e]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user follow'];
        }
    }

    public function isUserExistById(string $id): bool
    {
        $this->logger->info('UserInfoMapper.isUserExistById started');

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

	public function toggleUserBlock(string $blockerid, string $blockedid): array
	{
		$this->logger->info('UserInfoMapper.toggleUserBlock started');

		try {
			$this->db->beginTransaction();
			
			$query = "SELECT COUNT(*) FROM user_block_user WHERE blockerid = :blockerid AND blockedid = :blockedid";
			$stmt = $this->db->prepare($query);
			$stmt->execute(['blockerid' => $blockerid, 'blockedid' => $blockedid]);
			
			if ($stmt->fetchColumn() > 0) {
				$query = "DELETE FROM user_block_user WHERE blockerid = :blockerid AND blockedid = :blockedid";
				$stmt = $this->db->prepare($query);
				$stmt->execute(['blockerid' => $blockerid, 'blockedid' => $blockedid]);
				
                $queryUpdateBlocker = "UPDATE users_info SET amountblocked = amountblocked - 1 WHERE userid = :blockerid";
                $stmt = $this->db->prepare($queryUpdateBlocker);
                $stmt->execute(['blockerid' => $blockerid]);

				$action = false;
				$response = 'User unblocked successfully.';
			} else {
				$query = "INSERT INTO user_block_user (blockerid, blockedid) VALUES (:blockerid, :blockedid)";
				$stmt = $this->db->prepare($query);
				$stmt->execute(['blockerid' => $blockerid, 'blockedid' => $blockedid]);
				
                $queryUpdateBlocker = "UPDATE users_info SET amountblocked = amountblocked + 1 WHERE userid = :blockerid";
                $stmt = $this->db->prepare($queryUpdateBlocker);
                $stmt->execute(['blockerid' => $blockerid]);

				$action = true;
				$response = 'User blocked successfully.';
			}

			$this->db->commit();

			return ['status' => 'success', 'ResponseCode' => $response, 'isBlocked' => $action];
		} catch (\Exception $e) {
			$this->db->rollBack();
			$this->logger->error('Failed to toggle user block', ['exception' => $e]);
			return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user block'];
		}
	}

	public function getBlockedUsers(string $blockerid): array|false
	{
		$this->logger->info('UserInfoMapper.getBlockedUsers started');

		$query = "SELECT * FROM user_block_user WHERE blockerid = :blockerid";
		
		try {
			$stmt = $this->db->prepare($query);
			$stmt->bindValue(':blockerid', $blockerid, \PDO::PARAM_STR);
			$stmt->execute();

			$results = [];
			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$this->logger->info('UserInfoMapper.while', ['row' => $row]);
				$results[] = new UserBlock($row);
			}

			if (!empty($results)) {
				$this->logger->info("Fetched all blocked users from database", ['count' => count($results)]);
				return $results;
			} else {
				$this->logger->warning("No blocked users found for blockerid: $blockerid");
				return false;
			}
		} catch (\PDOException $e) {
			$this->logger->error("Database error while fetching blocked users: " . $e->getMessage());
			return false;
		}
	}

    public function updateUserInfoStats(string $userid): array
    {
        $this->logger->info('UserInfoMapper.updateUserInfoStats started');

        try {
            $this->db->beginTransaction();

            $queries = [
                'amountposts' => "SELECT COUNT(*) FROM posts WHERE userid = :userid",
                //'amounttrending' => "SELECT COUNT(*) FROM posts WHERE userid = :userid AND trending = 1",
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
                $stmt->execute(['userid' => $userid]);
                $updates[$field] = $stmt->fetchColumn();
            }

            $updateQuery = "UPDATE users_info SET ";
            $updateParts = [];
            $params = ['userid' => $userid];
            
            foreach ($updates as $field => $value) {
                $updateParts[] = "$field = :$field";
                $params[$field] = $value;
            }

            $updateQuery .= implode(', ', $updateParts) . " WHERE userid = :userid";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute($params);

            $this->db->commit();

            return ['status' => 'success', 'message' => 'User stats updated successfully', 'updated_data' => $updates];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to update user info stats', ['exception' => $e]);
            return ['status' => 'error', 'message' => 'Failed to update user stats'];
        }
    }
}
