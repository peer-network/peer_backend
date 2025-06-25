<?php

namespace Fawaz\Utils\TokenCalculations;

define('Q64_96_SCALE', bcpow('2', '96'));

class TokenHelper
{
    /**
     * Calculates the Peer Token price in EUR based on BTC-EUR and PeerToken-BTC prices.
     *
     * @param float $btcEURPrice Current BTC to EUR price.
     * @param float $peerTokenBTCPrice Current PeerToken to BTC price.
     * @return float|null Calculated PeerToken price in EUR.
     */
    public static function calculatePeerTokenEURPrice(float $btcEURPrice, float $peerTokenBTCPrice): ?string
    {
        
        $btcEURPrice = self::convertToQ96($btcEURPrice);
        $peerTokenBTCPrice = self::convertToQ96($peerTokenBTCPrice);

        $peerValue = self::mulQ96($btcEURPrice, $peerTokenBTCPrice);

        return ($peerValue);
    }

    /**
     * Calculates the price of one Peer Token in BTC based on liquidity pool data.
     *
     * @param float $btcPoolBTCAmount Total BTC in the liquidity pool.
     * @param float $liqPoolTokenAmount Total Peer Tokens in the liquidity pool.
     * @return float|null Peer Token price in BTC with 10-digit precision.
     */
    public static function calculatePeerTokenPriceValue(string $btcPoolBTCAmount, string $liqPoolTokenAmount): ?string
    {
        $beforeToken = self::divQ96($btcPoolBTCAmount, $liqPoolTokenAmount);
        
        return self::decodeFromQ96($beforeToken);
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
        string $numberOfTokens,
        float $peerFee,
        float $poolFee,
        float $burnFee,
        float $inviterFee = 0
    ): ?string {
        $allFees = self::convertToQ96((1 + $peerFee + $poolFee + $burnFee + $inviterFee));

        $requiredAmount = self::mulQ96($numberOfTokens, $allFees);

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
    ): ?string {
        return self::convertToQ96($feeAmount + $peerAmount + $burnAmount + $inviterAmount);
    }


     /**
     * Converts a floating-point number to its Q96 fixed-point representation.
     *
     * Q96 is a fixed-point format where values are scaled by 2^96.
     * This method multiplies the given float by 2^96 and returns the result as a string.
     *
     * @param float $value The floating-point number to convert.
     * @return string The Q96-scaled value as a string.
     */
    public static function convertToQ96(float $value): string
    {
        $decimalString = number_format($value, 30, '.', '');
        return bcmul($decimalString, Q64_96_SCALE, 0);
    }

    /**
     * Converts a Q96 fixed-point string back to a human-readable decimal string.
     *
     * This method divides the given Q96 value by 2^96 to recover the original number.
     *
     * @param string $q96Value The Q96-encoded string.
     * @param int $precision The number of decimal digits to include in the result (default: 18).
     * @return string The decoded decimal value.
     */
    public static function decodeFromQ96(string $q96Value, int $precision = 9): string
    {
        return bcdiv($q96Value, Q64_96_SCALE, $precision);
    }


    /**
     * Adds two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function addQ96(string $q96Value1, string $q96Value2): string
    {
        return bcadd($q96Value1, $q96Value2);
    }


    /**
     * Multiply two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function mulQ96(string $q96Value1, string $q96Value2): string
    {
        $result = bcmul($q96Value1, $q96Value2);

        return bcdiv($result, Q64_96_SCALE, 0);

    }
    
    /**
     * divide two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function divQ96(string $q96Value1, string $q96Value2): string
    {
        $scaled = bcmul($q96Value1, Q64_96_SCALE);
        return bcdiv($scaled, $q96Value2, 0);
    }

    /**
     * compare two Q96-encoded values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function compare(string $qValue1, string $qValue2): int
    {
        if (!self::isValidQ64_96($qValue1) || !self::isValidQ64_96($qValue2)) {
            return 0;
        }
        return bccomp($qValue1, $qValue2);
    }
        
    /**
     * Substract two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function subQ96(string $q96Value1, string $q96Value2): string
    {
        return bcsub($q96Value1, $q96Value2);
    }

    // Überprüft, ob der Wert eine gültige Q64.96-Zahl ist
    public static function isValidQ64_96(string $qValue): bool
    {
        if (!preg_match('/^[0-9]+$/', $qValue)) {
            return false;
        }
        if (bccomp($qValue, MAX_VAL_Q_96) >= 0) {
            return false;
        }
        return true;
    }
}
