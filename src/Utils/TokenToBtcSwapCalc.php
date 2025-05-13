<?php

namespace Fawaz\Utils;

class TokenToBtcSwapCalc
{
    /**
     * Calculates the equivalent amount of BTC for a given number of Peer Tokens.
     *
     * @param int $peerTokens Number of Peer Tokens to swap.
     * @return float Equivalent BTC amount.
     * 
     * 
     * newTokenPool = tokenPool + 100% (swap portion)
     *  newBtcPool = k / newTokenPool
     *   btcReceived = btcPool - newBtcPool
     */
    public static function convert(int $peerTokens): float
    {

        $tokenPool = self::getPeerTokenPool();
        $btcPool = self::getBtcPool();

        $constantK = $tokenPool * $btcPool;

        $newTokenPool = $tokenPool + $peerTokens;

        $newBtcPool = $constantK / $newTokenPool;

        $btcReceived = $btcPool - $newBtcPool;

        return $btcReceived;
    }

    /**
     * PENDING
     * 
     * Get Peer tokens from Liquidity Pool.
     * 
     * Should be calculated dynamically
     * 
     */
    private static function getPeerTokenPool(){
        return 100000;
    }

    /**
     * PENDING
     * 
     * Get BTC Amount from Liquidity Pool.
     * 
     * Should be calculated dynamically
     */
    private static function getBtcPool(){
        return 10000;
    }
}
