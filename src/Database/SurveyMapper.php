<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Surveys;
use Psr\Log\LoggerInterface;

class SurveyMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(string $survid, string $currentUserId): bool
    {
        $this->logger->info("SurveyMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM peer_surveys WHERE survid = :survid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['survid' => $survid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("SurveyMapper.fetchAll started");

        $sql = "SELECT * FROM peer_surveys ORDER BY survid ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Surveys($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched survid successfully" : "No survid found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching survid from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadById(int $survid): Surveys|false
    {
        $this->logger->info("SurveyMapper.loadById started");

        $sql = "SELECT * FROM peer_surveys WHERE survid = :survid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['survid' => $survid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Surveys($data);
        }

        $this->logger->warning("No survey found with", ['survid' => $survid]);

        return false;
    }

    public function loadByName(string $question): Surveys|false
    {
        $this->logger->info("SurveyMapper.loadByName started");

        $sql = "SELECT * FROM peer_surveys WHERE question = :question";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['question' => $question]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Surveys($data);
        }

        $this->logger->warning("No survey found with question", ['question' => $question]);

        return false;
    }

    public function insert(Surveys $survey): Surveys
    {
        $this->logger->info("QuizMapper.insert started");

        $data = $survey->getArrayCopy();

        $query = "INSERT INTO peer_surveys (userid, question, option1, option2, option3, option4, option5) VALUES (:userid, :question, :option1, :option2, :option3, :option4, :option5) RETURNING survid";
        $stmt = $this->db->prepare($query);
		$stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
		$stmt->bindValue(':question', $data['question'], \PDO::PARAM_STR);
		$stmt->bindValue(':option1', $data['option1'], \PDO::PARAM_STR);
		$stmt->bindValue(':option2', $data['option2'], \PDO::PARAM_STR);
		$stmt->bindValue(':option3', $data['option3'], \PDO::PARAM_STR);
		$stmt->bindValue(':option4', $data['option4'], \PDO::PARAM_STR);
		$stmt->bindValue(':option5', $data['option5'], \PDO::PARAM_STR);
        $stmt->execute();

		// Get the auto-generated tagid from RETURNING
		$generatedTagId = $stmt->fetchColumn();

		$data['survid'] = (int) $generatedTagId;
        $this->logger->info("Inserted new survey into database", ['survey' => $data]);

        return new Surveys($data);
    }

    public function update(Surveys $survey): Surveys
    {
        $this->logger->info("SurveyMapper.update started");

        $data = $survey->getArrayCopy();

        $query = "UPDATE peer_surveys SET userid = :userid, question = :question, option1 = :option1, option2 = :option2, option3 = :option3, option4 = :option4, option5 = :option5 WHERE survid = :survid";

        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Updated survey in database", ['survey' => $data]);

        return new Surveys($data);
    }

    public function delete(int $survid): bool
    {
        $this->logger->info("SurveyMapper.delete started");

        $query = "DELETE FROM peer_surveys WHERE survid = :survid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['survid' => $survid]);

        $deleted = (bool)$stmt->rowCount();
        if ($deleted) {
            $this->logger->info("Deleted survey from database", ['survid' => $survid]);
        } else {
            $this->logger->warning("No survey found to delete in database for", ['survid' => $survid]);
        }

        return $deleted;
    }
}
