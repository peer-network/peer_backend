<?php

namespace Fawaz\App;

final class Role {

    const USER = 0;
    const SYSTEM_ACCOUNT = 1;
    const ADVANCED = 2;
    const EXPERT = 4;
    const WEB3_BRIDGE_USER = 8;
    const ADMIN = 16;
    const CONTRIBUTOR = 32;
    const COORDINATOR = 64;
    const CREATOR = 128;
    const SUPER_MODERATOR = 256;
    const DIRECTOR = 512;
    const EDITOR = 1024;
    const EMPLOYEE = 2048;
    const MAINTAINER = 4096;
    const SUPER_EDITOR = 8192;
    const MODERATOR = 16384;
    const PUBLISHER = 32768;
    const REVIEWER = 65536;
    const SUBSCRIBER = 131072;
    const SUPER_ADMIN = 262144;
    const MANAGER = 524288;
    const DEVELOPER = 1048576;
    const COMPANY_ACCOUNT = 3;
    const BURN_ACCOUNT = 5;
    const LOOP_ACCOUNT = 6;

    public static function getMap() : array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_flip($reflectionClass->getConstants());
    }

    public static function getNames() : array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_keys($reflectionClass->getConstants());
    }

    public static function getValues() : array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \array_values($reflectionClass->getConstants());
    }

    private function __construct() {}
}
