<?php

namespace Fawaz\App;

use Fawaz\App\Surveys;
use Fawaz\Database\SurveyMapper;
use Psr\Log\LoggerInterface;

class SurveyService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected SurveyMapper $surveyMapper)
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

    private function respondWithError(string $responseCode): array
    {
        return ['status' => 'error', 'ResponseCode' => $responseCode];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized action attempted.');
            return false;
        }
        return true;
    }

    public function createSurvey(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('SurveyService.createSurvey started');

        $survid = $this->generateUUID();

        $surveyData = [
            'survid' => $survid,
            'userid' => $this->currentUserId,
            'question' => $args['question'] ?? '',
            'option1' => $args['option1'] ?? '',
            'option2' => $args['option2'] ?? '',
            'option3' => $args['option3'] ?? '',
            'option4' => $args['option4'] ?? '',
            'option5' => $args['option5'] ?? '',
        ];

        try {
            $survey = new Surveys($surveyData);
            $this->surveyMapper->insert($survey);

            $this->logger->info('Survey created successfully', ['survid' => $survid]);
            return ['status' => 'success', 'affectedRows' => $surveyData];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create survey', ['args' => $args, 'exception' => $e]);
            return $this->respondWithError('Failed to create survey');
        }
    }

    public function updateSurvey(array $input): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $requiredFields = ['survid', 'question', 'option1', 'option2', 'option3', 'option4', 'option5'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                $this->logger->warning("$field is required", ['input' => $input]);
                return $this->respondWithError("$field is required");
            }
        }

        $survid = $input['survid'];
        if (!self::isValidUUID($survid)) {
            return $this->respondWithError('Invalid survey ID');
        }

        if (!$this->surveyMapper->isCreator($survid, $this->currentUserId)) {
            return $this->respondWithError('Unauthorized: You can only update your own surveys.');
        }

        try {
            $survey = $this->surveyMapper->loadById($survid);
            if (!$survey) {
                return $this->respondWithError('Survey not found');
            }

            $survey->update($input);
            $this->surveyMapper->update($survey);

            $this->logger->info('Survey updated successfully', ['survid' => $survid]);
            return ['status' => 'success', 'affectedRows' => $survey->getArrayCopy()];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update survey', ['survid' => $survid, 'exception' => $e]);
            return $this->respondWithError('Failed to update survey');
        }
    }

    public function deleteSurvey(string $id): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($id)) {
            return $this->respondWithError('Invalid survey ID');
        }

        try {
            $survey = $this->surveyMapper->loadById($id);
            if (!$survey) {
                return $this->respondWithError('Survey not found');
            }

            if ($survey->getArrayCopy()['userid'] !== $this->currentUserId && !$this->surveyMapper->isCreator($id, $this->currentUserId)) {
                return $this->respondWithError('Unauthorized: You can only delete your own surveys.');
            }

            $this->surveyMapper->delete($id);
            $this->logger->info('Survey deleted successfully', ['id' => $id]);

            return ['status' => 'success', 'ResponseCode' => 'Survey deleted successfully'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete survey', ['id' => $id, 'exception' => $e]);
            return $this->respondWithError('Failed to delete survey');
        }
    }

	public function fetchAll(?array $args = []): array
	{
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		$this->logger->info('SurveyService.fetchAll started');

		$offset = max((int)($args['offset'] ?? 0), 0);
		$limit = min(max((int)($args['limit'] ?? 10), 1), 20);

		try {
			$surveys = $this->surveyMapper->fetchAll($offset, $limit);
			$result = array_map(fn(Surveys $survey) => $survey->getArrayCopy(), $surveys);

			$this->logger->info('Surveys fetched successfully', ['count' => count($result)]);
			return $this->createSuccessResponse('Surveys fetched successfully', [$result]);

		} catch (\Throwable $e) {
			$this->logger->error('Error fetching Surveys', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			return $this->respondWithError('Failed to fetch Surveys');
		}
	}

    public function fetchSurveyById(string $survid): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($survid)) {
            return $this->respondWithError('Invalid survey ID');
        }

        $this->logger->info('SurveyService.fetchSurveyById started');

        $survey = $this->surveyMapper->loadById($survid);
        if (!$survey) {
            return $this->respondWithError('Survey not found');
        }

        return ['status' => 'success', 'affectedRows' => $survey->getArrayCopy()];
    }
}
