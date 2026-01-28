BEGIN;

    -- Update for Paid Like
    UPDATE public.transactions
    SET transactiontype      = 'transferForLike',
        transferaction       = 'CREDIT',
        transactioncategory  = 'LIKE'
    WHERE transactiontype = 'postLiked'
    AND transferaction  = 'BURN';


    -- Update for Dislike
    UPDATE public.transactions
    SET transactiontype      = 'transferForDislike',
        transferaction       = 'CREDIT',
        transactioncategory  = 'DISLIKE'
    WHERE transactiontype = 'postDisLiked';

    -- Update for create Post
    UPDATE public.transactions
    SET transactiontype      = 'transferForPost',
        transferaction       = 'CREDIT',
        transactioncategory  = 'POST_CREATE'
    WHERE transactiontype = 'postCreated';


    --  Update for create comment
    UPDATE public.transactions
    SET transactiontype      = 'transferForComment',
        transferaction       = 'CREDIT',
        transactioncategory  = 'COMMENT'
    WHERE transactiontype = 'postComment';

COMMIT;

