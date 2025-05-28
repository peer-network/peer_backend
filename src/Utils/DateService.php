<?php

namespace Fawaz\Utils;

class DateService
{
    static function now(): string {
        return (new \DateTime())->format('Y-m-d H:i:s.u');
    }

    static function nowPlusMinutes(int $minutes): string {
        $date = (new \DateTime());

        $interval = (new \DateInterval('PT' . $minutes . 'M'));
        $date->add($interval);

        return $date->format("Y-m-d H:i:s.u");
    }
}
