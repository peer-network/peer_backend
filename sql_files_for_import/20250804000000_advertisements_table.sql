BEGIN;

-- Tabelle: advertisements
CREATE TABLE IF NOT EXISTS advertisements (
    advertisementid UUID PRIMARY KEY,
    postid UUID NOT NULL,
    userid UUID NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'basic' CHECK (status IN ('basic', 'pinned')),
    timestart TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    timeend TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_ads_time_order CHECK (timeend >= timestart),
    CONSTRAINT fk_ads_postid FOREIGN KEY (postid) REFERENCES posts(postid) ON DELETE CASCADE,
    CONSTRAINT fk_ads_userid FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_ads_post_status_time ON advertisements (postid, status, timestart, timeend);

-- Tabelle: advertisements_log
CREATE TABLE IF NOT EXISTS advertisements_log (
    id SERIAL PRIMARY KEY,
    advertisementid UUID NOT NULL,
    postid UUID NOT NULL,
    userid UUID NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'basic' CHECK (status IN ('basic', 'pinned')),
    timestart TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    timeend TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    tokencost NUMERIC(20,5) DEFAULT 0.0 NOT NULL,
    eurocost NUMERIC(20,5) DEFAULT 0.0 NOT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_adslog_time_order CHECK (timeend >= timestart),
    CONSTRAINT chk_adslog_costs_nonneg CHECK (tokencost >= 0 AND eurocost >= 0),
    CONSTRAINT fk_adslog_advsid FOREIGN KEY (advertisementid) REFERENCES advertisements(advertisementid) ON DELETE CASCADE,
    CONSTRAINT fk_adslog_postid FOREIGN KEY (postid) REFERENCES posts(postid) ON DELETE CASCADE,
    CONSTRAINT fk_adslog_userid FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_adslog_advsid ON advertisements_log(advertisementid);
CREATE INDEX IF NOT EXISTS idx_idx_adslog_post_status_time ON advertisements_log (postid, status, timestart, timeend);

-- Table: advertisements_info
CREATE TABLE IF NOT EXISTS advertisements_info (
    advertisementid UUID PRIMARY KEY,
    postid UUID NOT NULL,
    userid UUID NOT NULL,
    likes INTEGER DEFAULT 0 NOT NULL,
    dislikes INTEGER DEFAULT 0 NOT NULL,
    reports INTEGER DEFAULT 0 NOT NULL,
    views INTEGER DEFAULT 0 NOT NULL,
    saves INTEGER DEFAULT 0 NOT NULL,
    shares INTEGER DEFAULT 0 NOT NULL,
    comments INTEGER DEFAULT 0 NOT NULL,
    updatedat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_advsinf_advsid FOREIGN KEY (advertisementid) REFERENCES advertisements(advertisementid) ON DELETE CASCADE,
    CONSTRAINT fk_advsinf_postid FOREIGN KEY (postid) REFERENCES posts(postid) ON DELETE CASCADE,
    CONSTRAINT fk_advsinf_userid FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_advsinf_postid ON advertisements_info(postid);

-- Performance-Hinweis: Wir Legen einen Index an: Hilft (advertisementid.gemsearned)
CREATE INDEX IF NOT EXISTS idx_logwins_post_createdat ON logwins(postid, createdat);

COMMIT;