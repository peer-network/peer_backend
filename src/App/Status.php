<?php

namespace Fawaz\App;

final class Status
{
    public const NORMAL = 0;
    public const SUSPENDED = 1;
    public const ARCHIVED = 2;
    public const BANNED = 3;
    public const LOCKED = 4;
    public const PENDING_REVIEW = 5;
    public const DELETED = 6;

    public static function getMap(): array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_flip($reflectionClass->getConstants());
    }

    public static function getNames(): array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_keys($reflectionClass->getConstants());
    }

    public static function getValues(): array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_values($reflectionClass->getConstants());
    }

    private function __construct()
    {
    }
}
