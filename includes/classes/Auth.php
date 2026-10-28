<?php

/**
 * Administrator login with hashed passwords, session handling and account lock-out.
 */
class Auth
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_EDITOR = 'editor';

    /**
     * Returns null on success, otherwise a translation key describing the error.
     */
    public static function attempt(string $email, string $password, array $security): ?string
    {
        $user = Database::one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) {
            // Hash anyway so the response time does not reveal which emails exist
            password_verify($password, '$2y$10$2ZU2aU/.xp08ocT9mBNZDOPmWOJmPoKjtzkLrV7Ikr6yU5ER8ijGO');
            AuditLog::write('login_failed', 'Unknown email: ' . $email);
            return 'login.invalid';
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return 'login.locked';
        }

        if (!password_verify($password, $user['password_hash'])) {
            $failed = $user['failed_logins'] + 1;
            $lockUntil = null;
            if ($failed >= $security['max_failed_logins']) {
                $lockUntil = date('Y-m-d H:i:s', time() + $security['lock_minutes'] * 60);
                $failed = 0;
            }
            Database::query('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?', [$failed, $lockUntil, $user['id']]);
            AuditLog::write('login_failed', 'Wrong password for ' . $email, (int) $user['id']);
            return $lockUntil ? 'login.locked' : 'login.invalid';
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        Database::query('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?', [$user['id']]);

        // New session id after login prevents session fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        AuditLog::write('login', 'Signed in');
        return null;
    }

    public static function logout(): void
    {
        AuditLog::write('logout', 'Signed out');
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        static $user = false;
        if ($user === false) {
            $user = empty($_SESSION['user_id'])
                ? null
                : Database::one('SELECT id, name, email, role, last_login_at FROM users WHERE id = ?', [$_SESSION['user_id']]);
        }
        return $user;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === self::ROLE_ADMIN;
    }

    /** Stop the page unless someone is logged in (and, optionally, is an admin). */
    public static function require(bool $adminOnly = false): array
    {
        // Sign out after 30 minutes without activity
        if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
            $_SESSION = [];
            flash('warning', t('login.expired'));
        }
        $_SESSION['last_activity'] = time();

        $user = self::user();
        if (!$user) {
            redirect('admin/login.php');
        }
        if ($adminOnly && $user['role'] !== self::ROLE_ADMIN) {
            http_response_code(403);
            exit(t('error.forbidden'));
        }
        return $user;
    }

    /** Password rule: at least 8 characters with a letter and a digit. */
    public static function passwordIsStrong(string $password): bool
    {
        return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
    }
}
