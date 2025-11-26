<?php

declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\App\CommentInfo;
use Fawaz\Utils\PeerLoggerInterface;

class CommentInfoMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function loadById(string $commentid): CommentInfo|false
    {
        $this->logger->debug("CommentInfoMapper.loadById started");

        $sql = "SELECT * FROM comment_info WHERE commentid = :commentid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new CommentInfo($data);
        }

        return false;
    }

    public function insert(CommentInfo $commentInfo): bool
    {
        $this->logger->debug("CommentInfoMapper.insert started");

        $data = $commentInfo->getArrayCopy();

        $query = "INSERT INTO comment_info (commentid, userid, likes, reports, totalreports, comments) 
                  VALUES (:commentid, :userid, :likes, :reports, :totalreports, :comments)";

        $stmt = $this->db->prepare($query);

        if ($stmt->execute($data)) {
            $this->logger->info("Inserted new comment info into database", ['commentid' => $data['commentid']]);
            return true;
        } else {
            $this->logger->warning("Failed to insert new comment info into database", ['commentid' => $data['commentid']]);
            return false;
        }
    }

    public function update(CommentInfo $commentInfo): void
    {
        $this->logger->debug("CommentInfoMapper.update started");

        $data = $commentInfo->getArrayCopy();

        $sql = "UPDATE comment_info SET likes = :likes, reports = :reports, totalreports = :totalreports, comments = :comments WHERE commentid = :commentid AND userid = :userid";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($data)) {
            $this->logger->info("Updated comment info successfully", ['commentid' => $data['commentid']]);
        } else {
            $this->logger->error("Failed to update comment info", ['commentid' => $data['commentid']]);
        }
    }

    public function addUserActivity(string $action, string $userid, string $commentid): bool
    {
        $this->logger->debug("CommentInfoMapper.addUserActivity started");

        $table = match ($action) {
            'likeComment' => 'user_comment_likes',
            'reportComment' => 'user_reports',
            default => null,
        };

        if (!$table) {
            $this->logger->error("Invalid action provided", ['action' => $action]);
            return false;
        }

        $sqlCheck = "SELECT COUNT(*) FROM $table WHERE userid = :userid AND commentid = :commentid";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute(['userid' => $userid, 'commentid' => $commentid]);
        $exists = $stmtCheck->fetchColumn() > 0;

        if (!$exists) {
            $sql = "INSERT INTO $table (userid, commentid) VALUES (:userid, :commentid)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(['userid' => $userid, 'commentid' => $commentid]);
        }

        return false;
    }

    public function countLikes(string $commentid): int
    {
        $this->logger->debug("CommentMapper.countLikes started");

        $sql = "SELECT COUNT(*) FROM user_comment_likes WHERE commentid = :commentid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid]);
        return (int) $stmt->fetchColumn();
    }

    public function isLiked(string $commentid, string $userid): bool
    {
        $this->logger->debug("CommentMapper.isLiked started");

        $sql = "SELECT COUNT(*) FROM user_comment_likes WHERE commentid = :commentid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }
}
