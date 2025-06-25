<?php

namespace Fawaz\Utils\TokenCalculations;

const POOLFEE=0.01;

class SwapTokenHelper
{

    /**
     * Calculate the amount of BTC a user will receive when swapping a given amount of tokens.
     *
     * @param float $btcLpTokenReserve Initial BTC reserve in the liquidity pool.
     * @param float $lpTokenReserve Initial token reserve in the liquidity pool.
     * @param float $tokensToSwap Amount of tokens the user wants to swap for BTC.
     * @param float $POOLFEE Liquidity Pool fee.
     * @return float Amount of BTC the user will receive after fees and pool adjustment.
     */
    public static function calculateBtc(string $btcLpTokenReserve, string $lpTokenReserve, string $tokensToSwap, float $POOLFEE = 0.01): string
    {
        // Convert inputs to Q96 fixed-point format
        $tokensToSwapQ96 = $tokensToSwap;

        $POOLFEE = TokenHelper::convertToQ96($POOLFEE);
        // Step 1: Calculate fee in Q96
        $feeAmountQ96 = TokenHelper::mulQ96($tokensToSwap, $POOLFEE);

        // Step 2: Add pool fee to LP token reserve
        $lpTokenAmountAfterLPFee = TokenHelper::addQ96($lpTokenReserve, $feeAmountQ96);

        // Step 3: Calculate product of new token reserve and BTC reserve
        $constantProductQ96 = TokenHelper::mulQ96($lpTokenAmountAfterLPFee, $btcLpTokenReserve);

        // Step 4: Add user tokens to pool
        $lpAccountAfterTokenTransfer = TokenHelper::addQ96($lpTokenAmountAfterLPFee, $tokensToSwapQ96);

        // Step 5: Calculate new BTC reserve after swap
        $btcPeerTokenAfterSwap = TokenHelper::divQ96($constantProductQ96, $lpAccountAfterTokenTransfer);

        // Step 6: BTC received = Initial BTC reserve - new BTC reserve
        $btcReceivedQ96 = TokenHelper::subQ96($btcLpTokenReserve, $btcPeerTokenAfterSwap);

        // Convert result back to float for human-readability
        return $btcReceivedQ96;
    }

}
