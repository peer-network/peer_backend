-- take logwins where gems > 0. taking one logwins.userid per one day, make a sum of logwins.numbers as user_token_amount_per_mint

-- for each logwins.userid per one day make a transactions row

-- add to transactions
---- generate transactionid
---- generate operationid 
---- transferMintAccountToRecipient
---- senderid 00000000-0000-0000-0000-000000000001
---- recipientid userid from logwins
---- tokenamont user_token_amount_per_mint
---- transferaction CREDIT
---- message null
---- createdat logwins date
---- category TOKEN_MINT




-- add to mints
-- one row per day 
---- generate mintid
---- day data from logwins
---- gems_in_token_ratio 
------ 1. create a gems sum: take logwins table, group by one date and make a sum of logwins.gems
------ 2. gems_in_token_ratio = 5000 / gems sum

-- add to gems
---- by date and userid: add mintid and transactionid

-- logwins
---- set migrated = 1

BEGIN;

  CREATE EXTENSION IF NOT EXISTS "pgcrypto";

  CREATE TEMP TABLE tmp_logwins_scope ON COMMIT DROP AS
  SELECT
      token,
      userid,
      createdat::date AS day,
      gems::NUMERIC(30,10)     AS gems,
      numbers::NUMERIC(30,10)  AS numbers
  FROM logwins
  WHERE gems > 0;

  CREATE TEMP TABLE tmp_daily_day_gems ON COMMIT DROP AS
  SELECT
      day,
      SUM(gems)::NUMERIC(30,10) AS total_gems
  FROM tmp_logwins_scope
  GROUP BY day
  HAVING SUM(gems) > 0;

  WITH inserted_mints AS (
      INSERT INTO mints (mintid, day, gems_in_token_ratio)
      SELECT
          gen_random_uuid(),
          day,
          5000::NUMERIC(30,10) / total_gems
      FROM tmp_daily_day_gems
      ON CONFLICT (day) DO UPDATE
          SET gems_in_token_ratio = EXCLUDED.gems_in_token_ratio
      RETURNING mintid, day
  ),
  daily_user_tokens AS (
      SELECT
          userid,
          day,
          SUM(numbers)::NUMERIC(30,10) AS token_amount
      FROM tmp_logwins_scope
      GROUP BY userid, day
      HAVING SUM(numbers) > 0
  ),
  transactions_payload AS (
      SELECT
          gen_random_uuid() AS transactionid,
          gen_random_uuid() AS operationid,
          'transferMintAccountToRecipient'::VARCHAR AS transactiontype,
          '00000000-0000-0000-0000-000000000001'::UUID AS senderid,
          dut.userid AS recipientid,
          dut.token_amount,
          'CREDIT'::VARCHAR AS transferaction,
          dut.day,
          im.mintid
      FROM daily_user_tokens dut
      JOIN inserted_mints im USING (day)
  ),
  inserted_transactions AS (
      INSERT INTO transactions (
          transactionid,
          operationid,
          transactiontype,
          senderid,
          recipientid,
          tokenamount,
          transferaction,
          transactioncategory,
          message,
          createdat
      )
      SELECT
          tp.transactionid,
          tp.operationid,
          tp.transactiontype,
          tp.senderid,
          tp.recipientid,
          tp.token_amount,
          tp.transferaction,
          'TOKEN_MINT',
          NULL,
          (tp.day::TIMESTAMP + INTERVAL '10 hours')::TIMESTAMP(3)
      FROM transactions_payload tp
      RETURNING
          transactionid,
          recipientid AS userid,
          createdat::date AS day
  )
  UPDATE gems g
  SET
      mintid = im.mintid,
      transactionid = it.transactionid
  FROM inserted_transactions it
  JOIN inserted_mints im ON im.day = it.day
  WHERE g.userid = it.userid
    AND g.createdat::date = it.day
    AND (
          g.mintid IS DISTINCT FROM im.mintid
          OR g.transactionid IS DISTINCT FROM it.transactionid
        );

  UPDATE logwins lw
  SET migrated = 1
  FROM tmp_logwins_scope tls
  WHERE lw.token = tls.token;


  COMMIT;
