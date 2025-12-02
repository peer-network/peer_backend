<?php

declare(strict_types=1);

namespace Fawaz\App;

final class Role
{
    public const int USER = 0;
    public const int SYSTEM_ACCOUNT = 1; // LP Account
    public const int COMPANY_ACCOUNT = 2; // PEER BANK ACCOUNT
    public const int BURN_ACCOUNT = 4;
    public const int WEB3_BRIDGE_USER = 8;
    public const int ADMIN = 16;
    public const int CONTRIBUTOR = 32;
    public const int COORDINATOR = 64;
    public const int CREATOR = 128;
    public const int MODERATOR = 256;
    public const int DIRECTOR = 512;
    public const int EDITOR = 1024;
    public const int EMPLOYEE = 2048;
    public const int MAINTAINER = 4096;
    public const int SUPER_EDITOR = 8192;
    public const int SUPER_MODERATOR = 16384;
    public const int PUBLISHER = 32768;
    public const int REVIEWER = 65536;
    public const int SUBSCRIBER = 131072;
    public const int SUPER_ADMIN = 262144;
    public const int MANAGER = 524288;
    public const int DEVELOPER = 1048576;

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
