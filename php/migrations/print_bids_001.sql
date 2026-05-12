-- migrations/print_bids_001.sql
-- Print Bids: main bid record + line items for print jobs and signage jobs.

CREATE TABLE IF NOT EXISTS print_bids (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    client_name         VARCHAR(255) NOT NULL,
    title               VARCHAR(255) NULL,
    status              ENUM('draft','sent','approved','rejected') NOT NULL DEFAULT 'draft',
    printer_vendor_ids  TEXT NULL COMMENT 'JSON array of vendor IDs for print',
    signage_vendor_ids  TEXT NULL COMMENT 'JSON array of vendor IDs for signage',
    notes               TEXT NULL,
    created_by          INT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_bid_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    bid_id      INT NOT NULL,
    type        ENUM('print','signage') NOT NULL DEFAULT 'print',
    description VARCHAR(255) NULL,
    size        VARCHAR(100) NULL,
    paper       VARCHAR(100) NULL     COMMENT 'Print: paper stock',
    ink_spec    VARCHAR(100) NULL     COMMENT 'Print: e.g. 4/0 Full Color',
    material    VARCHAR(100) NULL     COMMENT 'Signage: substrate material',
    qty_1       VARCHAR(20)  NULL,
    qty_2       VARCHAR(20)  NULL,
    qty_3       VARCHAR(20)  NULL,
    qty_4       VARCHAR(20)  NULL,
    qty_5       VARCHAR(20)  NULL,
    notes       TEXT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pbi_bid FOREIGN KEY (bid_id) REFERENCES print_bids(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
