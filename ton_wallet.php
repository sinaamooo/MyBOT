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
    $seed   = hash_pbkdf2('sha512', $phrase, 'TON default seed' . $password, 100000, 64, true);
    $kp     = sodium_crypto_sign_seed_keypair(substr($seed, 0, 32));

    return [
        'public'  => sodium_crypto_sign_publickey($kp),
        'secret'  => sodium_crypto_sign_secretkey($kp),
    ];
}

// ============================================================
// 👛 قرارداد ولت v4R2
// ============================================================

/**
 * تأیید ولت — به‌جای حدس زدن کد قرارداد، کلید عمومی روی زنجیره را می‌خوانیم
 * و با کلیدی که از عبارت بازیابی ساخته‌ایم می‌سنجیم. اگر یکی بود، این عبارت
 * واقعاً صاحب همین آدرس است و امضای ما را ولت می‌پذیرد.
 */
function tonVerifyWallet($base, $address, $publicKey, $apiKey) {
    // مسیر runGetMethod نسبت به همان base داده می‌شود، نه با پیشوند ثابت
    [$r, $err] = tonApiCall($base, '/runGetMethod', 'POST', [
        'address' => $address, 'method' => 'get_public_key', 'stack' => [],
    ], $apiKey);
    if (!$r) return ['ok' => false, 'error' => 'پاسخ شبکه نامعتبر: ' . mb_substr((string)$err, 0, 200)];
    if (isset($r['ok']) && !$r['ok']) return ['ok' => false, 'error' => 'شبکه خطا داد: ' . mb_substr(json_encode($r, 320), 0, 160)];
    $stack = $r['result']['stack'] ?? [];
    if (!isset($stack[0][1])) return ['ok' => false, 'error' => 'ولت متد get_public_key ندارد — آدرس یا نسخه ولت را بررسی کنید'];
    // پاسخ می‌تواند «0x…» یا هگز خالی باشد؛ صفرهای ابتدایی هم معنی ندارند
    $onchain = strtolower(trim((string)$stack[0][1]));
    if (str_starts_with($onchain, '0x')) $onchain = substr($onchain, 2);
    $onchain = ltrim($onchain, '0');
    $mine    = ltrim(bin2hex($publicKey), '0');
    if ($onchain !== $mine) return ['ok' => false, 'error' => 'عبارت بازیابی با این آدرس نمی‌خواند'];
    return ['ok' => true];
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

    // body: either — همیشه به شکل ref می‌گذاریم تا مطمئن باشیم جا می‌شود
    $b->writeBit(1);
    $refs = $cell->refs;
    $cell = TonCell::fromBits($b);
    foreach ($refs as $r) $cell->addRef($r);
    $cell->addRef($payload !== '' ? tonBocFromBase64($payload) : new TonCell());

    return $cell;
}

/**
 * پیام خارجی امضاشده برای ولت v4R2.
 * $messages: آرایه‌ای از خروجی tonInternalMessage
 */
function tonSignedExternal($keys, $walletAddr, $seqno, $messages, $opts = []) {
    $subwallet  = (int)($opts['subwallet'] ?? 698983191);
    $version    = strtolower((string)($opts['version'] ?? 'v4r2'));
    $validUntil = (int)($opts['valid_until'] ?? (time() + 300));
    $sendMode   = (int)($opts['mode'] ?? 3);
    $stateInit  = $opts['state'] ?? null;      // فقط اگر ولت هنوز روی شبکه نیست
    if (strpos($version, 'v3') === 0 && !isset($opts['subwallet'])) $subwallet = 698983191;

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

    // پوشش پیام خارجی
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
        return [null, is_string($m) ? $m : json_encode($m, JSON_UNESCAPED_UNICODE)];
    }
    return [$j, ''];
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
