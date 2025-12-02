<?php

declare(strict_types=1);

namespace Fawaz\App;

final class Status
{
    public const int NORMAL = 0;
    public const int SUSPENDED = 1;
    public const int ARCHIVED = 2;
    public const int BANNED = 3;
    public const int LOCKED = 4;
    public const int PENDING_REVIEW = 5;
    public const int DELETED = 6;

    public static function getMap(): array
    {
        $reflectionClass = new \ReflectionClass(self::class);
        return \array_flip($reflectionClass->getConstants());
    }

    public static function getNames(): array
    {
        $reflectionClass = new \ReflectionClass(self::class);
        return \array_keys($reflectionClass->getConstants());
    }

    public static function getValues(): array
    {
        $reflectionClass = new \ReflectionClass(self::class);
        return \array_values($reflectionClass->getConstants());
    }

    private function __construct()
    {
    }
}
