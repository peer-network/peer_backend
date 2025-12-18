BEGIN;

ALTER TABLE tags
    ADD CONSTRAINT tags_name_unique
    UNIQUE (name);

CREATE INDEX IF NOT EXISTS idx_tags_name
    ON tags (name);

COMMIT;