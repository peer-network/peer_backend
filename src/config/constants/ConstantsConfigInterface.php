<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

interface ConstantsConfigInterface
{
    public function getData();

    public static function onboarding();
    public static function comment();
    public static function post();
    public static function wallet();
    public static function wallett();
    public static function user();
    public static function chat();
    public static function contact();
    public static function paging();
    public static function transaction();
    public static function contentFiltering();
    public static function dailyFree();
    public static function tokenomics();
    public static function minting();
}

