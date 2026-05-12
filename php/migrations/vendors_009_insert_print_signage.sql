-- migrations/vendors_009_insert_print_signage.sql
-- Direct INSERT of print shop and signage shop vendors.
-- No duplicate guard — if any already exist with different names, delete the
-- duplicates afterward via the vendor admin UI.

INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at) VALUES
-- ── PRINT SHOPS ──────────────────────────────────────────────────────────────
('The Print Guys Inc.',   'Sales Team',       '101 N 3rd Ave, Yakima, WA 98902',          'orders@printguys.com',           '(509) 453-6369', '["Yakima"]', '["Print Media","Printing"]', 1, NOW(), NOW()),
('Abbott''s Printing Inc.', 'Phylisha Sanborn', '500 S 2nd Ave, Yakima, WA 98902',         'peewee@abbottsprinting.com',     '(509) 452-8202', '["Yakima"]', '["Print Media","Printing"]', 1, NOW(), NOW()),
('Instant Press Inc.',    'Sales Team',       '601 W Yakima Ave, Yakima, WA 98902',        'instantpressyakima@hotmail.com', '(509) 457-6195', '["Yakima"]', '["Print Media","Printing"]', 1, NOW(), NOW()),
('Minuteman Press',       'Sales Team',       '104 S 5th Ave, Yakima, WA 98902',           'yakima@minutemanpress.com',      '(509) 452-6144', '["Yakima"]', '["Print Media","Printing"]', 1, NOW(), NOW()),
-- ── SIGNAGE SHOPS ─────────────────────────────────────────────────────────────
('Cascade Sign & Fabrication', 'Norm',        'Yakima, WA 98902',                          'normh.cascade@gmail.com',        '(509) 972-8099', '["Yakima"]', '["Billboards","Signage"]',   1, NOW(), NOW()),
('Sign Craft',            'Brad Harris',      '225 S 2nd Ave, Yakima, WA 98902',           'brad@yakimasigncraft.com',       '(509) 248-1129', '["Yakima"]', '["Billboards","Signage"]',   1, NOW(), NOW()),
('Eagle Signs',           'Sales Team',       '1511 S Keys Rd, Yakima, WA 98901',          'sales@eaglesignsllc.com',        '(509) 453-5511', '["Yakima"]', '["Billboards","Signage"]',   1, NOW(), NOW()),
('Advanced Digital Imaging', 'Sales Team',   '3402 W Washington Ave, Yakima, WA 98903',   'sales@adiyakima.com',            '(509) 452-4455', '["Yakima"]', '["Print Media","Printing","Billboards","Signage"]', 1, NOW(), NOW()),
('Sign Works',            'Kim Thompson',     '915 W Yakima Ave, Yakima, WA 98902',        'kim@signworksyakima.com',        '(509) 248-8235', '["Yakima"]', '["Billboards","Signage"]',   1, NOW(), NOW());
