BEGIN;

DELETE FROM transactions
WHERE message IN (
    'Buy like',
    'Buy dislike',
    'Buy comment',
    'Buy post',
    'Buy advertise basic',
    'Buy advertise pinned'
);

COMMIT;
