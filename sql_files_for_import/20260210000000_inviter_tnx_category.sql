BEGIN;


ALTER TABLE transactions
    DROP CONSTRAINT IF EXISTS transactions_transactioncategory_check;

ALTER TABLE transactions
    ADD CONSTRAINT transactions_transactioncategory_check CHECK (
        transactioncategory IS NULL OR transactioncategory IN (
            'P2P_TRANSFER',
            'AD_PINNED',
            'POST_CREATE',
            'LIKE',
            'DISLIKE',
            'COMMENT',
            'TOKEN_MINT',
            'SHOP_PURCHASE',
            'FEE',
            'INVITER_FEE_EARN'
        )
    );

UPDATE transactions SET transactioncategory = 'INVITER_FEE_EARN' WHERE transferaction = 'INVITER_FEE';
COMMIT;

