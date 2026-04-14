-- ============================================================
-- Media Buying Automation Platform
-- MySQL Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS mediapurchasing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediapurchasing;

-- ============================================================
-- USERS & ROLES
-- ============================================================
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120)  NOT NULL,
    email           VARCHAR(180)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('admin','buyer','client','vendor') NOT NULL DEFAULT 'buyer',
    phone           VARCHAR(30)   NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    reset_token     VARCHAR(100)  NULL,
    reset_expires   DATETIME      NULL,
    last_login      DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- CLIENTS
-- ============================================================
CREATE TABLE clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NULL,          -- optional portal login
    company_name    VARCHAR(200)  NOT NULL,
    contact_name    VARCHAR(120)  NOT NULL,
    email           VARCHAR(180)  NOT NULL,
    phone           VARCHAR(30)   NULL,
    address         TEXT          NULL,
    notes           TEXT          NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- VENDORS (Media Companies)
-- ============================================================
CREATE TABLE vendors (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NULL,          -- optional portal login
    company_name    VARCHAR(200)  NOT NULL,
    contact_name    VARCHAR(120)  NOT NULL,
    email           VARCHAR(180)  NOT NULL,
    phone           VARCHAR(30)   NULL,
    address         TEXT          NULL,
    media_types     VARCHAR(255)  NULL,          -- comma-separated: TV,Radio,Print,Digital,OOH
    billing_email   VARCHAR(180)  NULL,
    notes           TEXT          NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- MEDIA BUYS
-- ============================================================
CREATE TABLE media_buys (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)  NOT NULL,
    client_id       INT UNSIGNED  NOT NULL,
    vendor_id       INT UNSIGNED  NOT NULL,
    buyer_id        INT UNSIGNED  NOT NULL,      -- user with role=buyer
    status          ENUM(
                        'draft',
                        'sent_to_vendor',
                        'vendor_responded',
                        'under_evaluation',
                        'pending_client_approval',
                        'client_approved',
                        'client_revision_requested',
                        'negotiating',
                        'finalized',
                        'cancelled'
                    ) NOT NULL DEFAULT 'draft',
    media_type      VARCHAR(60)   NOT NULL,      -- TV, Radio, Print, Digital, OOH
    flight_start    DATE          NULL,
    flight_end      DATE          NULL,
    market          VARCHAR(120)  NULL,
    original_cost   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    negotiated_cost DECIMAL(12,2) NULL,
    final_cost      DECIMAL(12,2) NULL,
    description     TEXT          NULL,
    evaluation_notes TEXT         NULL,
    internal_notes  TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)  REFERENCES clients(id),
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id),
    FOREIGN KEY (buyer_id)   REFERENCES users(id)
);

-- ============================================================
-- MEDIA BUY LINE ITEMS
-- ============================================================
CREATE TABLE media_buy_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_buy_id    INT UNSIGNED  NOT NULL,
    description     VARCHAR(255)  NOT NULL,
    placement       VARCHAR(120)  NULL,          -- daypart, position, etc.
    spots           INT           NOT NULL DEFAULT 1,
    unit_cost       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_cost      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sort_order      INT           NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE CASCADE
);

-- ============================================================
-- NEGOTIATIONS
-- ============================================================
CREATE TABLE negotiations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_buy_id    INT UNSIGNED  NOT NULL,
    round           INT           NOT NULL DEFAULT 1,
    buyer_offer     DECIMAL(12,2) NOT NULL,
    vendor_counter  DECIMAL(12,2) NULL,
    buyer_notes     TEXT          NULL,
    vendor_notes    TEXT          NULL,
    status          ENUM('pending','countered','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE CASCADE
);

-- ============================================================
-- CLIENT APPROVALS
-- ============================================================
CREATE TABLE approvals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_buy_id    INT UNSIGNED  NOT NULL,
    client_id       INT UNSIGNED  NOT NULL,
    requested_by    INT UNSIGNED  NOT NULL,      -- buyer user
    token           VARCHAR(100)  NOT NULL UNIQUE,
    status          ENUM('pending','approved','revision_requested','rejected','expired')
                                  NOT NULL DEFAULT 'pending',
    requested_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at    DATETIME      NULL,
    expires_at      DATETIME      NOT NULL,
    revision_notes  TEXT          NULL,
    approved_cost   DECIMAL(12,2) NULL,
    ip_address      VARCHAR(45)   NULL,
    FOREIGN KEY (media_buy_id)  REFERENCES media_buys(id),
    FOREIGN KEY (client_id)     REFERENCES clients(id),
    FOREIGN KEY (requested_by)  REFERENCES users(id)
);

-- ============================================================
-- COMMUNICATIONS  (email + SMS log)
-- ============================================================
CREATE TABLE communications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type                ENUM('email','sms')        NOT NULL,
    direction           ENUM('inbound','outbound') NOT NULL,
    from_address        VARCHAR(180)  NULL,
    to_address          VARCHAR(180)  NULL,
    subject             VARCHAR(255)  NULL,
    body_text           LONGTEXT      NULL,
    body_html           LONGTEXT      NULL,
    status              ENUM('queued','sent','delivered','failed','received') NOT NULL DEFAULT 'queued',
    media_buy_id        INT UNSIGNED  NULL,
    approval_id         INT UNSIGNED  NULL,
    bill_id             INT UNSIGNED  NULL,
    sendgrid_message_id VARCHAR(100)  NULL,
    twilio_sid          VARCHAR(60)   NULL,
    error_message       TEXT          NULL,
    raw_payload         LONGTEXT      NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE SET NULL,
    FOREIGN KEY (approval_id)  REFERENCES approvals(id)  ON DELETE SET NULL
);

-- ============================================================
-- BILLS  (Invoices received from vendors)
-- ============================================================
CREATE TABLE bills (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id       INT UNSIGNED  NOT NULL,
    media_buy_id    INT UNSIGNED  NULL,
    invoice_number  VARCHAR(80)   NULL,
    invoice_date    DATE          NULL,
    due_date        DATE          NULL,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('queued','under_review','approved','paid','disputed','rejected')
                                  NOT NULL DEFAULT 'queued',
    intake_method   ENUM('email','manual_upload','vendor_portal') NOT NULL DEFAULT 'manual_upload',
    file_path       VARCHAR(500)  NULL,
    raw_email_id    INT UNSIGNED  NULL,           -- FK to communications
    notes           TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)    REFERENCES vendors(id),
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE SET NULL,
    FOREIGN KEY (raw_email_id) REFERENCES communications(id) ON DELETE SET NULL
);

-- ============================================================
-- BILL QUEUE
-- ============================================================
CREATE TABLE bill_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id         INT UNSIGNED  NOT NULL UNIQUE,
    priority        ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assigned_to     INT UNSIGNED  NULL,
    queued_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_at          DATETIME      NULL,
    status          ENUM('waiting','in_progress','on_hold','completed') NOT NULL DEFAULT 'waiting',
    processing_notes TEXT         NULL,
    completed_at    DATETIME      NULL,
    FOREIGN KEY (bill_id)      REFERENCES bills(id)  ON DELETE CASCADE,
    FOREIGN KEY (assigned_to)  REFERENCES users(id)  ON DELETE SET NULL
);

-- ============================================================
-- EMAIL / SMS TEMPLATES  (CMS-managed)
-- ============================================================
CREATE TABLE email_templates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120)  NOT NULL,
    slug            VARCHAR(80)   NOT NULL UNIQUE,
    category        ENUM(
                        'media_buy_request',
                        'negotiation',
                        'client_approval',
                        'client_revision',
                        'bill_received',
                        'bill_reminder',
                        'general'
                    ) NOT NULL DEFAULT 'general',
    channel         ENUM('email','sms','both') NOT NULL DEFAULT 'email',
    subject         VARCHAR(255)  NULL,
    body_html       LONGTEXT      NULL,
    body_text       LONGTEXT      NULL,
    sms_body        TEXT          NULL,
    variables       TEXT          NULL,           -- JSON list of available {{vars}}
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- WORKFLOW SETTINGS  (CMS-managed key/value config)
-- ============================================================
CREATE TABLE workflow_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(80)   NOT NULL UNIQUE,
    setting_value   TEXT          NULL,
    label           VARCHAR(120)  NOT NULL,
    description     TEXT          NULL,
    setting_group   VARCHAR(60)   NOT NULL DEFAULT 'general',
    updated_by      INT UNSIGNED  NULL,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE audit_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NULL,
    action          VARCHAR(80)   NOT NULL,
    entity_type     VARCHAR(60)   NULL,
    entity_id       INT UNSIGNED  NULL,
    details         TEXT          NULL,
    ip_address      VARCHAR(45)   NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user password is set via /setup.cfm on first run.
-- That page uses ColdFusion's native GenerateBCryptHash() and writes the
-- correct hash directly to this table.  Do NOT paste a hash here manually —
-- BCrypt hashes must be generated by the same algorithm the app uses.
--
-- If you want a quick SQL-only seed for dev, run setup.cfm once, copy the
-- hash it shows, then use it here.  Example placeholder (NOT a valid hash):
-- INSERT INTO users (name, email, password_hash, role) VALUES
-- ('System Admin', 'admin@example.com', '<hash from setup.cfm>', 'admin');

-- Default workflow settings
INSERT INTO workflow_settings (setting_key, label, description, setting_group, setting_value) VALUES
('approval_expiry_hours',    'Approval Link Expiry (hours)',       'How many hours before a client approval link expires', 'approvals', '72'),
('negotiation_max_rounds',   'Max Negotiation Rounds',             'Maximum back-and-forth rounds before escalation',       'negotiation', '3'),
('sendgrid_from_email',      'SendGrid From Email',                'Default from address for outbound emails',              'email', 'noreply@youragency.com'),
('sendgrid_from_name',       'SendGrid From Name',                 'Default sender name for outbound emails',               'email', 'Media Buying Team'),
('sendgrid_api_key',         'SendGrid API Key',                   'Your SendGrid API key (keep secret)',                   'email', ''),
('twilio_account_sid',       'Twilio Account SID',                 'Your Twilio Account SID',                               'sms', ''),
('twilio_auth_token',        'Twilio Auth Token',                  'Your Twilio Auth Token (keep secret)',                  'sms', ''),
('twilio_from_number',       'Twilio From Number',                 'Your Twilio phone number (E.164 format)',               'sms', ''),
('app_base_url',             'Application Base URL',               'Public base URL for generating approval links',         'general', 'https://yourdomain.com'),
('bill_queue_auto_assign',   'Auto-Assign Bills',                  'Automatically assign queued bills to available buyers', 'billing', '0'),
('inbound_email_domain',     'Inbound Email Domain',               'SendGrid inbound parse domain for receiving emails',    'email', 'mail.yourdomain.com');

-- Default email templates
INSERT INTO email_templates (name, slug, category, channel, subject, body_html, body_text, variables) VALUES

('Media Buy Request to Vendor', 'media_buy_request_vendor', 'media_buy_request', 'email',
 'Media Buy Request: {{buy_title}}',
 '<p>Dear {{vendor_contact}},</p><p>We would like to request a media buy proposal for the following:</p><p><strong>Campaign:</strong> {{buy_title}}<br><strong>Media Type:</strong> {{media_type}}<br><strong>Flight Dates:</strong> {{flight_start}} – {{flight_end}}<br><strong>Market:</strong> {{market}}<br><strong>Estimated Budget:</strong> ${{original_cost}}</p><p>{{description}}</p><p>Please respond with availability and your best rates.</p><p>Best regards,<br>{{buyer_name}}<br>{{agency_name}}</p>',
 'Dear {{vendor_contact}},\n\nWe would like to request a media buy proposal:\n\nCampaign: {{buy_title}}\nMedia Type: {{media_type}}\nFlight Dates: {{flight_start}} – {{flight_end}}\nMarket: {{market}}\nBudget: ${{original_cost}}\n\n{{description}}\n\nPlease respond with your best rates.\n\n{{buyer_name}}',
 '["buy_title","vendor_contact","media_type","flight_start","flight_end","market","original_cost","description","buyer_name","agency_name"]'),

('Negotiation Counter Offer', 'negotiation_counter', 'negotiation', 'email',
 'Re: {{buy_title}} — Counter Proposal',
 '<p>Dear {{vendor_contact}},</p><p>Thank you for your response regarding <strong>{{buy_title}}</strong>.</p><p>We are currently evaluating your proposal of <strong>${{vendor_rate}}</strong>. We would like to propose a rate of <strong>${{proposed_rate}}</strong>.</p><p>{{buyer_notes}}</p><p>Please let us know if this works for you.</p><p>{{buyer_name}}</p>',
 'Dear {{vendor_contact}},\n\nRe: {{buy_title}}\n\nWe propose a rate of ${{proposed_rate}} (your rate: ${{vendor_rate}}).\n\n{{buyer_notes}}\n\n{{buyer_name}}',
 '["buy_title","vendor_contact","vendor_rate","proposed_rate","buyer_notes","buyer_name"]'),

('Client Approval Request', 'client_approval_request', 'client_approval', 'email',
 'Action Required: Approve Media Buy — {{buy_title}}',
 '<p>Dear {{client_contact}},</p><p>A media buy is ready for your review and approval.</p><table border="1" cellpadding="8" style="border-collapse:collapse;width:100%"><tr><td><strong>Campaign</strong></td><td>{{buy_title}}</td></tr><tr><td><strong>Vendor</strong></td><td>{{vendor_name}}</td></tr><tr><td><strong>Media Type</strong></td><td>{{media_type}}</td></tr><tr><td><strong>Flight Dates</strong></td><td>{{flight_start}} – {{flight_end}}</td></tr><tr><td><strong>Total Cost</strong></td><td>${{total_cost}}</td></tr></table><p style="margin-top:20px"><a href="{{approval_link}}" style="background:#0d6efd;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px">Review &amp; Approve</a></p><p>This link expires on {{expires_at}}.</p>',
 'Dear {{client_contact}},\n\nPlease review and approve the following media buy:\n\nCampaign: {{buy_title}}\nVendor: {{vendor_name}}\nFlight: {{flight_start}} – {{flight_end}}\nCost: ${{total_cost}}\n\nApprove here: {{approval_link}}\n\nLink expires: {{expires_at}}',
 '["client_contact","buy_title","vendor_name","media_type","flight_start","flight_end","total_cost","approval_link","expires_at"]'),

('Client Revision Follow-up', 'client_revision_followup', 'client_revision', 'email',
 'Media Buy Updated: {{buy_title}}',
 '<p>Dear {{client_contact}},</p><p>We have updated the media buy <strong>{{buy_title}}</strong> based on your feedback.</p><p>{{revision_notes}}</p><p><a href="{{approval_link}}" style="background:#0d6efd;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px">Review Updated Proposal</a></p>',
 'Dear {{client_contact}},\n\nWe have updated {{buy_title}} per your feedback.\n\n{{revision_notes}}\n\nReview here: {{approval_link}}',
 '["client_contact","buy_title","revision_notes","approval_link"]'),

('Bill Received Confirmation', 'bill_received_confirmation', 'bill_received', 'email',
 'Invoice Received: {{invoice_number}}',
 '<p>Hi {{vendor_contact}},</p><p>We have received your invoice <strong>#{{invoice_number}}</strong> for ${{amount}} dated {{invoice_date}}.</p><p>It has been queued for processing. You will receive a payment confirmation once approved.</p><p>Reference ID: {{bill_id}}</p>',
 'Hi {{vendor_contact}},\n\nInvoice #{{invoice_number}} for ${{amount}} has been received and queued for processing.\n\nReference: {{bill_id}}',
 '["vendor_contact","invoice_number","amount","invoice_date","bill_id"]');
