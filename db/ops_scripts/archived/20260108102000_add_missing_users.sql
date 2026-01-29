 WITH missing_users AS (
      SELECT DISTINCT candidate_id
      FROM (
          SELECT userid AS candidate_id FROM logwins WHERE userid IS NOT NULL
          UNION ALL
          SELECT fromid AS candidate_id FROM logwins WHERE fromid IS NOT NULL
      ) AS logwins_users
      WHERE NOT EXISTS (
          SELECT 1 FROM users WHERE users.uid = logwins_users.candidate_id
      )
  ),
  slug_seed AS (
      SELECT COALESCE(MAX(slug), 0) AS base_slug FROM users
  ),
  candidates AS (
      SELECT mu.candidate_id AS uid,
             ROW_NUMBER() OVER (ORDER BY mu.candidate_id) AS rn
      FROM missing_users mu
  ),
  prepared_users AS (
      SELECT
          c.uid,
          format('logwins-missing-%s@placeholder.local', c.rn) AS email,
          format('logwins_missing_%s', c.rn) AS username,
          '$2y$10$placeholder-password-for-logwins' AS password,
          6 AS status,
          0 AS verified,
          ss.base_slug + c.rn AS slug,
          0 AS roles_mask,
          '0.0.0.0'::inet AS ip,
          NULL::varchar(100) AS img,
          NULL::text AS biography
      FROM candidates c
      CROSS JOIN slug_seed ss
  ),
  inserted_users AS (
      INSERT INTO users (
          uid, email, username, password, status, verified,
          slug, roles_mask, ip, img, biography
      )
      SELECT
          uid, email, username, password, status, verified,
          slug, roles_mask, ip, img, biography
      FROM prepared_users
      ON CONFLICT (uid) DO NOTHING
      RETURNING uid
  ),
  inserted_wallets AS (
      INSERT INTO wallett (userid)
      SELECT uid
      FROM prepared_users
      ON CONFLICT (userid) DO NOTHING
      RETURNING userid
  )
  INSERT INTO users_info (userid)
  SELECT uid
  FROM prepared_users
  ON CONFLICT (userid) DO NOTHING;
