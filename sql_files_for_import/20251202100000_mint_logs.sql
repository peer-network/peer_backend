DO $$
BEGIN
    -- Create table if it does not exist
    CREATE TABLE IF NOT EXISTS mint_logs (
        mintid         UUID PRIMARY KEY,
        mint_date      TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
        minted_amount  NUMERIC(30,10) NOT NULL CHECK (minted_amount >= 0 AND minted_amount <= 5000), -- considering possible drift
        createdat      TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status         VARCHAR(20) NOT NULL DEFAULT 'completed',  -- possible values: 'completed', 'failed'

        UNIQUE (mint_date)
    );

    -- Add status check constraint only if missing
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_mint_status'
          AND conrelid = 'mint_logs'::regclass
    ) THEN
        ALTER TABLE mint_logs
            ADD CONSTRAINT chk_mint_status
            CHECK (status IN ('completed', 'failed'));
    END IF;
END $$;
