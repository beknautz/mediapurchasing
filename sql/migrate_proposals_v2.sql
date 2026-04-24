-- Proposals v2: unified blocks model
-- Run AFTER migrate_proposals.sql
-- Replaces the flat items tables with a single sortable blocks table
-- that supports text (Summernote HTML), line items, and signature placeholders.

-- 1. Drop v1 item tables
DROP TABLE IF EXISTS proposal_template_items;
DROP TABLE IF EXISTS proposal_items;

-- 2. Drop v2 tables if re-running
DROP TABLE IF EXISTS proposal_template_blocks;
DROP TABLE IF EXISTS proposal_blocks;

-- 3. Remove flat text columns from proposals (now live as text blocks)
ALTER TABLE proposals
    DROP COLUMN IF EXISTS intro_text,
    DROP COLUMN IF EXISTS notes;

-- 4. Remove flat text columns from proposal_templates
ALTER TABLE proposal_templates
    DROP COLUMN IF EXISTS intro_text,
    DROP COLUMN IF EXISTS notes;

-- 5. Unified blocks table for proposals
CREATE TABLE proposal_blocks (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    proposal_id  INT UNSIGNED   NOT NULL,
    block_type   ENUM('text','item','signature') NOT NULL,
    sort_order   SMALLINT       NOT NULL DEFAULT 0,
    -- text block: Summernote HTML
    content      MEDIUMTEXT     NULL,
    -- item block
    description  TEXT           NULL,
    quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1.00,
    unit_price   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_price  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    -- signature block
    sig_label    VARCHAR(255)   NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_proposal_sort (proposal_id, sort_order),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Unified blocks table for templates
CREATE TABLE proposal_template_blocks (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    template_id  INT UNSIGNED   NOT NULL,
    block_type   ENUM('text','item','signature') NOT NULL,
    sort_order   SMALLINT       NOT NULL DEFAULT 0,
    content      MEDIUMTEXT     NULL,
    description  TEXT           NULL,
    quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1.00,
    unit_price   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    sig_label    VARCHAR(255)   NULL,
    INDEX idx_template_sort (template_id, sort_order),
    FOREIGN KEY (template_id) REFERENCES proposal_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
