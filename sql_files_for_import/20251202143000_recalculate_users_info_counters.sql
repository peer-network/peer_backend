BEGIN;

-- Recalculate amountposts from posts table
UPDATE users_info ui
SET amountposts = COALESCE(p.post_count, 0)
FROM (
    SELECT userid, COUNT(*) as post_count
    FROM posts
    GROUP BY userid
) p
WHERE ui.userid = p.userid;

-- users with no posts 
UPDATE users_info
SET amountposts = 0
WHERE userid NOT IN (SELECT userid FROM posts);




-- amountfollower 
UPDATE users_info ui
SET amountfollower = COALESCE(f.follower_count, 0)
FROM (
    SELECT followedid as userid, COUNT(*) as follower_count
    FROM follows
    GROUP BY followedid
) f
WHERE ui.userid = f.userid;

-- users with no followers 
UPDATE users_info
SET amountfollower = 0
WHERE userid NOT IN (SELECT followedid FROM follows);




-- amountfollowed 
UPDATE users_info ui
SET amountfollowed = COALESCE(f.followed_count, 0)
FROM (
    SELECT followerid as userid, COUNT(*) as followed_count
    FROM follows
    GROUP BY followerid
) f
WHERE ui.userid = f.userid;

-- users not following anyone 
UPDATE users_info
SET amountfollowed = 0
WHERE userid NOT IN (SELECT followerid FROM follows);



-- amountfriends 
UPDATE users_info ui
SET amountfriends = COALESCE(friends.friend_count, 0)
FROM (
    SELECT f1.followerid as userid, COUNT(*) as friend_count
    FROM follows f1
    INNER JOIN follows f2
        ON f1.followedid = f2.followerid
        AND f1.followerid = f2.followedid
    GROUP BY f1.followerid
) friends
WHERE ui.userid = friends.userid;

-- users with no friends 
UPDATE users_info
SET amountfriends = 0
WHERE userid NOT IN (
    SELECT DISTINCT f1.followerid
    FROM follows f1
    INNER JOIN follows f2
        ON f1.followedid = f2.followerid
        AND f1.followerid = f2.followedid
);




-- amountblocked
UPDATE users_info ui
SET amountblocked = COALESCE(b.blocked_count, 0)
FROM (
    SELECT blockerid as userid, COUNT(*) as blocked_count
    FROM user_block_user
    GROUP BY blockerid
) b
WHERE ui.userid = b.userid;

-- users who haven't blocked anyone
UPDATE users_info
SET amountblocked = 0
WHERE userid NOT IN (SELECT blockerid FROM user_block_user);



COMMIT;
