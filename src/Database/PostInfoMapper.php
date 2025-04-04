<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\PostInfo;
use Psr\Log\LoggerInterface;

class PostInfoMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function loadById(string $postid): ?PostInfo
    {
        $this->logger->info("PostInfoMapper.loadById started");

        $sql = "SELECT * FROM post_info WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new PostInfo($data);
        }

        return null;
    }

    public function isUserExistById(string $id): bool
    {
        $this->logger->info("PostInfoMapper.isUserExistById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function insert(PostInfo $postInfo): bool
    {
        $this->logger->info("PostInfoMapper.insert started");

        $data = $postInfo->getArrayCopy();

        $query = "INSERT INTO post_info 
                  (postid, userid, likes, dislikes, reports, views, saves, shares, comments) 
                  VALUES 
                  (:postid, :userid, :likes, :dislikes, :reports, :views, :saves, :shares, :comments)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':likes', $data['likes'], \PDO::PARAM_INT);
            $stmt->bindValue(':dislikes', $data['dislikes'], \PDO::PARAM_INT);
            $stmt->bindValue(':reports', $data['reports'], \PDO::PARAM_INT);
            $stmt->bindValue(':views', $data['views'], \PDO::PARAM_INT);
            $stmt->bindValue(':saves', $data['saves'], \PDO::PARAM_INT);
            $stmt->bindValue(':shares', $data['shares'], \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $data['comments'], \PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logger->info("Inserted new post info into database", ['postid' => $data['postid']]);
                return true;
            } else {
                $this->logger->warning("Failed to insert new post info into database", ['postid' => $data['postid']]);
                return false;
            }
        } catch (\PDOException $e) {
            $this->logger->error(
                "PostInfoMapper.insert: Exception occurred while inserting post info",
                [
                    'postid' => $data['postid'] ?? null,
                    'exception' => $e->getMessage()
                ]
            );
            return false;
        } catch (\Exception $e) {
            $this->logger->critical(
                "PostInfoMapper.update: Unexpected exception occurred",
                [
                    'postid' => $data['postid'] ?? null,
                    'exception' => $e->getMessage()
                ]
            );
        }
    }

    public function update(PostInfo $postInfo): void
    {
        $this->logger->info("PostInfoMapper.update started");

        $data = $postInfo->getArrayCopy(); 

        $sql = "UPDATE post_info 
                SET likes = :likes, 
                    dislikes = :dislikes, 
                    reports = :reports, 
                    views = :views, 
                    saves = :saves, 
                    shares = :shares, 
                    comments = :comments 
                WHERE postid = :postid";

        try {
            $stmt = $this->db->prepare($sql);

            // Bind each value explicitly
            $stmt->bindValue(':likes', $data['likes'], \PDO::PARAM_INT);
            $stmt->bindValue(':dislikes', $data['dislikes'], \PDO::PARAM_INT);
            $stmt->bindValue(':reports', $data['reports'], \PDO::PARAM_INT);
            $stmt->bindValue(':views', $data['views'], \PDO::PARAM_INT);
            $stmt->bindValue(':saves', $data['saves'], \PDO::PARAM_INT);
            $stmt->bindValue(':shares', $data['shares'], \PDO::PARAM_INT);
            $stmt->bindValue(':comments', $data['comments'], \PDO::PARAM_INT);
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);

            if ($stmt->execute()) {
                $this->logger->info("Updated post info successfully", ['postid' => $data['postid']]);
            } else {
                $this->logger->warning("Failed to update post info", ['postid' => $data['postid']]);
            }
        } catch (\PDOException $e) {
            $this->logger->error(
                "PostInfoMapper.update: Exception occurred while updating post info",
                [
                    'postid' => $data['postid'] ?? null,
                    'exception' => $e->getMessage()
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical(
                "PostInfoMapper.update: Unexpected exception occurred",
                [
                    'postid' => $data['postid'] ?? null,
                    'exception' => $e->getMessage()
                ]
            );
        }
    }

    public function delete(string $postid): bool
    {
        $this->logger->info("PostInfoMapper.delete started");

        try {
            $this->db->beginTransaction();

            $tables = [
                'user_post_likes',
                'user_post_dislikes',
                'user_post_reports',
                'user_post_saves',
                'user_post_shares',
                'user_post_views',
                'post_info'
            ];

            foreach ($tables as $table) {
                $sql = "DELETE FROM $table WHERE postid = :postid";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
                $stmt->execute();
            }

            $this->db->commit();
            $this->logger->info("Deleted post info and related user activities successfully", ['postid' => $postid]);
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to delete post info and related user activities", [
                'postid' => $postid,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function addUserActivity(string $action, string $userid, string $postid): bool
    {
        $this->logger->info("PostInfoMapper.addUserActivity started");

        $table = match ($action) {
            'likePost' => 'user_post_likes',
            'dislikePost' => 'user_post_dislikes',
            'reportPost' => 'user_post_reports',
            'savePost' => 'user_post_saves',
            'sharePost' => 'user_post_shares',
            'viewPost' => 'user_post_views',
            default => null,
        };

        if (!$table) {
            $this->logger->error("PostInfoMapper.addUserActivity: Invalid action provided", ['action' => $action]);
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Check if the record already exists
            $sqlCheck = "SELECT COUNT(*) FROM $table WHERE userid = :userid AND postid = :postid";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmtCheck->bindValue(':postid', $postid, \PDO::PARAM_STR);
            $stmtCheck->execute();
            $exists = $stmtCheck->fetchColumn() > 0;

            if (!$exists) {
                // Insert a new record
                $sql = "INSERT INTO $table (userid, postid) VALUES (:userid, :postid)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
                $stmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
                $success = $stmt->execute();

                if ($success) {
                    $this->db->commit();
                    $this->logger->info("User activity added successfully", ['action' => $action, 'userid' => $userid, 'postid' => $postid]);
                    return true;
                }
            }

            $this->db->rollBack();
            $this->logger->warning("User activity already exists or failed to add", ['action' => $action, 'userid' => $userid, 'postid' => $postid]);
            return false;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("PostInfoMapper.addUserActivity: Exception occurred", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function togglePostSaved(string $userid, string $postid): array
    {
        $this->logger->info("PostInfoMapper.togglePostSaved started");

        try {
            $this->db->beginTransaction();

            // Check if the post is already saved by the user
            $query = "SELECT COUNT(*) FROM user_post_saves WHERE userid = :userid AND postid = :postid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
            $stmt->execute();

            $isSaved = $stmt->fetchColumn() > 0;

            if ($isSaved) {
                // Delete the save record
                $query = "DELETE FROM user_post_saves WHERE userid = :userid AND postid = :postid";
                $action = 'unsaved';
                $issaved = false;

                // Decrement the save count in `post_info`
                $updatePostInfoQuery = "UPDATE post_info SET saves = saves - 1 WHERE postid = :postid";
                $updateStmt = $this->db->prepare($updatePostInfoQuery);
                $updateStmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
                $updateStmt->execute();
            } else {
                // Insert a new save record
                $query = "INSERT INTO user_post_saves (userid, postid) VALUES (:userid, :postid)";
                $action = 'saved';
                $issaved = true;

                // Increment the save count in `post_info`
                $updatePostInfoQuery = "UPDATE post_info SET saves = saves + 1 WHERE postid = :postid";
                $updateStmt = $this->db->prepare($updatePostInfoQuery);
                $updateStmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
                $updateStmt->execute();
            }

            // Execute the toggle action (delete or insert)
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
            $stmt->execute();

            $this->db->commit();

            return ['status' => 'success', 'isSaved' => $issaved, 'ResponseCode' => $action];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to toggle post save', [
                'userid' => $userid,
                'postid' => $postid,
                'exception' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle post save'];
        }
    }

    public function toggleUserFollow(string $followerid, string $followeduserid): array
    {
        $this->logger->info("PostInfoMapper.toggleUserFollow started");

        try {
            $this->db->beginTransaction();

            // Check if the follow relationship already exists
            $query = "SELECT COUNT(*) FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();

            $isFollowing = $stmt->fetchColumn() > 0;

            if ($isFollowing) {
                // Unfollow: delete the relationship
                $query = "DELETE FROM follows WHERE followerid = :followerid AND followedid = :followeduserid";
                $action = 'unfollowed';
                $isfollowing = false;
            } else {
                // Follow: insert the relationship
                $query = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid)";
                $action = 'followed';
                $isfollowing = true;
            }

            // Execute the toggle action
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();

            $this->db->commit();

            return ['status' => 'success', 'isfollowing' => $isfollowing, 'ResponseCode' => $action];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to toggle user follow', [
                'followerid' => $followerid,
                'followeduserid' => $followeduserid,
                'exception' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'ResponseCode' => 'Failed to toggle user follow.'];
        }
    }

}
