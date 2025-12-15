BEGIN;

-- Backfill transaction categories based on historical transaction types.
UPDATE transactions
SET transactioncategory = 'P2P_TRANSFER'
WHERE transactioncategory IS NULL
  AND transactiontype IN (
    'transferSenderToRecipient'
  );

UPDATE transactions
SET transactioncategory = 'ADVERTISEMENT'
WHERE transactioncategory IS NULL
  AND transactiontype IN ('transferForAds');

UPDATE transactions
SET transactioncategory = 'POST_CREATE'
WHERE transactioncategory IS NULL
  AND transactiontype IN ('transferForPost');

UPDATE transactions
SET transactioncategory = 'LIKE'
WHERE transactioncategory IS NULL
  AND transactiontype IN ('transferForLike');

UPDATE transactions
SET transactioncategory = 'DISLIKE'
WHERE transactioncategory IS NULL
  AND transactiontype IN ('transferForDislike');

UPDATE transactions
SET transactioncategory = 'COMMENT'
WHERE transactioncategory IS NULL
  AND transactiontype IN ('transferForComment');

UPDATE transactions
SET transactioncategory = 'FEE'
WHERE transactioncategory IS NULL
  AND transactiontype IN (
        'transferSenderToBurnWallet',
        'transferSenderToPeerWallet',
        'transferSenderToPoolWallet',
        'transferSenderToInviter'
  );

COMMIT;
