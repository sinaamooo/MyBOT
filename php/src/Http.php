<?php

final class Http
{
    public static $fake = null;
    private static array $hdr = [];
    const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private static function handle(array $r)
    {
        $method = strtoupper($r['method'] ?? 'GET');
        $url = $r['url'];
        if (!empty($r['params'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($r['params']);
        }
        $given = array_change_key_case($r['headers'] ?? [], CASE_LOWER);
        $headers = [];
        if (!isset($given['user-agent'])) {
            $headers[] = 'User-Agent: ' . self::UA;
        }
        if (!isset($given['accept'])) {
            $headers[] = 'Accept: application/json, text/plain, */*';
        }
        foreach ($r['headers'] ?? [] as $k => $v) {
            if ($v !== null && $v !== '') {
                $headers[] = "$k: $v";
            }
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $r['follow'] ?? true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => (int)($r['timeout'] ?? 12),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_ENCODING => '',
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if (array_key_exists('json', $r)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($r['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } elseif (isset($r['form'])) {
            $opts[CURLOPT_POSTFIELDS] = $r['form'];
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $proxy = $r['proxy'] ?? Config::get('PROXY');
        if ($proxy !== '' && empty($r['no_proxy'])) {
            $opts[CURLOPT_PROXY] = str_starts_with($proxy, 'socks5://') ? 'socks5h://' . substr($proxy, 9) : $proxy;
        }
        if (!empty($r['want_headers'])) {
            $id = spl_object_id($ch);
            self::$hdr[$id] = [];
            $opts[CURLOPT_HEADERFUNCTION] = function ($c, $line) use ($id) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    self::$hdr[$id][] = [strtolower(trim($k)), trim($v)];
                }
                return strlen($line);
            };
        }
        curl_setopt_array($ch, $opts);
        return [$ch, $url];
    }

    private static function takeHeaders($ch): array
    {
        $id = spl_object_id($ch);
        $h = self::$hdr[$id] ?? [];
        unset(self::$hdr[$id]);
        return $h;
    }

    public static function request(array $r): array
    {
        if (self::$fake) {
            return (self::$fake)($r) ?? ['status' => 0, 'body' => ''];
        }
        [$ch] = self::handle($r);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $h = self::takeHeaders($ch);
        curl_close($ch);
        return ['status' => $body === false ? 0 : $status, 'body' => $body === false ? '' : $body, 'headers' => $h];
    }

    public static function multi(array $reqs): array
    {
        $out = [];
        if (self::$fake) {
            foreach ($reqs as $k => $r) {
                $out[$k] = (self::$fake)($r) ?? ['status' => 0, 'body' => ''];
            }
            return $out;
        }
        $mh = curl_multi_init();
        $handles = [];
        foreach ($reqs as $k => $r) {
            [$ch] = self::handle($r);
            curl_multi_add_handle($mh, $ch);
            $handles[$k] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running && $st === CURLM_OK);
        foreach ($handles as $k => $ch) {
            $body = curl_multi_getcontent($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $out[$k] = ['status' => ($body === null || $body === '') && $status === 0 ? 0 : $status, 'body' => (string)$body, 'headers' => self::takeHeaders($ch)];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    public static function decode(array $res)
    {
        if ($res['status'] < 200 || $res['status'] >= 400 || $res['body'] === '') {
            return null;
        }
        $d = json_decode($res['body'], true);
        return json_last_error() === JSON_ERROR_NONE ? $d : null;
    }

    public static function json(string $url, array $o = [])
    {
        return self::decode(self::request(['url' => $url] + $o));
    }

    public static function postJson(string $url, $body, array $o = [])
    {
        return self::decode(self::request(['url' => $url, 'method' => 'POST', 'json' => $body] + $o));
    }

    public static function text(string $url, array $o = []): ?string
    {
        $r = self::request(['url' => $url] + $o);
        return ($r['status'] >= 200 && $r['status'] < 400) ? $r['body'] : null;
    }

    public static function multiJson(array $reqs): array
    {
        return array_map([self::class, 'decode'], self::multi($reqs));
    }
}

final class TG
{
    public static $fake = null;
    public static $hookSink = null;
    public static bool $hook = false;
    public static ?string $lastError = null;
    private static $ch = null;
    const QUICK = ['sendMessage', 'sendPhoto', 'answerCallbackQuery', 'sendChatAction', 'editMessageText', 'editMessageReplyMarkup'];

    public static function closeHook(?array $call = null): void
    {
        if (!self::$hook) {
            return;
        }
        self::$hook = false;
        $body = $call !== null ? (string)json_encode($call, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : 'ok';
        if ($body === '') {
            $body = 'ok';
            $call = null;
        }
        if (self::$hookSink) {
            (self::$hookSink)($body);
            return;
        }
        $lu = Config::$root . '/data/last_update.txt';
        if ((int)@filemtime($lu) < time() - 30) {
            @file_put_contents($lu, date('Y-m-d H:i:s') . ' reply=' . ($call['method'] ?? 'api') . "\n");
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . ($call !== null ? 'application/json' : 'text/plain'));
            header('Content-Length: ' . strlen($body));
            header('Connection: close');
        }
        echo $body;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }
    const SKIP = ['not modified', 'not found', 'rights', 'replied message', 'too long', "can't be edited", 'chat_write_forbidden', 'blocked', 'kicked'];

    private static function hasPremium(array $p): bool
    {
        foreach (['text', 'caption'] as $k) {
            if (isset($p[$k]) && is_string($p[$k]) && str_contains($p[$k], '<tg-emoji')) {
                return true;
            }
        }
        foreach ($p['reply_markup']['inline_keyboard'] ?? [] as $row) {
            foreach ($row as $b) {
                if (!empty($b['icon_custom_emoji_id'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function stripPremium(array $p): array
    {
        foreach (['text', 'caption'] as $k) {
            if (isset($p[$k]) && is_string($p[$k])) {
                $p[$k] = UI::stripPremium($p[$k]);
            }
        }
        if (isset($p['reply_markup']['inline_keyboard'])) {
            foreach ($p['reply_markup']['inline_keyboard'] as &$row) {
                foreach ($row as &$b) {
                    if (!empty($b['icon_custom_emoji_id'])) {
                        $ch = UI::emojiChar((string)$b['icon_custom_emoji_id']);
                        if (($b['text'] ?? '') === "\u{3164}") {
                            $b['text'] = $ch ?? '•';
                        } elseif ($ch !== null && preg_match('/^\p{Regional_Indicator}{2}$/u', $ch)) {
                            $b['text'] = $ch . ' ' . ($b['text'] ?? '');
                        }
                    }
                    unset($b['icon_custom_emoji_id']);
                }
            }
        }
        return $p;
    }

    public static function base(): string
    {
        return rtrim(Config::get('TG_API_BASE', 'https://api.telegram.org'), '/');
    }

    private static function raw(string $method, array $p): array
    {
        if (self::$fake) {
            return (self::$fake)($method, $p);
        }
        $files = [];
        $fields = [];
        foreach ($p as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_array($v) && isset($v['__bytes'])) {
                $tmp = tempnam(sys_get_temp_dir(), 'tg');
                file_put_contents($tmp, $v['__bytes']);
                $files[] = $tmp;
                $fields[$k] = new CURLFile($tmp, $v['mime'] ?? 'application/octet-stream', $v['name'] ?? 'file');
            } elseif (is_array($v) || is_bool($v)) {
                $fields[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } else {
                $fields[$k] = (string)$v;
            }
        }
        if (self::$ch === null) {
            self::$ch = curl_init();
        } else {
            curl_reset(self::$ch);
        }
        $ch = self::$ch;
        $opts = [CURLOPT_URL => self::base() . '/bot' . Config::get('BOT_TOKEN') . '/' . $method, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $files ? 60 : 20, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields,
            CURLOPT_TCP_KEEPALIVE => 1, CURLOPT_HTTPHEADER => ['Expect:']];
        if (defined('CURL_HTTP_VERSION_2TLS')) {
            $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }
        $proxy = Config::get('TELEGRAM_PROXY') ?: Config::get('PROXY');
        if ($proxy !== '') {
            $opts[CURLOPT_PROXY] = str_starts_with($proxy, 'socks5://') ? 'socks5h://' . substr($proxy, 9) : $proxy;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        if ($body === false && in_array(curl_errno($ch), [6, 7, 35, 52, 55, 56], true)) {
            usleep(200000);
            $body = curl_exec($ch);
        }
        foreach ($files as $f) {
            @unlink($f);
        }
        $d = $body ? json_decode($body, true) : null;
        return is_array($d) ? $d : ['ok' => false, 'description' => 'network error'];
    }

    public static function api(string $method, array $p = [])
    {
        if (isset($p['parse_mode']) === false && (isset($p['text']) || isset($p['caption'])) && !in_array($method, ['answerCallbackQuery'], true)) {
            $p['parse_mode'] = 'HTML';
        }
        if (in_array($method, ['sendMessage', 'editMessageText'], true) && !isset($p['link_preview_options'])) {
            $p['link_preview_options'] = ['is_disabled' => true];
        }
        if (($p['parse_mode'] ?? '') === 'HTML') {
            foreach (['text', 'caption'] as $k) {
                if (isset($p[$k]) && is_string($p[$k])) {
                    $p[$k] = UI::align(UI::emojiMap(UI::quoteGaps($p[$k])));
                }
            }
        }
        if (isset($p['reply_markup']['inline_keyboard'])) {
            foreach ($p['reply_markup']['inline_keyboard'] as &$row) {
                foreach ($row as &$b) {
                    $b = UI::iconize($b);
                }
                unset($b);
            }
            unset($row);
        }
        if ($method === 'answerCallbackQuery' && isset($p['text']) && is_string($p['text'])) {
            $p['text'] = UI::stripEmoji($p['text']) ?: null;
        }
        $quick = !empty($p['__quick']);
        unset($p['__quick']);
        if ($quick && self::$hook && Config::get('QUICK_REPLY', '1') !== '0' && in_array($method, self::QUICK, true)) {
            $simple = true;
            foreach ($p as $v) {
                if (is_array($v) && isset($v['__bytes'])) {
                    $simple = false;
                }
            }
            if ($simple) {
                self::closeHook(['method' => $method] + array_filter($p, fn($v) => $v !== null));
                self::$lastError = null;
                return true;
            }
        }
        self::closeHook();
        $r = self::raw($method, $p);
        if (empty($r['ok']) && (int)($r['error_code'] ?? 0) === 429 && ($wait = (int)($r['parameters']['retry_after'] ?? 1)) <= 3) {
            usleep(($wait ?: 1) * 1000000);
            $r = self::raw($method, $p);
        }
        if (empty($r['ok'])) {
            $desc = strtolower((string)($r['description'] ?? ''));
            $skip = false;
            foreach (self::SKIP as $s) {
                if (str_contains($desc, $s)) {
                    $skip = true;
                }
            }
            if (!$skip && self::hasPremium($p)) {
                $r = self::raw($method, self::stripPremium($p));
            }
        }
        if (empty($r['ok'])) {
            self::$lastError = (string)($r['description'] ?? 'error');
            return null;
        }
        self::$lastError = null;
        return $r['result'];
    }

    public static function multi(array $calls): array
    {
        $out = [];
        if (self::$fake) {
            foreach ($calls as $k => [$m, $p]) {
                $out[$k] = self::api($m, $p);
            }
            return $out;
        }
        self::closeHook();
        $mh = curl_multi_init();
        $hs = [];
        $proxy = Config::get('TELEGRAM_PROXY') ?: Config::get('PROXY');
        foreach ($calls as $k => [$m, $p]) {
            $ch = curl_init(self::base() . '/bot' . Config::get('BOT_TOKEN') . '/' . $m);
            $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($p, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Expect:'], CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8];
            if ($proxy !== '') {
                $opts[CURLOPT_PROXY] = str_starts_with($proxy, 'socks5://') ? 'socks5h://' . substr($proxy, 9) : $proxy;
            }
            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($mh, $ch);
            $hs[$k] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.2);
            }
        } while ($running && $st === CURLM_OK);
        foreach ($hs as $k => $ch) {
            $d = json_decode((string)curl_multi_getcontent($ch), true);
            $out[$k] = !empty($d['ok']) ? $d['result'] : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    public static function me(): array
    {
        return Cache::remember(self::meKey(), 86400, fn() => self::api('getMe') ?? []);
    }

    public static function meKey(): string
    {
        return 'bot_me:' . substr(md5(Config::get('BOT_TOKEN')), 0, 12);
    }

    public static function username(): string
    {
        return (string)(self::me()['username'] ?? '');
    }

    public static function botId(): int
    {
        return (int)(self::me()['id'] ?? 0);
    }

    public static function send(int|string $chat, string $text, ?array $markup = null, array $extra = [])
    {
        return self::api('sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => $markup] + $extra);
    }

    public static function edit(int|string $chat, int $msg, string $text, ?array $markup = null, array $extra = [])
    {
        return self::api('editMessageText', ['chat_id' => $chat, 'message_id' => $msg, 'text' => $text, 'reply_markup' => $markup] + $extra);
    }

    public static function delete(int|string $chat, int $msg): void
    {
        self::api('deleteMessage', ['chat_id' => $chat, 'message_id' => $msg]);
    }

    public static function answer(string $id, ?string $text = null, bool $alert = false): void
    {
        self::api('answerCallbackQuery', ['callback_query_id' => $id, 'text' => $text, 'show_alert' => $alert ?: null, '__quick' => true]);
    }

    public static function photo(int|string $chat, $photo, ?string $caption = null, ?array $markup = null, array $extra = [])
    {
        if (is_string($photo) && !preg_match('#^(https?://|[A-Za-z0-9_-]{20,}$)#', $photo)) {
            $photo = ['__bytes' => $photo, 'name' => 'card.png', 'mime' => 'image/png'];
        }
        return self::api('sendPhoto', ['chat_id' => $chat, 'photo' => $photo, 'caption' => $caption, 'reply_markup' => $markup] + $extra);
    }

    public static function document(int|string $chat, string $bytes, string $name, ?string $caption = null, array $extra = [])
    {
        return self::api('sendDocument', ['chat_id' => $chat, 'document' => ['__bytes' => $bytes, 'name' => $name], 'caption' => $caption] + $extra);
    }
}
