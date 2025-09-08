BEGIN;
ALTER TABLE user_preferences
    ADD COLUMN IF NOT EXISTS onboardingsWereShown JSONB NOT NULL DEFAULT '[]';

-- updated action prices 
UPDATE action_prices
SET post_price    = 2.0,
    like_price    = 0.30,
    dislike_price = 0.30,
    comment_price = 0.10,
    updatedat     = NOW()
WHERE currency = 'EUR';
COMMIT;