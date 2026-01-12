BEGIN;

UPDATE transactions
SET message = NULL
WHERE message IN (
    'Buy like',
    'Buy dislike',
    'Buy comment',
    'Buy post',
    'Buy advertise basic',
    'Buy advertise pinned'
);

COMMIT;
