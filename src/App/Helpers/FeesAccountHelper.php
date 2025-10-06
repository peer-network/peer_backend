<?php

namespace Fawaz\App\Helpers;

class FeesAccountHelper
{
    public static function getAccounts()
    {
        return [
            'PEER_BANK' => $_ENV['PEER_BANK'],
            'BURN_ACCOUNT' => $_ENV['BURN_ACCOUNT'],
            'LIQUIDITY_POOL' => $_ENV['LIQUIDITY_POOL'],
            'BTC_POOL' => $_ENV['BTC_POOL'],
        ];
    }
}
