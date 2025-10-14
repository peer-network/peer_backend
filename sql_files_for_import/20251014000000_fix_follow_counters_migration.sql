


BEGIN 

UPDATE users_info ui
SET 
    amountfollower = COALESCE(follower_counts.cnt, 0),
    amountfollowed = COALESCE(followed_counts.cnt, 0)
FROM 
    users_info ui2
    LEFT JOIN (
        SELECT followedid, COUNT(*) as cnt 
        FROM follows 
        GROUP BY followedid
    ) AS follower_counts ON ui2.userid = follower_counts.followedid
    LEFT JOIN (
        SELECT followerid, COUNT(*) as cnt 
        FROM follows 
        GROUP BY followerid
    ) AS followed_counts ON ui2.userid = followed_counts.followerid
WHERE ui.userid = ui2.userid
AND (
    ui.amountfollower IS DISTINCT FROM COALESCE(follower_counts.cnt, 0) 
    OR ui.amountfollowed IS DISTINCT FROM COALESCE(followed_counts.cnt, 0)
);

COMMIT;