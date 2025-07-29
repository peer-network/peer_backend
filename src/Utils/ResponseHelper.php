<?php
namespace Fawaz\Utils;

trait ResponseHelper
{
    private static function argsToString(array $args): string 
    {
        $result = serialize($args);
        return is_string($result) ? $result : '';
    }

    private static function hashObject(object $object): string 
    {
        $data = json_decode(json_encode($object, JSON_THROW_ON_ERROR), true);

        $sorted = self::recursiveKeySort($data);

        $json = json_encode($sorted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json);
    }

    private static function recursiveKeySort(array $array): array 
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::recursiveKeySort($value);
            }
        }
        ksort($array);
        return $array;
    }

    private static function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return self::respondWithError(30265);
            }
        }
        return [];
    }

    private static function respondWithError(string $responseCode): array
    {
        return ['status' => 'error', 'ResponseCode' => $responseCode];
    }

    private static function createSuccessResponse(string $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
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

    private static function generateUUID(): string
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

    private static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private static function validateDate(string $date, string $format = 'Y-m-d'): bool 
    {
        if (!is_string($date)) {
            return false;
        }

        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private static function isSameUser(string $userId, string $currentUserId): bool
    {
        return $userId === $currentUserId;
    }
}
