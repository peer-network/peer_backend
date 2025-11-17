<?php

declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\App\PostInfo;
use Fawaz\Utils\PeerLoggerInterface;

class PostInfoMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function loadById(string $postid): ?PostInfo
    {
        $this->logger->debug("PostInfoMapper.loadById started");

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
        $this->logger->debug("PostInfoMapper.isUserExistById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE uid = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function insert(PostInfo $postInfo): bool
    {
        $this->logger->debug("PostInfoMapper.insert started");

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
                $this->logger->error("Failed to insert new post info into database", ['postid' => $data['postid']]);
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
            return false;
        }
    }

    public function update(PostInfo $postInfo): void
    {
        $this->logger->debug("PostInfoMapper.update started");
        $data = $postInfo->getArrayCopy();

        try {
            $stmtSel = $this->db->prepare("
                SELECT likes, dislikes, reports, views, saves, shares, comments
                FROM post_info
                WHERE postid = :postid
                FOR UPDATE
            ");
            $stmtSel->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmtSel->execute();
            $old = $stmtSel->fetch(\PDO::FETCH_ASSOC) ?: [
                'likes' => 0,'dislikes' => 0,'reports' => 0,'views' => 0,'saves' => 0,'shares' => 0,'comments' => 0
            ];

            // Bereit (positiv clampen Logik erweitern)
            $dLikes    = max(0, (int)$data['likes']    - (int)$old['likes']);
            $dDislikes = max(0, (int)$data['dislikes'] - (int)$old['dislikes']);
            $dReports  = max(0, (int)$data['reports']  - (int)$old['reports']);
            $dViews    = max(0, (int)$data['views']    - (int)$old['views']);
            $dSaves    = max(0, (int)$data['saves']    - (int)$old['saves']);
            $dShares   = max(0, (int)$data['shares']   - (int)$old['shares']);
            $dComments = max(0, (int)$data['comments'] - (int)$old['comments']);

            // post_info auf absolute Werte setzen
            $stmtUpd = $this->db->prepare("
                UPDATE post_info
                SET likes=:likes, dislikes=:dislikes, reports=:reports,
                    views=:views, saves=:saves, shares=:shares, comments=:comments
                WHERE postid=:postid
            ");
            // Jeden Wert explizit binden
            $stmtUpd->bindValue(':likes', (int)$data['likes'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':dislikes', (int)$data['dislikes'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':reports', (int)$data['reports'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':views', (int)$data['views'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':saves', (int)$data['saves'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':shares', (int)$data['shares'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':comments', (int)$data['comments'], \PDO::PARAM_INT);
            $stmtUpd->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);

            if (!$stmtUpd->execute()) {
                throw new \RuntimeException("post_info update failed");
            }

            // Bereit auf eine aktive Anzeige buchen
            if (($dLikes + $dDislikes + $dReports + $dViews + $dSaves + $dShares + $dComments) > 0) {
                $affected = $this->mapRowToActiveAdsInfo(
                    $data['postid'],
                    $dLikes,
                    $dDislikes,
                    $dReports,
                    $dViews,
                    $dSaves,
                    $dShares,
                    $dComments
                );
                $this->logger->info("Ad info bumped (one active ad)", [
                    'postid' => $data['postid'], 'affected_rows' => $affected
                ]);
            }

            $this->logger->info("Updated post info (+ads info, Variant B) successfully", ['postid' => $data['postid']]);

        } catch (\Throwable $e) {
            $this->logger->error("PostInfoMapper.update failed/rolled back", [
                'postid' => $data['postid'] ?? null,
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function addUserActivity(string $action, string $userid, string $postid): bool
    {
        $this->logger->debug("PostInfoMapper.addUserActivity started");

        $table = match ($action) {
            'likePost' => 'user_post_likes',
            'dislikePost' => 'user_post_dislikes',
            'reportPost' => 'user_reports',
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
                    $this->logger->info("User activity added successfully", ['action' => $action, 'userid' => $userid, 'postid' => $postid]);
                    return true;
                }
            }

            $this->logger->error("User activity already exists or failed to add", ['action' => $action, 'userid' => $userid, 'postid' => $postid]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("PostInfoMapper.addUserActivity: Exception occurred", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function togglePostSaved(string $userid, string $postid): array
    {
        $this->logger->debug("PostInfoMapper.togglePostSaved started");

        try {

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
                $action = "11511";
                $issaved = false;

                // Decrement the save count in `post_info`
                $updatePostInfoQuery = "UPDATE post_info SET saves = saves - 1 WHERE postid = :postid";
                $updateStmt = $this->db->prepare($updatePostInfoQuery);
                $updateStmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
                $updateStmt->execute();
            } else {
                // Insert a new save record
                $query = "INSERT INTO user_post_saves (userid, postid) VALUES (:userid, :postid)";
                $action = "11512";
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


            return ['status' => 'success', 'isSaved' => $issaved, 'ResponseCode' => $action];
        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle post save', [
                'userid' => $userid,
                'postid' => $postid,
                'exception' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'ResponseCode' => "41502"];
        }
    }

    public function toggleUserFollow(string $followerid, string $followeduserid): array
    {
        $this->logger->debug("PostInfoMapper.toggleUserFollow started");

        try {

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
                $action = "11103";
                $isfollowing = false;
            } else {
                // Follow: insert the relationship
                $query = "INSERT INTO follows (followerid, followedid) VALUES (:followerid, :followeduserid)";
                $action = "11104";
                $isfollowing = true;
            }

            // Execute the toggle action
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':followerid', $followerid, \PDO::PARAM_STR);
            $stmt->bindValue(':followeduserid', $followeduserid, \PDO::PARAM_STR);
            $stmt->execute();


            return ['status' => 'success', 'isfollowing' => $isfollowing, 'ResponseCode' => $action];
        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle user follow', [
                'followerid' => $followerid,
                'followeduserid' => $followeduserid,
                'exception' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'ResponseCode' => "41103"];
        }
    }

    private function mapRowToActiveAdsInfo(
        string $postId,
        int $dLikes,
        int $dDislikes,
        int $dReports,
        int $dViews,
        int $dSaves,
        int $dShares,
        int $dComments
    ): int {
        $sql = "
            WITH one_ad AS (
              SELECT a.advertisementid
              FROM advertisements a
              WHERE a.postid = :postid
                AND a.timestart <= now()
                AND a.timeend   >  now()
              ORDER BY a.timestart DESC
              LIMIT 1
            )
            UPDATE advertisements_info ai
            SET likes    = ai.likes    + :dLikes,
                dislikes = ai.dislikes + :dDislikes,
                reports  = ai.reports  + :dReports,
                views    = ai.views    + :dViews,
                saves    = ai.saves    + :dSaves,
                shares   = ai.shares   + :dShares,
                comments = ai.comments + :dComments,
                updatedat= :updatedat
            FROM one_ad oa
            WHERE ai.advertisementid = oa.advertisementid
        ";
        $stmt = $this->db->prepare($sql);
        $updatedat = (new \DateTime())->format('Y-m-d H:i:s.u');
        // Jeden Wert explizit binden
        $stmt->bindValue(':postid', $postId, \PDO::PARAM_STR);
        $stmt->bindValue(':dLikes', $dLikes, \PDO::PARAM_INT);
        $stmt->bindValue(':dDislikes', $dDislikes, \PDO::PARAM_INT);
        $stmt->bindValue(':dReports', $dReports, \PDO::PARAM_INT);
        $stmt->bindValue(':dViews', $dViews, \PDO::PARAM_INT);
        $stmt->bindValue(':dSaves', $dSaves, \PDO::PARAM_INT);
        $stmt->bindValue(':dShares', $dShares, \PDO::PARAM_INT);
        $stmt->bindValue(':dComments', $dComments, \PDO::PARAM_INT);
        $stmt->bindValue(':updatedat', $updatedat, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
