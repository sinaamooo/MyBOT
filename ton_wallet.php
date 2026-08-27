<?php
/**
 * 🔐 لایه ولت TON — ساخت، امضا و ارسال تراکنش با PHP خالص
 *
 * فقط به افزونه‌های استاندارد PHP نیاز دارد: sodium (امضای Ed25519)، hash و curl.
 * هیچ کتابخانه بیرونی لازم نیست.
 *
 * ⚠️ هشدار مهم:
 * این فایل با کلید خصوصی ولت کار می‌کند. هرکس به آن دسترسی پیدا کند،
 * می‌تواند ولت را خالی کند. عبارت بازیابی رمزشده ذخیره می‌شود، ولی چون کلید
 * رمزگشایی هم روی همان سرور است، رمزگذاری فقط جلوی نگاه اتفاقی را می‌گیرد،
 * نه کسی که به فایل‌ها دسترسی کامل دارد.
 *
 * قواعدی که رعایت شده:
 *   • سقف مبلغ هر تراکنش و سقف روزانه — قبل از امضا بررسی می‌شوند
 *   • حالت آزمایشی: تراکنش ساخته و نشان داده می‌شود ولی فرستاده نمی‌شود
 *   • هر تراکنش فقط یک بار ارسال می‌شود (قفل روی seqno)
 */

// ============================================================
// 🧱 رشته بیت — پایه ساخت سلول
// ============================================================

class TonBits
{
    public $bits = '';           // رشته‌ای از '0' و '1'

    public function writeBit($b)       { $this->bits .= $b ? '1' : '0'; return $this; }
    public function writeBits($s)      { $this->bits .= $s; return $this; }

    /** عدد بدون علامت با طول مشخص */
    public function writeUint($value, $len) {
        if ($len <= 0) return $this;
        $v = '';
        // با رشته کار می‌کنیم تا اعداد ۶۴ بیتی هم سالم بمانند
        $n = (int)$value;
        for ($i = $len - 1; $i >= 0; $i--) $v .= (($n >> $i) & 1) ? '1' : '0';
        $this->bits .= $v;
        return $this;
    }

    /** بایت‌های خام */
    public function writeBytes($bin) {
        $len = strlen($bin);
        for ($i = 0; $i < $len; $i++) {
            $this->bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
        }
        return $this;
    }

    /**
     * Grams / VarUInteger 16 — اول طول بایت‌ها در ۴ بیت، بعد خود عدد.
     * مقدارها به‌صورت رشته می‌آیند چون nanoton می‌تواند از محدوده int بگذرد.
     */
    public function writeCoins($amountStr) {
        $hex = tonDecToHex((string)$amountStr);
        if ($hex === '' || $hex === '0') { $this->writeUint(0, 4); return $this; }
        if (strlen($hex) % 2) $hex = '0' . $hex;
        $bytes = strlen($hex) / 2;
        $this->writeUint($bytes, 4);
        $this->writeBytes(hex2bin($hex));
        return $this;
    }

    public function length() { return strlen($this->bits); }
}

/** عدد ده‌دهی بزرگ (رشته) → هگز — بدون نیاز به gmp/bcmath */
function tonDecToHex($dec) {
    $dec = ltrim(trim((string)$dec), '+');
    if ($dec === '' || !preg_match('/^\d+$/', $dec)) return '0';
    $dec = ltrim($dec, '0');
    if ($dec === '') return '0';

    $hex = '';
    while ($dec !== '' && $dec !== '0') {
        $carry = 0; $next = '';
        for ($i = 0, $n = strlen($dec); $i < $n; $i++) {
            $cur   = $carry * 10 + (int)$dec[$i];
            $q     = intdiv($cur, 16);
            $carry = $cur % 16;
            if ($next !== '' || $q > 0) $next .= (string)$q;
        }
        $hex = dechex($carry) . $hex;
        $dec = $next === '' ? '0' : $next;
    }
    return $hex === '' ? '0' : $hex;
}

/** هگز → عدد ده‌دهی (رشته) */
function tonHexToDec($hex) {
    $hex = ltrim(strtolower(trim((string)$hex)), '0x');
    if ($hex === '' || !preg_match('/^[0-9a-f]+$/', $hex)) return '0';
    $dec = '0';
    for ($i = 0, $n = strlen($hex); $i < $n; $i++) {
        $d = hexdec($hex[$i]);
        // dec = dec*16 + d
        $carry = $d; $out = '';
        for ($j = strlen($dec) - 1; $j >= 0; $j--) {
            $cur   = (int)$dec[$j] * 16 + $carry;
            $out   = (string)($cur % 10) . $out;
            $carry = intdiv($cur, 10);
        }
        while ($carry > 0) { $out = (string)($carry % 10) . $out; $carry = intdiv($carry, 10); }
        $dec = ltrim($out, '0');
        if ($dec === '') $dec = '0';
    }
    return $dec;
}

/** ضرب عدد ده‌دهی رشته‌ای در ۱۰^۹ — تبدیل TON به nanoTON */
function tonToNano($ton) {
    $s = trim((string)$ton);
    if ($s === '' || !preg_match('/^\d+(\.\d+)?$/', $s)) return '0';
    [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');
    $frac = substr(str_pad($frac, 9, '0'), 0, 9);
    $out  = ltrim($int . $frac, '0');
    return $out === '' ? '0' : $out;
}

/** nanoTON → TON خوانا */
function nanoToTon($nano) {
    $s = ltrim((string)$nano, '0');
    if ($s === '') return '0';
    $s = str_pad($s, 10, '0', STR_PAD_LEFT);
    $int  = substr($s, 0, -9);
    $frac = rtrim(substr($s, -9), '0');
    return $frac === '' ? $int : $int . '.' . $frac;
}

// ============================================================
// 🧊 سلول (Cell) و BOC
// ============================================================

class TonCell
{
    public $bits = '';       // رشته '0'/'1'
    public $refs = [];       // آرایه TonCell

    public static function fromBits(TonBits $b) {
        $c = new self();
        $c->bits = $b->bits;
        return $c;
    }

    public function addRef(TonCell $c) { $this->refs[] = $c; return $this; }

    /** بیت‌ها به بایت، با بیت پرکننده اگر مضرب ۸ نباشد */
    public function dataBytes() {
        $bits = $this->bits;
        $rem  = strlen($bits) % 8;
        if ($rem !== 0) $bits .= '1' . str_repeat('0', 7 - $rem);
        $out = '';
        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }
        return $out;
    }

    /** دو بایت توصیف‌گر سلول */
    public function descriptors() {
        $d1 = count($this->refs);                       // سلول معمولی، سطح ۰
        $len = strlen($this->bits);
        $d2 = intdiv($len, 8) + intdiv($len + 7, 8);    // floor + ceil
        return chr($d1) . chr($d2);
    }

    /** عمق سلول — بیشترین فاصله تا برگ */
    public function depth() {
        $max = 0;
        foreach ($this->refs as $r) {
            $d = $r->depth() + 1;
            if ($d > $max) $max = $d;
        }
        return $max;
    }

    /** هش استاندارد سلول: sha256(توصیف‌گرها + داده + عمق رفرنس‌ها + هش رفرنس‌ها) */
    public function hash() {
        $repr = $this->descriptors() . $this->dataBytes();
        foreach ($this->refs as $r) $repr .= pack('n', $r->depth());
        foreach ($this->refs as $r) $repr .= $r->hash();
        return hash('sha256', $repr, true);
    }

    /** همه سلول‌ها به ترتیب توپولوژیک — ریشه اول، بدون تکرار */
    public function flatten() {
        $list = [];
        $seen = [];
        $walk = function (TonCell $c) use (&$walk, &$list, &$seen) {
            $h = $c->hash();
            if (isset($seen[$h])) return;
            $seen[$h] = true;
            $list[] = $c;
            foreach ($c->refs as $r) $walk($r);
        };
        $walk($this);
        return $list;
    }
}

// ---------- CRC ----------

/** CRC-32C (Castagnoli) — BOC از این استفاده می‌کند */
function tonCrc32c($data) {
    static $table = null;
    if ($table === null) {
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $crc = $i;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 1) ? (($crc >> 1) ^ 0x82F63B78) : ($crc >> 1);
            }
            $table[$i] = $crc & 0xFFFFFFFF;
        }
    }
    $crc = 0xFFFFFFFF;
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $crc = ($table[($crc ^ ord($data[$i])) & 0xFF] ^ ($crc >> 8)) & 0xFFFFFFFF;
    }
    return ($crc ^ 0xFFFFFFFF) & 0xFFFFFFFF;
}

/** CRC-16/XMODEM — چک‌سام آدرس‌های TON */
function tonCrc16($data) {
    $crc = 0;
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? ((($crc << 1) ^ 0x1021) & 0xFFFF) : (($crc << 1) & 0xFFFF);
        }
    }
    return $crc & 0xFFFF;
}

// ---------- BOC ----------

/** سریال‌سازی یک سلول به BOC (باینری) */
function tonBocSerialize(TonCell $root) {
    $cells = $root->flatten();
    $index = [];
    foreach ($cells as $i => $c) $index[$c->hash()] = $i;

    $count    = count($cells);
    $refBytes = max(1, (int)ceil((max(1, $count)) ? (strlen(decbin($count)) / 8) : 1));
    if ($refBytes < 1) $refBytes = 1;

    // بدنه سلول‌ها
    $body = '';
    foreach ($cells as $c) {
        $body .= $c->descriptors() . $c->dataBytes();
        foreach ($c->refs as $r) {
            $ri = $index[$r->hash()];
            $body .= substr(pack('N', $ri), 4 - $refBytes);
        }
    }

    $totalSize = strlen($body);
    $offBytes  = max(1, (int)ceil(strlen(decbin(max(1, $totalSize))) / 8));

    // سربرگ
    $out  = hex2bin('b5ee9c72');
    $flags = 0b01000000 | $refBytes;      // has_crc32c = 1، بدون ایندکس و کش
    $out .= chr($flags);
    $out .= chr($offBytes);
    $out .= substr(pack('N', $count), 4 - $refBytes);   // تعداد سلول‌ها
    $out .= substr(pack('N', 1), 4 - $refBytes);        // تعداد ریشه‌ها
    $out .= substr(pack('N', 0), 4 - $refBytes);        // absent
    $out .= substr(pack('N', $totalSize), 4 - $offBytes);
    $out .= substr(pack('N', 0), 4 - $refBytes);        // ایندکس ریشه = ۰
    $out .= $body;
    $out .= pack('V', tonCrc32c($out));                 // CRC little-endian

    return $out;
}

/** خواندن BOC (باینری) → سلول ریشه */
function tonBocParse($bin) {
    if (strlen($bin) < 6) throw new Exception('BOC خیلی کوتاه است');
    $p = 0;
    $magic = bin2hex(substr($bin, 0, 4)); $p = 4;
    if ($magic !== 'b5ee9c72') throw new Exception('امضای BOC نامعتبر: ' . $magic);

    $flags    = ord($bin[$p++]);
    $refBytes = $flags & 0b111;
    $hasIdx   = (bool)($flags & 0b10000000);
    $hasCrc   = (bool)($flags & 0b01000000);
    $offBytes = ord($bin[$p++]);

    $rd = function ($n) use ($bin, &$p) {
        $v = 0;
        for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($bin[$p++]);
        return $v;
    };

    $cellCount = $rd($refBytes);
    $rootCount = $rd($refBytes);
    $rd($refBytes);                 // absent
    $rd($offBytes);                 // مجموع حجم
    $rootIdx = [];
    for ($i = 0; $i < $rootCount; $i++) $rootIdx[] = $rd($refBytes);
    if ($hasIdx) $p += $cellCount * $offBytes;

    // خواندن خام سلول‌ها
    $raw = [];
    for ($i = 0; $i < $cellCount; $i++) {
        $d1 = ord($bin[$p++]);
        $d2 = ord($bin[$p++]);
        $refs = $d1 & 7;
        $dataLen = intdiv($d2, 2) + ($d2 % 2);
        $data = substr($bin, $p, $dataLen); $p += $dataLen;

        $bits = '';
        for ($k = 0; $k < strlen($data); $k++) $bits .= str_pad(decbin(ord($data[$k])), 8, '0', STR_PAD_LEFT);
        if ($d2 % 2) {                       // بیت پرکننده را بردار
            $bits = rtrim($bits, '0');
            $bits = substr($bits, 0, max(0, strlen($bits) - 1));
        }

        $rr = [];
        for ($k = 0; $k < $refs; $k++) $rr[] = $rd($refBytes);
        $raw[$i] = ['bits' => $bits, 'refs' => $rr];
    }

    // ساخت درخت از آخر به اول (رفرنس‌ها همیشه بعد از والدشان می‌آیند)
    $built = [];
    for ($i = $cellCount - 1; $i >= 0; $i--) {
        $c = new TonCell();
        $c->bits = $raw[$i]['bits'];
        foreach ($raw[$i]['refs'] as $ri) {
            if (!isset($built[$ri])) throw new Exception('ترتیب سلول‌های BOC نامعتبر است');
            $c->addRef($built[$ri]);
        }
        $built[$i] = $c;
    }
    return $built[$rootIdx[0] ?? 0];
}

function tonBocToBase64(TonCell $c) { return base64_encode(tonBocSerialize($c)); }
function tonBocFromBase64($b64) {
    $bin = base64_decode(strtr(trim((string)$b64), '-_', '+/'), true);
    if ($bin === false) throw new Exception('base64 نامعتبر');
    return tonBocParse($bin);
}

// ============================================================
// 📮 آدرس
// ============================================================

/** آدرس TON → ['wc' => int, 'hash' => 32 بایت خام] */
function tonParseAddress($addr) {
    $addr = trim((string)$addr);
    if ($addr === '') throw new Exception('آدرس خالی است');

    // شکل خام: 0:هگز۶۴
    if (preg_match('/^(-?\d+):([0-9a-fA-F]{64})$/', $addr, $m)) {
        return ['wc' => (int)$m[1], 'hash' => hex2bin(strtolower($m[2]))];
    }

    // شکل base64url دوستانه
    $bin = base64_decode(strtr($addr, '-_', '+/'), true);
    if ($bin === false || strlen($bin) !== 36) throw new Exception('آدرس نامعتبر: ' . mb_substr($addr, 0, 20));

    $body = substr($bin, 0, 34);
    $crc  = unpack('n', substr($bin, 34, 2))[1];
    if (tonCrc16($body) !== $crc) throw new Exception('چک‌سام آدرس نمی‌خواند');

    $wc = ord($body[1]);
    if ($wc === 0xFF) $wc = -1;
    return ['wc' => $wc, 'hash' => substr($body, 2, 32)];
}

/** ['wc','hash'] → آدرس base64url دوستانه */
function tonAddressToString($wc, $hash, $bounceable = true, $testnet = false) {
    $tag = $bounceable ? 0x11 : 0x51;
    if ($testnet) $tag |= 0x80;
    $body = chr($tag) . chr($wc < 0 ? 0xFF : $wc) . $hash;
    $full = $body . pack('n', tonCrc16($body));
    return rtrim(strtr(base64_encode($full), '+/', '-_'), '=');
}

/** آدرس را داخل بیت‌ها می‌نویسد (MsgAddressInt) */
function tonWriteAddress(TonBits $b, $addr) {
    $a = tonParseAddress($addr);
    $b->writeBits('10');            // addr_std
    $b->writeBit(0);                // anycast: nothing
    $b->writeUint($a['wc'] & 0xFF, 8);
    $b->writeBytes($a['hash']);
    return $b;
}

// ============================================================
// 🔑 کلید از عبارت بازیابی
// ============================================================

/**
 * ۲۴ کلمه TON → جفت کلید Ed25519.
 * روش استاندارد TON: PBKDF2-HMAC-SHA512 با نمک "TON default seed".
 */
/**
 * Ed25519 لازم است و روی همه‌ی هاست‌ها روشن نیست.
 * اگر افزونه‌ی sodium نبود، دنبال polyfill خالص PHP می‌گردیم
 * (کتابخانه‌ی sodium_compat که همین نام توابع را تعریف می‌کند).
 *
 * برگشت: [true, ''] یا [false, 'دلیل و راه حل']
 */
function tonCryptoReady() {
    static $done = false;
    if (!$done) {
        $done = true;
        if (!function_exists('sodium_crypto_sign_seed_keypair')) {
            foreach ([
                __DIR__ . '/sodium_compat/autoload.php',
                __DIR__ . '/vendor/autoload.php',
                __DIR__ . '/lib/sodium_compat/autoload.php',
            ] as $f) {
                if (is_file($f)) { @require_once $f; break; }
            }
        }
    }

    foreach (['sodium_crypto_sign_seed_keypair', 'sodium_crypto_sign_detached',
              'sodium_crypto_sign_publickey', 'sodium_crypto_sign_secretkey'] as $fn) {
        if (!function_exists($fn)) return [false, tonCryptoHelp()];
    }
    if (!function_exists('hash_pbkdf2') || !in_array('sha512', hash_algos(), true))
        return [false, 'افزونه‌ی hash با sha512 روی این هاست نیست — بدون آن کلید ولت ساخته نمی‌شود.'];

    return [true, ''];
}

function tonCryptoHelp() {
    return "افزونه‌ی <b>sodium</b> روی این هاست روشن نیست.\n" .
           "بدون آن امضای تراکنش TON ممکن نیست (بقیه‌ی ربات کار می‌کند).\n\n" .
           "<b>راه اول — روشن کردنش (ساده‌تر):</b>\n" .
           "در پنل هاست (cPanel یا DirectAdmin) دنبال «Select PHP Version» یا\n" .
           "«PHP Extensions» بگردید، تیک <code>sodium</code> را بزنید و ذخیره کنید.\n" .
           "این افزونه از PHP 7.2 همراه خود PHP می‌آید، فقط خاموش است.\n" .
           "اگر پیدایش نکردید، از پشتیبانی هاست بخواهید <code>ext-sodium</code> را فعال کند.\n\n" .
           "<b>راه دوم — بدون دخالت هاست:</b>\n" .
           "کتابخانه‌ی <code>sodium_compat</code> را از\n" .
           "<code>github.com/paragonie/sodium_compat</code> دانلود کنید و پوشه‌اش را\n" .
           "کنار فایل‌های ربات با نام <code>sodium_compat</code> بگذارید.\n" .
           "ربات خودش پیدایش می‌کند و از همان استفاده می‌کند.";
}

function tonKeyFromMnemonic($words, $password = '') {
    [$ok, $why] = tonCryptoReady();
    if (!$ok) throw new Exception($why);

    if (is_string($words)) $words = preg_split('/\s+/u', trim($words));
    $words = array_values(array_filter(array_map(fn($w) => strtolower(trim($w)), $words), fn($w) => $w !== ''));
    if (count($words) !== 24) throw new Exception('عبارت بازیابی باید ۲۴ کلمه باشد (الان ' . count($words) . ')');

    $phrase = implode(' ', $words);

    // روش رسمی TON دو مرحله دارد و ترتیبش مهم است:
    //   ۱. entropy = HMAC-SHA512 با کلیدِ «عبارت ۲۴ کلمه‌ای» روی داده‌ی «رمز»
    //   ۲. seed    = PBKDF2-HMAC-SHA512 روی همان entropy با نمک "TON default seed"
    // (نسخه‌ی قبلی مستقیم PBKDF2 می‌زد و رمز را به نمک می‌چسباند — کلید غلط درمی‌آمد.)
    $entropy = hash_hmac('sha512', (string)$password, $phrase, true);
    $seed    = hash_pbkdf2('sha512', $entropy, 'TON default seed', 100000, 64, true);
    $kp      = sodium_crypto_sign_seed_keypair(substr($seed, 0, 32));

    return [
        'public'  => sodium_crypto_sign_publickey($kp),
        'secret'  => sodium_crypto_sign_secretkey($kp),
    ];
}

// ============================================================
// 👛 قرارداد ولت v4R2
// ============================================================

// ============================================================
// 👛 قراردادهای ولت — کد رسمی، نه حدسی
// ============================================================
//
// این سه رشته کد قرارداد ولت‌اند و مستقیم از کتابخانه‌ی رسمی
// @ton/ton برداشته شده‌اند (src/wallets/…). درستی‌شان حدس زده نشده:
// تست‌ها آدرس ساخته‌شده را با آدرس‌های معلومِ همان کتابخانه می‌سنجند.
//
//   v3R2  →  EQA0D_5WdusaCB-SpnoE6l5TzdBmgOkzTcXrdh0px6g3zJSk
//   v4R2  →  EQDnBF4JTFKHTYjulEJyNd4dstLGH1m51UrLdu01_tw4z2Au
//   (هر دو با کلیدِ ed25519 از seed = sha256("v4-treasure"))

function tonWalletCodes() {
    return [
        'v3r2' => ['enc' => 'b64', 'code' => 'te6cckEBAQEAcQAA3v8AIN0gggFMl7ohggEznLqxn3Gw7UTQ0x/THzHXC//jBOCk8mCDCNcYINMf0x/TH/gjE7vyY+1E0NMf0x/T/9FRMrryoVFEuvKiBPkBVBBV+RDyo/gAkyDXSpbTB9QC+wDo0QGkyMsfyx/L/8ntVBC9ba0=', 'name' => 'v3R2'],
        'v4r2' => ['enc' => 'b64', 'code' => 'te6ccgECFAEAAtQAART/APSkE/S88sgLAQIBIAIDAgFIBAUE+PKDCNcYINMf0x/THwL4I7vyZO1E0NMf0x/T//QE0VFDuvKhUVG68qIF+QFUEGT5EPKj+AAkpMjLH1JAyx9SMMv/UhD0AMntVPgPAdMHIcAAn2xRkyDXSpbTB9QC+wDoMOAhwAHjACHAAuMAAcADkTDjDQOkyMsfEssfy/8QERITAubQAdDTAyFxsJJfBOAi10nBIJJfBOAC0x8hghBwbHVnvSKCEGRzdHK9sJJfBeAD+kAwIPpEAcjKB8v/ydDtRNCBAUDXIfQEMFyBAQj0Cm+hMbOSXwfgBdM/yCWCEHBsdWe6kjgw4w0DghBkc3RyupJfBuMNBgcCASAICQB4AfoA9AQw+CdvIjBQCqEhvvLgUIIQcGx1Z4MesXCAGFAEywUmzxZY+gIZ9ADLaRfLH1Jgyz8gyYBA+wAGAIpQBIEBCPRZMO1E0IEBQNcgyAHPFvQAye1UAXKwjiOCEGRzdHKDHrFwgBhQBcsFUAPPFiP6AhPLassfyz/JgED7AJJfA+ICASAKCwBZvSQrb2omhAgKBrkPoCGEcNQICEekk30pkQzmkD6f+YN4EoAbeBAUiYcVnzGEAgFYDA0AEbjJftRNDXCx+AA9sp37UTQgQFA1yH0BDACyMoHy//J0AGBAQj0Cm+hMYAIBIA4PABmtznaiaEAga5Drhf/AABmvHfaiaEAQa5DrhY/AAG7SB/oA1NQi+QAFyMoHFcv/ydB3dIAYyMsFywIizxZQBfoCFMtrEszMyXP7AMhAFIEBCPRR8qcCAHCBAQjXGPoA0z/IVCBHgQEI9FHyp4IQbm90ZXB0gBjIywXLAlAGzxZQBPoCFMtqEssfyz/Jc/sAAgBsgQEI1xj6ANM/MFIkgQEI9Fnyp4IQZHN0cnB0gBjIywXLAlAFzxZQA/oCE8tqyx8Syz/Jc/sAAAr0AMntVA==', 'name' => 'v4R2'],
        'v5r1' => ['enc' => 'hex', 'code' => 'b5ee9c7241021401000281000114ff00f4a413f4bcf2c80b01020120020d020148030402dcd020d749c120915b8f6320d70b1f2082106578746ebd21821073696e74bdb0925f03e082106578746eba8eb48020d72101d074d721fa4030fa44f828fa443058bd915be0ed44d0810141d721f4058307f40e6fa1319130e18040d721707fdb3ce03120d749810280b99130e070e2100f020120050c020120060902016e07080019adce76a2684020eb90eb85ffc00019af1df6a2684010eb90eb858fc00201480a0b0017b325fb51341c75c875c2c7e00011b262fb513435c280200019be5f0f6a2684080a0eb90fa02c0102f20e011e20d70b1f82107369676ebaf2e08a7f0f01e68ef0eda2edfb218308d722028308d723208020d721d31fd31fd31fed44d0d200d31f20d31fd3ffd70a000af90140ccf9109a28945f0adb31e1f2c087df02b35007b0f2d0845125baf2e0855036baf2e086f823bbf2d0882292f800de01a47fc8ca00cb1f01cf16c9ed542092f80fde70db3cd81003f6eda2edfb02f404216e926c218e4c0221d73930709421c700b38e2d01d72820761e436c20d749c008f2e09320d74ac002f2e09320d71d06c712c2005230b0f2d089d74cd7393001a4e86c128407bbf2e093d74ac000f2e093ed55e2d20001c000915be0ebd72c08142091709601d72c081c12e25210b1e30f20d74a111213009601fa4001fa44f828fa443058baf2e091ed44d0810141d718f405049d7fc8ca0040048307f453f2e08b8e14038307f45bf2e08c22d70a00216e01b3b0f2d090e2c85003cf1612f400c9ed54007230d72c08248e2d21f2e092d200ed44d0d2005113baf2d08f54503091319c01810140d721d70a00f2e08ee2c8ca0058cf16c9ed5493f2c08de20010935bdb31e1d74cd0b4d6c35e', 'name' => 'W5 (v5R1)'],
    ];
}

/** فهرست نسخه‌های پشتیبانی‌شده — برای منوی پنل */
function tonWalletVersionList() {
    $out = [];
    foreach (tonWalletCodes() as $k => $v) $out[$k] = $v['name'];
    return $out;
}

/** کد قرارداد یک نسخه، به شکل سلول */
function tonWalletCode($version) {
    $all = tonWalletCodes();
    $version = strtolower(trim((string)$version));
    if (!isset($all[$version])) throw new Exception('نسخه ولت ناشناخته: ' . $version);
    $c = $all[$version];
    return $c['enc'] === 'hex'
        ? tonBocParse(hex2bin($c['code']))
        : tonBocFromBase64($c['code']);
}

/**
 * شناسه‌ی ولت (subwallet_id در v3/v4، wallet_id در v5).
 *
 *  v3/v4 : 698983191 + workchain
 *  v5r1  : networkGlobalId XOR int32(1 | wc(8) | version(8) | subwallet(15))
 *          که برای mainnet/workchain 0 برابر 2147483409 می‌شود.
 */
function tonWalletId($version, $wc = 0, $subwallet = 0, $networkGlobalId = -239) {
    $version = strtolower(trim((string)$version));
    if ($version !== 'v5r1') return 698983191 + (int)$wc;

    $bits = '1'
          . str_pad(decbin(((int)$wc) & 0xFF), 8, '0', STR_PAD_LEFT)
          . str_pad(decbin(0), 8, '0', STR_PAD_LEFT)          // walletVersion v5r1 = 0
          . str_pad(decbin(((int)$subwallet) & 0x7FFF), 15, '0', STR_PAD_LEFT);
    $ctx = bindec($bits) & 0xFFFFFFFF;                        // به‌شکل uint32
    $net = ((int)$networkGlobalId) & 0xFFFFFFFF;
    return ($net ^ $ctx) & 0xFFFFFFFF;
}

/** سلول داده‌ی اولیه‌ی ولت — چیدمانش برای هر نسخه فرق دارد */
function tonWalletData($publicKey, $version, $wc = 0, $subwallet = 0, $networkGlobalId = -239) {
    if (strlen((string)$publicKey) !== 32) throw new Exception('کلید عمومی باید ۳۲ بایت باشد');
    $version = strtolower(trim((string)$version));
    $wid = tonWalletId($version, $wc, $subwallet, $networkGlobalId);

    $b = new TonBits();
    if ($version === 'v5r1') {
        $b->writeBit(1);                 // امضا مجاز است
        $b->writeUint(0, 32);            // seqno
        $b->writeUint($wid, 32);         // wallet_id
        $b->writeBytes($publicKey);
        $b->writeBit(0);                 // دیکشنری افزونه‌ها، خالی
    } else {
        $b->writeUint(0, 32);            // seqno
        $b->writeUint($wid, 32);         // subwallet_id
        $b->writeBytes($publicKey);
        if ($version === 'v4r2') $b->writeBit(0);   // v4 یک بیت افزونه دارد، v3 ندارد
    }
    return TonCell::fromBits($b);
}

/** StateInit = split_depth(0) special(0) code(1) data(1) library(0) + دو رفرنس */
function tonWalletStateInit($publicKey, $version, $wc = 0, $subwallet = 0, $networkGlobalId = -239) {
    $si = new TonBits();
    $si->writeBit(0)->writeBit(0)->writeBit(1)->writeBit(1)->writeBit(0);
    $state = TonCell::fromBits($si);
    $state->addRef(tonWalletCode($version));
    $state->addRef(tonWalletData($publicKey, $version, $wc, $subwallet, $networkGlobalId));
    return $state;
}

/** آدرس ولت از روی کلید عمومی و نسخه */
function tonWalletAddress($publicKey, $version = 'v4r2', $wc = 0, $subwallet = 0,
                          $bounceable = true, $networkGlobalId = -239) {
    $state = tonWalletStateInit($publicKey, $version, $wc, $subwallet, $networkGlobalId);
    return tonAddressToString($wc, $state->hash(), $bounceable, false);
}

/**
 * همه‌ی آدرس‌هایی که یک کلید می‌تواند داشته باشد — سه نسخه × دو ورک‌چین.
 * برگشت: [ ['version'=>..,'name'=>..,'wc'=>..,'wallet_id'=>..,'address'=>..], … ]
 */
function tonWalletAllAddresses($publicKey, $wcList = [0], $subwallet = 0) {
    $out = [];
    foreach (tonWalletCodes() as $ver => $meta) {
        foreach ($wcList as $wc) {
            try {
                $out[] = [
                    'version'   => $ver,
                    'name'      => $meta['name'],
                    'wc'        => (int)$wc,
                    'wallet_id' => tonWalletId($ver, $wc, $subwallet),
                    'address'   => tonWalletAddress($publicKey, $ver, $wc, $subwallet),
                ];
            } catch (Throwable $e) { /* نسخه‌ای که ساخته نشد را رد می‌کنیم */ }
        }
    }
    return $out;
}

/** هش کد هر نسخه — برای شناختن نسخه از روی قرارداد روی زنجیره */
function tonCodeHashes() {
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (tonWalletCodes() as $k => $v) {
            try { $m[bin2hex(tonWalletCode($k)->hash())] = $k; } catch (Throwable $e) {}
        }
    }
    return $m;
}

/**
 * 🔑 تایید آفلاین — بدون هیچ تماس با شبکه.
 *
 * آدرسی که کاربر داده را با آدرس‌هایی که خودِ عبارت بازیابی می‌سازد
 * می‌سنجیم. اگر یکی بود، مالکیت ثابت است: فقط دارنده‌ی آن عبارت
 * می‌تواند آدرسی بسازد که hash قرارداد و کلیدش با آن بخواند.
 *
 * برگشت: ['ok'=>bool, 'version'=>.., 'wc'=>.., 'name'=>.., 'candidates'=>[…]]
 */
function tonMatchAddressOffline($publicKey, $address, $subwallet = 0) {
    $target = null;
    try { $target = tonParseAddress($address); } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'آدرس خوانده نشد: ' . $e->getMessage(), 'candidates' => []];
    }

    $cands = tonWalletAllAddresses($publicKey, [0, -1], $subwallet);
    foreach ($cands as $c) {
        $p = tonParseAddress($c['address']);
        // مقایسه روی خودِ hash و workchain — نه روی رشته، چون یک آدرس
        // چند نوشتار دارد (EQ/UQ، bounceable یا نه، base64 یا خام).
        if ($p['hash'] === $target['hash'] && (int)$p['wc'] === (int)$target['wc'])
            return ['ok' => true, 'version' => $c['version'], 'name' => $c['name'],
                    'wc' => $c['wc'], 'wallet_id' => $c['wallet_id'], 'candidates' => $cands];
    }
    return ['ok' => false, 'candidates' => $cands];
}

/**
 * تأیید ولت — به‌جای حدس زدن کد قرارداد، کلید عمومی روی زنجیره را می‌خوانیم
 * و با کلیدی که از عبارت بازیابی ساخته‌ایم می‌سنجیم. اگر یکی بود، این عبارت
 * واقعاً صاحب همین آدرس است و امضای ما را ولت می‌پذیرد.
 */
function tonVerifyWallet($base, $address, $publicKey, $apiKey) {
    // 🔑 اول آفلاین: اگر آدرس دقیقا یکی از آدرس‌های همین عبارت باشد،
    // مالکیت همان‌جا ثابت است و اصلا سراغ شبکه نمی‌رویم — نه محدودیت
    // نرخ، نه قطعی، نه انتظار.
    $off = tonMatchAddressOffline($publicKey, $address);
    if (!empty($off['ok']))
        return ['ok' => true, 'offline' => true, 'version' => $off['version'],
                'name' => $off['name'], 'wc' => $off['wc'], 'wallet_id' => $off['wallet_id']];

    // هر پیام شکست با وضعیت واقعی آدرس همراه می‌شود تا حدس نزنیم
    $withState = function ($res) use ($base, $address, $apiKey) {
        if (!empty($res['ok'])) return $res;
        $st  = tonAccountState($base, $address, $apiKey);
        $err = (string)($res['error'] ?? '');
        $res['state'] = $st;

        // اگر آدرس واقعا فعال است، حرف «deploy نشده» غلط و گمراه‌کننده است —
        // پس به‌جای اضافه کردن، جایش را می‌گیریم.
        if (($st['state'] ?? '') === 'active' && !empty($st['has_code'])
            && str_contains($err, 'فعال (deploy) نشده')) {
            $err = "این آدرس روی شبکه <b>فعال است و قرارداد دارد</b>، ولی متد " .
                   "<code>get_public_key</code> را جواب نداد.\n\n" .
                   "یعنی قرارداد این آدرس یک <b>ولت استاندارد نیست</b> — مثلا multisig، " .
                   "قرارداد سفارشی، یا نسخه‌ای که این متد را ندارد.\n\n" .
                   "<b>راه حل:</b> در کیف پول‌تان اگر چند ولت دارید (v4R2 و W5)، " .
                   "آدرسِ همانی را بردارید که این ۲۴ کلمه مال اوست — معمولا در " .
                   "تنظیمات کیف پول زیر «Wallet version» می‌شود بینشان جابه‌جا شد و " .
                   "آدرس هرکدام را دید.";
        }

        $res['error'] = $err . tonStateText($st);
        return $res;
    };

    // مسیر runGetMethod نسبت به همان base داده می‌شود، نه با پیشوند ثابت
    [$r, $err] = tonApiCallRetry($base, '/runGetMethod', 'POST', [
        'address' => $address, 'method' => 'get_public_key', 'stack' => [],
    ], $apiKey);
    if (!$r) {
        if (tonIsRateLimited($err))
            return ['ok' => false, 'rate' => true, 'raw' => (string)$err, 'error' => tonRateText()];
        return $withState(['ok' => false, 'error' => 'پاسخ شبکه نامعتبر: ' . mb_substr((string)$err, 0, 200),
                           'raw' => (string)$err]);
    }
    if (isset($r['ok']) && !$r['ok']) return $withState(['ok' => false, 'error' => 'شبکه خطا داد: ' . mb_substr(json_encode($r, 320), 0, 160)]);
    // خروج غیرصفر یعنی قرارداد چنین متدی ندارد یا اصلا اجرا نشد
    $exit  = $r['result']['exit_code'] ?? null;
    $stack = $r['result']['stack'] ?? [];

    if (is_numeric($exit) && (int)$exit !== 0) {
        return $withState(['ok' => false, 'raw' => mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 600),
                'error' => tonNotDeployedText((int)$exit)]);
    }

    if (!isset($stack[0])) {
        return $withState(['ok' => false, 'raw' => mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 600),
                'error' => tonNotDeployedText(is_numeric($exit) ? (int)$exit : null)]);
    }

    $onchain = tonStackHex($stack[0]);
    if ($onchain === null)
        return $withState(['ok' => false, 'raw' => json_encode($stack, JSON_UNESCAPED_UNICODE),
                'error' => 'پاسخ زنجیره خوانده نشد: ' . mb_substr(json_encode($stack[0], JSON_UNESCAPED_UNICODE), 0, 200)]);

    // 🛑 شبکه گاهی به‌جای نتیجه، «شناسه‌ی خود متد» را پس می‌دهد — یعنی
    // متد اجرا نشده چون قراردادی روی این آدرس نیست. بدون این بررسی،
    // آن عدد کوچک با کلید مقایسه می‌شد و پیام گمراه‌کننده‌ی
    // «عبارت بازیابی نمی‌خواند» می‌آمد.
    $methodId = (tonCrc16('get_public_key') & 0xffff) | 0x10000;
    if (hexdec($onchain) === $methodId && strlen($onchain) <= 8)
        return $withState(['ok' => false, 'raw' => json_encode($stack, JSON_UNESCAPED_UNICODE),
                'error' => tonNotDeployedText(null)]);

    // کلید عمومی ۳۲ بایت است؛ بعد از حذف صفرهای ابتدایی هم نباید
    // خیلی کوتاه‌تر از ۶۴ نویسه باشد. عدد کوتاه یعنی این کلید نیست.
    if (strlen($onchain) < 48)
        return $withState(['ok' => false, 'raw' => json_encode($stack, JSON_UNESCAPED_UNICODE),
                'error' => "چیزی که از این آدرس برگشت کلید عمومی نیست — فقط " . strlen($onchain) .
                           " نویسه است، در حالی که کلید ۶۴ نویسه دارد.\n\n" . tonNotDeployedText(null)]);

    $mine = ltrim(bin2hex($publicKey), '0');
    if ($onchain !== $mine) {
        // 🎯 مفیدترین کاری که می‌شود کرد: بگوییم این ۲۴ کلمه به کدام آدرس می‌رسند.
        // کد و داده‌ی همین آدرس را از زنجیره می‌گیریم، پس هیچ نسخه‌ای حدس زده نمی‌شود.
        $st  = tonAccountState($base, $address, $apiKey);
        $wc  = 0;
        try { $wc = tonParseAddress($address)['wc']; } catch (Throwable $e) {}
        $derived = tonDeriveSameVersion($st['code_b64'] ?? '', $st['data_b64'] ?? '', $onchain, $publicKey, $wc);
        $hint = $derived === null ? '' :
            "\n\n🎯 <b>این ۲۴ کلمه به این آدرس می‌رسند:</b>\n<code>" . $derived . "</code>\n" .
            "<i>(با همان نسخه‌ی ولتی که آدرس بالا دارد)</i>\n" .
            "اگر این آدرس را می‌شناسید، همین را در فیلد آدرس بگذارید.";

        return $withState(['ok' => false, 'raw' => json_encode($stack, JSON_UNESCAPED_UNICODE),
            'derived' => $derived, 'error' =>
            "عبارت بازیابی با این آدرس نمی‌خواند.\n\n" .
            'کلید این آدرس روی زنجیره: <code>' . mb_substr($onchain, 0, 16) . "…</code>\n" .
            'کلید عبارت بازیابی شما: <code>' . mb_substr($mine, 0, 16) . "…</code>\n\n" .
            "یعنی آدرس و ۲۴ کلمه مال دو ولت متفاوت‌اند. دو علت رایج دارد:\n\n" .
            "۱. آدرس از ولت دیگری کپی شده. آدرس را از <b>همان</b> کیف پولی بردارید که\n" .
            "این ۲۴ کلمه را از آن گرفته‌اید — دکمه‌ی دریافت (Receive) و کپی آدرس.\n\n" .
            "۲. آن ولت هنگام ساخت <b>رمز عبور</b> داشته (بعضی کیف پول‌ها می‌پرسند).\n" .
            "با رمز، همان ۲۴ کلمه کلید دیگری می‌سازد.\n" .
            "اگر رمز دارید، همان را در پنل کنار ۲۴ کلمه وارد کنید." . $hint]);
    }
    return ['ok' => true];
}

/**
 * وضعیت واقعی یک آدرس روی زنجیره.
 * برگشت: ['state'=>'active|uninitialized|frozen|?', 'balance'=>nanoton, 'code'=>hash کوتاه]
 * بدون این، «چرا تایید نشد» حدس می‌ماند: آدرس فعال است یا اصلا قراردادی ندارد؟
 */
function tonAccountState($base, $address, $apiKey) {
    [$r, $err] = tonApiCallRetry($base, '/getAddressInformation?address=' . rawurlencode($address), 'GET', null, $apiKey);
    if (!$r) return ['state' => '?', 'error' => (string)$err];
    $res = $r['result'] ?? [];
    $code = (string)($res['code'] ?? '');
    return [
        'state'    => (string)($res['state'] ?? '?'),
        'balance'  => (string)($res['balance'] ?? '0'),
        'code'     => $code === '' ? '' : substr(hash('sha256', $code), 0, 16),
        'has_code' => $code !== '',
        'code_b64' => $code,
        'data_b64' => (string)($res['data'] ?? ''),
    ];
}

/**
 * آدرسی که یک کلید عمومی، با «همان نوع ولتِ» یک آدرس موجود، به آن می‌رسد.
 *
 * هیچ کد قراردادی حدس زده نمی‌شود: کد و داده‌ی همان آدرس را از زنجیره
 * می‌گیریم، جای کلید عمومی را داخل داده پیدا می‌کنیم (همان‌جا که کلیدِ
 * روی زنجیره نشسته)، کلید خودمان را می‌گذاریم، و آدرس را می‌سازیم.
 * پس نتیجه برای هر نسخه‌ی ولتی درست است، حتی نسخه‌ای که نمی‌شناسیم.
 *
 * برگشت: آدرس رشته‌ای، یا null اگر نشد.
 */
function tonDeriveSameVersion($codeB64, $dataB64, $onchainPubHex, $myPublicKey, $wc = 0) {
    $codeB64 = trim((string)$codeB64);
    $dataB64 = trim((string)$dataB64);
    if ($codeB64 === '' || $dataB64 === '') return null;

    try {
        $code = tonBocFromBase64($codeB64);
        $data = tonBocFromBase64($dataB64);
    } catch (Throwable $e) { return null; }

    // کلید روی زنجیره را به بیت تبدیل کن تا جایش را در داده پیدا کنیم
    $pubHex = str_pad(ltrim((string)$onchainPubHex, '0'), 64, '0', STR_PAD_LEFT);
    if (!preg_match('/^[0-9a-f]{64}$/', $pubHex)) return null;
    $needle = '';
    foreach (str_split($pubHex) as $c) $needle .= str_pad(decbin(hexdec($c)), 4, '0', STR_PAD_LEFT);

    $mine = '';
    foreach (str_split(bin2hex($myPublicKey)) as $c) $mine .= str_pad(decbin(hexdec($c)), 4, '0', STR_PAD_LEFT);

    // بعضی ولت‌ها کلید را داخل سلول فرزند می‌گذارند، نه در خودِ داده.
    // پس همه‌ی درخت را می‌گردیم — نه فقط ریشه را.
    $replace = function (TonCell $c) use (&$replace, $needle, $mine) {
        $pos = strpos($c->bits, $needle);
        $new = new TonCell();
        $new->bits = $pos === false ? $c->bits
                   : substr($c->bits, 0, $pos) . $mine . substr($c->bits, $pos + 256);
        $found = $pos !== false;
        foreach ($c->refs as $r) {
            [$child, $f] = $replace($r);
            $new->refs[] = $child;
            $found = $found || $f;
        }
        return [$new, $found];
    };

    [$newData, $found] = $replace($data);
    if (!$found) return null;                 // کلید در هیچ سلولی پیدا نشد

    // StateInit: split_depth(0) special(0) code(1) data(1) library(0)
    $si = new TonBits();
    $si->writeBit(0)->writeBit(0)->writeBit(1)->writeBit(1)->writeBit(0);
    $state = TonCell::fromBits($si);
    $state->addRef($code)->addRef($newData);

    return tonAddressToString($wc, $state->hash(), false, false);
}

/** توضیح وضعیت آدرس، به زبان آدمیزاد */
function tonStateText($st) {
    if (!is_array($st)) return '';
    $s = (string)($st['state'] ?? '?');
    $bal = isset($st['balance']) ? nanoToTon((string)$st['balance']) : '?';
    $line = "\n\n<b>وضعیت این آدرس روی زنجیره:</b>\n";
    if ($s === 'active')          $line .= "• فعال ✅ · موجودی " . $bal . " TON\n";
    elseif ($s === 'uninitialized') $line .= "• هنوز فعال نشده (uninitialized) · موجودی " . $bal . " TON\n";
    elseif ($s === 'frozen')      $line .= "• منجمد (frozen) · موجودی " . $bal . " TON\n";
    else                          $line .= "• نامشخص" . (isset($st['error']) ? ' — ' . mb_substr((string)$st['error'], 0, 80) : '') . "\n";
    if (!empty($st['code'])) $line .= "• اثر قرارداد: <code>" . $st['code'] . "</code>\n";
    return $line;
}

/** پیام محدودیت نرخ — مشکل از تنظیمات شما نیست */
function tonRateText() {
    return "🚦 <b>شبکه TON محدودیت درخواست گذاشت (کد ۴۲۹).</b>\n\n" .
           "این یعنی سرویس toncenter رایگان است و بیش از حدش پرسیدیم — " .
           "<b>هیچ ربطی به درست یا غلط بودن آدرس و عبارت بازیابی شما ندارد.</b>\n\n" .
           "<b>راه حل (یک دقیقه):</b>\n" .
           "۱. در تلگرام به <code>@tonapibot</code> پیام بدهید\n" .
           "۲. یک کلید API رایگان بگیرید (mainnet)\n" .
           "۳. همان کلید را در فیلد «کلید API شبکه» بگذارید\n\n" .
           "با کلید، محدودیت برداشته می‌شود و همه‌چیز روان کار می‌کند.\n" .
           "بدون کلید هم می‌شود، ولی باید بین هر تلاش چند ثانیه صبر کنید.";
}

/** پیام «ولت هنوز روی زنجیره ننشسته» — رایج‌ترین علت شکست تایید */
function tonNotDeployedText($exitCode = null) {
    return "این آدرس هنوز روی شبکه <b>فعال (deploy) نشده</b>" .
           ($exitCode !== null ? ' (کد خروج ' . (int)$exitCode . ')' : '') . ".\n\n" .
           "ولت تازه‌ای که فقط ساخته‌اید، تا وقتی یک تراکنش <b>از آن</b> بیرون نرود\n" .
           "روی زنجیره وجود ندارد — پس کلید عمومی‌اش هم خوانده نمی‌شود.\n" .
           "<i>فقط واریز کردن به آن کافی نیست.</i>\n\n" .
           "<b>راه حل:</b>\n" .
           "۱. کمی TON به این ولت بفرستید (اگر خالی است)\n" .
           "۲. از داخل همان کیف پول، یک مبلغ خیلی کوچک به هر آدرسی <b>بفرستید</b>\n" .
           "۳. چند ثانیه صبر کنید و دوباره «تایید مالکیت» را بزنید\n\n" .
           "بعد از آن تراکنش، قرارداد ولت روی زنجیره می‌نشیند و تایید انجام می‌شود.";
}

/**
 * یک خانه‌ی stack پاسخ toncenter → هگز بدون صفر ابتدایی، یا null.
 * پیاده‌سازی‌های مختلف عدد را جور دیگری برمی‌گردانند: «0x…»، هگز خالی،
 * عدد ده‌دهی، یا حتی یک شیء تو در تو. همه را می‌پذیریم چون نپذیرفتنشان
 * یعنی کاربر بی‌دلیل پیام «نمی‌خواند» می‌گیرد.
 */
function tonStackHex($entry) {
    $v = null;
    if (is_array($entry)) {
        // شکل ["num","0x…"]
        if (isset($entry[1]) && is_scalar($entry[1])) $v = (string)$entry[1];
        // شکل {"number":{"number":"…"}} یا {"value":"…"}
        elseif (isset($entry['number']['number'])) $v = (string)$entry['number']['number'];
        elseif (isset($entry['number']) && is_scalar($entry['number'])) $v = (string)$entry['number'];
        elseif (isset($entry['value']) && is_scalar($entry['value'])) $v = (string)$entry['value'];
    } elseif (is_scalar($entry)) {
        $v = (string)$entry;
    }
    if ($v === null) return null;

    $v = strtolower(trim($v));
    if ($v === '') return null;

    // فقط رقم بودن مبهم است: کلید ۳۲ بایتی به هگز ۶۴ نویسه و به ده‌دهی
    // ۷۷ رقم می‌شود، و هر دو با [0-9a-f] می‌خوانند. چون toncenter هگز را
    // همیشه با 0x می‌دهد، رشته‌ی تماما رقمی را ده‌دهی می‌خوانیم.
    if (str_starts_with($v, '0x')) {
        $hex = substr($v, 2);
    } elseif (ctype_digit($v)) {
        $hex = tonDecToHex($v);          // ده‌دهی
    } elseif (preg_match('/^[0-9a-f]+$/', $v)) {
        $hex = $v;                       // هگز خالی (حتما حرف a تا f دارد)
    } else {
        return null;
    }

    if (!preg_match('/^[0-9a-f]*$/', $hex)) return null;
    $hex = ltrim($hex, '0');
    return $hex === '' ? '0' : $hex;
}

/**
 * ساخت پیام داخلی (MessageRelaxed) از یک پیام مارکت‌اپ.
 * $msg = ['address'=>..., 'amount'=>nanoton رشته, 'payload'=>base64|'', 'stateInit'=>base64|'']
 */
function tonInternalMessage($msg) {
    $b = new TonBits();
    $b->writeBit(0);        // int_msg_info$0
    $b->writeBit(1);        // ihr_disabled
    $b->writeBit(!empty($msg['bounce'] ?? true) ? 1 : 0);   // bounce
    $b->writeBit(0);        // bounced
    $b->writeBits('00');    // src: addr_none
    tonWriteAddress($b, (string)($msg['address'] ?? $msg['to'] ?? ''));
    $b->writeCoins((string)($msg['amount'] ?? $msg['value'] ?? '0'));
    $b->writeBit(0);        // بدون ارز اضافه
    $b->writeCoins('0');    // ihr_fee
    $b->writeCoins('0');    // fwd_fee
    $b->writeUint(0, 64);   // created_lt
    $b->writeUint(0, 32);   // created_at

    $cell    = TonCell::fromBits($b);
    $payload = trim((string)($msg['payload'] ?? ''));
    // اگر فقط متن یادداشت داده شده، خودمان بدنه‌ی کامنت می‌سازیم
    if ($payload === '' && trim((string)($msg['comment'] ?? '')) !== '') {
        $cb = new TonBits();
        $cb->writeUint(0, 32);                       // op = کامنت متنی
        $cb->writeBytes(substr((string)$msg['comment'], 0, 100));
        $payload = tonBocToBase64(TonCell::fromBits($cb));
    }
    $state   = trim((string)($msg['stateInit'] ?? ''));

    // init: maybe
    if ($state !== '') {
        $b->writeBit(1)->writeBit(1);          // just، به شکل ref
        $cell = TonCell::fromBits($b);
        $cell->addRef(tonBocFromBase64($state));
    } else {
        $b->writeBit(0);
        $cell = TonCell::fromBits($b);
    }

    // body: either — اگر جا می‌شود همان‌جا داخل خودِ پیام، وگرنه ref.
    //
    // قبلا همیشه ref می‌شد. غلط نبود (هر دو شکل در TL-B مجازند) ولی برای
    // بدنه‌ی خالی یک سلولِ بی‌مصرف می‌ساخت، کارمزد فوروارد را بالا می‌برد،
    // و خروجی را با کتابخانه‌های استاندارد TON ناهم‌سان می‌کرد.
    $bodyCell = $payload !== '' ? tonBocFromBase64($payload) : new TonCell();
    $refs = $cell->refs;

    $fits = (strlen($b->bits) + 1 + strlen($bodyCell->bits)) <= 1023
         && (count($refs) + count($bodyCell->refs)) <= 4;

    if ($fits) {
        $b->writeBit(0);                       // داخلِ خودِ پیام
        $b->writeBits($bodyCell->bits);
        $cell = TonCell::fromBits($b);
        foreach ($refs as $r) $cell->addRef($r);
        foreach ($bodyCell->refs as $r) $cell->addRef($r);
    } else {
        $b->writeBit(1);                       // به شکل ref
        $cell = TonCell::fromBits($b);
        foreach ($refs as $r) $cell->addRef($r);
        $cell->addRef($bodyCell);
    }

    return $cell;
}

/**
 * پیام خارجی امضاشده برای ولت v4R2.
 * $messages: آرایه‌ای از خروجی tonInternalMessage
 */
/**
 * 🔏 بدنه‌ی امضاشده‌ی ولت W5 (v5R1).
 *
 * W5 با v3/v4 فرق بنیادی دارد:
 *   • یک opcode سرِ پیام دارد: 0x7369676e، همان «sign»
 *   • به‌جای «mode + رفرنسِ پیام» پشت سر هم، یک «فهرست اکشن» می‌سازد که
 *     هر حلقه‌اش به حلقه‌ی قبلی رفرنس می‌دهد
 *   • و امضا در <b>انتهای</b> بدنه می‌نشیند، نه ابتدایش
 *
 * چیدمان:
 *   payload = op(32) | wallet_id(32) | valid_until(32) | seqno(32)
 *             | 1 (out_actions هست) | 0 (اکشن دیگری نیست)
 *             + رفرنس: فهرست اکشن‌ها
 *   body    = بیت‌های payload + امضا(512) ، با همان رفرنس‌ها
 */
function tonSignedBodyV5($keys, $walletId, $seqno, $messages, $validUntil, $sendMode) {
    // فهرست اکشن‌ها — از خالی شروع، هر پیام یک حلقه روی قبلی
    $list = TonCell::fromBits(new TonBits());           // out_list_empty
    foreach ($messages as $m) {
        $ab = new TonBits();
        $ab->writeUint(0x0ec3c86d, 32);                 // action_send_msg
        $ab->writeUint($sendMode & 0xFF, 8);
        $node = TonCell::fromBits($ab);
        $node->addRef($list);                           // prev
        $node->addRef($m);                              // out_msg
        $list = $node;
    }

    $p = new TonBits();
    $p->writeUint(0x7369676e, 32);                      // درخواست بیرونیِ امضاشده
    $p->writeUint($walletId, 32);
    $p->writeUint($validUntil, 32);
    $p->writeUint($seqno, 32);
    $p->writeBit(1);                                    // out_actions دارد
    $p->writeBit(0);                                    // اکشن افزونه‌ای ندارد
    $payload = TonCell::fromBits($p);
    $payload->addRef($list);

    [$cOk, $cWhy] = tonCryptoReady();
    if (!$cOk) throw new Exception($cWhy);
    $sig = sodium_crypto_sign_detached($payload->hash(), $keys['secret']);

    // امضا در انتها — همان چیزی که قرارداد W5 انتظار دارد
    $bb = new TonBits();
    $bb->writeBits($payload->bits);
    $bb->writeBytes($sig);
    $body = TonCell::fromBits($bb);
    foreach ($payload->refs as $r) $body->addRef($r);
    return $body;
}

function tonSignedExternal($keys, $walletAddr, $seqno, $messages, $opts = []) {
    $subwallet  = (int)($opts['subwallet'] ?? 698983191);
    $version    = strtolower((string)($opts['version'] ?? 'v4r2'));

    if (!str_starts_with($version, 'v3') && !str_starts_with($version, 'v4')
        && !str_starts_with($version, 'v5'))
        throw new Exception('نسخه‌ی ولت ناشناخته: ' . $version);
    if (strpos($version, 'v3') === 0 && !isset($opts['subwallet'])) $subwallet = 698983191;

    $validUntil = (int)($opts['valid_until'] ?? (time() + 300));
    $sendMode   = (int)($opts['mode'] ?? 3);
    $stateInit  = $opts['state'] ?? null;

    if (str_starts_with($version, 'v5')) {
        if (count($messages) > 255) throw new Exception('حداکثر ۲۵۵ پیام در هر تراکنش');
        $wid  = (int)($opts['wallet_id'] ?? tonWalletId('v5r1',
                      (int)($opts['wc'] ?? 0), (int)($opts['sub'] ?? 0)));
        $body = tonSignedBodyV5($keys, $wid, $seqno, $messages, $validUntil, $sendMode);
        return tonWrapExternal($walletAddr, $body, $stateInit);
    }

    if (count($messages) > 4) throw new Exception('حداکثر ۴ پیام در هر تراکنش');

    // بدنه داخلی که امضا می‌شود
    $inner = new TonBits();
    $inner->writeUint($subwallet, 32);
    $inner->writeUint($validUntil, 32);
    $inner->writeUint($seqno, 32);
    // v4 یک بایت op دارد، v3 ندارد
    if (strpos($version, 'v3') !== 0) $inner->writeUint(0, 8);   // op = ارسال ساده
    foreach ($messages as $_) $inner->writeUint($sendMode, 8);

    $innerCell = TonCell::fromBits($inner);
    foreach ($messages as $m) $innerCell->addRef($m);

    [$cOk, $cWhy] = tonCryptoReady();
    if (!$cOk) throw new Exception($cWhy);
    $sig = sodium_crypto_sign_detached($innerCell->hash(), $keys['secret']);

    // بدنه نهایی = امضا + همان بیت‌ها و رفرنس‌ها
    $bodyBits = new TonBits();
    $bodyBits->writeBytes($sig);
    $bodyBits->writeBits($innerCell->bits);
    $body = TonCell::fromBits($bodyBits);
    foreach ($innerCell->refs as $r) $body->addRef($r);

    return tonWrapExternal($walletAddr, $body, $stateInit);
}

/** پوششِ پیام خارجی — برای هر نسخه‌ی ولت یکی است، فقط بدنه فرق دارد */
function tonWrapExternal($walletAddr, TonCell $body, $stateInit = null) {
    $ext = new TonBits();
    $ext->writeBits('10');       // ext_in_msg_info$10
    $ext->writeBits('00');       // src: addr_none
    tonWriteAddress($ext, $walletAddr);
    $ext->writeCoins('0');       // import_fee

    if ($stateInit instanceof TonCell) $ext->writeBit(1)->writeBit(1);   // just، ref
    else                                $ext->writeBit(0);
    $ext->writeBit(1);           // body به شکل ref

    $cell = TonCell::fromBits($ext);
    if ($stateInit instanceof TonCell) $cell->addRef($stateInit);
    $cell->addRef($body);
    return $cell;
}

/** همان بالا، ولی خروجی base64 آماده ارسال */
function tonSignedExternalB64($keys, $walletAddr, $seqno, $messages, $opts = []) {
    return tonBocToBase64(tonSignedExternal($keys, $walletAddr, $seqno, $messages, $opts));
}

// ============================================================
// 🌐 ارتباط با شبکه TON
// ============================================================

/** درخواست به API شبکه — toncenter یا سازگارش */
function tonApiCall($base, $path, $method, $body, $apiKey, $timeout = 20) {
    $url = rtrim((string)$base, '/') . $path;
    $ch  = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if (trim((string)$apiKey) !== '') $headers[] = 'X-API-Key: ' . $apiKey;

    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if (strtoupper($method) === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opt);

    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) return [null, 'اتصال به شبکه TON برقرار نشد: ' . $err];
    $j = json_decode((string)$res, true);
    if (!is_array($j)) return [null, 'پاسخ نامعتبر (کد ' . $code . '): ' . mb_substr((string)$res, 0, 160)];
    if ($code < 200 || $code >= 300) {
        $m = $j['error'] ?? ($j['detail'] ?? ('کد ' . $code));
        $m = is_string($m) ? $m : json_encode($m, JSON_UNESCAPED_UNICODE);
        // 429 یعنی «زیاد پرسیدی»، نه «جواب منفی» — این دو را نباید یکی گرفت
        if ($code === 429) $m = TON_RATE_LIMITED . $m;
        return [null, $m];
    }
    return [$j, ''];
}

/** نشانه‌ی «شبکه محدودیت گذاشت» تا از «جواب منفی» تشخیص داده شود */
const TON_RATE_LIMITED = '[rate] ';

function tonIsRateLimited($err) { return str_starts_with((string)$err, TON_RATE_LIMITED); }

/**
 * همان tonApiCall، ولی اگر شبکه محدودیت گذاشت خودش صبر می‌کند و دوباره
 * می‌پرسد. سرویس رایگان toncenter حدود یک درخواست در ثانیه می‌دهد و
 * تایید ولت چند درخواست پشت سر هم دارد.
 */
function tonApiCallRetry($base, $path, $method, $body, $apiKey, $timeout = 20, $tries = 3) {
    $lastErr = '';
    for ($i = 0; $i < max(1, $tries); $i++) {
        if ($i > 0) usleep(1200000 * $i);       // ۱.۲ ثانیه، بعد ۲.۴ ثانیه
        [$j, $err] = tonApiCall($base, $path, $method, $body, $apiKey, $timeout);
        if ($j) return [$j, ''];
        $lastErr = $err;
        if (!tonIsRateLimited($err)) break;      // خطای دیگری بود، تکرار فایده ندارد
    }
    return [null, $lastErr];
}

/** seqno فعلی ولت — بدون آن نمی‌شود تراکنش ساخت */
function tonGetSeqno($base, $address, $apiKey) {
    [$j, $err] = tonApiCall($base, '/getWalletInformation?address=' . rawurlencode($address), 'GET', null, $apiKey);
    if (!$j) return [null, $err];
    $r = $j['result'] ?? $j;
    if (isset($r['seqno']))  return [(int)$r['seqno'], ''];
    if (isset($r['wallet']) && empty($r['wallet'])) return [0, ''];   // هنوز روی شبکه نیست
    return [null, 'seqno در پاسخ نبود'];
}

/** موجودی ولت به nanoTON */
function tonGetBalance($base, $address, $apiKey) {
    [$j, $err] = tonApiCall($base, '/getAddressBalance?address=' . rawurlencode($address), 'GET', null, $apiKey);
    if (!$j) return [null, $err];
    $v = $j['result'] ?? null;
    return is_scalar($v) ? [(string)$v, ''] : [null, 'موجودی خوانده نشد'];
}

/** ارسال BOC امضاشده به شبکه */
function tonSendBoc($base, $bocB64, $apiKey) {
    [$j, $err] = tonApiCall($base, '/sendBoc', 'POST', ['boc' => $bocB64], $apiKey, 30);
    if (!$j) return [false, $err];
    if (isset($j['ok']) && !$j['ok']) return [false, $j['error'] ?? 'شبکه رد کرد'];
    return [true, ''];
}
