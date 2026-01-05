-- This script updates the 'wallett' table by adding back the liquidity fees
-- that were removed from transactions of type 'transferSenderToRecipient'

DO $$
DECLARE
  r RECORD;
  fee numeric;
  net numeric;
BEGIN
  FOR r IN
    SELECT recipientid, tokenamount
    FROM public.transactions
    WHERE transactiontype = 'transferSenderToRecipient'
      AND createdat <= TIMESTAMP '2025-12-09 17:08:00'
    ORDER BY createdat ASC
  LOOP
	net := r.tokenamount / 1.01;
    fee := r.tokenamount - net;

    UPDATE public.wallett
    SET liquidity = liquidity + fee
    WHERE userid = r.recipientid;
  END LOOP;

UPDATE public.users_info ui
SET liquidity = w.liquidity
FROM public.wallett w
WHERE ui.userid = w.userid;

END $$;
