<?php

final class AuthController
{
    private static function socials(): array
    {
        return [
            'google' => setting('google_client_id') && setting('google_client_secret'),
            'telegram' => setting('telegram_login') === '1' && setting('telegram_bot_token'),
        ];
    }

    public function loginForm(): string
    {
        return view('auth/login', ['title' => 'ورود', 'side' => 'login', 'socials' => self::socials(), 'next' => input('next', '')], 'auth');
    }

    public function login(): never
    {
        $login = (string)input('login', '');
        $password = (string)($_POST['password'] ?? '');
        if ($login === '' || $password === '') {
            fail('ایمیل/موبایل و رمز عبور را وارد کنید.');
        }
        $res = Auth::attempt($login, $password);
        if (is_string($res)) {
            fail($res);
        }
        Auth::login($res, input('remember') === '1');
        flash('success', 'خوش آمدید ' . user_name($res) . ' 👋');
        redirect(safe_next(input('next'), $res['role'] === 'admin' ? 'admin' : 'dashboard'), true);
    }

    public function registerForm(): string
    {
        if (setting('register_enabled', '1') !== '1') {
            flash('warning', 'ثبت‌نام کاربر جدید فعلاً غیرفعال است.');
            redirect('login');
        }
        return view('auth/register', ['title' => 'ثبت‌نام', 'side' => 'register', 'socials' => self::socials(), 'backUrl' => 'login', 'backLabel' => 'بازگشت به ورود'], 'auth');
    }

    public function register(): never
    {
        if (setting('register_enabled', '1') !== '1') {
            fail('ثبت‌نام غیرفعال است.');
        }
        $first = mb_substr(trim((string)input('first_name')), 0, 80);
        $last = mb_substr(trim((string)input('last_name')), 0, 80);
        $email = mb_strtolower(trim((string)input('email')));
        $mobile = normalize_mobile((string)input('mobile'));
        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirmation'] ?? '');

        if ($first === '') {
            fail('نام خود را وارد کنید.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('ایمیل وارد شده معتبر نیست.');
        }
        if ($mobile !== null && !preg_match('/^09\d{9}$/', $mobile)) {
            fail('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
        }
        if (setting('require_mobile', '0') === '1' && !$mobile) {
            fail('شماره موبایل الزامی است.');
        }
        if (mb_strlen($pass) < 8 || !preg_match('/\d/', $pass) || !preg_match('/\p{L}/u', $pass)) {
            fail('رمز عبور باید حداقل ۸ کاراکتر و شامل عدد و حرف باشد.');
        }
        if ($pass !== $pass2) {
            fail('تکرار رمز عبور مطابقت ندارد.');
        }
        if (input('terms') !== '1') {
            fail('برای ثبت‌نام باید قوانین را بپذیرید.');
        }
        if (DB::value('SELECT id FROM users WHERE email = ?', [$email])) {
            fail('این ایمیل قبلاً ثبت شده است. وارد شوید یا رمز عبور را بازیابی کنید.');
        }
        if ($mobile && DB::value('SELECT id FROM users WHERE mobile = ?', [$mobile])) {
            fail('این شماره موبایل قبلاً ثبت شده است.');
        }
        $id = DB::insert('users', [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'mobile' => $mobile,
            'password' => password_hash($pass, PASSWORD_DEFAULT),
            'last_ip' => client_ip(),
        ]);
        self::welcome($id);
        Auth::login(DB::row('SELECT * FROM users WHERE id = ?', [$id]), true);
        flash('success', 'حساب کاربری شما با موفقیت ساخته شد. خوش آمدید! 🎉');
        redirect(safe_next(input('next'), 'dashboard'), true);
    }

    private static function welcome(int $userId): void
    {
        $bonus = (int)setting('register_bonus', 0);
        if ($bonus > 0) {
            Wallet::credit($userId, $bonus, 'bonus', 'هدیه ثبت‌نام');
        }
        Notifier::user($userId, 'به ' . site_name() . ' خوش آمدید!', $bonus > 0 ? money_text($bonus) . ' هدیه ثبت‌نام به کیف پول شما اضافه شد.' : 'اولین سفارش خود را همین حالا ثبت کنید.', 'dashboard/new-order', 'gift');
        Notifier::admin('کاربر جدید ثبت‌نام کرد', '', 'admin/users/' . $userId, 'user-plus', false);
    }

    public function logout(): never
    {
        Auth::logout();
        flash('info', 'از حساب کاربری خارج شدید.');
        redirect('/');
    }

    public function stopImpersonating(): never
    {
        Auth::stopImpersonating();
        redirect('admin/users');
    }

    // ------------------------------------------------------------------ password reset

    public function forgotForm(): string
    {
        return view('auth/forgot', ['title' => 'بازیابی رمز عبور', 'side' => 'login', 'backUrl' => 'login', 'backLabel' => 'بازگشت به ورود'], 'auth');
    }

    public function forgot(): never
    {
        $email = mb_strtolower(trim((string)input('email')));
        $u = $email ? DB::row('SELECT * FROM users WHERE email = ?', [$email]) : null;
        if ($u && $u['status'] === 'active') {
            $recent = (int)DB::value('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)', [$u['id']]);
            if ($recent < 3) {
                $token = bin2hex(random_bytes(32));
                DB::insert('password_resets', ['user_id' => $u['id'], 'token' => hash('sha256', $token)]);
                $link = abs_url('reset/' . $token);
                Notifier::mail($email, 'بازیابی رمز عبور', '<p>سلام ' . e(user_name($u)) . '،</p><p>برای تعیین رمز عبور جدید روی لینک زیر کلیک کنید (اعتبار: ۱ ساعت):</p><p><a href="' . e($link) . '" style="display:inline-block;background:#6C4CF1;color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none">تعیین رمز جدید</a></p><p style="color:#888;font-size:12px">اگر شما این درخواست را نداده‌اید، این ایمیل را نادیده بگیرید.</p>');
            }
        }
        flash('success', 'اگر این ایمیل در سیستم ثبت شده باشد، لینک بازیابی برای آن ارسال شد.');
        redirect('login');
    }

    public function resetForm(string $token): string
    {
        if (!self::resetUser($token)) {
            flash('error', 'لینک بازیابی نامعتبر یا منقضی شده است.');
            redirect('forgot');
        }
        return view('auth/reset', ['title' => 'رمز عبور جدید', 'side' => 'login', 'token' => $token], 'auth');
    }

    public function reset(string $token): never
    {
        $u = self::resetUser($token);
        if (!$u) {
            fail('لینک بازیابی نامعتبر یا منقضی شده است.', 'forgot');
        }
        $pass = (string)($_POST['password'] ?? '');
        if (mb_strlen($pass) < 8 || !preg_match('/\d/', $pass) || !preg_match('/\p{L}/u', $pass)) {
            fail('رمز عبور باید حداقل ۸ کاراکتر و شامل عدد و حرف باشد.');
        }
        if ($pass !== ($_POST['password_confirmation'] ?? '')) {
            fail('تکرار رمز عبور مطابقت ندارد.');
        }
        DB::update('users', ['password' => password_hash($pass, PASSWORD_DEFAULT), 'remember_token' => null], 'id = ?', [$u['id']]);
        DB::delete('password_resets', 'user_id = ?', [$u['id']]);
        Auth::login($u);
        flash('success', 'رمز عبور شما تغییر کرد.');
        redirect('dashboard');
    }

    private static function resetUser(string $token): ?array
    {
        if (!ctype_xdigit($token)) {
            return null;
        }
        return DB::row("SELECT u.* FROM password_resets r JOIN users u ON u.id = r.user_id
            WHERE r.token = ? AND r.created_at > (NOW() - INTERVAL 1 HOUR) AND u.status = 'active' LIMIT 1", [hash('sha256', $token)]);
    }

    // ------------------------------------------------------------------ social login

    public function google(): never
    {
        if (!self::socials()['google']) {
            fail('ورود با گوگل فعال نیست.', 'login');
        }
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $_SESSION['oauth_next'] = input('next', '');
        redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => setting('google_client_id'),
            'redirect_uri' => abs_url('auth/google/callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $_SESSION['oauth_state'],
            'prompt' => 'select_account',
        ]), true);
    }

    public function googleCallback(): never
    {
        $state = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);
        if (!$state || !hash_equals($state, (string)input('state')) || !input('code')) {
            fail('ورود با گوگل لغو شد یا نامعتبر بود.', 'login');
        }
        $tok = http_post('https://oauth2.googleapis.com/token', [
            'code' => input('code'),
            'client_id' => setting('google_client_id'),
            'client_secret' => setting('google_client_secret'),
            'redirect_uri' => abs_url('auth/google/callback'),
            'grant_type' => 'authorization_code',
        ]);
        $t = json_decode($tok['body'], true);
        if (empty($t['access_token'])) {
            fail('ارتباط با گوگل برقرار نشد.', 'login');
        }
        $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $t['access_token']]]);
        $info = json_decode((string)curl_exec($ch), true);
        if (empty($info['sub'])) {
            fail('اطلاعات حساب گوگل دریافت نشد.', 'login');
        }
        $u = DB::row('SELECT * FROM users WHERE google_id = ?', [$info['sub']]);
        if (!$u && !empty($info['email']) && !empty($info['email_verified'])) {
            $u = DB::row('SELECT * FROM users WHERE email = ?', [mb_strtolower($info['email'])]);
            if ($u) {
                DB::update('users', ['google_id' => $info['sub']], 'id = ?', [$u['id']]);
            }
        }
        if (!$u) {
            if (setting('register_enabled', '1') !== '1' || empty($info['email'])) {
                fail('حسابی با این ایمیل وجود ندارد.', 'login');
            }
            $id = DB::insert('users', [
                'first_name' => mb_substr($info['given_name'] ?? '', 0, 80),
                'last_name' => mb_substr($info['family_name'] ?? '', 0, 80),
                'email' => mb_strtolower($info['email']),
                'google_id' => $info['sub'],
                'last_ip' => client_ip(),
            ]);
            self::welcome($id);
            $u = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
        }
        self::finishSocial($u);
    }

    public function telegram(): string
    {
        if (!self::socials()['telegram']) {
            fail('ورود با تلگرام فعال نیست.', 'login');
        }
        $token = (string)setting('telegram_bot_token');
        if (!isset($_GET['hash'])) {
            if (input('start')) {
                redirect('https://oauth.telegram.org/auth?' . http_build_query([
                    'bot_id' => explode(':', $token)[0],
                    'origin' => preg_replace('~(https?://[^/]+).*~', '$1', abs_url()),
                    'request_access' => 'write',
                    'return_to' => abs_url('auth/telegram'),
                ]), true);
            }
            // Telegram returns the result in the URL fragment; forward it to the server.
            return '<!doctype html><meta charset="utf-8"><script>var m=location.hash.match(/tgAuthResult=([^&]+)/);if(m){try{var d=JSON.parse(atob(m[1].replace(/-/g,"+").replace(/_/g,"/")));var b=location.href.split("#")[0];location.replace(b+(b.indexOf("?")>-1?"&":"?")+new URLSearchParams(d).toString());}catch(e){location.replace("' . url('login') . '");}}else{location.replace("' . url('login') . '");}</script>';
        }
        $data = array_intersect_key($_GET, array_flip(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']));
        $hash = (string)$data['hash'];
        unset($data['hash']);
        ksort($data);
        $check = implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($data), $data));
        $calc = hash_hmac('sha256', $check, hash('sha256', $token, true));
        if (!hash_equals($calc, $hash) || time() - (int)($data['auth_date'] ?? 0) > 86400) {
            fail('اعتبارسنجی ورود تلگرام ناموفق بود.', 'login');
        }
        $tgId = (int)$data['id'];
        $u = DB::row('SELECT * FROM users WHERE telegram_id = ?', [$tgId]);
        if (!$u && ($cur = auth())) {
            DB::update('users', ['telegram_id' => $tgId], 'id = ?', [$cur['id']]);
            flash('success', 'حساب تلگرام شما متصل شد.');
            redirect('dashboard/profile');
        }
        if (!$u) {
            if (setting('register_enabled', '1') !== '1') {
                fail('ثبت‌نام غیرفعال است.', 'login');
            }
            $id = DB::insert('users', [
                'first_name' => mb_substr($data['first_name'] ?? '', 0, 80),
                'last_name' => mb_substr($data['last_name'] ?? '', 0, 80),
                'username' => !empty($data['username']) && !DB::value('SELECT id FROM users WHERE username = ?', [$data['username']]) ? mb_substr($data['username'], 0, 50) : null,
                'telegram_id' => $tgId,
                'last_ip' => client_ip(),
            ]);
            self::welcome($id);
            $u = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
        }
        self::finishSocial($u);
    }

    private static function finishSocial(array $u): never
    {
        if ($u['status'] !== 'active') {
            fail('حساب کاربری شما مسدود شده است.', 'login');
        }
        Auth::login($u, true);
        flash('success', 'خوش آمدید ' . user_name($u) . ' 👋');
        $next = $_SESSION['oauth_next'] ?? '';
        unset($_SESSION['oauth_next']);
        redirect(safe_next($next, $u['role'] === 'admin' ? 'admin' : 'dashboard'), true);
    }
}
