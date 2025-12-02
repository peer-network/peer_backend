DO $$
BEGIN
    CREATE TABLE IF NOT EXISTS mint_account (
        accountid        UUID PRIMARY KEY,

        initial_balance  NUMERIC(30,10) NOT NULL CHECK (initial_balance >= 0),
        current_balance  NUMERIC(30,10) NOT NULL,

        createdat        TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat        TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

        -- Ensure current_balance never exceeds initial_balance
        CHECK (current_balance <= initial_balance)
    );
    
    -- Ensure a single default row exists
    IF NOT EXISTS (SELECT 1 FROM mint_account) THEN
        INSERT INTO mint_account (accountid, initial_balance, current_balance)
        VALUES ('11111111-1111-1111-1111-111111111111', 19500000, 18115000); -- 02DEC20205
    END IF;
END $$;