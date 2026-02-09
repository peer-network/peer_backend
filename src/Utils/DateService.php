<?php

declare(strict_types=1);

namespace Fawaz\Utils;

use DateTime;
use InvalidArgumentException;

class DateService
{
    public static function now(): string
    {
        return new \DateTime()->format('Y-m-d H:i:s.u');
    }

    public static function nowPlusSeconds(int $seconds): string
    {
        $date = (new \DateTime());

        $interval = (new \DateInterval('PT' . $seconds . 'S'));
        $date->add($interval);

        return $date->format("Y-m-d H:i:s.u");
    }

    public static function dateOffsetToYYYYMMDD(string $dateOffset): string
{
    $today = new DateTime('today');

    // Day offsets: D0, D1, D2, ...
    if (preg_match('/^D(\d+)$/', $dateOffset, $matches)) {
        $days = (int)$matches[1];
        $date = (clone $today)->modify("-{$days} days");
        return $date->format('Ymd');
    }

    switch ($dateOffset) {
        case 'W0':
            // Start of current week (Monday)
            $date = (clone $today)->modify('monday this week');
            return $date->format('Ymd');

        case 'M0':
            // First day of current month
            $date = new DateTime('first day of this month');
            return $date->format('Ymd');

        case 'Y0':
            // First day of current year
            $date = new DateTime('first day of january this year');
            return $date->format('Ymd');

        default:
            throw new InvalidArgumentException("Unsupported dateOffset: {$dateOffset}");
    }
}
}
