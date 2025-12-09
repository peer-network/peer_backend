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

-- Create mint_info table with foreign keys and index
CREATE TABLE IF NOT EXISTS mint_info (
    gemid UUID PRIMARY KEY,
    operationid UUID NOT NULL,
    transactionid UUID NOT NULL,
    tokenamount NUMERIC(30,10) NOT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT tokenamount_nonzero CHECK (tokenamount != 0),

    CONSTRAINT fk_mint_info_transaction
      FOREIGN KEY (transactionid) REFERENCES transactions(transactionid)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_mint_info_gem
      FOREIGN KEY (gemid) REFERENCES gems(gemid)
      ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_mint_info_transactionid ON mint_info(transactionid);
CREATE INDEX IF NOT EXISTS idx_mint_info_operationid ON mint_info(operationid);