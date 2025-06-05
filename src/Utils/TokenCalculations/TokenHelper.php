<?php

namespace Fawaz\Utils\TokenCalculations;

class TokenHelper {
    public static function calculatePeerTokenEURPrice(float $btcEURPrice,float $peerTokenBTCPrice): ?float
    {
        return $btcEURPrice * $peerTokenBTCPrice;
    }

    public static function calculatePeerTokenPriceValue(float $btcPoolBTCAmount,float $liqPoolTokenAmount): ?float
    {
        // Berechne beforeToken mit hoher Präzision
        $beforeToken = bcdiv((string) $btcPoolBTCAmount, (string) $liqPoolTokenAmount, 20);

        $precision = 10;
        $multiplier = bcpow('10', (string)$precision);
        $scaled = bcmul($beforeToken, $multiplier, 0);
        $tokenPrice = bcdiv($scaled, $multiplier, $precision);
        return $tokenPrice;
    }

    public static function calculateTokenRequiredAmount(float $numberoftokens,float $peerFee,float $poolFee,float $burnFee, float $inviterFee = 0): ?float
    {
        $requiredAmount = $numberoftokens * (1 + $peerFee + $poolFee + $burnFee + $inviterFee);

        return $requiredAmount;
    }

    public static function calculateSwapTokenSenderRequiredAmountIncludingFees(float $feeAmount,float $peerAmount,float $burnAmount,float $inviterAmount = 0): ?float
    {
        $countAmount = $feeAmount + $peerAmount + $burnAmount + $inviterAmount;

        return $countAmount;
    }
    
    static function roundUp($value, $precision = 2)
    {
        $multiplier = pow(10, $precision);
        return ceil($value * $multiplier) / $multiplier;
    }

    static function roundUpFeeAmount($value)
    {
        return TokenHelper::roundUp($value, 2);
    }
}
