BEGIN;

-- Table: user_preferences
CREATE TABLE IF NOT EXISTS user_preferences (
    userid                           UUID PRIMARY KEY,
    content_filtering_severity_level SMALLINT DEFAULT NULL CHECK (
        content_filtering_severity_level IS NULL OR 
        (content_filtering_severity_level >= 0 AND content_filtering_severity_level <= 10)
    ),
    updatedat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,

    CONSTRAINT fk_user_preferences_users FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);
-- CREATE INDEX idx_userid_user_preferences ON user_preferences(userid);


-- Table: user_preferences
INSERT INTO user_preferences (userid)
    SELECT uid
    FROM users
    WHERE uid NOT IN (
        SELECT userid FROM user_preferences
    );

COMMIT;