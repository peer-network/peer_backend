<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\Comment;
use Fawaz\App\CommentAdvanced;
use Psr\Log\LoggerInterface;

class CommentMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(string $commentid, string $userid): bool
    {
        $this->logger->info("CommentMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM comments WHERE commentid = :commentid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
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

    public function insert(Comment $comment): Comment
    {
        $this->logger->info("CommentMapper.insert started");

        $daten = $data = $comment->getArrayCopy();

        $query = "INSERT INTO comments (commentid, userid, postid, parentid, content, createdat)
            VALUES (:commentid, :userid, :postid, :parentid, :content, :createdat)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Inserted new comment into database", ['comment' => $data]);

        $queryUserPostComments = "INSERT INTO user_post_comments (userid, postid, commentid, collected)
            VALUES (:userid, :postid, :commentid, 0)";
        $stmtUserPostComments = $this->db->prepare($queryUserPostComments);
        $stmtUserPostComments->execute([
            'userid' => $data['userid'],
            'postid' => $data['postid'],
            'commentid' => $data['commentid']
        ]);

        $this->logger->info("Inserted new entry into user_post_comments table", [
            'userid' => $data['userid'],
            'postid' => $data['postid'],
            'commentid' => $data['commentid']
        ]);

        return new Comment($daten);
    }

    public function delete(string $commentid): bool
    {
        $this->logger->info("CommentMapper.delete started");

        $query = "DELETE FROM comments WHERE commentid = :commentid";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['commentid' => $commentid]);

        $deleted = (bool)$stmt->rowCount();

        if ($deleted) {
            $this->logger->info("Deleted comment from database", ['commentid' => $commentid]);
        } else {
            $this->logger->warning("No comment found to delete in database for", ['commentid' => $commentid]);
        }

        return $deleted;
    }

    public function fetchAllByPostIdetaild(string $postId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchAllByPostIdetaild started");

        $sql = "
            SELECT 
                c.*,
                COALESCE(like_counts.like_count, 0) AS amountlikes,
                CASE WHEN ul.userid IS NOT NULL THEN TRUE ELSE FALSE END AS isliked,
                u.uid,
                u.username,
                u.img,
                CASE WHEN f1.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowing,
                CASE WHEN f2.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowed
            FROM 
                comments c
            LEFT JOIN 
                users u ON c.userid = u.uid
            LEFT JOIN 
                (SELECT commentid, COUNT(*) AS like_count FROM user_comment_likes GROUP BY commentid) like_counts 
                ON c.commentid = like_counts.commentid
            LEFT JOIN 
                user_comment_likes ul 
                ON c.commentid = ul.commentid AND ul.userid = :currentUserId
            LEFT JOIN 
                follows f1 
                ON u.uid = f1.followerid AND f1.followedid = :currentUserId -- Is the author following the current user?
            LEFT JOIN 
                follows f2 
                ON u.uid = f2.followedid AND f2.followerid = :currentUserId -- Is the current user following the author?
            WHERE 
                c.postid = :postId AND c.parentid IS NULL
            ORDER BY 
                c.createdat ASC
            LIMIT :limit OFFSET :offset;
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':postId', $postId, PDO::PARAM_STR);
        $stmt->bindValue(':currentUserId', $currentUserId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new CommentAdvanced([
                'commentid' => $row['commentid'],
                'userid' => $row['userid'],
                'postid' => $row['postid'],
                'parentid' => $row['parentid'],
                'content' => $row['content'],
                'amountlikes' => (int) $row['amountlikes'],
                'isliked' => (bool) $row['isliked'],
                'createdat' => $row['createdat'],
                'user' => [
                    'uid' => $row['uid'],
                    'username' => $row['username'],
                    'img' => $row['img'],
                    'isfollowed' => (bool) $row['isfollowed'],
                    'isfollowing' => (bool) $row['isfollowing'],
                ],
            ]);
        }

        $this->logger->info("Fetched comments for post", ['count' => count($results)]);

        return $results;
    }

    public function fetchAllByPostId(string $postId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchAllByPostId started");

        $sql = "SELECT * FROM comments WHERE postid = :postid AND parentid IS NULL ORDER BY createdat ASC LIMIT :limit OFFSET :offset";
        $params = [
            'postId' => $postId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comments[] = new Comment($row);
        }

        return $comments;
    }

    public function fetchByParentId(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchByParentId started");

        $sql = "
            SELECT 
                c.commentid,
                c.userid,
                c.postid,
                c.parentid,
                c.content,
                c.createdat,
                COALESCE(like_counts.like_count, 0) AS amountlikes,
                (ul.userid IS NOT NULL) AS isliked,
                u.uid,
                u.username,
                u.img,
                (f1.followerid IS NOT NULL) AS isfollowing,
                (f2.followerid IS NOT NULL) AS isfollowed
            FROM 
                comments c
            LEFT JOIN 
                users u ON c.userid = u.uid
            LEFT JOIN 
                (SELECT commentid, COUNT(*) AS like_count FROM user_comment_likes GROUP BY commentid) like_counts 
                ON c.commentid = like_counts.commentid
            LEFT JOIN 
                user_comment_likes ul 
                ON c.commentid = ul.commentid AND ul.userid = :currentUserId
            LEFT JOIN 
                follows f1 
                ON u.uid = f1.followerid AND f1.followedid = :currentUserId 
            LEFT JOIN 
                follows f2 
                ON u.uid = f2.followedid AND f2.followerid = :currentUserId 
            WHERE 
                c.parentid = :parentId
            ORDER BY 
                c.createdat ASC
            LIMIT :limit OFFSET :offset;
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':parentId', $parentId, PDO::PARAM_STR);
        $stmt->bindParam(':currentUserId', $currentUserId, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comments = array_map(function($row) {
            return new CommentAdvanced([
                'commentid' => $row['commentid'],
                'userid' => $row['userid'],
                'postid' => $row['postid'],
                'parentid' => $row['parentid'],
                'content' => $row['content'],
                'amountlikes' => (int) $row['amountlikes'],
                'isliked' => (bool) $row['isliked'],
                'createdat' => $row['createdat'],
                'user' => [
                    'uid' => $row['uid'],
                    'username' => $row['username'],
                    'img' => $row['img'],
                    'isfollowed' => (bool) $row['isfollowed'],
                    'isfollowing' => (bool) $row['isfollowing'],
                ],
            ]);
        }, $results);

        $this->logger->info("Fetched comments for post", ['count' => count($comments)]);

        return $comments;
    }

    public function fetchByParentIdd(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchByParentId started");

        $sql = "SELECT * FROM comments WHERE parentid = :parentid ORDER BY createdat ASC LIMIT :limit OFFSET :offset";
        $params = [
            'parentid' => $parentId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comments[] = new Comment($row);
        }

        return $comments;
    }

    public function loadById(string $id): Comment|false
    {
        $this->logger->info("CommentMapper.loadById started");

        $sql = "SELECT * FROM comments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Comment($data);
        }

        $this->logger->warning("No comment found with id", ['id' => $id]);
        return false;
    }
}
