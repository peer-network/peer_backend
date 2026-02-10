BEGIN;

CREATE TABLE IF NOT EXISTS user_device_tokens (
    userid UUID NOT NULL,
    token TEXT NOT NULL,
    platform VARCHAR(20) DEFAULT NULL,
    language VARCHAR(64) DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_device_tokens_user
        FOREIGN KEY (userid)
        REFERENCES users(uid)
        ON DELETE CASCADE,

    CONSTRAINT uq_user_device_tokens_user_token
        UNIQUE (userid, token)
);

CREATE INDEX IF NOT EXISTS idx_user_device_tokens_userid
    ON user_device_tokens(userid);

CREATE INDEX IF NOT EXISTS idx_user_device_tokens_token
    ON user_device_tokens(token);


COMMIT;
