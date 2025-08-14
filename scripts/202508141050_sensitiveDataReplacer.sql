-- Start transaction
BEGIN;

-- Users table anonymization
UPDATE users SET
    email = CONCAT('user_', uid::text, '@example.com'),
    username = CONCAT('user_', (RANDOM()*1000000)::INT),
    password = '$2a$12$' || SUBSTRING(MD5(RANDOM()::text), 1, 22) || SUBSTRING(MD5(RANDOM()::text), 1, 31),
    ip = CONCAT(
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT
    )::inet,
    biography = CASE WHEN biography IS NOT NULL THEN 
        CONCAT('Sample biography for user ', (RANDOM()*1000000)::INT) 
        ELSE NULL END,
    img = CASE WHEN img IS NOT NULL THEN 
        CONCAT('default_', (RANDOM()*10)::INT, '.jpg') 
        ELSE NULL END;

-- Users_info table anonymization
UPDATE users_info SET
    phone = CASE WHEN phone IS NOT NULL THEN 
        CONCAT('+1', (3000000000 + (RANDOM()*1000000000)::INT)::TEXT) 
        ELSE NULL END,
    pkey = CASE WHEN pkey IS NOT NULL THEN 
        SUBSTRING(MD5(RANDOM()::text), 1, 44) 
        ELSE NULL END;

-- Contactus table anonymization
UPDATE contactus SET
    email = CONCAT('contact_', msgid, '@example.com'),
    name = CONCAT('User ', msgid),
    message = CONCAT('Sample message ', msgid),
    ip = CONCAT(
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT
    )::inet;

-- Posts content anonymization
UPDATE posts SET
    title = CONCAT('Post title ', (RANDOM()*1000000)::INT),
    mediadescription = CASE WHEN mediadescription IS NOT NULL THEN 
        CONCAT('Media description ', (RANDOM()*1000000)::INT) 
        ELSE NULL END,
    media = CASE WHEN media IS NOT NULL THEN 
        CONCAT('media_', (RANDOM()*1000000)::INT, '.jpg') 
        ELSE NULL END,
    cover = CASE WHEN cover IS NOT NULL THEN 
        CONCAT('cover_', (RANDOM()*1000000)::INT, '.jpg') 
        ELSE NULL END;

-- Posts_media anonymization
UPDATE posts_media SET
    media = CONCAT('media_', (RANDOM()*1000000)::INT, 
        CASE contenttype 
            WHEN 'image' THEN '.jpg'
            WHEN 'video' THEN '.mp4'
            WHEN 'audio' THEN '.mp3'
            ELSE '.dat'
        END),
    options = NULL;

-- Comments anonymization
UPDATE comments SET
    content = CONCAT('Comment content ', (RANDOM()*1000000)::INT);

-- Chat messages anonymization
UPDATE chatmessages SET
    content = CONCAT('Message content ', (RANDOM()*1000000)::INT);

-- Chats anonymization
UPDATE chats SET
    name = CASE WHEN name IS NOT NULL THEN 
        CONCAT('Chat ', (RANDOM()*1000000)::INT) 
        ELSE NULL END,
    image = CASE WHEN image IS NOT NULL THEN 
        CONCAT('chat_', (RANDOM()*1000000)::INT, '.jpg') 
        ELSE NULL END;

-- Newsfeed anonymization
UPDATE newsfeed SET
    name = CASE WHEN name IS NOT NULL THEN 
        CONCAT('Feed ', (RANDOM()*1000000)::INT) 
        ELSE NULL END,
    image = CASE WHEN image IS NOT NULL THEN 
        CONCAT('feed_', (RANDOM()*1000000)::INT, '.jpg') 
        ELSE NULL END;

-- Access tokens anonymization
UPDATE access_tokens SET
    access_token = CONCAT('token_', SUBSTRING(MD5(RANDOM()::text), 1, 32));

-- Refresh tokens anonymization
UPDATE refresh_tokens SET
    refresh_token = CONCAT('refresh_', SUBSTRING(MD5(RANDOM()::text), 1, 32));

-- Password reset tokens anonymization
UPDATE password_resets SET
    token = CONCAT('reset_', SUBSTRING(MD5(RANDOM()::text), 1, 32));

-- Password reset requests anonymization
UPDATE password_reset_requests SET
    token = CONCAT('request_', SUBSTRING(MD5(RANDOM()::text), 1, 32));

-- Token holders anonymization
UPDATE token_holders SET
    token = CONCAT('holder_', SUBSTRING(MD5(RANDOM()::text), 1, 32));

-- User referral info anonymization
UPDATE user_referral_info SET
    referral_link = CONCAT('https://example.com/r/', SUBSTRING(MD5(RANDOM()::text), 1, 8)),
    qr_code_url = CONCAT('https://example.com/qr/', SUBSTRING(MD5(RANDOM()::text), 1, 8));

-- Log data anonymization
UPDATE logdata SET
    ip = CONCAT(
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT
    )::inet,
    browser = CASE 
        WHEN (RANDOM()*5)::INT = 0 THEN 'Chrome'
        WHEN (RANDOM()*5)::INT = 1 THEN 'Firefox'
        WHEN (RANDOM()*5)::INT = 2 THEN 'Safari'
        WHEN (RANDOM()*5)::INT = 3 THEN 'Edge'
        ELSE 'Opera'
    END;

-- Logdaten anonymization
UPDATE logdaten SET
    ip = CONCAT(
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT, '.',
        (RANDOM()*255)::INT
    )::inet,
    browser = CASE 
        WHEN (RANDOM()*5)::INT = 0 THEN 'Chrome'
        WHEN (RANDOM()*5)::INT = 1 THEN 'Firefox'
        WHEN (RANDOM()*5)::INT = 2 THEN 'Safari'
        WHEN (RANDOM()*5)::INT = 3 THEN 'Edge'
        ELSE 'Opera'
    END,
    url = CONCAT('/path/', (RANDOM()*1000)::INT),
    location = CASE WHEN location IS NOT NULL THEN 
        CONCAT('City ', (RANDOM()*100)::INT) 
        ELSE NULL END,
    request_payload = NULL;

-- Financial data randomization (preserving structure but changing values)
UPDATE wallet SET
    numbers = (RANDOM()*1000)::NUMERIC(30,10),
    numbersq = (RANDOM()*1000)::NUMERIC(64);

UPDATE wallett SET
    liquidity = (RANDOM()*10000)::NUMERIC(30,10),
    liquiditq = (RANDOM()*10000)::NUMERIC(64);

UPDATE gems SET
    gems = (RANDOM()*100)::NUMERIC(30,10);

UPDATE mcap SET
    coverage = (RANDOM()*1000000)::NUMERIC(30,10),
    tokenprice = (RANDOM()*100)::NUMERIC(30,10),
    gemprice = (RANDOM()*10)::NUMERIC(30,10),
    daygems = (RANDOM()*1000)::NUMERIC(30,10),
    daytokens = (RANDOM()*1000)::NUMERIC(30,10),
    totaltokens = (RANDOM()*1000000)::NUMERIC(30,10);

-- Commit transaction
COMMIT;