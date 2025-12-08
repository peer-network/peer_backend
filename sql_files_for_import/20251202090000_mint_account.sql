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
