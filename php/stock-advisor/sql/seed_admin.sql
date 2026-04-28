-- seed_admin.sql
-- Insert the first admin user for stockwatch.enigmaiq.ai
--
-- Generate a fresh password hash before running:
--   php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
-- Then replace the hash value below.

INSERT INTO users (email, password_hash, full_name, role, is_active, created_at, updated_at)
VALUES (
    'admin@enigmaiq.com',
    '$2y$12$REPLACE_WITH_GENERATED_HASH',
    'Admin User',
    'admin',
    1,
    NOW(),
    NOW()
);
