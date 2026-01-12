BEGIN;

-- Tabelle: shop_orders
CREATE TABLE IF NOT EXISTS shop_orders (
    shoporderid UUID PRIMARY KEY,
    userid UUID NOT NULL,
    transactionoperationid UUID NOT NULL,
    shopitemid VARCHAR(255) DEFAULT NULL,
    size VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    addressline1 VARCHAR(255) DEFAULT NULL,
    addressline2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(255) DEFAULT NULL,
    zipcode VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT fk_shop_order_userid FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
);


ALTER TABLE shop_orders
    ADD CONSTRAINT shop_orders_country_check CHECK (
        country IS NULL OR country IN (
            'GERMANY'
        )
    );
COMMIT;