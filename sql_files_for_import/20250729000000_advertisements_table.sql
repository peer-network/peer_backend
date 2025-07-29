BEGIN;

-- Tabelle: advertisements
CREATE TABLE advertisements (
    advertisementid UUID PRIMARY KEY,
    postid UUID NOT NULL,
    userid UUID NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'basic' CHECK (status IN ('basic', 'pinned')),
    timestart TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    timeend TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_advertisement_postid FOREIGN KEY (postid) REFERENCES posts(postid) ON DELETE CASCADE,
    CONSTRAINT fk_advertisement_userid FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE,
    UNIQUE (advertisementid, status, timestart)
);
CREATE INDEX IF NOT EXISTS idx_ads_postid ON advertisements(postid);
CREATE INDEX IF NOT EXISTS idx_ads_status ON advertisements(status);

-- Tabelle: advertisements_log
CREATE TABLE advertisements_log (
    id SERIAL PRIMARY KEY,
    advertisementid UUID NOT NULL,
    postid UUID NOT NULL,
    userid UUID NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'basic' CHECK (status IN ('basic', 'pinned')),
    timestart TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    timeend TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    tokencost NUMERIC(20,5) DEFAULT 0.0 NOT NULL,
    eurocost NUMERIC(20,5) DEFAULT 0.0 NOT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_adslog_postid ON advertisements_log(postid);
CREATE INDEX IF NOT EXISTS idx_adslog_status ON advertisements_log(status);

COMMIT;