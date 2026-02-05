\timing on

DROP TABLE IF EXISTS tmp_user_interval_metrics;

CREATE TEMP TABLE tmp_user_interval_metrics AS
WITH params AS (
    SELECT DATE '2025-11-27' AS start_date,
           DATE '2026-01-20' AS end_date,
           (DATE '2026-01-20' - DATE '2025-11-27')::numeric AS period_days
),
eligible_users AS (
    SELECT u.uid,
           u.username,
           ui.liquidity,
           u.createdat AS register_date
    FROM users u
    JOIN users_info ui ON ui.userid = u.uid
    CROSS JOIN params pr
    WHERE u.roles_mask = 0
      -- AND u.createdat <= pr.start_date::timestamp
      -- AND u.uid NOT IN (
      --       '6f2e3706-1700-4342-b9aa-5585a6c5eb4d', -- Ender
      --       '82079cb8-0a3a-11ef-b7f2-e89c25791d89',  -- Jakob
      --       'f59d869f-d36d-4c7f-b435-236015f8dbfd' -- CTO_guy
      -- )
),
period_posts AS (
    SELECT p.userid,
           COUNT(*) AS posts_count,
           MIN(p.createdat) AS first_post_date
    FROM posts p
    CROSS JOIN params pr
    WHERE p.createdat >= pr.start_date::timestamp
      AND p.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY p.userid
),
user_base AS (
    SELECT eu.uid,
           eu.username,
           eu.liquidity,
           eu.register_date,
           pp.posts_count,
           pp.first_post_date,
           (pr.period_days / NULLIF(pp.posts_count::numeric, 0)) AS post_interval_days
    FROM eligible_users eu
    JOIN period_posts pp ON pp.userid = eu.uid
    CROSS JOIN params pr
    WHERE pp.posts_count > 0
),
grouped_users AS (
    SELECT ub.*,
           CASE
             WHEN ub.post_interval_days >= 7 THEN '1_post_per_>=7_days'
             WHEN ub.post_interval_days < 7 AND ub.post_interval_days >= 4 THEN '1_post_per_4_to_<7_days'
             WHEN ub.post_interval_days < 4 AND ub.post_interval_days >= 2 THEN '1_post_per_2_to_<4_days'
             WHEN ub.post_interval_days < 2 AND ub.post_interval_days >= 1 THEN '1_post_per_1_to_<2_days'
             WHEN ub.post_interval_days < 1 THEN '1_post_per_<1_day'
             ELSE NULL
           END AS group_name
    FROM user_base ub
),
transactions_in_period AS (
    SELECT t.*,
           CASE
             WHEN t.tokenamount::text ~ '^-?[0-9]+(\.[0-9]+)?$' THEN t.tokenamount::numeric
             ELSE 0
           END AS amount
    FROM transactions t
    CROSS JOIN params pr
    WHERE t.createdat >= pr.start_date::timestamp
      AND t.createdat < (pr.end_date + INTERVAL '1 day')
),
user_expenses AS (
    SELECT senderid AS userid,
           SUM(CASE WHEN transactioncategory = 'POST_CREATE' THEN amount ELSE 0 END) AS expense_posts,
           SUM(CASE WHEN transactioncategory = 'LIKE' THEN amount ELSE 0 END) AS expense_likes,
           SUM(CASE WHEN transactioncategory = 'DISLIKE' THEN amount ELSE 0 END) AS expense_dislikes,
           SUM(CASE WHEN transactioncategory = 'COMMENT' THEN amount ELSE 0 END) AS expense_comments,
           SUM(CASE WHEN transactioncategory = 'AD_PINNED' THEN amount ELSE 0 END) AS expense_ads,
           SUM(CASE WHEN transactioncategory = 'P2P_TRANSFER' THEN amount ELSE 0 END) AS expense_p2p_out
    FROM transactions_in_period
    WHERE senderid IS NOT NULL
    GROUP BY senderid
),
user_income_tx AS (
    SELECT recipientid AS userid,
           SUM(CASE WHEN transactioncategory IN ('MINT', 'TOKEN_MINT') THEN amount ELSE 0 END) AS earn_mints,
           SUM(CASE WHEN transactioncategory = 'P2P_TRANSFER' THEN amount ELSE 0 END) AS earn_p2p_in
    FROM transactions_in_period
    WHERE recipientid IS NOT NULL
    GROUP BY recipientid
),
gems_enriched AS (
    SELECT g.userid,
           g.whereby,
           g.gems,
           g.createdat,
           g.gems * COALESCE(m.gems_in_token_ratio,
                              (SELECT m2.gems_in_token_ratio
                               FROM mints m2
                               WHERE m2.day = DATE(g.createdat)
                               ORDER BY m2.day DESC
                               LIMIT 1),
                              0) AS gems_token_amount
    FROM gems g
    LEFT JOIN mints m ON m.mintid = g.mintid
    CROSS JOIN params pr
    WHERE g.createdat >= pr.start_date::timestamp
      AND g.createdat < (pr.end_date + INTERVAL '1 day')
),
user_gem_income AS (
    SELECT userid,
           SUM(CASE WHEN whereby = 2 THEN gems_token_amount ELSE 0 END) AS earn_likes_gems,
           SUM(CASE WHEN whereby = 1 THEN gems_token_amount ELSE 0 END) AS earn_views_gems,
           SUM(CASE WHEN whereby = 4 THEN gems_token_amount ELSE 0 END) AS earn_comments_gems
    FROM gems_enriched
    GROUP BY userid
),
likes_in_posts AS (
    SELECT p.userid, COUNT(*) AS likes_in_posts
    FROM user_post_likes upl
    JOIN posts p ON p.postid = upl.postid
    CROSS JOIN params pr
    WHERE upl.createdat >= pr.start_date::timestamp
      AND upl.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY p.userid
),
likes_in_comments AS (
    SELECT c.userid, COUNT(*) AS likes_in_comments
    FROM user_comment_likes ucl
    JOIN comments c ON c.commentid = ucl.commentid
    CROSS JOIN params pr
    WHERE ucl.createdat >= pr.start_date::timestamp
      AND ucl.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY c.userid
),
likes_out_posts AS (
    SELECT userid, COUNT(*) AS likes_out_posts
    FROM user_post_likes upl
    CROSS JOIN params pr
    WHERE upl.createdat >= pr.start_date::timestamp
      AND upl.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY userid
),
likes_out_comments AS (
    SELECT userid, COUNT(*) AS likes_out_comments
    FROM user_comment_likes ucl
    CROSS JOIN params pr
    WHERE ucl.createdat >= pr.start_date::timestamp
      AND ucl.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY userid
),
dislikes_in AS (
    SELECT p.userid, COUNT(*) AS dislikes_in
    FROM user_post_dislikes upd
    JOIN posts p ON p.postid = upd.postid
    CROSS JOIN params pr
    WHERE upd.createdat >= pr.start_date::timestamp
      AND upd.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY p.userid
),
dislikes_out AS (
    SELECT userid, COUNT(*) AS dislikes_out
    FROM user_post_dislikes upd
    CROSS JOIN params pr
    WHERE upd.createdat >= pr.start_date::timestamp
      AND upd.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY userid
),
comments_made AS (
    SELECT userid, COUNT(*) AS comments_count
    FROM user_post_comments upc
    CROSS JOIN params pr
    WHERE upc.createdat >= pr.start_date::timestamp
      AND upc.createdat < (pr.end_date + INTERVAL '1 day')
    GROUP BY userid
)
SELECT
    gu.uid,
    gu.username,
    gu.register_date,
    gu.first_post_date,
    gu.posts_count,
    gu.post_interval_days,
    gu.group_name,
    gu.liquidity AS balance_tokens,
    COALESCE(ue.expense_posts, 0) AS expense_posts,
    COALESCE(ue.expense_likes, 0) AS expense_likes,
    COALESCE(ue.expense_dislikes, 0) AS expense_dislikes,
    COALESCE(ue.expense_comments, 0) AS expense_comments,
    COALESCE(ue.expense_ads, 0) AS expense_ads,
    COALESCE(ue.expense_p2p_out, 0) AS expense_p2p_out,
    (COALESCE(ue.expense_posts, 0) + COALESCE(ue.expense_likes, 0) + COALESCE(ue.expense_dislikes, 0) +
     COALESCE(ue.expense_comments, 0) + COALESCE(ue.expense_ads, 0) + COALESCE(ue.expense_p2p_out, 0)) AS expense_total,
    COALESCE(uit.earn_mints, 0) AS earn_mints,
    COALESCE(ug.earn_likes_gems, 0) AS earn_likes_gems,
    COALESCE(ug.earn_views_gems, 0) AS earn_views_gems,
    COALESCE(ug.earn_comments_gems, 0) AS earn_comments_gems,
    COALESCE(uit.earn_p2p_in, 0) AS earn_p2p_in,
    (COALESCE(uit.earn_mints, 0) + COALESCE(ug.earn_likes_gems, 0) +
     COALESCE(ug.earn_views_gems, 0) + COALESCE(ug.earn_comments_gems, 0) + COALESCE(uit.earn_p2p_in, 0)) AS earn_total,
    COALESCE(lip.likes_in_posts, 0) + COALESCE(lic.likes_in_comments, 0) AS likes_in,
    COALESCE(lop.likes_out_posts, 0) + COALESCE(loc.likes_out_comments, 0) AS likes_out,
    COALESCE(di.dislikes_in, 0) AS dislikes_in,
    COALESCE(dout.dislikes_out, 0) AS dislikes_out,
    COALESCE(cm.comments_count, 0) AS comments_made
FROM grouped_users gu
LEFT JOIN user_expenses ue ON ue.userid = gu.uid
LEFT JOIN user_income_tx uit ON uit.userid = gu.uid
LEFT JOIN user_gem_income ug ON ug.userid = gu.uid
LEFT JOIN likes_in_posts lip ON lip.userid = gu.uid
LEFT JOIN likes_in_comments lic ON lic.userid = gu.uid
LEFT JOIN likes_out_posts lop ON lop.userid = gu.uid
LEFT JOIN likes_out_comments loc ON loc.userid = gu.uid
LEFT JOIN dislikes_in di ON di.userid = gu.uid
LEFT JOIN dislikes_out dout ON dout.userid = gu.uid
LEFT JOIN comments_made cm ON cm.userid = gu.uid
WHERE gu.group_name IS NOT NULL;

CREATE OR REPLACE TEMP VIEW tmp_group_liquidity AS
SELECT group_name,
       MIN(balance_tokens) AS min_liquidity,
       MAX(balance_tokens) AS max_liquidity
FROM tmp_user_interval_metrics
GROUP BY group_name
ORDER BY group_name;

CREATE OR REPLACE TEMP VIEW tmp_user_export AS
SELECT 
       username,
       register_date,
       first_post_date,
       posts_count,
       post_interval_days,
       group_name,
       balance_tokens,
       expense_posts,
       expense_likes,
       expense_dislikes,
       expense_comments,
       expense_ads,
       expense_p2p_out,
       expense_total,
       earn_mints,
       earn_likes_gems,
       earn_views_gems,
       earn_comments_gems,
       earn_p2p_in,
       earn_total,
       likes_in,
       likes_out,
       dislikes_in,
       dislikes_out,
       comments_made
FROM tmp_user_interval_metrics
ORDER BY group_name, post_interval_days, username;

\echo 'Export grouped liquidity min/max per posting bucket'
\copy (SELECT * FROM tmp_group_liquidity) TO '0-groups_data.csv' WITH CSV HEADER

\echo 'Export per-user analytics'
\copy (SELECT * FROM tmp_user_export) TO '1-users_1_posts_per_X_to_Y_days.csv' WITH CSV HEADER