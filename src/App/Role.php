<?php

declare(strict_types=1);

namespace Fawaz\App;

final class Role
{
    public const USER = 0;
    public const SYSTEM_ACCOUNT = 1; // LP Account
    public const COMPANY_ACCOUNT = 2; // PEER BANK ACCOUNT
    public const BURN_ACCOUNT = 4;
    public const WEB3_BRIDGE_USER = 8;
    public const ADMIN = 16;
    public const PEER_SHOP = 32;
    public const COORDINATOR = 64;
    public const CREATOR = 128;
    public const MODERATOR = 256;
    public const DIRECTOR = 512;
    public const EDITOR = 1024;
    public const EMPLOYEE = 2048;
    public const MAINTAINER = 4096;
    public const SUPER_EDITOR = 8192;
    public const SUPER_MODERATOR = 16384;
    public const PUBLISHER = 32768;
    public const REVIEWER = 65536;
    public const SUBSCRIBER = 131072;
    public const SUPER_ADMIN = 262144;
    public const MANAGER = 524288;
    public const DEVELOPER = 1048576;

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

    /**
     * Maps a roles mask to an array of role names.
     *
     * Should only return valid roles.
     */
    public static function mapRolesMaskToNames(int $rolesMask): array
    {
        $map = self::getMap();

        if (isset($map[$rolesMask])) {
            return [$map[$rolesMask]];
        }

        return [];
    }

}
