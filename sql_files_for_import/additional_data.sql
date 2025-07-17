
-- tester01
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('b9e94945-abd7-46a5-8c92-59037f1d73bf', 'tester01@tester.de', 'tester01',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 0, '75.50.101.245',
'/profile/b9e94945-abd7-46a5-8c92-59037f1d73bf.jpg',
'/userData/b9e94945-abd7-46a5-8c92-59037f1d73bf.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('b9e94945-abd7-46a5-8c92-59037f1d73bf', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('b9e94945-abd7-46a5-8c92-59037f1d73bf', 5000, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);


INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('b9e94945-abd7-46a5-8c92-59037f1d73bf', 5000, 0);


-- tester02
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('6520ac47-f262-4f7e-b643-9dc5ee4cfa82', 'tester02@tester.de', 'tester02',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 16, '75.50.101.245',
'/profile/6520ac47-f262-4f7e-b643-9dc5ee4cfa82.jpg',
'/userData/6520ac47-f262-4f7e-b643-9dc5ee4cfa82.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('6520ac47-f262-4f7e-b643-9dc5ee4cfa82', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('6520ac47-f262-4f7e-b643-9dc5ee4cfa82', 5000, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);

INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('6520ac47-f262-4f7e-b643-9dc5ee4cfa82', 5000, 0);

-- tester03
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('dbe72768-0d47-4d29-99e7-b6ec4eadfaa3', 'tester03@tester.de', 'tester03',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 0, '75.50.101.245',
'/profile/dbe72768-0d47-4d29-99e7-b6ec4eadfaa3.jpg',
'/userData/dbe72768-0d47-4d29-99e7-b6ec4eadfaa3.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('dbe72768-0d47-4d29-99e7-b6ec4eadfaa3', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('dbe72768-0d47-4d29-99e7-b6ec4eadfaa3', 5000, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);

INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('dbe72768-0d47-4d29-99e7-b6ec4eadfaa3', 5000, 0);


-- burn_account
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f', 'burn@system.com', 'burn_account',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 4, '127.0.0.1',
'/profile/7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f.jpg',
'/userData/7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f', 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);


INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('7e0b2d21-d2b0-4af5-8b73-5f8efc04b11f', 0, 0);


-- lp_account
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4', 'lp@system.com', 'lp_account',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 1, '127.0.0.1',
'/profile/3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4.jpg',
'/userData/3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4', 100000, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);


INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4', 100000, 0);


-- company_account
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df93', 'company@system.com', 'company_account',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 2, '127.0.0.1',
'/profile/85d5f836-b1f5-4c4e-9381-1b058e13df93.jpg',
'/userData/85d5f836-b1f5-4c4e-9381-1b058e13df93.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df93', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df93', 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);

INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df93', 0, 0);

-- btc_account
INSERT INTO users (uid, email, username, password, status, verified, slug, roles_mask, ip, img, biography)
VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df94', 'btc@system.com', 'btc_account',
'$argon2id$v=19$m=65536,t=3,p=2$OXF0NlY5R09xRDRXLkREaw$E/P8IL1rNIRboG0Bl39kkNm9ozcoVxtNH/6NogztAD0', 0, 1, 97183, 1, '127.0.0.1',
'/profile/85d5f836-b1f5-4c4e-9381-1b058e13df94.jpg',
'/userData/85d5f836-b1f5-4c4e-9381-1b058e13df94.txt');

INSERT INTO dailyfree (userid, liken, comments, posten) VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df94', 0, 0, 0);

INSERT INTO users_info (userid, liquidity, amountposts, amountfollower, amountfollowed, amountfriends, amountblocked, isprivate, invited, phone, pkey) 
VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df94', 0.1, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL);

INSERT INTO wallett (userid, liquidity, liquiditq) VALUES ('85d5f836-b1f5-4c4e-9381-1b058e13df94', 0.1, 0);


--action_prices
INSERT INTO action_prices (post_price, like_price, dislike_price, comment_price, currency) 
VALUES (2.0, 0.30, 0.50, 0.05,'EUR');

--advertisement
INSERT INTO posts (postid, userid, feedid, contenttype, title, mediadescription, media, cover, options, status, createdat) 
VALUES ('4008c0dd-296c-46d3-811d-f90a2c077757', 'b9e94945-abd7-46a5-8c92-59037f1d73bf', NULL, 'image', 'Test Advertisement Post', 'This is a test post used for advertisement CI tests.', '[{"path":"\/image\/1c9448a1-3608-423f-a038-2f267c943151.webp","options":{"size":"4.27 KB","resolution":"200x300"}}]', NULL, NULL, 1, date_trunc('day', NOW()) - INTERVAL '2 days');

INSERT INTO advertisements (advertisementid, postid, userid, status, timestart, timeend) 
VALUES ('a1111111-aaaa-1111-aaaa-111111111111', '4008c0dd-296c-46d3-811d-f90a2c077757', 'b9e94945-abd7-46a5-8c92-59037f1d73bf', 'basic', date_trunc('day', NOW()) - INTERVAL '1 day', date_trunc('day', NOW()) + INTERVAL '3 days');

INSERT INTO advertisements_log (advertisementid, postid, userid, status, timestart, timeend, tokencost, eurocost, createdat) 
VALUES ('a1111111-aaaa-1111-aaaa-111111111111', '4008c0dd-296c-46d3-811d-f90a2c077757', 'b9e94945-abd7-46a5-8c92-59037f1d73bf', 'basic', date_trunc('day', NOW()) - INTERVAL '1 day', date_trunc('day', NOW()) + INTERVAL '3 days', 500.00000, 50.00000, date_trunc('day', NOW()) - INTERVAL '2 days');
