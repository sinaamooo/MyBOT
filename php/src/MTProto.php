<?php

final class MTError extends RuntimeException
{
    public string $type;

    public function __construct(int $code, string $type)
    {
        parent::__construct("$code $type", $code);
        $this->type = $type;
    }
}

final class TLReader
{
    public string $b;
    public int $p = 0;

    public function __construct(string $b)
    {
        $this->b = $b;
    }

    public function raw(int $n): string
    {
        if ($this->p + $n > strlen($this->b)) {
            throw new RuntimeException('tl: unexpected end');
        }
        $s = substr($this->b, $this->p, $n);
        $this->p += $n;
        return $s;
    }

    public function uint(): int
    {
        return unpack('V', $this->raw(4))[1];
    }

    public function int(): int
    {
        $v = $this->uint();
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }

    public function long(): int
    {
        return unpack('P', $this->raw(8))[1];
    }

    public function double(): float
    {
        return unpack('e', $this->raw(8))[1];
    }

    public function bytes(): string
    {
        $l = ord($this->raw(1));
        $head = 1;
        if ($l === 254) {
            $x = $this->raw(3);
            $l = ord($x[0]) | (ord($x[1]) << 8) | (ord($x[2]) << 16);
            $head = 4;
        }
        $s = $this->raw($l);
        $pad = (4 - (($head + $l) % 4)) % 4;
        $this->p += $pad;
        return $s;
    }
}

final class TL
{
    const VECTOR = 0x1cb5c415;
    const GZIP = 0x3072cfa1;
    const BOOL_TRUE = 0x997275b5;
    const BOOL_FALSE = 0xbc799737;

    private static ?array $s = null;

    public static function schema(): array
    {
        return self::$s ??= Config::data('tl');
    }

    public static function layer(): int
    {
        return (int)self::schema()['layer'];
    }

    public static function int(int $v): string
    {
        return pack('V', $v & 0xffffffff);
    }

    public static function long(int $v): string
    {
        return pack('P', $v);
    }

    public static function bytes(string $s): string
    {
        $l = strlen($s);
        if ($l <= 253) {
            $out = chr($l) . $s;
        } else {
            $out = chr(254) . chr($l & 255) . chr(($l >> 8) & 255) . chr(($l >> 16) & 255) . $s;
        }
        $pad = (4 - strlen($out) % 4) % 4;
        return $out . str_repeat("\0", $pad);
    }

    private static function flagsFor(array $params, string $field, array $args): int
    {
        $f = 0;
        foreach ($params as [$name, $type]) {
            if (preg_match('/^' . preg_quote($field, '/') . '\.(\d+)\?(.+)$/', $type, $m)) {
                $v = $args[$name] ?? null;
                if ($v !== null && $v !== false) {
                    $f |= 1 << (int)$m[1];
                }
            }
        }
        return $f;
    }

    private static function encodeParams(array $params, array $args): string
    {
        $out = '';
        $flags = [];
        foreach ($params as [$name, $type]) {
            if ($type === '#') {
                $flags[$name] = self::flagsFor($params, $name, $args);
                $out .= self::int($flags[$name]);
                continue;
            }
            if (preg_match('/^(\w+)\.(\d+)\?(.+)$/', $type, $m)) {
                if (!(($flags[$m[1]] ?? 0) & (1 << (int)$m[2]))) {
                    continue;
                }
                if ($m[3] === 'true') {
                    continue;
                }
                $type = $m[3];
            }
            $out .= self::encode($type, $args[$name] ?? null);
        }
        return $out;
    }

    public static function encode(string $type, $v): string
    {
        switch ($type) {
            case 'int':
                return self::int((int)$v);
            case 'long':
                return self::long((int)$v);
            case 'double':
                return pack('e', (float)$v);
            case 'string':
            case 'bytes':
                return self::bytes((string)$v);
            case 'int128':
            case 'int256':
                return (string)$v;
            case 'Bool':
                return self::int($v ? self::BOOL_TRUE : self::BOOL_FALSE);
        }
        if (preg_match('/^Vector<(.+)>$/', $type, $m)) {
            $out = self::int(self::VECTOR) . self::int(count((array)$v));
            foreach ((array)$v as $x) {
                $out .= self::encode($m[1], $x);
            }
            return $out;
        }
        return self::object((array)$v);
    }

    public static function object(array $o): string
    {
        $s = self::schema();
        $name = (string)($o['_'] ?? '');
        if (!isset($s['n'][$name])) {
            throw new RuntimeException("tl: unknown constructor $name");
        }
        $id = (int)$s['n'][$name];
        return self::int($id) . self::encodeParams($s['c'][(string)$id][2], $o);
    }

    public static function method(string $name, array $args = []): string
    {
        $f = self::schema()['f'][$name] ?? null;
        if (!$f) {
            throw new RuntimeException("tl: unknown method $name");
        }
        return self::int((int)$f[0]) . self::encodeParams($f[1], $args);
    }

    public static function decode(TLReader $r, string $type)
    {
        switch ($type) {
            case 'int':
                return $r->int();
            case '#':
                return $r->uint();
            case 'long':
                return $r->long();
            case 'double':
                return $r->double();
            case 'string':
            case 'bytes':
                return $r->bytes();
            case 'int128':
                return $r->raw(16);
            case 'int256':
                return $r->raw(32);
            case 'true':
                return true;
        }
        if (preg_match('/^([Vv])ector<(.+)>$/', $type, $m)) {
            if ($m[1] === 'V') {
                $id = $r->uint();
                if ($id === self::GZIP) {
                    return self::decode(new TLReader(self::gunzip($r->bytes())), $type);
                }
                if ($id !== self::VECTOR) {
                    throw new RuntimeException(sprintf('tl: expected vector, got %08x', $id));
                }
            }
            $n = $r->uint();
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = self::decode($r, $m[2]);
            }
            return $out;
        }
        return self::boxed($r);
    }

    public static function gunzip(string $s): string
    {
        $d = @gzdecode($s);
        if ($d === false) {
            $d = @gzinflate(substr($s, 10));
        }
        if ($d === false) {
            throw new RuntimeException('tl: gzip');
        }
        return $d;
    }

    public static function boxed(TLReader $r)
    {
        $id = $r->uint();
        if ($id === self::GZIP) {
            return self::boxed(new TLReader(self::gunzip($r->bytes())));
        }
        if ($id === self::BOOL_TRUE) {
            return true;
        }
        if ($id === self::BOOL_FALSE) {
            return false;
        }
        if ($id === self::VECTOR) {
            $n = $r->uint();
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = self::boxed($r);
            }
            return $out;
        }
        $c = self::schema()['c'][(string)$id] ?? null;
        if (!$c) {
            throw new RuntimeException(sprintf('tl: unknown constructor %08x', $id));
        }
        $o = ['_' => $c[0]];
        $flags = [];
        foreach ($c[2] as [$name, $type]) {
            if ($type === '#') {
                $flags[$name] = $o[$name] = $r->uint();
                continue;
            }
            if (preg_match('/^(\w+)\.(\d+)\?(.+)$/', $type, $m)) {
                if (!(($flags[$m[1]] ?? 0) & (1 << (int)$m[2]))) {
                    if ($m[3] === 'true') {
                        $o[$name] = false;
                    }
                    continue;
                }
                $type = $m[3];
            }
            $o[$name] = self::decode($r, $type);
        }
        return $o;
    }
}

final class BigMath
{
    private static function der(int $tag, string $body): string
    {
        $l = strlen($body);
        if ($l < 128) {
            $len = chr($l);
        } else {
            $b = ltrim(pack('N', $l), "\0");
            $len = chr(0x80 | strlen($b)) . $b;
        }
        return chr($tag) . $len . $body;
    }

    private static function derInt(string $be): string
    {
        $be = ltrim($be, "\0");
        if ($be === '' || (ord($be[0]) & 0x80)) {
            $be = "\0" . $be;
        }
        return self::der(0x02, $be);
    }

    public static function pem(string $n, string $e): string
    {
        $rsa = self::der(0x30, self::derInt($n) . self::derInt($e));
        $alg = self::der(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $spki = self::der(0x30, $alg . self::der(0x03, "\0" . $rsa));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function pad(string $be, int $len): string
    {
        $be = ltrim($be, "\0");
        return str_repeat("\0", max(0, $len - strlen($be))) . $be;
    }

    public static function powmod(string $base, string $exp, string $mod): string
    {
        $len = strlen(ltrim($mod, "\0"));
        if (function_exists('gmp_powm')) {
            $r = gmp_powm(gmp_import($base), gmp_import($exp), gmp_import($mod));
            return self::pad(gmp_export($r), $len);
        }
        if (function_exists('bcpowmod')) {
            $r = bcpowmod(self::toDec($base), self::toDec($exp), self::toDec($mod));
            return self::pad(self::fromDec($r), $len);
        }
        $out = '';
        $ok = openssl_public_encrypt(self::pad($base, $len), $out, self::pem($mod, $exp), OPENSSL_NO_PADDING);
        if (!$ok) {
            throw new RuntimeException('powmod: no big integer support (install gmp or bcmath)');
        }
        return self::pad($out, $len);
    }

    private static function toDec(string $be): string
    {
        $d = '0';
        foreach (str_split($be) as $c) {
            $d = bcadd(bcmul($d, '256'), (string)ord($c));
        }
        return $d;
    }

    private static function fromDec(string $d): string
    {
        $out = '';
        while (bccomp($d, '0') > 0) {
            $out = chr((int)bcmod($d, '256')) . $out;
            $d = bcdiv($d, '256', 0);
        }
        return $out;
    }

    private static function addmod(int $x, int $y, int $m): int
    {
        return $x >= $m - $y ? $x - ($m - $y) : $x + $y;
    }

    private static function mulmod(int $a, int $b, int $m): int
    {
        if ($a < 3037000499 && $b < 3037000499) {
            return ($a * $b) % $m;
        }
        $r = 0;
        $a %= $m;
        while ($b > 0) {
            if ($b & 1) {
                $r = self::addmod($r, $a, $m);
            }
            $a = self::addmod($a, $a, $m);
            $b >>= 1;
        }
        return $r;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b) {
            [$a, $b] = [$b, $a % $b];
        }
        return $a;
    }

    public static function factor(int $n): array
    {
        if (function_exists('gmp_init')) {
            $N = gmp_init($n);
            for ($c = 1; $c < 64; $c++) {
                $x = gmp_init(random_int(2, $n - 1));
                $y = $x;
                $g = gmp_init(1);
                while (gmp_cmp($g, 1) === 0) {
                    $x = gmp_mod(gmp_add(gmp_mul($x, $x), $c), $N);
                    $y = gmp_mod(gmp_add(gmp_mul($y, $y), $c), $N);
                    $y = gmp_mod(gmp_add(gmp_mul($y, $y), $c), $N);
                    $g = gmp_gcd(gmp_abs(gmp_sub($x, $y)), $N);
                }
                if (gmp_cmp($g, $N) !== 0) {
                    $p = gmp_intval($g);
                    return [min($p, intdiv($n, $p)), max($p, intdiv($n, $p))];
                }
            }
        }
        if ($n % 2 === 0) {
            return [2, intdiv($n, 2)];
        }
        for ($c = 1; $c < 64; $c++) {
            $f = fn($v) => self::addmod(self::mulmod($v, $v, $n), $c, $n);
            $y = random_int(1, $n - 1);
            $m = 256;
            $g = $r = $q = 1;
            $x = $ys = $y;
            do {
                $x = $y;
                for ($i = 0; $i < $r; $i++) {
                    $y = $f($y);
                }
                $k = 0;
                do {
                    $ys = $y;
                    for ($i = 0, $lim = min($m, $r - $k); $i < $lim; $i++) {
                        $y = $f($y);
                        $q = self::mulmod($q, abs($x - $y), $n);
                    }
                    $g = self::gcd($q, $n);
                    $k += $m;
                } while ($k < $r && $g === 1);
                $r *= 2;
            } while ($g === 1);
            if ($g === $n) {
                do {
                    $ys = $f($ys);
                    $g = self::gcd(abs($x - $ys), $n);
                } while ($g === 1);
            }
            if ($g !== $n && $g > 1) {
                return [min($g, intdiv($n, $g)), max($g, intdiv($n, $g))];
            }
        }
        throw new RuntimeException('pq factorization failed');
    }
}

final class MTConn
{
    public $sock;
    public int $dc;
    public string $key = '';
    public string $keyId = '';
    public int $salt = 0;
    public int $offset = 0;
    private string $session;
    private int $seq = 0;
    private int $lastId = 0;
    private bool $inited = false;

    public function __construct(int $dc, string $ip, int $port = 443)
    {
        $this->dc = $dc;
        $this->session = random_bytes(8);
        $this->sock = self::open($ip, $port);
        stream_set_timeout($this->sock, 15);
        fwrite($this->sock, "\xee\xee\xee\xee");
    }

    private static function open(string $ip, int $port)
    {
        $proxy = Config::get('MTPROTO_PROXY') ?: Config::get('TELEGRAM_PROXY');
        if ($proxy !== '' && preg_match('#^(socks5h?|http)://(?:([^:@]*):([^@]*)@)?([^:/]+):(\d+)#i', $proxy, $p)) {
            $s = @stream_socket_client("tcp://{$p[4]}:{$p[5]}", $en, $es, 10);
            if (!$s) {
                throw new RuntimeException("proxy connect: $es");
            }
            stream_set_timeout($s, 15);
            if (stripos($p[1], 'socks') === 0) {
                $auth = $p[2] !== '';
                fwrite($s, "\x05\x01" . ($auth ? "\x02" : "\x00"));
                $r = fread($s, 2);
                if ($r === "\x05\x02") {
                    fwrite($s, "\x01" . chr(strlen(urldecode($p[2]))) . urldecode($p[2]) . chr(strlen(urldecode($p[3]))) . urldecode($p[3]));
                    if (substr((string)fread($s, 2), 1, 1) !== "\x00") {
                        throw new RuntimeException('socks auth failed');
                    }
                } elseif ($r !== "\x05\x00") {
                    throw new RuntimeException('socks handshake failed');
                }
                fwrite($s, "\x05\x01\x00\x01" . inet_pton($ip) . pack('n', $port));
                $r = fread($s, 10);
                if (strlen((string)$r) < 2 || $r[1] !== "\x00") {
                    throw new RuntimeException('socks connect failed');
                }
            } else {
                $h = "CONNECT $ip:$port HTTP/1.1\r\nHost: $ip:$port\r\n";
                if ($p[2] !== '') {
                    $h .= 'Proxy-Authorization: Basic ' . base64_encode(urldecode($p[2]) . ':' . urldecode($p[3])) . "\r\n";
                }
                fwrite($s, $h . "\r\n");
                $resp = '';
                while (!str_contains($resp, "\r\n\r\n") && !feof($s)) {
                    $resp .= fread($s, 1);
                }
                if (!preg_match('#^HTTP/\S+\s+200#', $resp)) {
                    throw new RuntimeException('http proxy connect failed');
                }
            }
            return $s;
        }
        $s = @stream_socket_client("tcp://$ip:$port", $en, $es, 10);
        if (!$s) {
            throw new RuntimeException("connect $ip: $es");
        }
        return $s;
    }

    public function close(): void
    {
        if ($this->sock) {
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    private function sendPacket(string $data): void
    {
        $buf = pack('V', strlen($data)) . $data;
        while ($buf !== '') {
            $w = @fwrite($this->sock, $buf);
            if ($w === false || $w === 0) {
                throw new RuntimeException('mtproto: write failed');
            }
            $buf = substr($buf, $w);
        }
    }

    private function readN(int $n): string
    {
        $out = '';
        while (strlen($out) < $n) {
            $c = fread($this->sock, $n - strlen($out));
            if ($c === false || $c === '') {
                $meta = stream_get_meta_data($this->sock);
                throw new RuntimeException($meta['timed_out'] ? 'mtproto: timeout' : 'mtproto: connection closed');
            }
            $out .= $c;
        }
        return $out;
    }

    private function recvPacket(): string
    {
        $len = unpack('V', $this->readN(4))[1];
        if ($len > 16 * 1024 * 1024) {
            throw new RuntimeException('mtproto: bad length');
        }
        $data = $this->readN($len);
        if ($len === 4) {
            $code = unpack('V', $data)[1];
            $code = $code >= 0x80000000 ? $code - 0x100000000 : $code;
            throw new MTError($code, 'TRANSPORT_' . abs($code));
        }
        return $data;
    }

    private function msgId(): int
    {
        $t = microtime(true) + $this->offset;
        $sec = (int)floor($t);
        $id = ($sec << 32) | ((int)(($t - $sec) * 4294967296) & 0xfffffffc);
        if ($id <= $this->lastId) {
            $id = $this->lastId + 4;
        }
        return $this->lastId = $id;
    }

    public function plain(string $body): TLReader
    {
        $this->sendPacket(str_repeat("\0", 8) . TL::long($this->msgId()) . TL::int(strlen($body)) . $body);
        $r = new TLReader($this->recvPacket());
        $r->raw(8);
        $r->long();
        $len = $r->uint();
        return new TLReader($r->raw($len));
    }

    private static function igeEnc(string $data, string $key, string $iv): string
    {
        $out = '';
        $x = substr($iv, 0, 16);
        $y = substr($iv, 16, 16);
        for ($i = 0, $n = strlen($data); $i < $n; $i += 16) {
            $p = substr($data, $i, 16);
            $c = openssl_encrypt($p ^ $x, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING) ^ $y;
            $x = $c;
            $y = $p;
            $out .= $c;
        }
        return $out;
    }

    private static function igeDec(string $data, string $key, string $iv): string
    {
        $out = '';
        $x = substr($iv, 0, 16);
        $y = substr($iv, 16, 16);
        for ($i = 0, $n = strlen($data); $i < $n; $i += 16) {
            $c = substr($data, $i, 16);
            $p = openssl_decrypt($c ^ $y, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING) ^ $x;
            $x = $c;
            $y = $p;
            $out .= $p;
        }
        return $out;
    }

    public static function ige(string $data, string $key, string $iv, bool $enc): string
    {
        return $enc ? self::igeEnc($data, $key, $iv) : self::igeDec($data, $key, $iv);
    }

    private function kdf(string $msgKey, int $x): array
    {
        $a = hash('sha256', $msgKey . substr($this->key, $x, 36), true);
        $b = hash('sha256', substr($this->key, 40 + $x, 36) . $msgKey, true);
        return [substr($a, 0, 8) . substr($b, 8, 16) . substr($a, 24, 8), substr($b, 0, 8) . substr($a, 8, 16) . substr($b, 24, 8)];
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
        $this->keyId = substr(sha1($key, true), -8);
    }

    private function sendEncrypted(string $body, bool $content = true): int
    {
        $id = $this->msgId();
        $seq = $content ? $this->seq * 2 + 1 : $this->seq * 2;
        if ($content) {
            $this->seq++;
        }
        $plain = TL::long($this->salt) . $this->session . TL::long($id) . TL::int($seq) . TL::int(strlen($body)) . $body;
        $padLen = 16 - ((strlen($plain) + 12) % 16) + 12;
        $plain .= random_bytes($padLen);
        $msgKey = substr(hash('sha256', substr($this->key, 88, 32) . $plain, true), 8, 16);
        [$k, $iv] = $this->kdf($msgKey, 0);
        $this->sendPacket($this->keyId . $msgKey . self::igeEnc($plain, $k, $iv));
        return $id;
    }

    private function recvEncrypted(): array
    {
        $data = $this->recvPacket();
        if (substr($data, 0, 8) !== $this->keyId) {
            throw new RuntimeException('mtproto: auth key mismatch');
        }
        $msgKey = substr($data, 8, 16);
        [$k, $iv] = $this->kdf($msgKey, 8);
        $plain = self::igeDec(substr($data, 24), $k, $iv);
        if (substr(hash('sha256', substr($this->key, 96, 32) . $plain, true), 8, 16) !== $msgKey) {
            throw new RuntimeException('mtproto: msg_key mismatch');
        }
        $r = new TLReader($plain);
        $r->raw(16);
        $mid = $r->long();
        $r->uint();
        $len = $r->uint();
        return [$mid, $r->raw($len)];
    }

    public function call(string $body, int $deadline = 20)
    {
        if (!$this->inited) {
            $body = TL::int(0xda9b0d0d) . TL::int(TL::layer())
                . TL::int(0xc1cd5ea9) . TL::int(0) . TL::int((int)Config::get('API_ID'))
                . TL::bytes('MyBOT Server') . TL::bytes(PHP_OS_FAMILY) . TL::bytes('1.0') . TL::bytes('en') . TL::bytes('') . TL::bytes('en')
                . $body;
        }
        $req = $this->sendEncrypted($body);
        $end = time() + $deadline;
        $resends = 0;
        while (time() < $end) {
            [$mid, $payload] = $this->recvEncrypted();
            $res = $this->handle(new TLReader($payload), $req, $mid);
            if ($res === null) {
                continue;
            }
            if ($res[0] === 'resend') {
                if (++$resends > 5) {
                    throw new RuntimeException('mtproto: too many resends');
                }
                $req = $this->sendEncrypted($body);
                continue;
            }
            $this->inited = true;
            return $res[1];
        }
        throw new RuntimeException('mtproto: timeout');
    }

    private function handle(TLReader $r, int $req, int $mid): ?array
    {
        $id = $r->uint();
        switch ($id) {
            case 0x73f1f8dc:
                $n = $r->uint();
                $found = null;
                for ($i = 0; $i < $n; $i++) {
                    $imid = $r->long();
                    $r->uint();
                    $len = $r->uint();
                    $res = $this->handle(new TLReader($r->raw($len)), $req, $imid);
                    if ($res !== null && ($found === null || $res[0] !== 'resend')) {
                        $found = $res;
                    }
                }
                return $found;
            case 0xf35c6d01:
                $rid = $r->long();
                if ($rid !== $req) {
                    return null;
                }
                $peek = unpack('V', substr($r->b, $r->p, 4))[1];
                if ($peek === 0x2144ca19) {
                    $r->uint();
                    $code = $r->int();
                    throw new MTError($code, $r->bytes());
                }
                if ($peek === TL::GZIP) {
                    $r->uint();
                    $r = new TLReader(TL::gunzip($r->bytes()));
                    $peek = unpack('V', substr($r->b, 0, 4))[1];
                    if ($peek === 0x2144ca19) {
                        $r->uint();
                        $code = $r->int();
                        throw new MTError($code, $r->bytes());
                    }
                }
                return ['ok', TL::boxed($r)];
            case 0xedab447b:
                $bad = $r->long();
                $r->int();
                $r->int();
                $this->salt = $r->long();
                return $bad === $req ? ['resend', null] : null;
            case 0xa7eff811:
                $bad = $r->long();
                $r->int();
                $code = $r->int();
                if (in_array($code, [16, 17], true)) {
                    $this->offset = ($mid >> 32) - time();
                    $this->lastId = 0;
                } elseif (in_array($code, [32, 33], true)) {
                    $this->seq = $code === 32 ? $this->seq + 16 : max(0, $this->seq - 1);
                } elseif ($code === 48) {
                    return null;
                }
                return $bad === $req ? ['resend', null] : null;
            case 0x9ec20908:
                $r->long();
                $r->long();
                $this->salt = $r->long();
                return null;
            case TL::GZIP:
                return $this->handle(new TLReader(TL::gunzip($r->bytes())), $req, $mid);
            default:
                return null;
        }
    }

    public function createKey(): void
    {
        $nonce = random_bytes(16);
        $r = $this->plain(TL::int(0xbe7e8ef1) . $nonce);
        if ($r->uint() !== 0x05162463 || $r->raw(16) !== $nonce) {
            throw new RuntimeException('mtproto: bad resPQ');
        }
        $serverNonce = $r->raw(16);
        $pqBytes = $r->bytes();
        $r->uint();
        $fps = [];
        for ($i = 0, $n = $r->uint(); $i < $n; $i++) {
            $fps[] = $r->long();
        }
        $key = MTProto::rsaKey();
        if (!in_array($key['fp'], $fps, true)) {
            throw new RuntimeException('mtproto: no matching rsa key');
        }
        $pq = 0;
        foreach (str_split($pqBytes) as $c) {
            $pq = ($pq << 8) | ord($c);
        }
        [$p, $q] = BigMath::factor($pq);
        $be = fn(int $v) => ltrim(pack('J', $v), "\0");
        $newNonce = random_bytes(32);
        $inner = TL::int(0xa9f55f95) . TL::bytes($pqBytes) . TL::bytes($be($p)) . TL::bytes($be($q)) . $nonce . $serverNonce . $newNonce . TL::int($this->dc);
        $padded = $inner . random_bytes(192 - strlen($inner));
        $rev = strrev($padded);
        do {
            $tmp = random_bytes(32);
            $withHash = $rev . hash('sha256', $tmp . $padded, true);
            $aes = self::igeEnc($withHash, $tmp, str_repeat("\0", 32));
            $tmpXor = $tmp ^ hash('sha256', $aes, true);
            $block = $tmpXor . $aes;
        } while (strcmp($block, $key['n']) >= 0);
        $enc = BigMath::powmod($block, $key['e'], $key['n']);
        $r = $this->plain(TL::int(0xd712e4be) . $nonce . $serverNonce . TL::bytes($be($p)) . TL::bytes($be($q)) . TL::long($key['fp']) . TL::bytes($enc));
        $id = $r->uint();
        if ($id !== 0xd0e8075c || $r->raw(16) !== $nonce || $r->raw(16) !== $serverNonce) {
            throw new RuntimeException('mtproto: dh params failed');
        }
        $encAnswer = $r->bytes();
        $tk = sha1($newNonce . $serverNonce, true) . substr(sha1($serverNonce . $newNonce, true), 0, 12);
        $tiv = substr(sha1($serverNonce . $newNonce, true), 12, 8) . sha1($newNonce . $newNonce, true) . substr($newNonce, 0, 4);
        $ans = self::igeDec($encAnswer, $tk, $tiv);
        $a = new TLReader(substr($ans, 20));
        if ($a->uint() !== 0xb5890dba || $a->raw(16) !== $nonce || $a->raw(16) !== $serverNonce) {
            throw new RuntimeException('mtproto: bad dh inner');
        }
        $g = $a->int();
        $dhPrime = $a->bytes();
        $gA = $a->bytes();
        $serverTime = $a->int();
        $this->offset = $serverTime - time();
        for ($retry = 0; $retry < 3; $retry++) {
            $b = random_bytes(256);
            $b[0] = chr(ord($b[0]) & 0x7f);
            $b[255] = chr(ord($b[255]) | 1);
            $gB = BigMath::powmod(chr($g), $b, $dhPrime);
            $data = TL::int(0x6643b654) . $nonce . $serverNonce . TL::long($retry) . TL::bytes($gB);
            $dh = sha1($data, true) . $data;
            $dh .= random_bytes((16 - strlen($dh) % 16) % 16);
            $r = $this->plain(TL::int(0xf5045f1f) . $nonce . $serverNonce . TL::bytes(self::igeEnc($dh, $tk, $tiv)));
            $id = $r->uint();
            if ($id === 0x3bcbf734) {
                $authKey = BigMath::powmod($gA, $b, $dhPrime);
                $this->setKey(str_pad($authKey, 256, "\0", STR_PAD_LEFT));
                $this->salt = unpack('P', substr($newNonce, 0, 8) ^ substr($serverNonce, 0, 8))[1];
                return;
            }
            if ($id !== 0x46dc1fb9) {
                break;
            }
        }
        throw new RuntimeException('mtproto: dh gen failed');
    }
}

final class MTProto
{
    const DCS = [1 => '149.154.175.50', 2 => '149.154.167.51', 3 => '149.154.175.100', 4 => '149.154.167.91', 5 => '149.154.171.5'];
    const RSA = 'MIIBCgKCAQEA6LszBcC1LGzyr992NzE0ieY+BSaOW622Aa9Bd4ZHLl+TuFQ4lo4g5nKaMBwK/BIb9xUfg0Q29/2mgIR6Zr9krM7HjuIcCzFvDtr+L0GQjae9H0pRB2OO62cECs5HKhT5DZ98K33vmWiLowc621dQuwKWSQKjWf50XYFw42h21P2KXUGyp2y/+aEyZ+uVgLLQbRA1dEjSDZ2iGRy12Mk5gpYc397aYp438fsJoHIgJ2lgMv5h7WY9t6N/byY9Nw9p21Og3AoXSL2q/2IJ1WRUhebgAdGVMlV1fkuOQoEzR7EdpqtQD9Cs5+bfo3Nhmcyvk5ftB0WkJ9z6bNZ7yxrP8wIDAQAB';

    public static $fake = null;
    public static array $addr = [];
    public static ?array $rsa = null;
    private static ?MTConn $conn = null;

    public static function enabled(): bool
    {
        return (int)Config::get('API_ID') > 0 && Config::get('API_HASH') !== '' && Config::get('BOT_TOKEN') !== '';
    }

    public static function rsaKey(): array
    {
        if (self::$rsa) {
            return self::$rsa;
        }
        $der = base64_decode(self::RSA);
        $r = new TLReader($der);
        $readLen = function () use ($r) {
            $l = ord($r->raw(1));
            if ($l & 0x80) {
                $n = $l & 0x7f;
                $l = 0;
                foreach (str_split($r->raw($n)) as $c) {
                    $l = ($l << 8) | ord($c);
                }
            }
            return $l;
        };
        $r->raw(1);
        $readLen();
        $r->raw(1);
        $n = ltrim($r->raw($readLen()), "\0");
        $r->raw(1);
        $e = ltrim($r->raw($readLen()), "\0");
        $fp = unpack('P', substr(sha1(TL::bytes($n) . TL::bytes($e), true), -8))[1];
        return ['n' => $n, 'e' => $e, 'fp' => $fp];
    }

    private static function state(): array
    {
        $s = DB::kvGet('mtproto');
        $s = is_array($s) ? $s : [];
        $token = md5(Config::get('BOT_TOKEN') . '|' . Config::get('API_ID'));
        if (($s['token'] ?? '') !== $token) {
            $s = ['token' => $token, 'dc' => 2, 'keys' => []];
        }
        return $s;
    }

    private static function save(array $s): void
    {
        DB::kvSet('mtproto', $s);
    }

    private static function connect(array &$s, int $dc): MTConn
    {
        [$ip, $port] = self::$addr[$dc] ?? [self::DCS[$dc] ?? self::DCS[2], 443];
        $c = new MTConn($dc, $ip, $port);
        $k = $s['keys'][$dc] ?? null;
        if ($k && !empty($k['k'])) {
            $c->setKey(base64_decode($k['k']));
            $c->salt = (int)($k['salt'] ?? 0);
            $c->offset = (int)($k['off'] ?? 0);
        } else {
            $c->createKey();
            $s['keys'][$dc] = ['k' => base64_encode($c->key), 'salt' => $c->salt, 'off' => $c->offset, 'bot' => false];
            self::save($s);
        }
        return $c;
    }

    private static function remember(array &$s, MTConn $c): void
    {
        if (isset($s['keys'][$c->dc])) {
            $s['keys'][$c->dc]['salt'] = $c->salt;
            $s['keys'][$c->dc]['off'] = $c->offset;
        }
    }

    private static function login(array &$s, MTConn $c): void
    {
        $c->call(TL::method('auth.importBotAuthorization', ['flags' => 0, 'api_id' => (int)Config::get('API_ID'), 'api_hash' => Config::get('API_HASH'), 'bot_auth_token' => Config::get('BOT_TOKEN')]));
        $s['keys'][$c->dc]['bot'] = true;
        $s['dc'] = $c->dc;
        self::remember($s, $c);
        self::save($s);
    }

    public static function call(string $method, array $args = [])
    {
        if (self::$fake) {
            return (self::$fake)($method, $args);
        }
        if (!self::enabled()) {
            throw new RuntimeException('mtproto disabled');
        }
        $lock = fopen(sys_get_temp_dir() . '/mybot_mtp_' . md5(Config::$root) . '.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        try {
            $s = self::state();
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $dc = (int)($s['dc'] ?? 2);
                try {
                    if (!self::$conn || self::$conn->dc !== $dc) {
                        self::$conn?->close();
                        self::$conn = self::connect($s, $dc);
                    }
                    $c = self::$conn;
                    if (empty($s['keys'][$dc]['bot'])) {
                        self::login($s, $c);
                    }
                    $res = $c->call(TL::method($method, $args));
                    self::remember($s, $c);
                    self::save($s);
                    return $res;
                } catch (MTError $e) {
                    if (preg_match('/_MIGRATE_(\d+)/', $e->type, $m)) {
                        $s['dc'] = (int)$m[1];
                        self::$conn?->close();
                        self::$conn = null;
                        continue;
                    }
                    if (in_array($e->type, ['AUTH_KEY_UNREGISTERED', 'SESSION_REVOKED', 'USER_DEACTIVATED'], true)) {
                        $s['keys'][$dc]['bot'] = false;
                        continue;
                    }
                    if ($e->type === 'TRANSPORT_404') {
                        unset($s['keys'][$dc]);
                        self::save($s);
                        self::$conn?->close();
                        self::$conn = null;
                        continue;
                    }
                    throw $e;
                } catch (RuntimeException $e) {
                    self::$conn?->close();
                    self::$conn = null;
                    if ($attempt >= 1) {
                        throw $e;
                    }
                }
            }
            throw new RuntimeException('mtproto: giving up');
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public static function resolve(string $username): ?array
    {
        $u = strtolower(ltrim(trim($username), '@'));
        if (!preg_match('/^[a-z0-9_]{4,32}$/', $u)) {
            return null;
        }
        $hit = Cache::get('mtu:' . $u, $found);
        if ($found) {
            return $hit ?: null;
        }
        try {
            $r = self::call('contacts.resolveUsername', ['flags' => 0, 'username' => $u]);
        } catch (MTError $e) {
            if (in_array($e->type, ['USERNAME_NOT_OCCUPIED', 'USERNAME_INVALID'], true)) {
                Cache::set('mtu:' . $u, false, 3600);
            }
            return null;
        } catch (Throwable $e) {
            error_log('mtproto resolve: ' . $e->getMessage());
            return null;
        }
        $peer = $r['peer'] ?? [];
        $out = null;
        if (($peer['_'] ?? '') === 'peerUser') {
            foreach ($r['users'] ?? [] as $usr) {
                if (($usr['_'] ?? '') === 'user' && $usr['id'] === $peer['user_id']) {
                    $out = ['kind' => 'mt', 'type' => 'user', 'id' => $usr['id'], 'access_hash' => $usr['access_hash'] ?? 0,
                        'name' => trim(($usr['first_name'] ?? '') . ' ' . ($usr['last_name'] ?? '')) ?: $u, 'username' => $usr['username'] ?? $u];
                }
            }
        } elseif (in_array($peer['_'] ?? '', ['peerChannel', 'peerChat'], true)) {
            foreach ($r['chats'] ?? [] as $ch) {
                if (($ch['_'] ?? '') === 'channel' && $ch['id'] === ($peer['channel_id'] ?? null)) {
                    $out = ['kind' => 'mt', 'type' => 'channel', 'id' => $ch['id'], 'access_hash' => $ch['access_hash'] ?? 0,
                        'name' => $ch['title'] ?? $u, 'username' => $ch['username'] ?? $u];
                }
            }
        }
        Cache::set('mtu:' . $u, $out ?? false, $out ? 7 * 86400 : 3600);
        return $out;
    }

    public static function gifts(array $t, int $max = 500): ?array
    {
        $peer = $t['type'] === 'channel'
            ? ['_' => 'inputPeerChannel', 'channel_id' => $t['id'], 'access_hash' => $t['access_hash']]
            : ['_' => 'inputPeerUser', 'user_id' => $t['id'], 'access_hash' => $t['access_hash']];
        $items = [];
        $total = 0;
        $offset = '';
        try {
            do {
                $r = self::call('payments.getSavedStarGifts', ['flags' => 0, 'peer' => $peer, 'offset' => $offset, 'limit' => 100]);
                $total = (int)($r['count'] ?? 0);
                foreach ($r['gifts'] ?? [] as $sg) {
                    $items[] = self::ownedGift($sg);
                }
                $offset = (string)($r['next_offset'] ?? '');
            } while ($offset !== '' && count($items) < $max);
        } catch (Throwable $e) {
            error_log('mtproto gifts: ' . $e->getMessage());
            return $items ? [$items, max($total, count($items))] : null;
        }
        return [$items, max($total, count($items))];
    }

    public static function ownedGift(array $sg): array
    {
        $g = $sg['gift'] ?? [];
        if (($g['_'] ?? '') === 'starGiftUnique') {
            $gift = ['base_name' => $g['title'] ?? 'Gift', 'name' => $g['slug'] ?? '', 'number' => $g['num'] ?? 0];
            foreach ($g['attributes'] ?? [] as $a) {
                $rar = $a['rarity']['permille'] ?? null;
                switch ($a['_'] ?? '') {
                    case 'starGiftAttributeModel':
                        $gift['model'] = ['name' => $a['name'], 'rarity_per_mille' => $rar];
                        break;
                    case 'starGiftAttributePattern':
                        $gift['symbol'] = ['name' => $a['name'], 'rarity_per_mille' => $rar];
                        break;
                    case 'starGiftAttributeBackdrop':
                        $gift['backdrop'] = ['name' => $a['name'], 'rarity_per_mille' => $rar, 'colors' => ['center_color' => $a['center_color'], 'edge_color' => $a['edge_color']]];
                        break;
                }
            }
            $usd = isset($g['value_usd_amount']) ? $g['value_usd_amount'] / 100 : null;
            return ['type' => 'unique', 'gift' => $gift, 'value_usd' => $usd];
        }
        return ['type' => 'regular', 'gift' => ['id' => (string)($g['id'] ?? ''), 'star_count' => $g['stars'] ?? 0, 'total_count' => $g['availability_total'] ?? null],
            'convert_star_count' => $sg['convert_stars'] ?? null];
    }
}
