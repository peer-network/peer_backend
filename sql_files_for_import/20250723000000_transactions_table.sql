BEGIN;
-- Table: transactions
CREATE TABLE IF NOT EXISTS transactions (
    transactionid UUID PRIMARY KEY,
    transuniqueid UUID NOT NULL,
    transactiontype VARCHAR(255) DEFAULT NULL,
    senderid UUID DEFAULT NULL,
    recipientid UUID DEFAULT NULL,
    tokenamount VARCHAR(255) DEFAULT 0 NOT NULL,
    transferaction VARCHAR(255) DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_senderid FOREIGN KEY (senderid) REFERENCES users(uid),
    CONSTRAINT fk_recipientid FOREIGN KEY (recipientid) REFERENCES users(uid)
);

COMMIT;