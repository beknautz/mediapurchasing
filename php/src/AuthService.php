<?php
/**
 * src/AuthService.php
 * Media Buying Platform — authentication service
 */

class AuthService extends BaseService
{
    // -----------------------------------------------------------------------
    // login()
    // Validates credentials, updates last_login timestamp, and returns a
    // result array. The caller is responsible for writing to $_SESSION.
    //
    // Returns:
    //   ['success' => bool, 'user' => array|null, 'message' => string]
    // -----------------------------------------------------------------------
    // Returns the user row array on success, or false on failure.
    public function login(string $email, string $password)
    {
        $email = trim(strtolower($email));

        if ($email === '' || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, role, name, phone, is_active
               FROM users
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!(bool) $user['is_active']) {
            return false;
        }

        if (!$this->checkPassword($password, $user['password_hash'])) {
            return false;
        }

        // Update last_login timestamp
        $upd = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $upd->execute([':id' => $user['id']]);

        $this->auditLog('login', 'user', (int) $user['id'], 'Successful login');

        unset($user['password_hash']);

        return $user;
    }

    // -----------------------------------------------------------------------
    // hashPassword()
    // Hashes a plaintext password using PBKDF2-SHA256.
    // Storage format: "pbkdf2sha256:10000:{saltHex}:{hashHex}"
    // -----------------------------------------------------------------------
    public function hashPassword(string $plaintext): string
    {
        $salt       = random_bytes(16);
        $saltHex    = bin2hex($salt);
        $iterations = 10000;
        $hashHex    = hash_pbkdf2('sha256', $plaintext, $salt, $iterations, 32, false);

        return 'pbkdf2sha256:' . $iterations . ':' . $saltHex . ':' . $hashHex;
    }

    // -----------------------------------------------------------------------
    // checkPassword()
    // Parses the stored hash, recomputes the PBKDF2 digest, and uses a
    // timing-safe comparison to defend against timing attacks.
    // -----------------------------------------------------------------------
    public function checkPassword(string $plaintext, string $storedHash): bool
    {
        // Expected format: pbkdf2sha256:{iterations}:{saltHex}:{hashHex}
        $parts = explode(':', $storedHash);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2sha256') {
            // Fall back to PHP's built-in password_verify for bcrypt hashes
            // stored by older versions of the application.
            return password_verify($plaintext, $storedHash);
        }

        [, $iterations, $saltHex, $knownHashHex] = $parts;
        $iterations = (int) $iterations;

        if ($iterations <= 0 || $saltHex === '' || $knownHashHex === '') {
            return false;
        }

        $salt        = hex2bin($saltHex);
        $computedHex = hash_pbkdf2('sha256', $plaintext, $salt, $iterations, 32, false);

        return hash_equals($knownHashHex, $computedHex);
    }

    // -----------------------------------------------------------------------
    // getUsers()
    // Returns a paginated list of users, optionally filtered by role.
    //
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // -----------------------------------------------------------------------
    public function getUsers(string $role = '', int $page = 1): array
    {
        $sql    = 'SELECT id, email, name, role, is_active, last_login, created_at
                     FROM users';
        $params = [];

        if ($role !== '') {
            $sql   .= ' WHERE role = :role';
            $params[':role'] = $role;
        }

        $sql .= ' ORDER BY name ASC';

        return $this->paginate($sql, $params, $page, PAGE_SIZE);
    }

    // -----------------------------------------------------------------------
    // saveUser()
    // Inserts a new user or updates an existing one.
    //
    // $data keys (insert): email, password (plaintext), first_name, last_name, role
    // $data keys (update): id, email, first_name, last_name, role, is_active
    //                      optionally: password (triggers re-hash)
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveUser(array $data): array
    {
        $id       = (int) ($data['id'] ?? 0);
        $email    = trim(strtolower($data['email'] ?? ''));
        $name     = trim($data['name'] ?? '');
        $role     = trim($data['role'] ?? 'buyer');
        $isActive = isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1;

        if ($email === '' || $name === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Email and name are required.'];
        }

        if ($id === 0) {
            // ---- INSERT ----
            if (empty($data['password'])) {
                return ['success' => false, 'id' => 0, 'message' => 'Password is required for new users.'];
            }

            $hash = $this->hashPassword($data['password']);

            $stmt = $this->db->prepare(
                'INSERT INTO users (email, password_hash, name, role, is_active, created_at)
                 VALUES (:email, :hash, :name, :role, :is_active, NOW())'
            );
            $stmt->execute([
                ':email'     => $email,
                ':hash'      => $hash,
                ':name'      => $name,
                ':role'      => $role,
                ':is_active' => $isActive,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_user', 'user', $newId, "Created user {$email}");

            return ['success' => true, 'id' => $newId, 'message' => 'User created successfully.'];
        }

        // ---- UPDATE ----
        $params = [
            ':email'     => $email,
            ':name'      => $name,
            ':role'      => $role,
            ':is_active' => $isActive,
            ':id'        => $id,
        ];

        $passwordClause = '';
        if (!empty($data['password'])) {
            $passwordClause  = ', password_hash = :hash';
            $params[':hash'] = $this->hashPassword($data['password']);
        }

        $stmt = $this->db->prepare(
            'UPDATE users
                SET email     = :email,
                    name      = :name,
                    role      = :role,
                    is_active = :is_active'
            . $passwordClause .
            ' WHERE id = :id'
        );
        $stmt->execute($params);

        $this->auditLog('update_user', 'user', $id, "Updated user {$email}");

        return ['success' => true, 'id' => $id, 'message' => 'User updated successfully.'];
    }
}
