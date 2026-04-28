<?php
/**
 * stock-advisor/src/AuthService.php
 * Authentication against the bkTrade users table.
 * Schema: id, email, password_hash, full_name, role, is_active, last_login_at
 */
class AuthService extends BaseService
{
    /**
     * Validate credentials. Returns the user row on success, false on failure.
     */
    public function login(string $email, string $password)
    {
        $email = trim(strtolower($email));

        if ($email === '' || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, full_name, role, is_active
               FROM users
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                 ->execute([':id' => $user['id']]);

        return $user;
    }
}
