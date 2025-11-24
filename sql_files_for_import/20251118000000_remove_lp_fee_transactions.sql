
BEGIN;

DELETE FROM transactions
WHERE transferaction = 'POOL_FEE'
   OR transactiontype = 'transferSenderToPoolWallet';

COMMIT;