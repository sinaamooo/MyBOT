<?php

final class Http
{
    public static $fake = null;
    const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private static function handle(array $r)
    {
        $method = strtoupper($r['method'] ?? 'GET');
        $url = $r['url'];
        if (!empty($r['params'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($r['params']);
        }
        $headers = ['User-Agent: ' . self::UA, 'Accept: application/json, text/plain, */*'];
        foreach ($r['headers'] ?? [] as $k => $v) {
            if ($v !== null && $v !== '') {
                $headers[] = "$k: $v";
            }
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
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
        $proxy = Config::get('PROXY');
        if ($proxy !== '' && empty($r['no_proxy'])) {
            $opts[CURLOPT_PROXY] = str_starts_with($proxy, 'socks5://') ? 'socks5h://' . substr($proxy, 9) : $proxy;
        }
        curl_setopt_array($ch, $opts);
        return [$ch, $url];
    }

    public static function request(array $r): array
    {
        if (self::$fake) {
            return (self::$fake)($r) ?? ['status' => 0, 'body' => ''];
        }
        [$ch] = self::handle($r);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $body === false ? 0 : $status, 'body' => $body === false ? '' : $body];
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
            $out[$k] = ['status' => ($body === null || $body === '') && $status === 0 ? 0 : $status, 'body' => (string)$body];
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
    public static ?string $lastError = null;
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
                    unset($b['icon_custom_emoji_id']);
                }
            }
        }
        return $p;
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
        $ch = curl_init('https://api.telegram.org/bot' . Config::get('BOT_TOKEN') . '/' . $method);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields];
        $proxy = Config::get('TELEGRAM_PROXY') ?: Config::get('PROXY');
        if ($proxy !== '') {
            $opts[CURLOPT_PROXY] = str_starts_with($proxy, 'socks5://') ? 'socks5h://' . substr($proxy, 9) : $proxy;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        if ($body === false) {
            usleep(300000);
            $body = curl_exec($ch);
        }
        curl_close($ch);
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
        $r = self::raw($method, $p);
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

    public static function me(): array
    {
        return Cache::remember('bot_me', 86400, fn() => self::api('getMe') ?? []);
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
        self::api('answerCallbackQuery', ['callback_query_id' => $id, 'text' => $text, 'show_alert' => $alert]);
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
