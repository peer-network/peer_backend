BEGIN;

-- Remove the default value from transactions.tokenamount
-- Keeps existing NOT NULL and type constraints intact
ALTER TABLE transactions
    ALTER COLUMN tokenamount DROP DEFAULT;

COMMIT;

