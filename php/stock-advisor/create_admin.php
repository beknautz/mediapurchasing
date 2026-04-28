#!/usr/bin/env php
<?php
/**
 * create_admin.php — One-time CLI utility to create an admin user.
 * Run from the stock-advisor directory:
 *   php create_admin.php
 *
 * Delete this file after use.
 */
define('CRON_CONTEXT', true);
require_once __DIR__ . '/bootstrap.php';

echo "=== Stock Advisor — Create Admin User ===\n\n";

$email    = prompt('Email address: ');
$fullName = prompt('Full name: ');
$password = prompt('Password: ', hidden: true);
$confirm  = prompt('Confirm password: ', hidden: true);

if ($password !== $confirm) {
    echo "\nPasswords do not match. Aborting.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "\nPassword must be at least 8 characters.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Check for existing user
$check = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
$check->execute([':e' => strtolower(trim($email))]);

if ($check->fetch()) {
    echo "\nA user with that email already exists.\n";
    exit(1);
}

$stmt = $pdo->prepare(
    'INSERT INTO users (email, password_hash, full_name, role, is_active, created_at, updated_at)
     VALUES (:email, :hash, :name, :role, 1, NOW(), NOW())'
);
$stmt->execute([
    ':email' => strtolower(trim($email)),
    ':hash'  => $hash,
    ':name'  => trim($fullName),
    ':role'  => 'admin',
]);

echo "\nAdmin user created successfully!\n";
echo "  Email: {$email}\n";
echo "  Name:  {$fullName}\n\n";
echo "Delete this file now: rm create_admin.php\n";

function prompt(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && PHP_OS_FAMILY !== 'Windows') {
        system('stty -echo');
        $val = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $val = trim(fgets(STDIN));
    }
    return $val;
}
