<?php

declare(strict_types=1);

namespace Fawaz\Utils;

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
}
