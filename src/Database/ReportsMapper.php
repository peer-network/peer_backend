<?php

declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ReportTargetType;
use DateTime;
use Fawaz\App\Models\UserReport;
use Fawaz\App\Models\ModerationTicket;
use Fawaz\App\Models\Moderation;
use Fawaz\config\constants\ConstantsModeration;

class ReportsMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff)
        );
    }

    public function loadReportById(string $id)
    {
        // To be implemented
    }

    public function addReport(
        string $reporter_userid,
        ReportTargetType $targettype,
        string $targetid,
        string $hash_content_sha256,
        ?string $message = null
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

            // Add Ticket for reports
            $moderationTicketId = $this->getTicketId($targetid, $targetTypeString, $createdat);

            // Insert a new record
            $sql = "INSERT INTO user_reports (
                reportid, 
                reporter_userid, 
                targetid, 
                targettype, 
                collected, 
                createdat,
                moderationticketid,
                hash_content_sha256
            ) VALUES (
                :reportid, 
                :reporter_userid, 
                :targetid, 
                :targettype, 
                :collected, 
                :createdat,
                :moderationticketid,
                :hash_content_sha256
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':reportid', $reportId, \PDO::PARAM_STR);
            $stmt->bindValue(':reporter_userid', $reporter_userid, \PDO::PARAM_STR);
            $stmt->bindValue(':targetid', $targetid, \PDO::PARAM_STR);
            $stmt->bindValue(':targettype', $targetTypeString, \PDO::PARAM_STR);
            $stmt->bindValue(':moderationticketid', $moderationTicketId, \PDO::PARAM_STR);
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


    /**
     * Get TicketId by TargetId and TargetType
     *
     * Check if a ticket already exists for the target (post, comment, user)
     * If exists, use the existing ticket ID
     * If not, create a new ticket
     */
    private function getTicketId(string $targetid, string $targettype, string $createdat): string
    {
        $moderationTicketId = $this->generateUUID();

        $existingTicket = UserReport::query()->where('targetid', $targetid)->where('targettype', $targettype)->first();

        $status = array_keys(ConstantsModeration::contentModerationStatus())[0];

        if ($existingTicket && isset($existingTicket['moderationticketid']) && $existingTicket['moderationticketid']) {
            $ticketStatus = ModerationTicket::query()->where('uid', $existingTicket['moderationticketid'])->where('status', $status)->first();

            if ($ticketStatus) {
                // Ticket is already open and awaiting review
                $this->logger->info("ReportsMapper: addReport: Ticket already exists and is awaiting review");
                $moderationTicketId = $existingTicket['moderationticketid'];

                // Update the reports count
                $reportsCount = UserReport::query()->where('moderationticketid', $moderationTicketId)->count() + 1;
                ModerationTicket::query()->where('uid', $moderationTicketId)->updateColumns(['reportscount' => $reportsCount]);
            }
        } else {


            $data = [
                'uid' => $moderationTicketId,
                'status' => $status,
                'reportscount' => 1,
                'contenttype' => $targettype,
                'createdat' => $createdat
            ];

            ModerationTicket::query()->insert($data);
        }

        return $moderationTicketId;
    }

    /**
     * Check if the target (post, comment, user) is already moderated
     * 
     * if it has a moderationid, it means it has been moderated: return true
     * if not, return false
     */
    public function isModerated(string $targetid, string $targettype): bool
    {
        $reports = UserReport::query()->where('targetid', $targetid)->where('targettype', $targettype)->first();

        return !empty($reports) && isset($reports['moderationid']) && $reports['moderationid'] != null;
    }

}
