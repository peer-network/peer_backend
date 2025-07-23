BEGIN;
-- Table: transactions
CREATE TABLE IF NOT EXISTS transactions (
    transactionId UUID PRIMARY KEY,
    transUniqueId UUID NOT NULL,
    transactionType VARCHAR(255) DEFAULT NULL,
    senderId UUID DEFAULT NULL,
    recipientId UUID DEFAULT NULL,
    tokenAmount VARCHAR(255) DEFAULT 0 NOT NULL,
    transferAction VARCHAR(255) DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_senderId FOREIGN KEY (senderId) REFERENCES users(uid),
    CONSTRAINT fk_recipientId FOREIGN KEY (recipientId) REFERENCES users(uid)
);

COMMIT;