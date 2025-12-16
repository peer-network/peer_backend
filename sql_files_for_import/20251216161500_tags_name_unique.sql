BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS tags_name_unique_idx
    ON tags (name);

COMMIT;