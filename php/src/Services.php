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
        return FileCache::swr('gift_dataset', 600, function () {
            $d = Http::json(self::RAW . '/main/Gifts_Details.json', ['timeout' => 15]);
            if (!is_array($d) || empty($d['upgraded'])) {
                return null;
            }
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
        }) ?? ['index' => [], 'by_id' => [], 'unupgraded' => [], 'updated' => null];
    }

    public static function attributeFloors(string $short): array
    {
        return FileCache::swr('gift_attr:' . $short, 1800, fn() => self::attrsFrom(Http::json(self::RAW . "/main/models/$short/prices.json", ['timeout' => 8])));
    }

    private static function attrsFrom($d): array
    {
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
    }

    public static function prefetch(array $collections, int $cap = 0, bool $old = true): void
    {
        $ds = self::dataset();
        $reqs = [];
        foreach (array_unique($collections) as $c) {
            $short = $ds['index'][self::norm($c)]['short_name'] ?? null;
            if ($short && !isset($reqs[$short]) && !FileCache::has('gift_attr:' . $short, FileCache::$inline ? 1800 : 7 * 86400)) {
                $reqs[$short] = ['url' => self::RAW . "/main/models/$short/prices.json", 'timeout' => 8];
            }
        }
        if ($cap > 0) {
            $reqs = array_slice($reqs, 0, $cap, true);
        }
        foreach (array_chunk($reqs, 24, true) as $chunk) {
            foreach (Http::multiJson($chunk) as $short => $d) {
                if (is_array($d) || !FileCache::has('gift_attr:' . $short, 7 * 86400)) {
                    FileCache::put('gift_attr:' . $short, self::attrsFrom($d));
                }
            }
        }
        if ($old) {
            self::floorsDaysAgo();
        }
    }

    public static function floorsDaysAgo(int $days = 7): array
    {
        return FileCache::swr('gift_old:' . $days, 6 * 3600, function () use ($days) {
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
        $f = FileCache::swr('portals_floors', 300, fn() => Http::json('https://portals-market.com/api/collections/floors', ['headers' => ['Authorization' => $auth], 'timeout' => 10])['floorPrices'] ?? []);
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

    private static function fromTme(string $slug, int $n, ?string $page): ?array
    {
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

    private static function fromFragment(string $slug, int $n, $d): ?array
    {
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
        return Cache::remember("gift_meta:$slug-$n", 1800, function () use ($slug, $n) {
            $reqs = [
                'tme' => ['url' => "https://t.me/nft/$slug-$n", 'timeout' => 6, 'headers' => ['Accept' => 'text/html']],
                'frag' => ['url' => 'https://nft.fragment.com/gift/' . strtolower($slug) . "-$n.json", 'timeout' => 6],
            ];
            $short = self::dataset()['index'][self::norm($slug)]['short_name'] ?? null;
            if ($short && !FileCache::has('gift_attr:' . $short, 7 * 86400)) {
                $reqs['attr'] = ['url' => self::RAW . "/main/models/$short/prices.json", 'timeout' => 6];
            }
            $res = Http::multi($reqs);
            if (isset($res['attr']) && ($ad = Http::decode($res['attr']))) {
                FileCache::put('gift_attr:' . $short, self::attrsFrom($ad));
            }
            $tb = $res['tme'];
            $t = self::fromTme($slug, $n, $tb['status'] >= 200 && $tb['status'] < 400 ? $tb['body'] : null);
            if ($t && $t['model']) {
                return $t;
            }
            $f = self::fromFragment($slug, $n, Http::decode($res['frag']));
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
        $dir = Config::$root . '/data/cards';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $out = [];
        $reqs = [];
        foreach ($names as $k => $n) {
            if (!$n) {
                continue;
            }
            $f = $dir . '/g_' . md5(strtolower($n)) . '.jpg';
            if (is_file($f) && time() - (int)@filemtime($f) < 7 * 86400 && ($b = @file_get_contents($f))) {
                $out[$k] = $b;
                continue;
            }
            $reqs[$k] = ['url' => 'https://nft.fragment.com/gift/' . strtolower($n) . '.small.jpg', 'timeout' => 4];
        }
        foreach ($reqs ? Http::multi($reqs) : [] as $k => $r) {
            if ($r['status'] >= 200 && $r['status'] < 300 && strlen($r['body']) > 500) {
                $out[$k] = $r['body'];
                @file_put_contents($dir . '/g_' . md5(strtolower((string)$names[$k])) . '.jpg', $r['body']);
            }
        }
        ksort($out);
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
        if (Cache::get('tr_google_cool')) {
            return null;
        }
        $d = null;
        for ($try = 0; $try < 2 && !is_array($d); $try++) {
            $r = Http::request(['url' => 'https://translate.googleapis.com/translate_a/single', 'params' => ['client' => 'gtx', 'sl' => 'auto', 'tl' => $tl, 'dt' => 't', 'q' => $t], 'timeout' => 8]);
            if ($r['status'] === 429) {
                Cache::set('tr_google_cool', 1, 600);
                return null;
            }
            $d = Http::decode($r);
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
    const DC = [1 => 'Miami', 2 => 'Amsterdam', 3 => 'Miami', 4 => 'Amsterdam', 5 => 'Singapore'];

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

final class Instagram
{
    const LINK = '~(?:https?://)?(?:www\.)?instagram\.com/(?:[A-Za-z0-9_.]+/)?(?:p|reel|reels|tv|stories|share)/\S+~i';
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

    public static array $meta = [];

    private static function meta(array $n): void
    {
        $num = fn(...$v) => ($x = array_values(array_filter($v, fn($y) => is_numeric($y)))) ? (int)$x[0] : null;
        $cap = $n['caption']['text'] ?? ($n['edge_media_to_caption']['edges'][0]['node']['text'] ?? null);
        self::$meta = [
            'owner' => $n['user']['username'] ?? $n['owner']['username'] ?? null,
            'name' => $n['user']['full_name'] ?? $n['owner']['full_name'] ?? null,
            'text' => is_string($cap) ? $cap : null,
            'likes' => $num($n['like_count'] ?? null, $n['edge_media_preview_like']['count'] ?? null, $n['edge_liked_by']['count'] ?? null),
            'comments' => $num($n['comment_count'] ?? null, $n['edge_media_to_comment']['count'] ?? null, $n['edge_media_preview_comment']['count'] ?? null),
            'views' => $num($n['play_count'] ?? null, $n['ig_play_count'] ?? null, $n['video_play_count'] ?? null, $n['view_count'] ?? null, $n['video_view_count'] ?? null),
            'duration' => is_numeric($n['video_duration'] ?? null) ? (float)$n['video_duration'] : null,
            'date' => $num($n['taken_at'] ?? null, $n['taken_at_timestamp'] ?? null),
            'reel' => in_array($n['product_type'] ?? '', ['clips', 'igtv'], true) || !empty($n['is_video']) || !empty($n['video_versions']),
        ];
    }

    public static function post(string $link): ?array
    {
        self::$meta = [];
        $out = [];
        if (self::enabled() && ($d = self::call(['type' => 'postdownloader', 'link' => $link]))) {
            foreach ($d['result']['medias'] ?? [] as $m) {
                if (!empty($m['url'])) {
                    $out[] = ['url' => $m['url'], 'kind' => in_array(strtolower((string)($m['extension'] ?? '')), ['mp4', 'mov'], true) ? 'video' : 'photo'];
                }
            }
        }
        return $out ?: self::direct($link);
    }

    const ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    const MOBILE_UA = 'Instagram 275.0.0.27.98 Android (33/13; 280dpi; 720x1423; Xiaomi; Redmi 7; onclite; qcom; en_US; 458229237)';

    private static function proxy(): ?string
    {
        $p = Config::get('INSTAGRAM_PROXY');
        return $p !== '' ? $p : null;
    }

    private static function browser(array $extra = []): array
    {
        return $extra + [
            'User-Agent' => Http::UA,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Sec-Ch-Ua' => '"Chromium";v="131", "Google Chrome";v="131", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    private static function req(array $r): array
    {
        $p = self::proxy();
        return $r + ['timeout' => 10] + ($p ? ['proxy' => $p] : []);
    }

    public static function mediaId(string $sc): ?string
    {
        if (strlen($sc) > 28) {
            $sc = substr($sc, 0, -28);
        }
        $n = '0';
        foreach (str_split($sc) as $c) {
            $v = strpos(self::ALPHA, $c);
            if ($v === false) {
                return null;
            }
            $n = function_exists('bcmul') ? bcadd(bcmul($n, '64'), (string)$v) : (string)((int)$n * 64 + $v);
        }
        return $n;
    }

    private static function best(array $list): ?array
    {
        $best = null;
        foreach ($list as $v) {
            if (!empty($v['url']) && (!$best || ($v['width'] ?? 0) * ($v['height'] ?? 0) > ($best['width'] ?? 0) * ($best['height'] ?? 0))) {
                $best = $v;
            }
        }
        return $best;
    }

    private static function nodes(?array $n): array
    {
        if (!$n) {
            return [];
        }
        self::meta($n);
        $out = [];
        if (!empty($n['carousel_media']) || !empty($n['video_versions']) || !empty($n['image_versions2'])) {
            foreach (!empty($n['carousel_media']) ? $n['carousel_media'] : [$n] as $x) {
                if (!empty($x['video_versions']) && ($v = self::best($x['video_versions']))) {
                    $out[] = ['url' => $v['url'], 'kind' => 'video'];
                } elseif ($img = self::best($x['image_versions2']['candidates'] ?? [])) {
                    $out[] = ['url' => $img['url'], 'kind' => 'photo'];
                }
            }
            return $out;
        }
        $list = !empty($n['edge_sidecar_to_children']['edges']) ? array_column($n['edge_sidecar_to_children']['edges'], 'node') : [$n];
        foreach ($list as $x) {
            if (!empty($x['is_video']) && !empty($x['video_url'])) {
                $out[] = ['url' => $x['video_url'], 'kind' => 'video'];
            } elseif (!empty($x['display_url'])) {
                $out[] = ['url' => $x['display_url'], 'kind' => 'photo'];
            }
        }
        return $out;
    }

    private static function entry(string $name, string $html): ?array
    {
        if (preg_match('/\["' . preg_quote($name, '/') . '",\[\],(\{.*?\}),\d+\]/s', $html, $m)) {
            $d = json_decode($m[1], true);
            return is_array($d) ? $d : null;
        }
        return null;
    }

    private static function cookies(array $res): array
    {
        $c = [];
        foreach ($res['headers'] ?? [] as [$k, $v]) {
            if ($k === 'set-cookie' && preg_match('/^([^=;]+)=([^;]*)/', $v, $m) && $m[2] !== '' && $m[2] !== '""') {
                $c[$m[1]] = $m[2];
            }
        }
        return $c;
    }

    private static function fromEmbed(string $html): array
    {
        if ($html === '') {
            return [];
        }
        foreach (['/"init",\[\],\[(.*?)\]\],/s', '/"contextJSON":"((?:[^"\\\\]|\\\\.)*)"/s'] as $i => $rx) {
            if (!preg_match($rx, $html, $m)) {
                continue;
            }
            $ctx = $i === 0 ? (json_decode($m[1], true)['contextJSON'] ?? null) : json_decode('"' . $m[1] . '"');
            $ctx = is_string($ctx) ? json_decode($ctx, true) : null;
            $n = $ctx['gql_data']['shortcode_media'] ?? $ctx['gql_data']['xdt_shortcode_media'] ?? $ctx['context']['media'] ?? null;
            if ($out = self::nodes(is_array($n) ? $n : null)) {
                return $out;
            }
        }
        if (preg_match('/\\\\?"video_url\\\\?":\\\\?"(https:[^"]+?)\\\\?"/', $html, $vm)) {
            return [['url' => str_replace(['\\/', '\\u0026', '\\'], ['/', '&', ''], $vm[1]), 'kind' => 'video']];
        }
        if (preg_match('/<img[^>]+class="EmbeddedMediaImage"[^>]*>/', $html, $im) && preg_match('/src="([^"]+)"/', $im[0], $s2)) {
            return [['url' => html_entity_decode($s2[1]), 'kind' => 'photo']];
        }
        return [];
    }

    private static function fromPage(string $html): array
    {
        if (!preg_match_all('/"xig_polaris_media":(\{.*?\}),"extensions"/s', $html, $mm)) {
            return [];
        }
        foreach ($mm[1] as $j) {
            $d = json_decode($j, true);
            if ($out = self::nodes($d['if_not_gated_logged_out'] ?? null)) {
                return $out;
            }
        }
        return [];
    }

    public static function shortcode(string $link): ?string
    {
        if (preg_match('~/share/(?:reel/|p/)?([A-Za-z0-9_-]+)~', $link, $m)) {
            $r = Http::request(self::req(['url' => "https://www.instagram.com/share/$m[1]/", 'follow' => false, 'want_headers' => true, 'headers' => ['User-Agent' => 'curl/7.88.1', 'Accept' => '*/*'], 'timeout' => 10]));
            foreach ($r['headers'] ?? [] as [$k, $v]) {
                if ($k === 'location' && preg_match('~/(?:p|reel|reels|tv)/([A-Za-z0-9_-]+)~', $v, $x)) {
                    return $x[1];
                }
            }
            return null;
        }
        return preg_match('~/(?:p|reel|reels|tv)/([A-Za-z0-9_-]+)~', $link, $m) ? $m[1] : null;
    }

    public static function direct(string $link): ?array
    {
        $sc = self::shortcode($link);
        $pk = $sc ? self::mediaId($sc) : null;
        if (!$sc || !$pk) {
            self::$lastStatus = 404;
            return null;
        }
        $mobile = ['User-Agent' => self::MOBILE_UA, 'X-IG-App-Locale' => 'en_US', 'X-IG-Device-Locale' => 'en_US', 'X-IG-Mapped-Locale' => 'en_US',
            'Accept-Language' => 'en-US', 'X-FB-HTTP-Engine' => 'Liger', 'X-IG-App-ID' => '567067343352427', 'Accept' => '*/*'];
        $res = Http::multi([
            'mobile' => self::req(['url' => "https://i.instagram.com/api/v1/media/$pk/info/", 'headers' => $mobile]),
            'embed' => self::req(['url' => "https://www.instagram.com/p/$sc/embed/captioned/", 'headers' => self::browser()]),
            'page' => self::req(['url' => "https://www.instagram.com/p/$sc/", 'headers' => self::browser(), 'want_headers' => true]),
        ]);
        $wantVideo = (bool)preg_match('~/(?:reel|reels|tv)/~i', $link);
        $hasVideo = fn(array $o) => (bool)array_filter($o, fn($x) => $x['kind'] === 'video');
        $fallback = null;
        $meta = [];
        $take = function (array $out) use ($wantVideo, $hasVideo, &$fallback, &$meta) {
            if (!$out) {
                return false;
            }
            if (!$wantVideo || $hasVideo($out)) {
                return true;
            }
            if ($fallback === null) {
                $fallback = $out;
                $meta = self::$meta;
            }
            return false;
        };
        $d = Http::decode($res['mobile']);
        if ($take($out = self::nodes($d['items'][0] ?? null))) {
            return $out;
        }
        $html = (string)($res['page']['body'] ?? '');
        if ($take($out = self::fromPage($html))) {
            return $out;
        }
        if ($take($out = self::fromEmbed((string)($res['embed']['body'] ?? '')))) {
            return $out;
        }
        $lsd = self::entry('LSD', $html)['token'] ?? (preg_match('/"lsd":"([^"]+)"/', $html, $lm) ? $lm[1] : rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '='));
        $sec = self::entry('InstagramSecurityConfig', $html);
        $site = self::entry('SiteData', $html) ?? [];
        $polaris = self::entry('PolarisSiteData', $html) ?? [];
        $ck = self::cookies($res['page']);
        $csrf = $sec['csrf_token'] ?? $ck['csrftoken'] ?? '';
        $cookie = implode('; ', array_filter([
            $csrf !== '' ? "csrftoken=$csrf" : null,
            !empty($polaris['device_id']) ? 'ig_did=' . $polaris['device_id'] : (isset($ck['ig_did']) ? 'ig_did=' . $ck['ig_did'] : null),
            !empty($polaris['machine_id']) ? 'mid=' . $polaris['machine_id'] : (isset($ck['mid']) ? 'mid=' . $ck['mid'] : null),
            isset($ck['datr']) ? 'datr=' . $ck['datr'] : null,
            'ig_nrcb=1', 'wd=1280x720', 'dpr=2',
        ]));
        $api = ['User-Agent' => Http::UA, 'Accept' => '*/*', 'Accept-Language' => 'en-US,en;q=0.9', 'Content-Type' => 'application/x-www-form-urlencoded',
            'X-IG-App-ID' => '936619743392459', 'X-FB-LSD' => $lsd, 'X-CSRFToken' => $csrf, 'X-IG-WWW-Claim' => '0', 'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://www.instagram.com', 'Referer' => "https://www.instagram.com/p/$sc/", 'Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Dest' => 'empty', 'Cookie' => $cookie];
        $common = ['av' => '0', '__d' => 'www', '__user' => '0', '__a' => '1', '__req' => 'b', '__hs' => $site['haste_session'] ?? '20126.HYP:instagram_web_pkg.2.1...0',
            'dpr' => '2', '__ccg' => 'EXCELLENT', '__rev' => (string)($site['__spin_r'] ?? '1019933358'), '__hsi' => (string)($site['hsi'] ?? '7436540909012459023'),
            '__comet_req' => preg_match('/__comet_req=(\d+)/', $html, $cm) ? $cm[1] : '7', 'lsd' => $lsd,
            'jazoest' => preg_match('/jazoest=(\d+)/', $html, $jm) ? $jm[1] : (string)random_int(2000, 29999),
            '__spin_r' => (string)($site['__spin_r'] ?? '1019933358'), '__spin_b' => $site['__spin_b'] ?? 'trunk', '__spin_t' => (string)($site['__spin_t'] ?? time()),
            'fb_api_caller_class' => 'RelayModern', 'server_timestamps' => 'true'];
        $res2 = Http::multi([
            'gql' => self::req(['url' => 'https://www.instagram.com/graphql/query', 'method' => 'POST', 'headers' => $api + ['X-FB-Friendly-Name' => 'PolarisPostActionLoadPostQueryQuery', 'X-ASBD-ID' => '129477'],
                'form' => http_build_query($common + ['fb_api_req_friendly_name' => 'PolarisPostActionLoadPostQueryQuery', 'doc_id' => '8845758582119845',
                    'variables' => json_encode(['shortcode' => $sc, 'fetch_tagged_user_count' => null, 'hoisted_comment_id' => null, 'hoisted_reply_id' => null])])]),
            'polaris' => self::req(['url' => 'https://www.instagram.com/api/graphql', 'method' => 'POST', 'headers' => $api + ['X-FB-Friendly-Name' => 'PolarisLoggedOutDesktopWWWPostRootContentQuery', 'X-ASBD-ID' => '359341'],
                'form' => http_build_query($common + ['fb_api_req_friendly_name' => 'PolarisLoggedOutDesktopWWWPostRootContentQuery', 'doc_id' => '27130156389949648',
                    'variables' => json_encode(['media_id' => $pk], JSON_UNESCAPED_SLASHES)])]),
        ]);
        $g = Http::decode($res2['gql']);
        if ($take($out = self::nodes($g['data']['xdt_shortcode_media'] ?? $g['data']['shortcode_media'] ?? null))) {
            return $out;
        }
        $p = Http::decode($res2['polaris']);
        if ($take($out = self::nodes($p['data']['xig_polaris_media']['if_not_gated_logged_out'] ?? null))) {
            return $out;
        }
        if ($fallback) {
            self::$meta = $meta;
            return $fallback;
        }
        error_log('instagram ' . $sc . ': mobile=' . ($res['mobile']['status'] ?? 0) . ' embed=' . ($res['embed']['status'] ?? 0) . ' page=' . ($res['page']['status'] ?? 0)
            . ' gql=' . ($res2['gql']['status'] ?? 0) . ' polaris=' . ($res2['polaris']['status'] ?? 0));
        self::$lastStatus = 404;
        return null;
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

final class Pinterest
{
    const LINK = '~https?://(?:[a-z]{2,3}\.)?(?:www\.)?(?:pinterest\.[a-z]{2,3}(?:\.[a-z]{2})?/pin/[^\s/?#]+|pin\.it/[A-Za-z0-9]+)~i';
    const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    public static array $meta = [];

    public static function link(string $t): ?string
    {
        return preg_match(self::LINK, $t, $m) ? $m[0] : null;
    }

    public static function id(string $link): ?string
    {
        if (preg_match('~/pin/(?:[^/?#]*--)?(\d{5,})~', $link, $m)) {
            return $m[1];
        }
        if (stripos($link, 'pin.it/') === false) {
            return null;
        }
        $r = Http::request(['url' => $link, 'timeout' => 8, 'headers' => ['User-Agent' => self::UA]]);
        foreach ([$r['final'] ?? '', $r['body']] as $hay) {
            if (preg_match('~pinterest\.[a-z.]+/pin/(?:[^/?#"]*--)?(\d{5,})~', (string)$hay, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    private static function fromPage(string $html, string $id): ?array
    {
        if (!preg_match_all('~<script[^>]*>(\{.*?\})</script>~s', $html, $mm)) {
            return null;
        }
        foreach ($mm[1] as $js) {
            if (!str_contains($js, 'initialReduxState')) {
                continue;
            }
            $d = json_decode($js, true);
            $pins = $d['props']['initialReduxState']['pins'] ?? $d['initialReduxState']['pins'] ?? null;
            if (is_array($pins) && $pins) {
                return $pins[$id] ?? reset($pins);
            }
        }
        return null;
    }

    public static function pin(string $link): ?array
    {
        $id = self::id($link);
        if (!$id) {
            return null;
        }
        $data = json_encode(['options' => ['id' => $id, 'field_set_key' => 'detailed'], 'context' => new stdClass()]);
        $h = ['User-Agent' => self::UA, 'Accept-Language' => 'en-US,en;q=0.9'];
        $reqs = [
            'api' => ['url' => 'https://www.pinterest.com/resource/PinResource/get/', 'params' => ['source_url' => "/pin/$id/", 'data' => $data], 'timeout' => 8,
                'headers' => $h + ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', 'X-Pinterest-PWS-Handler' => 'www/pin/[id].js']],
            'page' => ['url' => "https://www.pinterest.com/pin/$id/", 'timeout' => 8, 'headers' => $h + ['Accept' => 'text/html']],
        ];
        $out = Http::collect($reqs, fn($k) => $k, function ($k, $d) use ($id) {
            $p = $k === 'api' ? ($d['resource_response']['data'] ?? null) : (is_string($d) ? self::fromPage($d, $id) : null);
            return is_array($p) && self::media($p) ? $p : null;
        }, 9, fn($o) => isset($o['api']) || isset($o['page']) && self::media($o['page'])[0]['kind'] === 'video');
        $p = $out['api'] ?? $out['page'] ?? null;
        if ($p && isset($out['api'], $out['page']) && self::media($out['api'])[0]['kind'] !== 'video' && self::media($out['page'])[0]['kind'] === 'video') {
            $p = $out['page'];
        }
        return $p ? $p + ['id' => $id] : null;
    }

    private static function vurl($v): ?array
    {
        $u = is_array($v) ? ($v['url'] ?? null) : null;
        return is_string($u) && preg_match('~^https?://[a-z0-9.-]*pinimg\.com/\S+?\.(mp4|m3u8)(?:\?\S*)?$~i', $u, $mm) ? [$u, strtolower($mm[1]), (int)($v['width'] ?? 0)] : null;
    }

    private static function scan($node, array &$out): void
    {
        if (!is_array($node)) {
            return;
        }
        $list = array_values(array_filter(array_map([self::class, 'vurl'], $node)));
        if (!$list && ($one = self::vurl($node))) {
            $list = [$one];
        }
        if ($list) {
            $key = preg_replace('~(?:_t\d+|_\d+w)?\.(?:mp4|m3u8)$~i', '', basename((string)parse_url($list[0][0], PHP_URL_PATH)));
            $out[$key] = array_merge($out[$key] ?? [], $list);
            return;
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                self::scan($v, $out);
            }
        }
    }

    private static function video(array $list): array
    {
        $best = null;
        $w = -1;
        foreach ($list as [$u, $ext, $width]) {
            if ($ext === 'mp4' && $width >= $w) {
                $w = $width;
                $best = $u;
            }
        }
        $alts = [];
        foreach ($list as [$u, $ext]) {
            if ($ext === 'm3u8') {
                $alts[] = preg_replace(['~/hls/~i', '~\.m3u8(?:\?\S*)?$~i'], ['/720p/', '.mp4'], $u);
            }
        }
        $alts = array_values(array_unique(array_diff($alts, [$best])));
        return ['kind' => 'video', 'url' => $best ?? array_shift($alts), 'alts' => $alts];
    }

    private static function photo(array $images): ?array
    {
        $img = $images['originals']['url'] ?? $images['orig']['url'] ?? $images['736x']['url'] ?? null;
        return $img ? ['kind' => 'photo', 'url' => $img, 'alts' => array_values(array_filter([$images['736x']['url'] ?? null], fn($u) => $u !== $img))] : null;
    }

    public static function media(array $p): array
    {
        $v = [];
        self::scan($p['videos'] ?? $p['video'] ?? null, $v);
        if ($v) {
            return [self::video(reset($v))];
        }
        $items = [];
        foreach ((array)($p['story_pin_data']['pages'] ?? []) as $pg) {
            $v = [];
            self::scan($pg, $v);
            if ($v) {
                array_push($items, ...array_map([self::class, 'video'], array_values($v)));
                continue;
            }
            foreach ((array)($pg['blocks'] ?? []) as $b) {
                if ($ph = self::photo((array)($b['image']['images'] ?? []))) {
                    $items[] = $ph;
                }
            }
        }
        if ($items) {
            return array_slice($items, 0, 10);
        }
        $v = [];
        self::scan(array_diff_key($p, array_flip(['pinner', 'native_creator', 'board', 'origin_pinner', 'closeup_attribution', 'rich_metadata', 'attribution'])), $v);
        if ($v) {
            return array_slice(array_map([self::class, 'video'], array_values($v)), 0, 10);
        }
        $pics = [];
        foreach ((array)($p['carousel_data']['carousel_slots'] ?? []) as $sl) {
            if ($ph = self::photo((array)($sl['images'] ?? []))) {
                $pics[] = $ph;
            }
        }
        if (!$pics && ($ph = self::photo((array)($p['images'] ?? [])))) {
            $pics[] = $ph;
        }
        return array_slice($pics, 0, 10);
    }

    public static function info(array $p): array
    {
        $text = trim((string)($p['description'] ?? '') ?: (string)($p['closeup_unified_description'] ?? ''));
        return [
            'title' => trim((string)($p['title'] ?? '') ?: (string)($p['grid_title'] ?? '')),
            'saves' => $p['aggregated_pin_data']['aggregated_stats']['saves'] ?? $p['repin_count'] ?? null,
            'date' => !empty($p['created_at']) ? (strtotime((string)$p['created_at']) ?: null) : null,
            'text' => $text,
        ];
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
        if (MTProto::enabled() && ($mt = MTProto::resolve($username))) {
            return $mt;
        }
        $c = TG::api('getChat', ['chat_id' => '@' . $username]);
        if ($c) {
            if (($c['type'] ?? '') === 'private') {
                return ['kind' => 'user', 'id' => (int)$c['id'], 'name' => UI::fullName($c), 'username' => $c['username'] ?? $username];
            }
            return ['kind' => 'chat', 'id' => (int)$c['id'], 'name' => $c['title'] ?? $username, 'username' => $c['username'] ?? $username];
        }
        return MTProto::enabled() ? MTProto::resolve($username) : null;
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
        if ($hm && $prev !== $name) {
            DB::q('INSERT OR REPLACE INTO names(user_id, name, ts) VALUES(?, ?, ?)', [$uid, $name, time()]);
        }
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
            if ((int)$j['msgs'] < 5) {
                DB::q('UPDATE joins SET msgs=msgs+1 WHERE chat_id=? AND user_id=?', [$chat, $u['id']]);
            }
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
        if (mb_strlen($text) >= 15 && ($hasLink || $fwd || $score > 0 || preg_match('/@[A-Za-z]/', $text))) {
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
