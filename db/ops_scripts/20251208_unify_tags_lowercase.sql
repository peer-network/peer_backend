BEGIN;

UPDATE tags
SET name = LOWER(name);

WITH tag_groups AS (
    SELECT
        name,
        MIN(tagid) AS primary_tagid
    FROM tags
    GROUP BY name
),
normalized_post_tags AS (
    SELECT
        tg.primary_tagid AS tagid,
        pt.postid,
        MIN(pt.createdat) AS createdat
    FROM post_tags pt
    JOIN tags t
      ON t.tagid = pt.tagid
    JOIN tag_groups tg
      ON tg.name = t.name
    GROUP BY tg.primary_tagid, pt.postid
)
INSERT INTO post_tags (tagid, postid, createdat)
SELECT tagid, postid, createdat
FROM normalized_post_tags
ON CONFLICT (postid, tagid) DO NOTHING;

WITH tag_groups AS (
    SELECT
        name,
        MIN(tagid) AS primary_tagid
    FROM tags
    GROUP BY name
)
DELETE FROM post_tags pt
USING tags t, tag_groups tg
WHERE pt.tagid = t.tagid
  AND t.name = tg.name
  AND pt.tagid <> tg.primary_tagid;

WITH tag_groups AS (
    SELECT
        name,
        MIN(tagid) AS primary_tagid
    FROM tags
    GROUP BY name
)
DELETE FROM tags t
USING tag_groups tg
WHERE t.name = tg.name
  AND t.tagid <> tg.primary_tagid;

CREATE UNIQUE INDEX IF NOT EXISTS tags_name_unique_idx ON tags (name);

COMMIT;