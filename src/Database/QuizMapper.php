<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Quiz;
use Psr\Log\LoggerInterface;

class QuizMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(int $quizid, string $currentUserId): bool
    {
        $this->logger->info("QuizMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM peer_quiz WHERE quizid = :quizid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['quizid' => $quizid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("QuizMapper.fetchAll started");

        $sql = "SELECT * FROM peer_quiz ORDER BY quizid ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Quiz($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched quiz successfully" : "No quiz found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching quiz from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadById(int $id): Quiz|false
    {
        $this->logger->info("QuizMapper.loadById started");

        $sql = "SELECT * FROM peer_quiz WHERE quizid = :quizid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['quizid' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Quiz($data);
        }

        $this->logger->warning("No quiz found with quizid", ['quizid' => $id]);

        return false;
    }

    public function loadByName(string $question): Quiz|false
    {
        $this->logger->info("QuizMapper.loadByName started");

        $sql = "SELECT * FROM peer_quiz WHERE question = :question";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['question' => $question]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Quiz($data);
        }

        $this->logger->warning("No quiz found with question", ['question' => $question]);

        return false;
    }

    public function insert(Quiz $quiz): Quiz
    {
        $this->logger->info("QuizMapper.insert started");

        $data = $quiz->getArrayCopy();

        $query = "INSERT INTO peer_quiz (userid, question, option1, option2, option3, option4, iscorrect) VALUES (:userid, :question, :option1, :option2, :option3, :option4, :iscorrect) RETURNING quizid";
        $stmt = $this->db->prepare($query);
		$stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
		$stmt->bindValue(':question', $data['question'], \PDO::PARAM_STR);
		$stmt->bindValue(':option1', $data['option1'], \PDO::PARAM_STR);
		$stmt->bindValue(':option2', $data['option2'], \PDO::PARAM_STR);
		$stmt->bindValue(':option3', $data['option3'], \PDO::PARAM_STR);
		$stmt->bindValue(':option4', $data['option4'], \PDO::PARAM_STR);
		$stmt->bindValue(':iscorrect', $data['iscorrect'], \PDO::PARAM_STR);
        $stmt->execute();

		// Get the auto-generated tagid from RETURNING
		$generatedTagId = $stmt->fetchColumn();

		$data['quizid'] = (int) $generatedTagId;
        $this->logger->info("Inserted new quiz into database", ['quizzes' => $data]);

        return new Quiz($data);
    }

    public function update(Quiz $quiz): Quiz
    {
        $this->logger->info("QuizMapper.update started");

        $data = $quiz->getArrayCopy();

        $query = "UPDATE peer_quiz SET userid = :userid, question = :question, option1 = :option1, option2 = :option2, option3 = :option3, option4 = :option4, iscorrect = :iscorrect WHERE quizid = :quizid";

        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Updated quiz in database", ['quiz' => $data]);

        return new Quiz($data);
    }

    public function delete(int $quizid): bool
    {
        $this->logger->info("QuizMapper.delete started");

        $query = "DELETE FROM peer_quiz WHERE quizid = :quizid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['quizid' => $quizid]);

        $deleted = (bool)$stmt->rowCount();
        if ($deleted) {
            $this->logger->info("Deleted quiz from database", ['quizid' => $quizid]);
        } else {
            $this->logger->warning("No quiz found to delete in database for", ['quizid' => $quizid]);
        }

        return $deleted;
    }
}
