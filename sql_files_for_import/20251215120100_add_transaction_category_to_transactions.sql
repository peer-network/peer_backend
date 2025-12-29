BEGIN;

ALTER TABLE transactions
    ADD COLUMN transactioncategory VARCHAR(255) DEFAULT NULL;

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
            'FEE'
        )
    );

COMMIT;
