<?php

// ---------------------------------------------------------------------------
// Config / settings
// ---------------------------------------------------------------------------

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($key === '__flush') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all('SELECT `key`, `value` FROM settings') as $r) {
                $cache[$r['key']] = $r['value'];
            }
        } catch (Throwable) {
        }
    }
    $v = $cache[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function setting_set(string $key, ?string $value): void
{
    DB::query('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
    setting('__flush');
}

function site_name(): string
{
    return setting('site_name', 'نامبیکس');
}

// ---------------------------------------------------------------------------
// URLs & assets
// ---------------------------------------------------------------------------

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (PHP_SAPI === 'cli' || defined('NBX_FORCE_CONFIG_BASE')) {
        $base = rtrim((string)parse_url((string)config('app.url', ''), PHP_URL_PATH), '/');
    } else {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
    }
    return $base;
}

function url(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');
    if (config('app.pretty_urls', true)) {
        $u = base_path() . '/' . $path;
    } else {
        $u = base_path() . '/index.php' . ($path !== '' ? '?r=' . $path : '');
    }
    if ($query) {
        $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    }
    return $u;
}

function abs_url(string $path = '', array $query = []): string
{
    $app = (string)config('app.url', '');
    $p = parse_url($app);
    if (!empty($p['host'])) {
        $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $origin = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $origin . url($path, $query);
}

function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . NBX_VERSION;
}

function upload_url(?string $path): string
{
    return $path ? base_path() . '/' . ltrim($path, '/') : '';
}

function current_path(): string
{
    return $GLOBALS['__path'] ?? '/';
}

function is_active(string $prefix, bool $exact = false): bool
{
    $p = '/' . trim(current_path(), '/');
    $prefix = '/' . trim($prefix, '/');
    return $exact ? $p === $prefix : ($p === $prefix || str_starts_with($p, rtrim($prefix, '/') . '/'));
}

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

function e(mixed $s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fa(mixed $s): string
{
    return strtr((string)$s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function en_digits(mixed $s): string
{
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function num(int|float|string|null $n, int $dec = 0): string
{
    return fa(number_format((float)$n, $dec));
}

function money(int|float|string|null $n, bool $unit = true): string
{
    return num(round((float)$n)) . ($unit ? ' <small class="unit">' . e(setting('currency', 'تومان')) . '</small>' : '');
}

function money_text(int|float|string|null $n): string
{
    return num(round((float)$n)) . ' ' . setting('currency', 'تومان');
}

function short_num(int|float $n): string
{
    if ($n >= 1000000) {
        return num(round($n / 1000000, 1), $n % 1000000 ? 1 : 0) . 'M';
    }
    if ($n >= 1000) {
        return num(round($n / 1000, 1), $n % 1000 ? 1 : 0) . 'K';
    }
    return num($n);
}

function str_limit(?string $s, int $n = 80): string
{
    $s = trim(strip_tags((string)$s));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? mb_substr($s, 0, 120) : bin2hex(random_bytes(4));
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'لحظاتی پیش';
    }
    if ($diff < 3600) {
        return fa(intdiv($diff, 60)) . ' دقیقه پیش';
    }
    if ($diff < 86400) {
        return fa(intdiv($diff, 3600)) . ' ساعت پیش';
    }
    if ($diff < 86400 * 7) {
        return fa(intdiv($diff, 86400)) . ' روز پیش';
    }
    return jdate('j F Y', $datetime);
}

function icon(string $name, string $class = ''): string
{
    static $icons = null;
    $icons ??= require __DIR__ . '/icons.php';
    $body = $icons[$name] ?? $icons['box'];
    return '<svg class="i ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function brand(string $name, string $class = ''): string
{
    static $brands = null;
    $brands ??= require __DIR__ . '/brands.php';
    if (!isset($brands[$name])) {
        return icon($name ?: 'globe', $class);
    }
    return '<svg class="i brand ' . $class . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . $brands[$name] . '"/></svg>';
}

function brand_exists(string $name): bool
{
    static $brands = null;
    $brands ??= require __DIR__ . '/brands.php';
    return isset($brands[$name]);
}

/** Brand tile: a glossy rounded square in the brand colours. */
function brand_tile(?array $cat, string $size = 'md'): string
{
    $c1 = $cat['color'] ?? '#6C4CF1';
    $c2 = $cat['color2'] ?? $c1;
    $ic = $cat['icon'] ?? 'grid';
    return '<span class="btile btile-' . $size . '" style="--c1:' . e($c1) . ';--c2:' . e($c2) . '">' . brand($ic) . '</span>';
}

function avatar(?array $u, string $size = 'md'): string
{
    if (!$u) {
        return '<span class="avatar avatar-' . $size . '">' . icon('user') . '</span>';
    }
    if (!empty($u['avatar'])) {
        return '<span class="avatar avatar-' . $size . '"><img src="' . e(upload_url($u['avatar'])) . '" alt="" loading="lazy"></span>';
    }
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? $u['email'] ?? '?');
    $letter = mb_substr($name, 0, 1);
    $hue = crc32((string)($u['id'] ?? $name)) % 360;
    return '<span class="avatar avatar-' . $size . '" style="--h:' . $hue . '">' . e($letter) . '</span>';
}

function user_name(?array $u): string
{
    if (!$u) {
        return 'کاربر حذف شده';
    }
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : ($u['username'] ?? $u['email'] ?? ('کاربر #' . $u['id']));
}

function stars(float $rating): string
{
    return '<span class="stars">' . icon('star', 'star-fill') . '<b>' . num($rating, 1) . '</b></span>';
}

// ---------------------------------------------------------------------------
// Status dictionaries
// ---------------------------------------------------------------------------

function order_statuses(): array
{
    return [
        'unpaid' => ['در انتظار پرداخت', 'info', 'hourglass'],
        'pending' => ['در صف انجام', 'warning', 'clock'],
        'processing' => ['در حال پردازش', 'warning', 'loader'],
        'in_progress' => ['در حال انجام', 'primary', 'activity'],
        'completed' => ['تکمیل شده', 'success', 'check-circle'],
        'partial' => ['انجام ناقص', 'orange', 'alert'],
        'canceled' => ['لغو شده', 'danger', 'x-circle'],
        'refunded' => ['مسترد شده', 'muted', 'repeat'],
    ];
}

function payment_statuses(): array
{
    return [
        'pending' => ['در انتظار پرداخت', 'warning', 'hourglass'],
        'review' => ['در حال بررسی', 'info', 'clock'],
        'paid' => ['موفق', 'success', 'check-circle'],
        'failed' => ['ناموفق', 'danger', 'x-circle'],
        'rejected' => ['رد شده', 'danger', 'x-circle'],
    ];
}

function ticket_statuses(): array
{
    return [
        'open' => ['باز', 'warning', 'inbox'],
        'answered' => ['پاسخ داده شده', 'success', 'check-circle'],
        'customer_reply' => ['پاسخ کاربر', 'info', 'message'],
        'closed' => ['بسته شده', 'muted', 'lock'],
    ];
}

function service_types(): array
{
    return [
        'member' => 'افزایش ممبر',
        'follower' => 'افزایش فالوور',
        'like' => 'افزایش لایک',
        'view' => 'افزایش بازدید',
        'comment' => 'کامنت و تعامل',
        'other' => 'سایر خدمات',
    ];
}

function service_badges(): array
{
    return [
        'new' => ['جدید', 'blue'],
        'hot' => ['محبوب', 'red'],
        'bestseller' => ['پرفروش', 'amber'],
        'special' => ['ویژه', 'green'],
        'offer' => ['تخفیف ویژه', 'pink'],
    ];
}

function badge(string $label, string $color = 'muted', ?string $ic = null): string
{
    return '<span class="badge badge-' . e($color) . '">' . ($ic ? icon($ic) : '<i class="dot"></i>') . e($label) . '</span>';
}

function status_badge(string $status, string $dict = 'order'): string
{
    $map = match ($dict) {
        'payment' => payment_statuses(),
        'ticket' => ticket_statuses(),
        default => order_statuses(),
    };
    [$label, $color, $ic] = $map[$status] ?? [$status, 'muted', null];
    return badge($label, $color, $ic);
}

function stock_state(array $s): array
{
    if ($s['stock'] === null) {
        return ['unlimited', 'موجود', 'success'];
    }
    $stock = (int)$s['stock'];
    if ($stock <= 0 || $stock < (int)$s['min_qty']) {
        return ['out', 'ناموجود', 'danger'];
    }
    if ($stock <= (int)($s['stock_alert'] ?: setting('low_stock_default', 500))) {
        return ['low', 'موجودی کم', 'warning'];
    }
    return ['in', 'موجود', 'success'];
}

function service_price(array $s, int $qty): int
{
    return (int)ceil(((int)$s['price'] * $qty) / 1000);
}

function service_url(array $s): string
{
    return url('service/' . $s['slug']);
}

// ---------------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------------

function render(string $__view, array $__data = []): string
{
    extract($__data, EXTR_SKIP);
    ob_start();
    include APP_PATH . '/views/' . $__view . '.php';
    return (string)ob_get_clean();
}

function view(string $view, array $data = [], ?string $layout = null): string
{
    $data['content'] = render($view, $data);
    return $layout ? render('layouts/' . $layout, $data) : $data['content'];
}

function partial(string $name, array $data = []): string
{
    return render('partials/' . $name, $data);
}

function push(string $stack): void
{
    $GLOBALS['__stack_name'][] = $stack;
    ob_start();
}

function endpush(): void
{
    $stack = array_pop($GLOBALS['__stack_name']);
    $GLOBALS['__stacks'][$stack][] = ob_get_clean();
}

function stack(string $stack): string
{
    return implode("\n", $GLOBALS['__stacks'][$stack] ?? []);
}

// ---------------------------------------------------------------------------
// Request / response
// ---------------------------------------------------------------------------

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    if ($v === null || $v === '') {
        return $default;
    }
    return (int)preg_replace('/[^\d\-]/', '', en_digits((string)$v));
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function redirect(string $to, bool $isUrl = false): never
{
    if (!$isUrl && !preg_match('~^https?://~', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to, true, 302);
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    if ($ref && $host === ($_SERVER['HTTP_HOST'] ?? parse_url(abs_url(), PHP_URL_HOST))) {
        redirect($ref, true);
    }
    redirect($fallback);
}

function json(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code = 404, string $message = ''): never
{
    http_response_code($code);
    if (is_ajax()) {
        json(['ok' => false, 'message' => $message ?: 'خطا'], $code);
    }
    echo view('errors/error', ['code' => $code, 'message' => $message, 'title' => 'خطا ' . $code], 'blank');
    exit;
}

/** Only allow redirecting to local paths (prevents open redirects). */
function safe_next(?string $next, string $fallback = 'dashboard'): string
{
    if ($next && str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, '\\')) {
        return $next;
    }
    return url($fallback);
}

// ---------------------------------------------------------------------------
// Session: CSRF, flash, old input
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

function csrf_valid(): bool
{
    $t = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [$type, $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function with_input(): void
{
    $data = $_POST;
    unset($data['password'], $data['password_confirmation'], $data['_token'], $data['current_password']);
    $_SESSION['_old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['__old'][$key] ?? $default;
}

function fail(string $message, string $to = ''): never
{
    if (is_ajax()) {
        json(['ok' => false, 'message' => $message], 422);
    }
    with_input();
    flash('error', $message);
    $to === '' ? back() : redirect($to);
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

function paginate(string $select, string $from, array $params = [], int $per = 15, string $order = ''): array
{
    $page = max(1, input_int('page', 1));
    $total = (int)DB::value("SELECT COUNT(*) $from", $params);
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    $offset = ($page - 1) * $per;
    $items = DB::all("SELECT $select $from $order LIMIT $per OFFSET $offset", $params);
    return compact('items', 'total', 'pages', 'page', 'per');
}

function page_url(int $page): string
{
    $q = $_GET;
    unset($q['r']);
    $q['page'] = $page;
    return url(trim(current_path(), '/'), $q);
}

// ---------------------------------------------------------------------------
// Auth shortcuts
// ---------------------------------------------------------------------------

function auth(): ?array
{
    return Auth::user();
}

function is_admin(): bool
{
    return (Auth::user()['role'] ?? '') === 'admin';
}

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

function random_code(int $len = 8, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $s;
}

function normalize_mobile(?string $m): ?string
{
    $m = preg_replace('/\D+/', '', en_digits((string)$m));
    if ($m === '') {
        return null;
    }
    if (str_starts_with($m, '0098')) {
        $m = '0' . substr($m, 4);
    } elseif (str_starts_with($m, '98') && strlen($m) === 12) {
        $m = '0' . substr($m, 2);
    } elseif (strlen($m) === 10 && $m[0] === '9') {
        $m = '0' . $m;
    }
    return $m;
}

function log_error(string $message): void
{
    @file_put_contents(STORAGE_PATH . '/logs/app-' . date('Y-m') . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function http_post(string $url, array|string $data, array $headers = [], int $timeout = 25): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => is_array($data) ? http_build_query($data) : $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Numbix/' . NBX_VERSION,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['ok' => $body !== false && $code < 500, 'code' => $code, 'body' => $body === false ? '' : $body, 'error' => $err];
}
