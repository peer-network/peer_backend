<?php

namespace Fawaz\Utils;

trait ResponseHelper
{
    private static function argsToJsString($args) 
    {
        return json_encode($args);
    }

    private static function hashObject(object $object): string 
    {
        // Konvertiere Objekt in ein assoziatives Array mit öffentlichen Properties
        $data = json_decode(json_encode($object, JSON_THROW_ON_ERROR), true);

        // Optional: Sortiere rekursiv nach Schlüsseln für stabile Hashes
        $sorted = self::recursiveKeySort($data);

        // Kodieren & hashen
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

    private static function argsToString($args) 
    {
        return serialize($args);
    }

    private static function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return self::respondWithError(30301);
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
}
