BEGIN;
-- Table: eligibility_token_expires
CREATE TABLE IF NOT EXISTS eligibility_token_expires (
    userid UUID NOT NULL,
    eligibility_token TEXT NOT NULL,
    expiresat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);

COMMIT;