BEGIN;
-- Table: btc_swap_transactions 
CREATE TABLE IF NOT EXISTS btc_swap_transactions  (
    swapid UUID PRIMARY KEY,
    operationid UUID NOT NULL,
    transactiontype VARCHAR(255) DEFAULT NULL,
    userid UUID DEFAULT NULL,
    btcaddress VARCHAR(255) DEFAULT NULL,
    tokenamount NUMERIC(30,10) DEFAULT 0.0 NOT NULL,
    btcamount NUMERIC(30,10) DEFAULT 0.0 NOT NULL,
    status VARCHAR(255) DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_userId FOREIGN KEY (userid) REFERENCES users(uid),
    CONSTRAINT fk_operationId FOREIGN KEY (operationid) REFERENCES transactions(transactionid) ON DELETE CASCADE
);

COMMIT;