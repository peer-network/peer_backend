<?php

namespace Fawaz\Utils\TokenCalculations;

class TokenHelper
{
    /**
     * Calculates the Peer Token price in EUR based on BTC-EUR and PeerToken-BTC prices.
     *
     * @param float $btcEURPrice Current BTC to EUR price.
     * @param float $peerTokenBTCPrice Current PeerToken to BTC price.
     * @return float|null Calculated PeerToken price in EUR.
     */
    public static function calculatePeerTokenEURPrice(float $btcEURPrice, float $peerTokenBTCPrice): ?float
    {
        return $btcEURPrice * $peerTokenBTCPrice;
    }

    /**
     * Calculates the price of one Peer Token in BTC based on liquidity pool data.
     *
     * @param float $btcPoolBTCAmount Total BTC in the liquidity pool.
     * @param float $liqPoolTokenAmount Total Peer Tokens in the liquidity pool.
     * @return float|null Peer Token price in BTC with 10-digit precision.
     */
    public static function calculatePeerTokenPriceValue(float $btcPoolBTCAmount, float $liqPoolTokenAmount): ?string
    {
        // Calculate token price with high precision using BC Math
        $beforeToken = bcdiv((string) $btcPoolBTCAmount, (string) $liqPoolTokenAmount, 20);

        $precision = 10;
        $multiplier = bcpow('10', (string) $precision);
        $scaled = bcmul($beforeToken, $multiplier, 0);
        $tokenPrice = bcdiv($scaled, $multiplier, $precision);

        $tokenPriceFormattedFromScientificNotation = number_format((float)$tokenPrice, 10, '.', '');

        return $tokenPriceFormattedFromScientificNotation;
    }

    /**
     * Calculates the total number of Peer Tokens required including all applicable fees.
     *
     * @param float $numberOfTokens Base amount of tokens.
     * @param float $peerFee Percentage fee for Peer.
     * @param float $poolFee Percentage fee for liquidity pool.
     * @param float $burnFee Percentage of tokens to be burned.
     * @param float $inviterFee Optional percentage for inviter reward.
     * @return float|null Total required tokens including all fees.
     */
    public static function calculateTokenRequiredAmount(
        float $numberOfTokens,
        float $peerFee,
        float $poolFee,
        float $burnFee,
        float $inviterFee = 0
    ): ?float {
        $requiredAmount = $numberOfTokens * (1 + $peerFee + $poolFee + $burnFee + $inviterFee);
        return $requiredAmount;
    }

    /**
     * Sums up all the fee-related amounts to calculate the total amount a sender must provide in a swap.
     *
     * @param float $feeAmount Network or service fee.
     * @param float $peerAmount Actual token amount.
     * @param float $burnAmount Burned token amount.
     * @param float $inviterAmount Optional amount for inviter reward.
     * @return float|null Total required from sender including all parts.
     */
    public static function calculateSwapTokenSenderRequiredAmountIncludingFees(
        float $feeAmount,
        float $peerAmount,
        float $burnAmount,
        float $inviterAmount = 0
    ): ?float {
        return $feeAmount + $peerAmount + $burnAmount + $inviterAmount;
    }

    /**
     * Rounds a float value up to a given precision.
     *
     * @param float $value The number to round up.
     * @param int $precision Number of decimal places.
     * @return float Rounded up value.
     */
    public static function roundUp(float $value, int $precision = 2): float
    {
        $multiplier = pow(10, $precision);
        return ceil($value * $multiplier) / $multiplier;
    }

    /**
     * Rounds a fee amount up to 2 decimal places.
     *
     * @param float $value The fee value to round.
     * @return float Rounded fee.
     */
    public static function roundUpFeeAmount(float $value): float
    {
        return self::roundUp($value, 2);
    }

    /**
     * Rounds a BTC amount up to 9 decimal places (common BTC precision).
     *
     * @param float $value BTC value to round.
     * @return float Rounded BTC value.
     */
    public static function roundUpBTCAmount(float $value): float
    {
        return self::roundUp($value, 9);
    }
}