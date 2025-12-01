<?php

declare(strict_types=1);

namespace Fawaz\Utils;

final class ContentFilterHelper
{
    public const CONTENT_TYPES = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT'];
    public const POST_FILTER_TYPES = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT', 'FOLLOWED', 'FOLLOWER', 'VIEWED', 'FRIENDS'];

    public static function normalizeToUpper(array $values): array
    {
        return array_map('strtoupper', $values);
    }

    public static function invalidAgainstAllowed(array $values, array $allowed): array
    {
        return array_values(array_diff(self::normalizeToUpper($values), $allowed));
    }

    public static function mapContentTypesForDb(array $filterBy): array
    {
        // Converts GraphQL enum values to DB content types
        $mapping = [
            'IMAGE' => 'image',
            'AUDIO' => 'audio',
            'VIDEO' => 'video',
            'TEXT'  => 'text',
        ];

        $upper = self::normalizeToUpper($filterBy);
        $result = [];
        foreach ($upper as $val) {
            if (isset($mapping[$val])) {
                $result[] = $mapping[$val];
            }
        }
        return $result;
    }
}
