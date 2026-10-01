<?php

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    private const COOKIE = 'nbx_remember';

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id && !empty($_COOKIE[self::COOKIE])) {
            $id = self::fromRememberCookie();
        }
        if ($id) {
            $u = DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
            if ($u && $u['status'] === 'active') {
                self::$user = $u;
                $_SESSION['uid'] = (int)$u['id'];
            } else {
                unset($_SESSION['uid']);
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int)self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    public static function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        unset($_SESSION['impersonator']);
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_ip' => client_ip()], 'id = ?', [$user['id']]);
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            DB::update('users', ['remember_token' => hash('sha256', $token)], 'id = ?', [$user['id']]);
            self::setCookie($user['id'] . '|' . $token, time() + 86400 * 30);
        }
        self::refresh();
    }

    public static function logout(): void
    {
        if ($id = self::id()) {
            DB::update('users', ['remember_token' => null], 'id = ?', [$id]);
        }
        self::setCookie('', time() - 3600);
        $_SESSION = [];
        session_regenerate_id(true);
        self::refresh();
    }

    /** Try email / username / mobile + password. Returns user or error message. */
    public static function attempt(string $login, string $password): array|string
    {
        $ip = client_ip();
        $recent = (int)DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)', [$ip]);
        if ($recent >= 6) {
            return 'تعداد تلاش‌های ناموفق زیاد است. ۱۵ دقیقه دیگر دوباره تلاش کنید.';
        }
        $login = trim(en_digits($login));
        $mobile = normalize_mobile($login);
        $u = DB::row('SELECT * FROM users WHERE email = ? OR username = ? OR mobile = ? LIMIT 1', [mb_strtolower($login), $login, $mobile ?: '-']);
        if (!$u || !$u['password'] || !password_verify($password, $u['password'])) {
            DB::insert('login_attempts', ['ip' => $ip, 'login' => mb_substr($login, 0, 190)]);
            return 'اطلاعات ورود صحیح نیست.';
        }
        if ($u['status'] !== 'active') {
            return 'حساب کاربری شما مسدود شده است. با پشتیبانی تماس بگیرید.';
        }
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        DB::delete('login_attempts', 'ip = ?', [$ip]);
        return $u;
    }

    public static function impersonate(int $userId): void
    {
        $admin = self::id();
        $_SESSION['uid'] = $userId;
        $_SESSION['impersonator'] = $admin;
        self::refresh();
    }

    public static function stopImpersonating(): bool
    {
        if (empty($_SESSION['impersonator'])) {
            return false;
        }
        $_SESSION['uid'] = (int)$_SESSION['impersonator'];
        unset($_SESSION['impersonator']);
        self::refresh();
        return true;
    }

    private static function fromRememberCookie(): ?int
    {
        $parts = explode('|', (string)$_COOKIE[self::COOKIE], 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return null;
        }
        $hash = DB::value('SELECT remember_token FROM users WHERE id = ?', [(int)$parts[0]]);
        if ($hash && hash_equals($hash, hash('sha256', $parts[1]))) {
            session_regenerate_id(true);
            return (int)$parts[0];
        }
        self::setCookie('', time() - 3600);
        return null;
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => base_path() ?: '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
