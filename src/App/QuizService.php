<?php

namespace Fawaz\App;

use Fawaz\App\Quiz;
use Fawaz\Database\QuizMapper;
use Psr\Log\LoggerInterface;

class QuizService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected QuizMapper $quizMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning("Unauthorized action attempted.");
            return false;
        }
        return true;
    }

    public function createQuiz(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('QuizService.createQuiz started');

        $quizData = [
            'quizid' => 0,
            'userid' => $this->currentUserId,
            'question' => $args['question'] ?? '',
            'option1' => $args['option1'] ?? '',
            'option2' => $args['option2'] ?? '',
            'option3' => $args['option3'] ?? '',
            'option4' => $args['option4'] ?? '',
            'iscorrect' => $args['iscorrect'] ?? '',
        ];

        try {
            $quiz = new Quiz($quizData);
            $quiz = $this->quizMapper->insert($quiz);

            $this->logger->info('Quiz created successfully', ['quizid' => $quiz]);
            return ['status' => 'success', 'affectedRows' => $quiz];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to create quiz');
        }
    }

    public function updateQuiz(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $requiredFields = ['quizid', 'question', 'option1', 'option2', 'option3', 'option4', 'iscorrect'];
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                $this->logger->warning("$field is required", ['args' => $args]);
                return $this->respondWithError("$field is required");
            }
        }

        $quizid = (int)$args['quizid'];

        if (!$this->quizMapper->isCreator($quizid, $this->currentUserId)) {
            return $this->respondWithError('Unauthorized: You can only update your own quizzes.');
        }

        try {
            $quiz = $this->quizMapper->loadById($quizid);
            if (!$quiz) {
                return $this->respondWithError('Quiz not found');
            }

            $quiz->update($args);
            $this->quizMapper->update($quiz);

            $this->logger->info('Quiz updated successfully', ['quizid' => $quizid]);
            return ['status' => 'success', 'affectedRows' => $quiz->getArrayCopy()];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update quiz', ['quizid' => $quizid, 'exception' => $e]);
            return $this->respondWithError('Failed to update quiz');
        }
    }

    public function deleteQuiz(int $quizid): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		$quizid = (int)$quizid;

        try {
            $quiz = $this->quizMapper->loadById($quizid);
            if (!$quiz) {
                return $this->respondWithError('Quiz not found');
            }

            if ($quiz->getArrayCopy()['userid'] !== $this->currentUserId && !$this->quizMapper->isCreator($quizid, $this->currentUserId)) {
                return $this->respondWithError('Unauthorized: You can only delete your own quizzes.');
            }

            $this->quizMapper->delete($quizid);
            $this->logger->info('Quiz deleted successfully', ['quizid' => $quizid]);

            return ['status' => 'success', 'ResponseCode' => 'Quiz deleted successfully'];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to delete quiz');
        }
    }

	public function fetchAll(?array $args = []): array
	{
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		$this->logger->info("QuizService.fetchAll started");

		$offset = max((int)($args['offset'] ?? 0), 0);
		$limit = min(max((int)($args['limit'] ?? 10), 1), 20);

		try {
			$quizzes = $this->surveyMapper->fetchAll($offset, $limit);
			$result = array_map(fn(Quiz $quiz) => $quiz->getArrayCopy(), $quizzes);

			$this->logger->info("Quizzes fetched successfully", ['count' => count($result)]);
			return $this->createSuccessResponse('Quizzes fetched successfully', [$result]);

		} catch (\Throwable $e) {
			$this->logger->error("Error fetching Quizzes", [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			return $this->respondWithError('Failed to fetch Quizzes');
		}
	}

    public function fetchQuizById(int $quizid): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info("QuizService.fetchQuizById started");

        $quiz = $this->quizMapper->loadById($quizid);
        if (!$quiz) {
            return $this->respondWithError('Quiz not found');
        }

        return ['status' => 'success', 'affectedRows' => $quiz->getArrayCopy()];
    }
}
