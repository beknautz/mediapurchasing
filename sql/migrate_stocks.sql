-- migrate_stocks.sql
-- Stock & Mutual Fund Recommendation App schema
-- Run once against the mediapurchasing database.

-- ---------------------------------------------------------------------------
-- stocks — watchlist of tracked symbols
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stocks (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    symbol     VARCHAR(20)      NOT NULL,
    name       VARCHAR(200)     NOT NULL DEFAULT '',
    asset_type ENUM('stock','etf','mutual_fund') NOT NULL DEFAULT 'stock',
    sector     VARCHAR(100)     NOT NULL DEFAULT '',
    active     TINYINT(1)       NOT NULL DEFAULT 1,
    added_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- price_history — daily OHLCV candles
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_history (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    stock_id   INT UNSIGNED     NOT NULL,
    trade_date DATE             NOT NULL,
    open       DECIMAL(14,4)    NOT NULL DEFAULT 0,
    high       DECIMAL(14,4)    NOT NULL DEFAULT 0,
    low        DECIMAL(14,4)    NOT NULL DEFAULT 0,
    close      DECIMAL(14,4)    NOT NULL DEFAULT 0,
    volume     BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ph_stock_date (stock_id, trade_date),
    KEY idx_ph_stock   (stock_id),
    KEY idx_ph_date    (trade_date),
    CONSTRAINT fk_ph_stock FOREIGN KEY (stock_id) REFERENCES stocks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- indicators — pre-calculated technical indicators per symbol per day
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS indicators (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    stock_id    INT UNSIGNED   NOT NULL,
    trade_date  DATE           NOT NULL,
    rsi14       DECIMAL(8,4)   NULL COMMENT 'Relative Strength Index (14-period)',
    macd_line   DECIMAL(14,6)  NULL COMMENT 'MACD line (EMA12 - EMA26)',
    signal_line DECIMAL(14,6)  NULL COMMENT 'Signal line (9-period EMA of MACD)',
    macd_hist   DECIMAL(14,6)  NULL COMMENT 'MACD histogram (macd_line - signal_line)',
    sma20       DECIMAL(14,4)  NULL COMMENT '20-period simple moving average',
    sma50       DECIMAL(14,4)  NULL COMMENT '50-period simple moving average',
    bb_upper    DECIMAL(14,4)  NULL COMMENT 'Bollinger Band upper (sma20 + 2*stddev)',
    bb_middle   DECIMAL(14,4)  NULL COMMENT 'Bollinger Band middle (sma20)',
    bb_lower    DECIMAL(14,4)  NULL COMMENT 'Bollinger Band lower (sma20 - 2*stddev)',
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ind_stock_date (stock_id, trade_date),
    KEY idx_ind_stock (stock_id),
    CONSTRAINT fk_ind_stock FOREIGN KEY (stock_id) REFERENCES stocks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- recommendations — daily BUY / SELL / HOLD advice
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recommendations (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    stock_id    INT UNSIGNED   NOT NULL,
    rec_date    DATE           NOT NULL,
    action      ENUM('BUY','SELL','HOLD') NOT NULL DEFAULT 'HOLD',
    confidence  TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100 composite score',
    close_price DECIMAL(14,4)  NULL,
    notes       TEXT           NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rec_stock_date (stock_id, rec_date),
    KEY idx_rec_stock  (stock_id),
    KEY idx_rec_date   (rec_date),
    CONSTRAINT fk_rec_stock FOREIGN KEY (stock_id) REFERENCES stocks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- schwab_tokens — single-row OAuth 2.0 token store (one app-level token)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schwab_tokens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    access_token  TEXT         NOT NULL,
    refresh_token TEXT         NOT NULL,
    token_type    VARCHAR(50)  NOT NULL DEFAULT 'Bearer',
    expires_at    DATETIME     NOT NULL,
    scope         VARCHAR(500) NOT NULL DEFAULT '',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Seed a handful of example symbols (optional — edit freely)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO stocks (symbol, name, asset_type, sector) VALUES
    ('AAPL',  'Apple Inc.',                          'stock',       'Technology'),
    ('MSFT',  'Microsoft Corporation',               'stock',       'Technology'),
    ('SPY',   'SPDR S&P 500 ETF Trust',              'etf',         'Index'),
    ('QQQ',   'Invesco QQQ Trust',                   'etf',         'Index'),
    ('SWPPX', 'Schwab S&P 500 Index Fund',           'mutual_fund', 'Index');
