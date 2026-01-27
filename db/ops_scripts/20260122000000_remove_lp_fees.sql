BEGIN;

UPDATE transactions AS credit
SET
  tokenamount = credit.tokenamount + lp.tokenamount
FROM
  transactions AS lp
WHERE
  credit.transferaction = 'CREDIT'
  AND lp.transferaction = 'POOL_FEE'
  AND credit.operationid = lp.operationid
  AND credit.senderid = lp.senderid;

DELETE FROM transactions
WHERE
  transferaction = 'POOL_FEE';

COMMIT;
