codex resume 019bdb1d-f0d9-70e1-a104-bbaed49412d9

Goal:
- use db schema is in `/Users/fcody/Desktop/Peer/peer_backend/sql_files_for_import/` to find db tables relations
- Create csv files with user data using bakcend db of 2 types:
  - 0-groups_data.csv
  - 1-users_1_posts_per_X_to_Y_days.csv



Global rules:
- `time period` start date is 2025-11-28, finish date is 2026-01-20 (since f096107b-ed72-4a2b-9f46-2347a6e4a0b7 - Sergeo(bot network) started)
- `register date` is `users.createdat`
- 4 groups of users whos post interval is (this is a criterium for splitting into groups):
    1 post per  < 7.0 and >= 4 days
    1 per < 4 and >= 2 days
    1 per < 2 and >= 1 day
    1 per < 1 day
      - users with 0 posts are excluded
      - post interval is `time period in days`/`post count`
- 0-groups_data.csv:
  - group_name
  - min and max user token amount from each group `users_info.liquidity`
Exclude from results
  - exclude users whos `register date` timestamp > `time period` start date
  - Ender 6f2e3706-1700-4342-b9aa-5585a6c5eb4d, 
  - Jakob 82079cb8-0a3a-11ef-b7f2-e89c25791d89, 
  - all accounts with `users.roles_mask` != 0


- 1-users_1_posts_per_X_to_Y_days.csv
    - username `users.username`
    - `register date`
    - First post date `posts`
    - Tokens
      - Balance - `users_info.liquidity`
      - Total expenses for the `time period`:
        - Posts - all `transactions.transactioncategory` = POST_CREATE
        - Likes - all `transactions.transactioncategory` = LIKE
        - Dislikes - all `transactions.transactioncategory` = DISLIKE
        - Comments - all `transactions.transactioncategory` = COMMENT
        - Ads - all `transactions.transactioncategory` = AD_PINNED
        - P2P - all `transactions.transactioncategory` = P2P_TRANSFER and `senderid` = `userid`
      - Total earns for the `time period`:
        - mints `transactions.transactioncategory` = MINT
        - Likes - SUM(`gems.gems` * `mints.gemsInToken` per day in `time pediod` where whereby = 2) 
        - Views - SUM(`gems.gems` * `mints.gemsInToken` per day in `time pediod` where whereby = 1) 
        - Comments - SUM(`gems.gems` * `mints.gemsInToken` per day in `time pediod` where whereby = 4) 
        - P2P - all `transactions.transactioncategory` = P2P_TRANSFER and `recipientid` = `userid`
    - User activity counts
      - Posts `posts`
      - Likes in `user_post_likes` + `user_comment_likes` where `posts.userid`/`comments.userid` for `user_post/comment_likes.postid/commentid` = userid
      - Likes out `user_post_likes` + `user_comment_likes` where `user_post/comment_likes.userid` = userid
      - Dislikes in `user_post_dislikes`
      - Dislikes out `user_post_dislikes`
      - Comment `user_post_comment`
    - Register ip `logdata.ip` on `userid` and `action_type` = 'verifiedAccount'



## SQL extraction query

The script below runs inside `psql` and materializes a temporary table with all filtered user metrics so the two CSV exports can re-use the same expensive calculations. It follows every rule above, including the registration-date cutoff, manual account exclusions, and the four posting-frequency buckets. Adjust the file paths in the \copy commands if you want the CSVs somewhere else.

```sql
-- Generates data for 0-groups_data.csv and 1-users_1_posts_per_X_to_Y_days.csv
-- Run inside psql. Adjust \copy destinations if necessary.

\timing on

DROP TABLE IF EXISTS tmp_user_interval_metrics;

CREATE TEMP TABLE tmp_user_interval_metrics AS
WITH params AS (
    SELECT DATE '2025-11-28' AS start_date,
           DATE '2026-01-20' AS end_date,
           (DATE '2026-01-20' - DATE '2025-11-28')::numeric AS period_days
),
user_registration AS (
    SELECT DISTINCT ON (userid)
           userid,
           ip AS register_ip
    FROM logdata
    WHERE action_type = 'verifiedAccount'
    ORDER BY userid, createdat
),
eligible_users AS (
    SELECT u.uid,
           u.username,
           ui.liquidity,
           u.createdat AS register_date,
           reg.register_ip
    FROM users u
    JOIN users_info ui ON ui.userid = u.uid
    JOIN user_registration reg ON reg.userid = u.uid
    CROSS JOIN params pr
    WHERE u.roles_mask = 0
      AND u.createdat <= pr.start_date::timestamp
      AND u.uid NOT IN (
            '6f2e3706-1700-4342-b9aa-5585a6c5eb4d', -- Ender
            '82079cb8-0a3a-11ef-b7f2-e89c25791d89'  -- Jakob
      )
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
           eu.register_ip,
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
    gu.register_ip,
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
SELECT username,
       register_date,
       register_ip,
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
```

**Notes:**
- Mint earnings accept both `MINT` and `TOKEN_MINT` categories until legacy data is cleaned up.
- If a `gems` record references a `mintid`, that ratio is used; otherwise it falls back to the ratio defined on the same calendar day (or zero when no day-level ratio exists).
- Users whose computed post interval falls outside the four requested ranges are omitted because they do not belong to any group.
- Transactions with non-numeric tokenamount values safely default to 0 so the script no longer aborts when legacy data contains text payloads.
- Remove the `\timing` or change the `\copy` output targets when scripting in automation.
