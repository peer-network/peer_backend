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
    public static function calculateBtc(float $btcLpTokenReserve, float $lpTokenReserve, float $tokensToSwap, float $POOLFEE = 0.01): float
    {
        // Step 1: New token reserve after adding the pool fee.
        $tokenWithPoolFee = TokenHelper::roundUpFeeAmount($tokensToSwap * $POOLFEE);

        // Count LP after including Fees
        $lpTokenAmountAfterLPFee = TokenHelper::roundUpFeeAmount($lpTokenReserve + $tokenWithPoolFee);
        $contsAfterLpFees = TokenHelper::roundUpBTCAmount($lpTokenAmountAfterLPFee * $btcLpTokenReserve);   

        
        // Count LP After Adding Amount of Peer Token
        $lpAccountAfterTokenTransfer = TokenHelper::roundUpFeeAmount($lpTokenAmountAfterLPFee + $tokensToSwap);
        $btcPeerTokenAfterSwap = TokenHelper::roundUpBTCAmount($contsAfterLpFees / $lpAccountAfterTokenTransfer);  

        
        // BTC amount to user
        $btcReceived = TokenHelper::roundUpBTCAmount($btcLpTokenReserve - $btcPeerTokenAfterSwap);

        return $btcReceived;
    }
}
