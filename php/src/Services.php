<?php

final class Gifts
{
    const REPO = 'ssamy2/TelegramGiftsAssests';
    const RAW = 'https://raw.githubusercontent.com/ssamy2/TelegramGiftsAssests';
    const LINK = '~(?:https?://)?(?:t\.me|telegram\.me|telegram\.dog)/nft/([A-Za-z0-9]+)-(\d+)|(?:https?://)?nft\.fragment\.com/gift/([A-Za-z0-9]+)-(\d+)~i';

    public static function norm(?string $n): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string)$n));
    }

    public static function parseLink(string $t): ?array
    {
        if (!preg_match(self::LINK, $t, $m)) {
            return null;
        }
        return [($m[1] ?? '') !== '' ? $m[1] : $m[3], (int)(($m[2] ?? '') !== '' ? $m[2] : $m[4])];
    }

    public static function findLinks(string $t): array
    {
        preg_match_all(self::LINK, $t, $mm, PREG_SET_ORDER);
        return array_map(fn($m) => [($m[1] ?? '') !== '' ? $m[1] : $m[3], (int)(($m[2] ?? '') !== '' ? $m[2] : $m[4])], $mm);
    }

    public static function splitName(string $slug): string
    {
        return preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $slug);
    }

    public static function dataset(): array
    {
        return Cache::remember('gift_dataset', 600, function () {
            $d = Http::json(self::RAW . '/main/Gifts_Details.json', ['timeout' => 20]) ?: [];
            $index = [];
            $byId = [];
            foreach ($d['upgraded'] ?? [] as $it) {
                foreach ([$it['full_name'] ?? null, $it['short_name'] ?? null] as $k) {
                    if ($k) {
                        $index[self::norm($k)] = $it;
                    }
                }
                if (!empty($it['regular_id'])) {
                    $byId[(string)$it['regular_id']] = $it;
                }
            }
            $un = [];
            foreach ($d['unupgraded'] ?? [] as $it) {
                if (!empty($it['id'])) {
                    $un[(string)$it['id']] = $it;
                }
            }
            return ['index' => $index, 'by_id' => $byId, 'unupgraded' => $un, 'updated' => $d['last_updated'] ?? null];
        });
    }

    public static function attributeFloors(string $short): array
    {
        return Cache::remember('gift_attr:' . $short, 600, function () use ($short) {
            $d = Http::json(self::RAW . "/main/models/$short/prices.json", ['timeout' => 15]);
            if (!is_array($d)) {
                return [];
            }
            $out = [];
            foreach (['models', 'backdrops', 'symbols'] as $k) {
                foreach ($d[$k] ?? [] as $n => $v) {
                    $out[$k][self::norm($n)] = $v;
                }
            }
            return $out;
        });
    }

    public static function floorsDaysAgo(int $days = 7): array
    {
        return Cache::remember('gift_old:' . $days, 6 * 3600, function () use ($days) {
            $until = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
            $c = Http::json('https://api.github.com/repos/' . self::REPO . '/commits', ['params' => ['until' => $until, 'per_page' => 1, 'path' => 'Gifts_Details.json'], 'timeout' => 15]);
            $sha = $c[0]['sha'] ?? null;
            if (!$sha) {
                return [];
            }
            $old = Http::json(self::RAW . "/$sha/Gifts_Details.json", ['timeout' => 20]) ?: [];
            $out = [];
            foreach ($old['upgraded'] ?? [] as $it) {
                if (!empty($it['floor_price_ton'])) {
                    $out[self::norm($it['full_name'] ?? '')] = (float)$it['floor_price_ton'];
                }
            }
            return $out;
        });
    }

    private static function portalsFloor(string $short): ?float
    {
        $auth = Config::get('PORTALS_AUTH');
        if ($auth === '') {
            return null;
        }
        $f = Cache::remember('portals_floors', 300, fn() => Http::json('https://portals-market.com/api/collections/floors', ['headers' => ['Authorization' => $auth], 'timeout' => 10])['floorPrices'] ?? []);
        $v = $f[$short] ?? $f[str_replace('_', '', $short)] ?? null;
        return is_numeric($v) ? (float)$v : null;
    }

    public static function valueOf(string $collection, ?string $model, ?string $backdrop, ?string $symbol): array
    {
        $ds = self::dataset();
        $it = $ds['index'][self::norm($collection)] ?? null;
        if (!$it) {
            return ['collection' => null, 'floor' => null, 'model' => null, 'backdrop' => null, 'symbol' => null, 'estimate' => null, 'floor_7d' => null];
        }
        $floor = isset($it['floor_price_ton']) ? (float)$it['floor_price_ton'] : null;
        $live = self::portalsFloor($it['short_name'] ?? '');
        if ($live) {
            $floor = $floor ? min($floor, $live) : $live;
        }
        $a = self::attributeFloors($it['short_name'] ?? '');
        $mf = $model ? ($a['models'][self::norm($model)] ?? null) : null;
        $bf = $backdrop ? ($a['backdrops'][self::norm($backdrop)] ?? null) : null;
        $sf = $symbol ? ($a['symbols'][self::norm($symbol)] ?? null) : null;
        $c = array_filter([$floor, $mf, $bf, $sf]);
        return ['collection' => $it['full_name'] ?? null, 'floor' => $floor, 'model' => $mf, 'backdrop' => $bf, 'symbol' => $sf,
            'estimate' => $c ? max($c) : null, 'floor_7d' => self::floorsDaysAgo()[self::norm($it['full_name'] ?? '')] ?? null];
    }

    private static function clean(string $s): string
    {
        return trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'));
    }

    private static function fromTme(string $slug, int $n): ?array
    {
        $page = Http::text("https://t.me/nft/$slug-$n", ['timeout' => 10]);
        if (!$page) {
            return null;
        }
        $meta = ['slug' => $slug, 'number' => $n, 'name' => self::splitName($slug), 'model' => null, 'backdrop' => null, 'symbol' => null,
            'model_rarity' => null, 'backdrop_rarity' => null, 'symbol_rarity' => null, 'owner' => null, 'quantity' => null, 'image' => null];
        if (preg_match('/<meta property="og:title" content="([^"]+)"/', $page, $m)) {
            $meta['name'] = preg_replace('/\s*#\d+$/', '', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $page, $m)) {
            $meta['image'] = $m[1];
        }
        $found = false;
        preg_match_all('/<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>/is', $page, $rows, PREG_SET_ORDER);
        foreach ($rows as [, $th, $td]) {
            $key = strtolower(self::clean($th));
            $rar = preg_match('/<mark[^>]*>(.*?)<\/mark>/s', $td, $rm) ? self::clean($rm[1]) : null;
            $val = self::clean(preg_replace('/<mark[^>]*>.*?<\/mark>/s', '', $td));
            if (in_array($key, ['model', 'backdrop', 'symbol'], true)) {
                $meta[$key] = $val;
                $meta[$key . '_rarity'] = $rar;
                $found = true;
            } elseif ($key === 'owner') {
                $meta['owner'] = $val;
            } elseif (in_array($key, ['quantity', 'issued'], true)) {
                $meta['quantity'] = $val;
            }
        }
        if (!$found && preg_match('/<meta property="og:description" content="([^"]+)"/', $page, $m)) {
            $desc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            foreach (['Model', 'Backdrop', 'Symbol'] as $k) {
                if (preg_match("/$k:\s*([^\n(]+?)\s*(?:\(([\d.,]+%)\))?(?:\n|$)/", $desc, $mm)) {
                    $meta[strtolower($k)] = trim($mm[1]);
                    $meta[strtolower($k) . '_rarity'] = $mm[2] ?? null;
                    $found = true;
                }
            }
        }
        return $found ? $meta : null;
    }

    private static function fromFragment(string $slug, int $n): ?array
    {
        $d = Http::json('https://nft.fragment.com/gift/' . strtolower($slug) . "-$n.json", ['timeout' => 10]);
        if (!is_array($d) || empty($d['attributes'])) {
            return null;
        }
        $meta = ['slug' => $slug, 'number' => $n, 'name' => preg_replace('/\s*#\d+$/', '', $d['name'] ?? self::splitName($slug)), 'model' => null,
            'backdrop' => null, 'symbol' => null, 'model_rarity' => null, 'backdrop_rarity' => null, 'symbol_rarity' => null, 'owner' => null,
            'quantity' => null, 'image' => $d['image'] ?? null];
        foreach ($d['attributes'] as $a) {
            $t = strtolower((string)($a['trait_type'] ?? ''));
            if (in_array($t, ['model', 'backdrop', 'symbol'], true)) {
                $meta[$t] = $a['value'] ?? null;
            }
        }
        return $meta;
    }

    public static function meta(string $slug, int $n): ?array
    {
        return Cache::remember("gift_meta:$slug-$n", 300, function () use ($slug, $n) {
            $t = self::fromTme($slug, $n);
            if ($t && $t['model']) {
                return $t;
            }
            $f = self::fromFragment($slug, $n);
            if ($f) {
                if ($t) {
                    $f['owner'] = $t['owner'];
                    $f['quantity'] = $t['quantity'];
                }
                return $f;
            }
            return $t;
        });
    }

    public static function images(array $names): array
    {
        $reqs = [];
        foreach ($names as $k => $n) {
            if ($n) {
                $reqs[$k] = ['url' => 'https://nft.fragment.com/gift/' . strtolower($n) . '.small.jpg', 'timeout' => 6];
            }
        }
        $out = [];
        foreach ($reqs ? Http::multi($reqs) : [] as $k => $r) {
            if ($r['status'] >= 200 && $r['status'] < 300 && strlen($r['body']) > 500) {
                $out[$k] = $r['body'];
            }
        }
        return $out;
    }
}

final class Translate
{
    const LANGS = ['fa' => ['فارسی', 'fa', 'fa-IR'], 'en' => ['انگلیسی', 'en', 'en-GB'], 'ru' => ['روسی', 'ru', 'ru-RU'], 'zh' => ['چینی', 'zh-CN', 'zh-CN'], 'ar' => ['عربی', 'ar', 'ar-SA']];

    private static function guess(string $t): string
    {
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $t)) {
            return preg_match('/[پچژگکی]/u', $t) ? 'fa' : 'ar';
        }
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $t)) {
            return 'ru';
        }
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $t)) {
            return 'zh-CN';
        }
        return 'en';
    }

    private static function google(string $t, string $tl): ?string
    {
        $d = null;
        for ($try = 0; $try < 2 && !is_array($d); $try++) {
            $d = Http::json('https://translate.googleapis.com/translate_a/single', ['params' => ['client' => 'gtx', 'sl' => 'auto', 'tl' => $tl, 'dt' => 't', 'q' => $t], 'timeout' => 10]);
        }
        if (!is_array($d) || !is_array($d[0] ?? null)) {
            return null;
        }
        $out = '';
        foreach ($d[0] as $p) {
            $out .= $p[0] ?? '';
        }
        return $out !== '' ? $out : null;
    }

    private static function lingva(string $t, string $tl): ?string
    {
        foreach (['https://lingva.ml', 'https://lingva.lunar.icu'] as $h) {
            $d = Http::json("$h/api/v1/auto/$tl/" . rawurlencode(mb_substr($t, 0, 1500)), ['timeout' => 10]);
            if (!empty($d['translation'])) {
                return $d['translation'];
            }
        }
        return null;
    }

    private static function mymemory(string $t, string $full): ?string
    {
        $src = self::guess($t);
        $d = Http::json('https://api.mymemory.translated.net/get', ['params' => ['q' => mb_substr($t, 0, 480), 'langpair' => "$src|$full"], 'timeout' => 10]);
        $o = $d['responseData']['translatedText'] ?? null;
        return $o && !str_contains($o, 'MYMEMORY WARNING') ? $o : null;
    }

    public static function run(string $text, string $lang): ?string
    {
        [, $tl, $full] = self::LANGS[$lang];
        $out = '';
        foreach (mb_str_split($text, 3500) ?: [$text] as $chunk) {
            $r = self::google($chunk, $tl) ?? self::lingva($chunk, $tl) ?? self::mymemory($chunk, $full);
            if ($r === null) {
                return null;
            }
            $out .= $r;
        }
        return $out;
    }
}

final class Account
{
    const DC = [1 => 'Miami 🇺🇸', 2 => 'Amsterdam 🇳🇱', 3 => 'Miami 🇺🇸', 4 => 'Amsterdam 🇳🇱', 5 => 'Singapore 🇸🇬'];

    public static function created(int $id): int
    {
        $a = Config::data('anchors');
        if ($id <= $a[0][0]) {
            return $a[0][1];
        }
        $n = count($a);
        if ($id >= $a[$n - 1][0]) {
            [$i0, $t0] = $a[$n - 2];
            [$i1, $t1] = $a[$n - 1];
        } else {
            $k = 1;
            while ($k < $n && $a[$k][0] <= $id) {
                $k++;
            }
            [$i0, $t0] = $a[$k - 1];
            [$i1, $t1] = $a[$k];
        }
        return (int)($t0 + ($id - $i0) * ($t1 - $t0) / ($i1 - $i0));
    }

    public static function dc(string $fileId): ?int
    {
        $raw = base64_decode(strtr($fileId, '-_', '+/') . str_repeat('=', (4 - strlen($fileId) % 4) % 4), true);
        if ($raw === false) {
            return null;
        }
        $out = '';
        $n = strlen($raw);
        for ($i = 0; $i < $n; $i++) {
            if ($raw[$i] === "\0" && $i + 1 < $n) {
                $out .= str_repeat("\0", ord($raw[$i + 1]));
                $i++;
            } else {
                $out .= $raw[$i];
            }
        }
        if (strlen($out) < 8) {
            return null;
        }
        $dc = unpack('V', substr($out, 4, 4))[1];
        return $dc >= 1 && $dc <= 5 ? $dc : null;
    }

    public static function level(int $xp): int
    {
        $lvl = 1;
        $need = 50;
        while ($xp >= $need && $lvl < 100) {
            $xp -= $need;
            $lvl++;
            $need = (int)($need * 1.35);
        }
        return $lvl;
    }
}

final class Banking
{
    public static function card(string $t): ?string
    {
        $t = trim(Fmt::en($t));
        if (!preg_match('/^(?:\d[\s\-_.]?){15}\d$/', $t)) {
            return null;
        }
        $d = preg_replace('/\D/', '', $t);
        return strlen($d) === 16 ? $d : null;
    }

    public static function iban(string $t): ?string
    {
        $t = strtoupper(preg_replace('/[\s\-]/', '', Fmt::en($t)));
        if (!str_starts_with($t, 'IR') && preg_match('/^\d{24}$/', $t)) {
            $t = 'IR' . $t;
        }
        return preg_match('/^IR\d{24}$/', $t) ? $t : null;
    }

    public static function luhn(string $d): bool
    {
        $sum = 0;
        foreach (str_split($d) as $i => $c) {
            $v = (int)$c;
            if ($i % 2 === 0) {
                $v *= 2;
                if ($v > 9) {
                    $v -= 9;
                }
            }
            $sum += $v;
        }
        return $sum % 10 === 0;
    }

    public static function ibanOk(string $iban): bool
    {
        $moved = substr($iban, 4) . substr($iban, 0, 4);
        $num = '';
        foreach (str_split($moved) as $c) {
            $num .= ctype_alpha($c) ? (string)(ord($c) - 55) : $c;
        }
        $r = 0;
        foreach (str_split($num, 7) as $part) {
            $r = (int)(($r . $part) % 97);
        }
        return $r === 1;
    }

    public static function bankOfCard(string $d): ?string
    {
        return Config::data('banks')['bins'][substr($d, 0, 6)] ?? null;
    }

    public static function bankOfIban(string $i): ?string
    {
        return Config::data('banks')['iban'][substr($i, 4, 3)] ?? null;
    }

    public static function format(string $d): string
    {
        return implode('-', str_split($d, 4));
    }

    private static function flatten($data, string $prefix = '', array &$out = []): array
    {
        if (is_array($data)) {
            $i = 0;
            foreach ($data as $k => $v) {
                if (array_is_list($data) && $i++ >= 5) {
                    break;
                }
                self::flatten($v, $prefix . $k . '.', $out);
            }
        } else {
            $out[rtrim($prefix, '.')] = $data;
        }
        return $out;
    }

    public static function lookup(string $d): array
    {
        $info = ['card' => $d, 'valid' => self::luhn($d), 'bank' => self::bankOfCard($d), 'owner' => null, 'iban' => null, 'extra' => []];
        $url = Config::get('CARDINFO_API_URL', 'http://167.235.240.118/api/Cardinfo/');
        if ($url === '') {
            return $info;
        }
        $data = Http::json($url, ['params' => ['number' => $d], 'headers' => ['Authorization' => 'Bearer ' . Config::get('CARDINFO_API_TOKEN', 'codesazan'), 'Content-Type' => 'application/x-www-form-urlencoded'], 'timeout' => 12]);
        if (!is_array($data)) {
            return $info;
        }
        $skip = ['status', 'ok', 'success', 'code', 'message', 'number', 'card', 'cardnumber', 'card_number'];
        $owners = ['name', 'owner', 'fullname', 'full_name', 'owner_name', 'ownername', 'cardowner', 'card_owner', 'holder'];
        foreach (self::flatten($data) as $key => $v) {
            $leaf = substr(strrchr('.' . $key, '.'), 1);
            $low = strtolower($leaf);
            if ($v === null || $v === '' || in_array($low, $skip, true)) {
                continue;
            }
            if (in_array($low, $owners, true)) {
                $info['owner'] ??= (string)$v;
            } elseif (in_array($low, ['bank', 'bank_name', 'bankname'], true)) {
                $info['bank'] ??= (string)$v;
            } elseif (in_array($low, ['iban', 'sheba', 'shaba'], true)) {
                $info['iban'] ??= (string)$v;
            } elseif (is_scalar($v) && count($info['extra']) < 6) {
                $info['extra'][$leaf] = $v;
            }
        }
        return $info;
    }
}

final class Phone
{
    public static function clean(string $t): ?string
    {
        $t = preg_replace('/[\s\-()]/', '', Fmt::en($t));
        if (str_starts_with($t, '00')) {
            $t = '+' . substr($t, 2);
        }
        if (!preg_match('/^\+?\d{7,15}$/', $t)) {
            return null;
        }
        if ($t[0] !== '+') {
            $t = (str_starts_with($t, '09') && strlen($t) === 11) ? '+98' . substr($t, 1) : '+' . $t;
        }
        return $t;
    }

    private static function key(string $raw): ?string
    {
        $raw = str_replace("\u{FE0F}", '', $raw);
        foreach (Config::data('phone') as $k => $v) {
            if (str_contains($raw, $k)) {
                return $k;
            }
        }
        return null;
    }

    public static function check(string $phone): ?array
    {
        $url = Config::get('PHONE_CHECK_API_URL', 'https://api-bot.site/check/Check.php');
        if ($url === '') {
            return null;
        }
        $r = Http::request(['url' => $url, 'params' => ['phone' => $phone], 'timeout' => 20]);
        $d = Http::decode($r);
        $raw = is_array($d) ? (string)($d['status'] ?? $d['result'] ?? $d['Status'] ?? '') : '';
        $k = self::key($raw) ?? self::key($r['body']);
        return $k ? [$k, Config::data('phone')[$k]] : null;
    }
}

final class Instagram
{
    const LINK = '~(?:https?://)?(?:www\.)?instagram\.com/(?:[A-Za-z0-9_.]+/)?(?:p|reel|reels|tv|stories)/\S+~i';
    public static int $lastStatus = 0;

    public static function enabled(): bool
    {
        return Config::get('INSTAGRAM_API_KEY') !== '' && self::url() !== '';
    }

    private static function url(): string
    {
        return Config::get('INSTAGRAM_API_URL', 'https://api.codesazan.ir/Instagram');
    }

    public static function link(string $t): ?string
    {
        return preg_match(self::LINK, $t, $m) ? $m[0] : null;
    }

    private static function call(array $p): ?array
    {
        $d = Http::json(self::url(), ['params' => ['key' => Config::get('INSTAGRAM_API_KEY')] + $p, 'timeout' => 60]);
        self::$lastStatus = is_array($d) ? (int)($d['status'] ?? 0) : 0;
        return self::$lastStatus === 200 ? $d : null;
    }

    public static function post(string $link): ?array
    {
        $d = self::call(['type' => 'postdownloader', 'link' => $link]);
        if (!$d) {
            return null;
        }
        $out = [];
        foreach ($d['result']['medias'] ?? [] as $m) {
            if (!empty($m['url'])) {
                $out[] = ['url' => $m['url'], 'kind' => in_array(strtolower((string)($m['extension'] ?? '')), ['mp4', 'mov'], true) ? 'video' : 'photo'];
            }
        }
        return $out;
    }

    public static function profile(string $u): ?array
    {
        $d = self::call(['type' => 'instainfo', 'username' => $u]);
        $r = $d['result'] ?? null;
        return is_array($r) && array_is_list($r) ? ($r[0] ?? null) : $r;
    }

    public static function stories(string $u): ?array
    {
        $d = self::call(['type' => 'pagestory', 'username' => $u]);
        return $d ? array_values(array_map(fn($s) => ['url' => $s['story'], 'kind' => ($s['type'] ?? '') === 'video' ? 'video' : 'photo'], array_filter($d['result'] ?? [], fn($s) => !empty($s['story'])))) : null;
    }

    public static function highlights(string $u): ?array
    {
        $d = self::call(['type' => 'pagehightlight', 'username' => $u]);
        return $d ? array_values(array_map(fn($h) => ['title' => $h['Title'] ?? 'Highlight', 'id' => (string)$h['hightlightIDS']], array_filter($d['result'] ?? [], fn($h) => !empty($h['hightlightIDS'])))) : null;
    }

    public static function highlightItems(string $id): ?array
    {
        $d = self::call(['type' => 'gethightlight', 'id' => $id]);
        return $d ? array_values(array_map(fn($h) => ['url' => $h['highlite'], 'kind' => ($h['type'] ?? '') === 'video' ? 'video' : 'photo'], array_filter($d['result'] ?? [], fn($h) => !empty($h['highlite'])))) : null;
    }
}

final class Resolver
{
    public static function resolve(string $username): ?array
    {
        $username = ltrim(trim($username), '@');
        if ($username === '') {
            return null;
        }
        if ($row = DB::userByUsername($username)) {
            return ['kind' => 'user', 'id' => (int)$row['id'], 'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $username, 'username' => $username];
        }
        $c = TG::api('getChat', ['chat_id' => '@' . $username]);
        if ($c) {
            if (($c['type'] ?? '') === 'private') {
                return ['kind' => 'user', 'id' => (int)$c['id'], 'name' => UI::fullName($c), 'username' => $c['username'] ?? $username];
            }
            return ['kind' => 'chat', 'id' => (int)$c['id'], 'name' => $c['title'] ?? $username, 'username' => $c['username'] ?? $username];
        }
        return null;
    }
}

final class Guard
{
    const SELF_WINDOW = 900;
    const SELF_THRESHOLD = 6;
    const TABCHI_THRESHOLD = 5;

    public static function norm(string $t): string
    {
        $t = mb_strtolower(Fmt::en($t));
        $t = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], $t);
        return preg_replace('/\s+/u', ' ', $t);
    }

    public static function scammy(string $text): bool
    {
        $t = self::norm($text);
        $rx = '/' . implode('|', array_map(fn($p) => "(?:$p)", Config::data('scam'))) . '/iu';
        if (preg_match($rx, $t)) {
            return true;
        }
        foreach (Settings::get('scam_extra_keywords') ?: [] as $k) {
            if ($k !== '' && str_contains($t, self::norm($k))) {
                return true;
            }
        }
        return false;
    }

    private static function plainDigits(string $t): string
    {
        $out = '';
        foreach (mb_str_split($t) as $ch) {
            $cp = mb_ord($ch);
            if ($cp >= 0x1D7CE && $cp <= 0x1D7FF) {
                $out .= (string)(($cp - 0x1D7CE) % 10);
            } elseif (($cp >= 0x06F0 && $cp <= 0x06F9) || ($cp >= 0x0660 && $cp <= 0x0669)) {
                $out .= (string)($cp % 16);
            } elseif ($cp >= 0xFF10 && $cp <= 0xFF19) {
                $out .= (string)($cp - 0xFF10);
            } elseif ($cp >= 0x2080 && $cp <= 0x2089) {
                $out .= (string)($cp - 0x2080);
            } elseif ($cp === 0x2070 || ($cp >= 0x2074 && $cp <= 0x2079)) {
                $out .= (string)($cp - 0x2070);
            } else {
                $out .= $ch;
            }
        }
        return $out;
    }

    public static function clockInName(string $name): ?array
    {
        if (preg_match('/(?<!\d)([01]?\d|2[0-3])\s*[:：︰∶.]\s*([0-5]\d)(?!\d)/u', self::plainDigits($name), $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        return null;
    }

    public static function clockIsNow(array $hm, int $tol = 3): bool
    {
        foreach (['Asia/Tehran', 'UTC', '+03:00', '+04:00'] as $tz) {
            $now = new DateTime('now', new DateTimeZone($tz));
            $H = (int)$now->format('G');
            $M = (int)$now->format('i');
            foreach ([$H, $H % 12 ?: 12] as $h) {
                $diff = abs(($h * 60 + $M) - ($hm[0] * 60 + $hm[1]));
                if ($diff <= $tol || $diff >= 1440 - $tol) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function fullyFormatted(?string $text, ?array $entities): bool
    {
        if (!$text || !$entities || mb_strlen($text) < 2) {
            return false;
        }
        $total = strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2;
        foreach ($entities as $e) {
            if (in_array($e['type'], ['bold', 'italic', 'underline', 'strikethrough', 'spoiler', 'code', 'pre', 'blockquote', 'expandable_blockquote'], true)
                && $e['offset'] == 0 && $e['length'] >= $total - 1) {
                return true;
            }
        }
        return false;
    }

    private static function addSelf(int $chat, int $user, int $points, string $reason): int
    {
        DB::q('INSERT INTO self_events(chat_id, user_id, ts, points, reason) VALUES(?, ?, ?, ?, ?)', [$chat, $user, time(), $points, $reason]);
        return self::selfScore($chat, $user);
    }

    public static function selfScore(int $chat, int $user): int
    {
        return (int)(DB::one('SELECT COALESCE(SUM(points), 0) s FROM self_events WHERE chat_id=? AND user_id=? AND ts>=?', [$chat, $user, time() - self::SELF_WINDOW])['s'] ?? 0);
    }

    public static function selfReset(int $chat, int $user): void
    {
        DB::q('DELETE FROM self_events WHERE chat_id=? AND user_id=?', [$chat, $user]);
        Cache::forget("fs:$chat:$user");
    }

    public static function selfOnMessage(int $chat, array $user, ?string $text, ?array $entities, ?float $replyDelta): int
    {
        $uid = (int)$user['id'];
        $score = self::selfScore($chat, $uid);
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $hm = self::clockInName($name);
        $prev = DB::one('SELECT name FROM names WHERE user_id=?', [$uid])['name'] ?? null;
        DB::q('INSERT OR REPLACE INTO names(user_id, name, ts) VALUES(?, ?, ?)', [$uid, $name, time()]);
        if ($hm) {
            if (self::clockIsNow($hm)) {
                $score = self::addSelf($chat, $uid, 4, 'clock_now');
            }
            if ($prev && $prev !== $name && self::clockInName($prev)) {
                $score = self::addSelf($chat, $uid, 4, 'clock_changed');
            }
        }
        $k = "fs:$chat:$uid";
        if (self::fullyFormatted($text, $entities)) {
            $streak = (int)(Cache::get($k) ?? 0) + 1;
            Cache::set($k, $streak, self::SELF_WINDOW);
            if ($streak >= 3) {
                $score = self::addSelf($chat, $uid, 2, 'auto_format');
            }
        } else {
            Cache::forget($k);
        }
        if ($replyDelta !== null && $replyDelta <= 0) {
            $score = self::addSelf($chat, $uid, 1, 'instant_reply');
        }
        return $score;
    }

    public static function selfOnEdit(int $chat, int $user, float $delta): int
    {
        if ($delta <= 1) {
            return self::addSelf($chat, $user, 3, 'instant_edit');
        }
        if ($delta <= 3) {
            return self::addSelf($chat, $user, 2, 'fast_edit');
        }
        return self::selfScore($chat, $user);
    }

    public static function joined(int $chat, int $user): void
    {
        DB::q('INSERT OR REPLACE INTO joins(chat_id, user_id, ts, msgs) VALUES(?, ?, ?, 0)', [$chat, $user, time()]);
    }

    public static function tabchi(array $m): array
    {
        $u = $m['from'];
        $chat = (int)$m['chat']['id'];
        $text = $m['text'] ?? $m['caption'] ?? '';
        $score = 0;
        $reasons = [];
        $now = time();
        $j = DB::one('SELECT ts, msgs FROM joins WHERE chat_id=? AND user_id=?', [$chat, $u['id']]);
        if ($j) {
            $age = $now - (int)$j['ts'];
            if ($age < 30) {
                $score += 3;
                $reasons[] = 'joined<30s';
            } elseif ($age < 180) {
                $score += 2;
                $reasons[] = 'joined<3m';
            }
            DB::q('UPDATE joins SET msgs=msgs+1 WHERE chat_id=? AND user_id=?', [$chat, $u['id']]);
        } else {
            DB::q('INSERT OR IGNORE INTO joins(chat_id, user_id, ts, msgs) VALUES(?, ?, 0, 1)', [$chat, $u['id']]);
        }
        $first = !$j || (int)$j['msgs'] === 0;
        $ents = $m['entities'] ?? $m['caption_entities'] ?? [];
        $hasLink = (bool)preg_match('~(?:https?://|www\.|t\.me/|telegram\.(?:me|dog)/|tg://)~i', $text);
        foreach ($ents as $e) {
            if (in_array($e['type'], ['url', 'text_link'], true)) {
                $hasLink = true;
            }
        }
        if (preg_match('~(?:t\.me|telegram\.me)/(?:\+|joinchat/)~i', $text)) {
            $score += 3;
            $reasons[] = 'invite';
        } elseif ($hasLink) {
            $score += 2;
            $reasons[] = 'link';
        }
        if (preg_match('/(?<![\w@])@[A-Za-z][A-Za-z0-9_]{4,}/', $text) && empty($m['reply_to_message'])) {
            $score += 1;
            $reasons[] = 'mention';
        }
        if (preg_match('/عضو\s*شو|جوین\s*شو|جوین\s*بده|کانال\s*(?:ما|من)|تبلیغ|ممبر|فالوور|سفارش|ارزان|تخفیف|فروش\s*ویژه|درآمد|کسب\s*درآمد|ثبت\s*نام|join|subscribe|promo|free\s*money|airdrop|ایردراپ|بونوس|bonus/iu', $text)) {
            $score += 1;
            $reasons[] = 'ad_words';
        }
        $fwd = ($m['forward_origin']['type'] ?? null) === 'channel';
        if ($fwd) {
            $score += 2;
            $reasons[] = 'forward_channel';
        }
        if (!empty($m['via_bot']) && !empty($m['reply_markup'])) {
            $score += 2;
            $reasons[] = 'inline_buttons';
        }
        if ($first && ($hasLink || !empty($m['forward_origin']))) {
            $score += 1;
            $reasons[] = 'first_message';
        }
        if (mb_strlen($text) >= 15) {
            $h = md5(self::norm($text));
            $other = (int)DB::one('SELECT COUNT(*) c FROM msglog WHERE user_id=? AND hash=? AND chat_id<>? AND ts>=?', [$u['id'], $h, $chat, $now - 900])['c'];
            $same = (int)DB::one('SELECT COUNT(*) c FROM msglog WHERE user_id=? AND hash=? AND chat_id=? AND ts>=?', [$u['id'], $h, $chat, $now - 600])['c'];
            DB::q('INSERT INTO msglog(user_id, chat_id, hash, ts) VALUES(?, ?, ?, ?)', [$u['id'], $chat, $h, $now]);
            if ($other) {
                $score += 4;
                $reasons[] = 'cross_group';
            }
            if ($same >= 2) {
                $score += 3;
                $reasons[] = 'repeated';
            }
        }
        return [$score, $reasons];
    }
}
