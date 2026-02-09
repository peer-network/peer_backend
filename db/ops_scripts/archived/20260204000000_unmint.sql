-- Unmint: reset minted gems, remove mint transactions, clear mints
-- Intent: roll back mint artifacts so gems can be re-minted.
-- Safe-ish to re-run: updates are idempotent; deletes remove only mint data.

BEGIN;

-- Reset only gems tied to mints or mint transactions
UPDATE gems
SET
    collected = 0,
    transactionid = NULL,
    mintid = NULL;

-- Remove mint transactions
DELETE FROM transactions
WHERE transactioncategory = 'TOKEN_MINT';

-- Remove all mint rows
DELETE FROM mints;

COMMIT;
