<?php

namespace Fawaz\Utils;

trait ResponseHelper
{
    // public function __construct(
    //     private ResponseMessagesProvider $responseMessagesProvider
    // ) {}

    private function argsToJsString($args) {
        return json_encode($args);
    }

    private function argsToString($args) {
        return serialize($args);
    }

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return self::respondWithError(30301);
            }
        }
        return [];
    }

    private function respondWithError(int $responseCode): array
    {
        return [
            'status' => 'error', 
            'ResponseCode' => $responseCode, 
            // 'ResponseMessage' => $this->responseMessagesProvider->getMessage((string)$responseCode)
        ];
    }

    private function createSuccessResponse(int $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            // 'ResponseMessage' => $this->responseMessagesProvider->getMessage((string)$message),
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
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

    private function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    /**
     * Validate Authenticated User
     */
    private function checkAuthentication($currentUserId): bool
    {
        if ($currentUserId === null) {
            return false;
        }
        return true;
    }

    private static function validateDate(string $date, string $format = 'Y-m-d'): bool 
    {
        if (!is_string($date)) {
            return false;
        }

        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
