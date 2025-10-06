<?php
declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Psr\Log\LoggerInterface;
use Fawaz\Utils\ReportTargetType;
use DateTime;

class ReportsMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    public function loadReportById(string $id) {
        // To be implemented
    }

    public function addReport(
        string $reporter_userid, 
        ReportTargetType $targettype, 
        string $targetid, 
        string $hash_content_sha256, 
        ?string $message = NULL
    ): ?bool {

        $this->logger->debug("ReportsMapper.addReports started");

        $reportId = $this->generateUUID();

        $targetTypeString = $targettype->value;
        $debugData = [
            'reporter_userid' => $reporter_userid, 
            'targetid' => $targetid,
            'targettype' => $targetTypeString
        ];
        
        try {

            // Check if the record already exists
            $sqlCheck = "SELECT COUNT(*) 
                FROM user_reports 
                WHERE reporter_userid = :reporter_userid
                    AND targetid = :targetid 
                    AND targettype = :targettype
                    AND hash_content_sha256 = :hash_content_sha256
            ";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->bindValue(':reporter_userid', $reporter_userid, \PDO::PARAM_STR);
            $stmtCheck->bindValue(':targetid', $targetid, \PDO::PARAM_STR);
            $stmtCheck->bindValue(':targettype', $targetTypeString, \PDO::PARAM_STR);
            $stmtCheck->bindValue(':hash_content_sha256', $hash_content_sha256, \PDO::PARAM_STR);
            $stmtCheck->execute();

            $exists = $stmtCheck->fetchColumn() > 0;
            if ($exists > 0) {
                $this->logger->warning("User activity already exists", $debugData);
                return true;
            }

            $createdat = (string)(new DateTime())->format('Y-m-d H:i:s.u');
            // Insert a new record
            $sql = "INSERT INTO user_reports (
                reportid, 
                reporter_userid, 
                targetid, 
                targettype, 
                collected, 
                createdat,
                hash_content_sha256
            ) VALUES (
                :reportid, 
                :reporter_userid, 
                :targetid, 
                :targettype, 
                :collected, 
                :createdat,
                :hash_content_sha256
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':reportid', $reportId, \PDO::PARAM_STR);
            $stmt->bindValue(':reporter_userid', $reporter_userid, \PDO::PARAM_STR);
            $stmt->bindValue(':targetid', $targetid, \PDO::PARAM_STR);
            $stmt->bindValue(':targettype', $targetTypeString, \PDO::PARAM_STR);
            $stmt->bindValue(':collected', 0, \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_STR);
            $stmt->bindValue(':hash_content_sha256', $hash_content_sha256, \PDO::PARAM_STR);

            $success = $stmt->execute();

            if ($success) {
                $this->logger->info("ReportsMapper: addReport: Report added successfully", $debugData);
                return false;
            }

            $this->logger->warning("ReportsMapper: addReport: Failed to add report", $debugData);
            return null;
        } catch (\Exception $e) {
            $this->logger->error("ReportsMapper.addReport: Exception occurred", ['exception' => $e->getMessage()]);
            return null;
        }
    }
}
