BEGIN;

CREATE TABLE IF NOT EXISTS mint_account (
    accountid        UUID PRIMARY KEY,
    initial_balance  NUMERIC(30,10) NOT NULL CHECK (initial_balance >= 0),
    current_balance  NUMERIC(30,10) NOT NULL,
    createdat        TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedat        TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (current_balance <= initial_balance)
);


INSERT INTO mint_account (accountid, initial_balance, current_balance)
SELECT '00000000-0000-0000-0000-000000000001', 19500000, 18115000
WHERE NOT EXISTS (SELECT 1 FROM mint_account);


CREATE TABLE IF NOT EXISTS mints (
    mintid              UUID PRIMARY KEY,
    day                 DATE UNIQUE NOT NULL,
    gems_in_token_ratio NUMERIC(30,10) NOT NULL CHECK (gems_in_token_ratio > 0),
    createdat           TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Add optional UUID columns to gems
ALTER TABLE gems
    ADD COLUMN IF NOT EXISTS mintid UUID DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS transactionid UUID DEFAULT NULL;

-- Add foreign key constraints (if not already present)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_gems_mintid'
    ) THEN
        ALTER TABLE gems
            ADD CONSTRAINT fk_gems_mintid FOREIGN KEY (mintid) REFERENCES mints(mintid);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_gems_transactionid'
    ) THEN
        ALTER TABLE gems
            ADD CONSTRAINT fk_gems_transactionid FOREIGN KEY (transactionid) REFERENCES transactions(transactionid);
    END IF;
END $$ LANGUAGE plpgsql;

-- Indexes to speed up lookups
CREATE INDEX IF NOT EXISTS idx_gems_mintid ON gems(mintid);
CREATE INDEX IF NOT EXISTS idx_gems_transactionid ON gems(transactionid);

COMMIT;
