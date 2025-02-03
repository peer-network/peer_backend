<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\User;
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

        $stmt = $this->db->prepare('SELECT userid, liquidity, amountposts, amounttrending, amountfollower, amountfollowed, isprivate, updatedat FROM users_info WHERE userid = :id');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

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

        $data = $user->getArrayCopy();
        $query = "UPDATE users_info SET liquidity = :liquidity, amountposts = :amountposts, amounttrending = :amounttrending, amountfollower = :amountfollower, amountfollowed = :amountfollowed, isprivate = :isprivate, updatedat = :updatedat WHERE userid = :userid";
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
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new User($data);
        }

        $this->logger->warning("No user found with id", ['id' => $id]);

        return false;
    }

    public function updateUsers(User $user): User
    {
        $this->logger->info('UserInfoMapper.updateUsers started');

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

                //// Here Mark third
				$queryUpdateFollower = "UPDATE users_info SET amountfollowed = amountfollowed - 1 WHERE userid = :followerid";
				$stmt = $this->db->prepare($queryUpdateFollower);
				$stmt->execute(['followerid' => $followerid]);

                //// Here Mark third
				$queryUpdateFollowed = "UPDATE users_info SET amountfollower = amountfollower - 1 WHERE userid = :followeduserid";
				$stmt = $this->db->prepare($queryUpdateFollowed);
				$stmt->execute(['followeduserid' => $followeduserid]);

				$action = false;
				$response = 'UnFollow user set successful.';
			} else {
				$query = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid)";
				$stmt = $this->db->prepare($query);
				$stmt->execute(['followerid' => $followerid, 'followeduserid' => $followeduserid]);

                //// Here Mark third
				$queryUpdateFollower = "UPDATE users_info SET amountfollowed = amountfollowed + 1 WHERE userid = :followerid";
				$stmt = $this->db->prepare($queryUpdateFollower);
				$stmt->execute(['followerid' => $followerid]);

                //// Here Mark third
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
}
