<?php

require __DIR__ . '/../src/bootstrap.php';

$dbFile = sys_get_temp_dir() . '/mybot_test_' . getmypid() . '.db';
@unlink($dbFile);
Config::set('BOT_TOKEN', '42:TEST');
Config::set('ADMIN_IDS', '999');
Config::set('DB_PATH', $dbFile);
Config::set('CARDINFO_API_URL', 'http://cardinfo.test/api/');
Config::set('PHONE_CHECK_API_URL', 'https://phone.test/check.php');
DB::connect();
Market::reset([]);

$GLOBALS['calls'] = [];
$GLOBALS['mid'] = 100;
$GLOBALS['reject_premium'] = false;
$GLOBALS['user_gifts'] = ['total_count' => 0, 'gifts' => []];
$GLOBALS['http'] = [];
const BOT = ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'TestBot'];
const NON_MEMBER = -1001;

TG::$fake = function (string $method, array $p) {
    $GLOBALS['calls'][] = [$method, $p];
    $msg = function ($chat, $text = null, $caption = null) {
        return ['message_id' => ++$GLOBALS['mid'], 'date' => time(), 'chat' => ['id' => $chat, 'type' => $chat > 0 ? 'private' : 'supergroup'], 'from' => BOT, 'text' => $text, 'caption' => $caption];
    };
    $hasPremium = str_contains((string)($p['text'] ?? ''), '<tg-emoji');
    switch ($method) {
        case 'getMe':
            return ['ok' => true, 'result' => BOT];
        case 'sendMessage':
        case 'editMessageText':
            if ($GLOBALS['reject_premium'] && $hasPremium) {
                return ['ok' => false, 'description' => 'Bad Request: DOCUMENT_INVALID'];
            }
            return ['ok' => true, 'result' => $msg($p['chat_id'], $p['text'])];
        case 'sendPhoto':
            if ($GLOBALS['reject_premium'] && str_contains((string)($p['caption'] ?? ''), '<tg-emoji')) {
                return ['ok' => false, 'description' => 'Bad Request: DOCUMENT_INVALID'];
            }
            return ['ok' => true, 'result' => $msg($p['chat_id'], null, $p['caption'] ?? null) + ['photo' => [['file_id' => 'AgACAgQAAxkBAAIBfakefileid' . $GLOBALS['mid']]]]];
        case 'sendVideo':
        case 'sendDocument':
            return ['ok' => true, 'result' => $msg($p['chat_id'], null, $p['caption'] ?? null)];
        case 'getChatMember':
            $u = $p['user_id'];
            if ($p['chat_id'] == NON_MEMBER) {
                return ['ok' => true, 'result' => ['status' => 'left', 'user' => ['id' => $u, 'is_bot' => false, 'first_name' => 'u']]];
            }
            return ['ok' => true, 'result' => ['status' => $u == 777 ? 'administrator' : 'member', 'user' => ['id' => $u, 'is_bot' => false, 'first_name' => 'u']]];
        case 'getChat':
            return ['ok' => false, 'description' => 'Bad Request: chat not found'];
        case 'getUserProfilePhotos':
            return ['ok' => true, 'result' => ['total_count' => 0, 'photos' => []]];
        case 'getUserGifts':
            return ['ok' => true, 'result' => $GLOBALS['user_gifts']];
        case 'getCustomEmojiStickers':
            return ['ok' => true, 'result' => []];
        case 'copyMessage':
            return ['ok' => true, 'result' => ['message_id' => 1]];
        default:
            return ['ok' => true, 'result' => true];
    }
};

Http::$fake = function (array $r) {
    $url = $r['url'];
    foreach ($GLOBALS['http'] as $pattern => $resp) {
        if (str_contains($url, $pattern)) {
            $v = is_callable($resp) ? $resp($r) : $resp;
            if ($v === null) {
                continue;
            }
            return ['status' => 200, 'body' => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)];
        }
    }
    return ['status' => 0, 'body' => ''];
};

function base_http(): void
{
    $prices = ['TON' => 3.0, 'TRX' => 0.25, 'BTC' => 100000.0, 'ETH' => 4000.0];
    $GLOBALS['http'] = [
        'api.binance.com/api/v3/ticker/24hr' => function ($r) use ($prices) {
            $sym = str_replace('USDT', '', $r['params']['symbol']);
            return isset($prices[$sym]) ? ['lastPrice' => (string)$prices[$sym], 'priceChangePercent' => '1.5'] : null;
        },
        'api.nobitex.ir/market/stats' => ['stats' => ['usdt-rls' => ['latest' => '1000000']]],
        'open.er-api.com' => ['rates' => ['USD' => 1, 'AED' => 3.6725, 'EUR' => 0.9, 'GBP' => 0.8, 'TRY' => 41]],
        'translate.googleapis.com' => fn($r) => [[['[' . $r['params']['tl'] . '] ' . $r['params']['q'], $r['params']['q']]]],
    ];
}


function mt_fake(): void
{
    MTProto::$fake = function (string $method, array $args) {
        $GLOBALS['mt_calls'][] = [$method, $args];
        if ($method === 'contacts.resolveUsername') {
            if ($args['username'] !== 'tofan') {
                throw new MTError(400, 'USERNAME_NOT_OCCUPIED');
            }
            return ['_' => 'contacts.resolvedPeer', 'peer' => ['_' => 'peerUser', 'user_id' => 777000111], 'chats' => [],
                'users' => [['_' => 'user', 'id' => 777000111, 'access_hash' => -55, 'first_name' => 'Tofan', 'username' => 'tofan']]];
        }
        if ($method === 'payments.getSavedStarGifts') {
            return ['_' => 'payments.savedStarGifts', 'count' => 2, 'gifts' => [
                ['_' => 'savedStarGift', 'gift' => ['_' => 'starGiftUnique', 'title' => 'Plush Pepe', 'slug' => 'PlushPepe-1', 'num' => 1, 'value_usd_amount' => 300000,
                    'attributes' => [['_' => 'starGiftAttributeModel', 'name' => 'Kek', 'rarity' => ['permille' => 5]], ['_' => 'starGiftAttributeBackdrop', 'name' => 'Black', 'center_color' => 0x363738, 'edge_color' => 0x0E0F0F, 'rarity' => ['permille' => 9]]]]],
                ['_' => 'savedStarGift', 'gift' => ['_' => 'starGift', 'id' => 5170233102089322756, 'stars' => 100, 'availability_total' => 10], 'convert_stars' => 85],
            ], 'chats' => [], 'users' => []];
        }
        throw new MTError(400, 'METHOD_INVALID');
    };
}

$pass = 0;
$fail = 0;
function check($cond, string $name): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: $name\n";
    }
}

function reset_calls(): void
{
    $GLOBALS['calls'] = [];
}

function sent(array $methods = ['sendMessage', 'sendPhoto', 'editMessageText']): array
{
    return array_values(array_filter($GLOBALS['calls'], fn($c) => in_array($c[0], $methods, true)));
}

function last_text(): ?string
{
    $s = sent();
    if (!$s) {
        return null;
    }
    $c = end($s)[1];
    return $c['text'] ?? $c['caption'] ?? null;
}

function msg(string $text, int $chat = 5, int $user = 5, ?array $reply = null, ?array $entities = null, string $name = 'Ali'): array
{
    $m = ['message_id' => ++$GLOBALS['mid'], 'date' => time(), 'chat' => ['id' => $chat, 'type' => $chat > 0 ? 'private' : 'supergroup', 'title' => 'G'],
        'from' => ['id' => $user, 'is_bot' => false, 'first_name' => $name], 'text' => $text];
    if ($reply) {
        $m['reply_to_message'] = $reply;
    }
    if ($entities) {
        $m['entities'] = $entities;
    }
    return $m;
}

function feed(array $m): void
{
    Bot::handle(['update_id' => ++$GLOBALS['mid'], 'message' => $m]);
}

function cb(string $data, int $user, int $chat, int $mid = 900, string $text = 'x'): void
{
    Bot::handle(['update_id' => ++$GLOBALS['mid'], 'callback_query' => ['id' => (string)$GLOBALS['mid'], 'from' => ['id' => $user, 'is_bot' => false, 'first_name' => 'u'],
        'chat_instance' => 'c', 'data' => $data, 'message' => ['message_id' => $mid, 'date' => time(), 'chat' => ['id' => $chat, 'type' => $chat > 0 ? 'private' : 'supergroup'], 'from' => BOT, 'text' => $text]]]);
}

function buttons(array $call): array
{
    $out = [];
    foreach ($call[1]['reply_markup']['inline_keyboard'] ?? [] as $row) {
        foreach ($row as $b) {
            $out[] = $b;
        }
    }
    return $out;
}

base_http();

check(Parse::amount('10 تتر') === [10.0, 'تتر'], 'parse 10 tether');
check(Parse::amount('۱۰ تتر') === [10.0, 'تتر'], 'parse persian digits');
check(Parse::amount('500,000 تومن') === [500000.0, 'تومن'], 'parse separators');
check(Parse::amount('2 میلیون تومان') === [2000000.0, 'تومان'], 'parse million');
check(Parse::amount('2.5m ریال') === [2500000.0, 'ریال'], 'parse m');
check(Parse::amount('$100') === [100.0, '$'], 'parse $100');
check(Parse::amount('100$') === [100.0, '$'], 'parse 100$');
check(Parse::amount('10 btc') === [10.0, 'btc'], 'parse btc');
check(Parse::amount('3 bnb') === [3.0, 'bnb'], 'parse bnb');
check(Parse::amount('5 میلیون') === null && Parse::amount('200+79') === null && Parse::amount('سلام خوبی') === null, 'parse rejects');
foreach ([['تتر', 'crypto', 'USDT'], ['تون', 'crypto', 'TON'], ['ton', 'crypto', 'TON'], ['ترون', 'crypto', 'TRX'], ['تومان', 'toman', 'IRT'], ['ریال', 'rial', 'IRR'],
          ['درهم', 'fiat', 'AED'], ['دلار', 'fiat', 'USD'], ['$', 'fiat', 'USD'], ['لیر', 'fiat', 'TRY'], ['ریال عربستان', 'fiat', 'SAR'], ['دلار کانادا', 'fiat', 'CAD'],
          ['استارز', 'stars', 'STARS'], ['pepe', 'crypto', 'PEPE'], ['xyzcoin', 'crypto_guess', 'XYZCOIN']] as [$u, $k, $c]) {
    check(Currencies::resolve($u) === [$k, $c], "resolve $u");
}
foreach ([['200+79', '279'], ['698*789', '550,722'], ['698×789', '550,722'], ['797÷788', '1.0114213198'], ['(2+3)*4', '20'], ['2^10', '1,024'], ['۱۰۰-۲۵', '75'], ['10/4', '2.5'], ['-2^2', '-4'], ['7%3', '1']] as [$e, $r]) {
    $x = Parse::expression($e);
    check($x && Calc::format(Calc::evaluate($x)) === $r, "calc $e");
}
foreach (['0912-345-6789', '1403/05/12', 'سلام', '123', '+98'] as $e) {
    check(Parse::expression($e) === null, "calc rejects $e");
}
$threw = false;
try {
    Calc::evaluate('1/0');
} catch (Throwable $e) {
    $threw = true;
}
check($threw, 'calc div zero');
foreach ([['0xdAC17F958D2ee523a2206206994597C13D831ec7', 'evm'], ['TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'tron'], ['bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 'btc'],
          ['1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'btc'], ['DH5yaieqoZN36fDVciNyRueRGvGLR3mr7L', 'doge'], ['EQCD39VS5jcptHL8vMjEXrzGaRcCVYto7HUn4bpAOg8xqB2N', 'ton'],
          ['rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh', 'xrp'], ['9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM', 'sol'], ['0x' . str_repeat('a', 64), 'hex64x'],
          [str_repeat('f', 64), 'hex64'], [str_repeat('5', 88), 'solsig']] as [$t, $k]) {
    check(Chain::classify($t) === $k, "classify $k");
}
check(Chain::classify('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u') === null, 'tron checksum');
check(Chain::tronB58('41a614f803b6fd780986a42c78ec9c7f77e6ded13c') === 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'tron hex to b58');
check(Chain::extract('check this TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t please', true) === null, 'extract strict');
check(Chain::extract('https://etherscan.io/tx/0x' . str_repeat('b', 64), true) === ['hex64x', '0x' . str_repeat('b', 64)], 'extract explorer');
check(Fmt::jalaliParts(2026, 10, 30) === [1405, 8, 8] && Fmt::jalaliParts(2024, 3, 20) === [1403, 1, 1], 'jalali');
check(Fmt::num(1234567.891) === '1,234,568' && Fmt::num(0.0000123456) === '0.00001235' && Fmt::toman(1050000.4) === '1,050,000', 'number format');
check(UI::fill("<b>T</b>\n<blockquote>A: {a}\nB: {b}\nC: {c}</blockquote>\nend {d}", ['a' => null, 'b' => '2', 'c' => null, 'd' => 'x']) === "<b>T</b>\n<blockquote>B: 2</blockquote>\nend x", 'fill drops lines');
check(Banking::luhn('6037997599503209') && !Banking::luhn('6037997599503201') && Banking::bankOfCard('6037997599503209') === 'ملی', 'card helpers');
check(Banking::ibanOk('IR820540102680020817909002') && !Banking::ibanOk('IR820540102680020817909003') && Banking::bankOfIban('IR820540102680020817909002') === 'پارسیان', 'iban');
check(Phone::clean('09121234567') === '+989121234567' && Phone::clean('+1 500 555 0006') === '+15005550006', 'phone clean');
check(Guard::scammy('بیا پیوی حرف بزنیم') && Guard::scammy('pv bia') && !Guard::scammy('سلام قیمت تتر چنده؟'), 'scam phrases');
check(Guard::clockInName('Ali | 12:45') === [12, 45] && Guard::clockInName('𝟏𝟐:𝟑𝟎 Sara') === [12, 30], 'clock in name');
$tg = Tgju::parse('<tr id="f-diff_usd"><th>USD دلار</th><td>1,050,000</td><td>1</td><td>0.1%</td></tr><tr id="f-diff_try"><th>لیر ترکیه</th><td>24,800</td></tr>');
check(($tg['USD']['toman'] ?? 0) == 105000 && ($tg['TRY']['toman'] ?? 0) == 2480, 'tgju parse');
check(Entities::html('Hi bold 😎', [['type' => 'bold', 'offset' => 3, 'length' => 4], ['type' => 'custom_emoji', 'offset' => 8, 'length' => 2, 'custom_emoji_id' => '55']]) === 'Hi <b>bold</b> <tg-emoji emoji-id="55">😎</tg-emoji>', 'entities html');
check(Persian::visual('ارزش کل (TON)') !== '' && Persian::shape('سلام') !== 'سلام', 'persian shaping');
check(Account::created(100000000) < Account::created(6000000000) && Account::level(10000) > 5, 'account helpers');

reset_calls();
feed(msg('/start'));
$s = sent()[0] ?? null;
check($s && str_contains($s[1]['text'], 'خوش اومدی'), 'start text');
check($s && array_filter(buttons($s), fn($b) => str_contains($b['url'] ?? '', 'startgroup')), 'start footer');

reset_calls();
feed(msg('10 تتر'));
$t = last_text();
check(str_contains($t, '1,000,000') && str_contains($t, '3.3333') && str_contains($t, '40') && !str_contains($t, '{'), 'crypto conversion');

reset_calls();
feed(msg('500000 تومان'));
check(str_contains(last_text(), '500,000') && str_contains(last_text(), '<code>5</code> USDT'), 'toman conversion');

reset_calls();
feed(msg('500 درهم'));
$ph = sent(['sendPhoto']);
check($ph && str_contains($ph[0][1]['caption'], 'درهم امارات') && str_starts_with($ph[0][1]['photo']['__bytes'], "\xFF\xD8"), 'fiat card');

reset_calls();
feed(msg('1000 استارز'));
check(str_contains((string)last_text(), 'استارز'), 'stars');

reset_calls();
feed(msg('698*789'));
check(str_contains(last_text(), '550,722'), 'calculator');
$target = msg('hello', 5, 12345, null, null, 'Target');
reset_calls();
feed(msg('UID', 5, 5, $target));
$s = sent()[0];
check(str_contains($s[1]['text'], '12345') && buttons($s)[0]['copy_text']['text'] === '12345', 'uid');

reset_calls();
$src = msg('Hello world', -100, 8);
feed(msg('ترجمه', -100, 7, $src));
$prompt = sent(['sendMessage'])[0];
check(array_map('count', $prompt[1]['reply_markup']['inline_keyboard']) === [3, 2], 'translate 3+2');
$pmid = $GLOBALS['mid'] - 0;
$row = DB::one('SELECT message_id FROM translations ORDER BY created_at DESC LIMIT 1');
reset_calls();
cb('tr:fa', 8, -100, (int)$row['message_id']);
check(($GLOBALS['calls'][0][0] ?? '') === 'answerCallbackQuery' && !empty($GLOBALS['calls'][0][1]['show_alert']), 'translate not yours');
reset_calls();
cb('tr:fa', 7, -100, (int)$row['message_id']);
$ed = sent(['editMessageText']);
check($ed && str_contains($ed[0][1]['text'], '[fa] Hello world'), 'translate result');
reset_calls();
cb('tr:en', 7, -100, (int)$row['message_id']);
check(!sent(['editMessageText']), 'translate once');

reset_calls();
feed(msg('/emoji 😎', 5, 5, null, [['type' => 'custom_emoji', 'offset' => 7, 'length' => 2, 'custom_emoji_id' => '5368324170671202286']]));
check(last_text() === '<code>5368324170671202286</code>', 'emoji cmd');

Settings::set('emoji_map', ['🪙' => '111']);
$GLOBALS['reject_premium'] = true;
reset_calls();
feed(msg('10 تتر'));
$sm = sent(['sendPhoto']);
check(count($sm) === 2 && str_contains($sm[0][1]['caption'], '<tg-emoji') && !str_contains($sm[1][1]['caption'], '<tg-emoji'), 'premium fallback');
$GLOBALS['reject_premium'] = false;
Settings::set('emoji_map', Settings::defaults()['emoji_map']);

Settings::set('force_channels', [['id' => NON_MEMBER, 'title' => 'Main', 'url' => 'https://t.me/main']]);
reset_calls();
feed(msg('salam', -200, 55));
$del = array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'deleteMessage');
$w = sent(['sendMessage']);
check($del && $w && str_contains($w[0][1]['text'], 'کانال') && buttons($w[0])[0]['url'] === 'https://t.me/main', 'force join group');
reset_calls();
feed(msg('salam', -200, 777));
check(!array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'deleteMessage'), 'force join admin exempt');
Settings::set('force_channels', []);

reset_calls();
feed(msg('بیا پیوی', -300, 61));
check(str_contains((string)last_text(), 'هشدار'), 'scam phrase warn');
$scammer = msg('hi', -300, 62, null, null, 'Bad');
reset_calls();
feed(msg('/scam', -300, 63, $scammer));
$go = sent(['sendMessage'])[0];
$url = buttons($go)[0]['url'];
check(str_contains($url, 'start=scam_'), 'scam go private');
$sid = (int)substr($url, strrpos($url, '_') + 1);
feed(msg("/start scam_$sid", 63, 63));
check(str_contains(last_text(), 'مدارک'), 'scam ask evidence');
feed(msg('he took my money', 63, 63));
reset_calls();
cb("scam:submit:$sid", 63, 63);
$r = DB::scam($sid);
check($r['status'] === 'pending' && $r['evidence'], 'scam pending');
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMessage' && $c[1]['chat_id'] == 999), 'scam sent to admin');
reset_calls();
cb("scamadm:approve:$sid", 999, 999);
check(DB::scam($sid)['status'] === 'approved', 'scam approved');
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMessage' && $c[1]['chat_id'] == -300 && str_contains($c[1]['text'], 'کلاهبردار')), 'scam broadcast');
reset_calls();
feed(msg('/check', -300, 64, $scammer));
check(str_contains(last_text(), 'لیست کلاهبرداران'), 'scam check');

reset_calls();
feed(msg('/panel', 999, 999));
check(str_contains((string)last_text(), 'پنل مدیریت'), 'admin panel');
cb('adm:tedit:calc_result', 999, 999);
feed(msg('حاصل {expr} میشه {result}', 999, 999));
reset_calls();
feed(msg('2+2'));
check(last_text() === 'حاصل 2+2 میشه 4', 'admin edit text');
reset_calls();
feed(msg('/panel', 5, 5));
check(!str_contains((string)last_text(), 'پنل مدیریت'), 'non admin panel');

foreach (['home', 'texts:0', 'texts:1', 'text:conv_crypto', 'btns', 'btn:btn_channel', 'bstyle:btn_channel:danger', 'fj', 'set:1', 'set:2', 'texts:price', 'text:conv_crypto:price', 'tog:anti_self:set', 'scams', 'stats', 'bcmenu'] as $d) {
    reset_calls();
    cb("adm:$d", 999, 999);
    check($GLOBALS['calls'] && !array_filter($GLOBALS['calls'], fn($c) => ($c[1]['text'] ?? '') === '⛔️'), "admin nav $d");
}
check((Settings::get('buttons')['btn_channel']['style'] ?? '') === 'danger' && Settings::get('anti_self') === false, 'admin toggles');
Settings::set('anti_self', true);
reset_calls();
feed(msg('/start', 6, 6));
$add = array_values(array_filter(buttons(sent(['sendMessage'])[0]), fn($b) => str_contains($b['url'] ?? '', 'startgroup')))[0];
check(($add['icon_custom_emoji_id'] ?? '') === '5256143829672672750' && !str_contains($add['text'], '👥'), 'button premium emoji');
check(str_contains(sent(['sendMessage'])[0][1]['text'], '<tg-emoji emoji-id="5260399854500191689">'), 'text premium emoji');
cb('adm:val:usdt_toman_manual', 999, 999);
feed(msg('۱۰۵۰۰۰', 999, 999));
check(Settings::get('usdt_toman_manual') === 105000, 'admin numeric setting');
Settings::set('usdt_toman_manual', 0);
Cache::forget('usdt_toman');
Settings::set('emoji_map', []);

reset_calls();
feed(msg('/settings', -400, 777));
check(str_contains(last_text(), 'تنظیمات گروه'), 'group settings');
cb('gs:calculator', 777, -400);
reset_calls();
feed(msg('2+2', -400, 778));
check(!sent(), 'calculator disabled in group');

$GLOBALS['http']['raw.githubusercontent.com/ssamy2/TelegramGiftsAssests/main/Gifts_Details.json'] = ['upgraded' => [
    ['full_name' => 'Toy Bear', 'short_name' => 'toy_bear', 'floor_price_ton' => 14.0], ['full_name' => 'Plush Pepe', 'short_name' => 'plush_pepe', 'floor_price_ton' => 4000.0]]];
$GLOBALS['http']['/models/'] = ['models' => ['Wizard' => 20.0], 'backdrops' => ['Black' => 5000.0], 'symbols' => []];
$GLOBALS['http']['api.github.com'] = [['sha' => 'abc']];
$GLOBALS['http']['TelegramGiftsAssests/abc/Gifts_Details.json'] = ['upgraded' => [['full_name' => 'Toy Bear', 'floor_price_ton' => 10.0], ['full_name' => 'Plush Pepe', 'floor_price_ton' => 4400.0]]];
$uniq = fn($base, $slug, $n, $model, $bd) => ['type' => 'unique', 'send_date' => 0, 'gift' => ['gift_id' => '1', 'base_name' => $base, 'name' => "$slug-$n", 'number' => $n,
    'model' => ['name' => $model], 'symbol' => ['name' => 'Rose'], 'backdrop' => ['name' => $bd, 'colors' => ['center_color' => 0x5B8DEF, 'edge_color' => 0x1E3A8A]]]];
$GLOBALS['user_gifts'] = ['total_count' => 2, 'gifts' => [$uniq('Toy Bear', 'ToyBear', 55, 'Wizard', 'Amber'), $uniq('Plush Pepe', 'PlushPepe', 7, 'Cozy Galaxy', 'Black')]];
reset_calls();
feed(msg('/gift', -500, 70, null, null, 'Sina'));
$ph = sent(['sendPhoto']);
$cap = $ph[0][1]['caption'] ?? '';
check(str_contains($cap, 'https://t.me/nft/ToyBear-55') && str_contains($cap, 'https://t.me/nft/PlushPepe-7'), 'gift links in caption');
check(str_contains($cap, '5,020') && str_contains($cap, 'ضرر'), 'gift value and loss');
check(str_starts_with($ph[0][1]['photo']['__bytes'] ?? '', "\x89PNG"), 'gift card png');

Config::set('API_ID', '12345');
Config::set('API_HASH', 'hash');
mt_fake();
reset_calls();
feed(msg('/gift @tofan', -500, 70, null, null, 'Sina'));
$ph = sent(['sendPhoto']);
$cap = $ph[0][1]['caption'] ?? '';
check(str_contains($cap, 'https://t.me/nft/PlushPepe-1') && str_contains($cap, 'Tofan'), 'gift by username via mtproto');
reset_calls();
feed(msg('/gift @nobody_x', -500, 70, null, null, 'Sina'));
check(str_contains((string)last_text(), 'نتوانستم'), 'gift unknown username');
MTProto::$fake = null;
Config::set('API_ID', '');

$GLOBALS['http']['cardinfo.test'] = ['result' => ['name' => 'علی رضایی']];
reset_calls();
feed(msg('6037-9975-9950-3209', -600, 80));
check(str_contains(last_text(), 'ملی') && str_contains(last_text(), 'علی رضایی'), 'card info');
reset_calls();
feed(msg('6037-9975-9950-3201', -600, 80));
check(!sent(), 'invalid card silent in group');
feed(msg('IR820540102680020817909002', -600, 80));
check(str_contains(last_text(), 'پارسیان'), 'iban');

$GLOBALS['http']['phone.test'] = ['status' => '⚫️'];
reset_calls();
feed(msg('/phone +15005550006'));
check(str_contains(last_text(), 'بن شده'), 'phone checker');

$sender = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$recv = 'TKHuVq1oKVruCGLvqVexFs6dawKv6fQgFs';
$GLOBALS['http']['tronscanapi.com/api/transaction-info'] = ['hash' => str_repeat('e', 64), 'contractRet' => 'SUCCESS', 'confirmed' => true, 'timestamp' => 1700000000000,
    'contractData' => ['owner_address' => $sender, 'to_address' => $recv, 'amount' => 5000000]];
$GLOBALS['http']['api.trongrid.io/v1/accounts'] = ['success' => true, 'data' => [['balance' => 7000000, 'create_time' => 1600000000000, 'trc20' => []]]];
$GLOBALS['http']['tronscanapi.com/api/accountv2'] = ['totalTransactionCount' => 3];
$GLOBALS['http']['tronscanapi.com/api/account/tokens'] = ['total' => 1, 'data' => [['tokenId' => '_', 'tokenType' => 'trc10', 'quantity' => 7, 'tokenAbbr' => 'trx']]];
$GLOBALS['http']['token_trc20/transfers'] = ['total' => 1, 'token_transfers' => [['quant' => '1000000', 'tokenInfo' => ['tokenDecimal' => 6, 'tokenAbbr' => 'USDT'],
    'from_address' => 'TA', 'to_address' => $recv, 'block_ts' => 1700000000000, 'transaction_id' => str_repeat('f', 64)]]];
reset_calls();
feed(msg(str_repeat('e', 64), -700, 90));
$txm = array_values(array_filter(sent(['sendMessage']), fn($c) => str_contains($c[1]['text'], 'هش تراکنش')))[0] ?? null;
check($txm && str_contains($txm[1]['text'], '5 TRX') && str_contains($txm[1]['text'], 'COIN'), 'tron tx');
$wb = array_values(array_filter(buttons($txm ?? [0, []]), fn($b) => str_starts_with($b['callback_data'] ?? '', 'wl:')));
check(count($wb) === 2, 'tx wallet buttons');
reset_calls();
cb($wb[1]['callback_data'], 91, -700);
check(!empty($GLOBALS['calls'][0][1]['show_alert']), 'wallet button owner only');
reset_calls();
cb($wb[1]['callback_data'], 90, -700);
$wm = array_values(array_filter(sent(['sendMessage']), fn($c) => str_contains($c[1]['text'], 'TRON') && str_contains($c[1]['text'], 'موجودی')))[0] ?? null;
check($wm && str_contains($wm[1]['text'], 'تاریخ ساخت'), 'tron wallet created');
$ex = array_values(array_filter(buttons($wm ?? [0, []]), fn($b) => in_array(substr($b['callback_data'] ?? '', 0, 3), ['tt:', 'tj:'], true)));
check(count($ex) === 2, 'tron extra buttons');
reset_calls();
cb($ex[0]['callback_data'], 90, -700);
check(str_contains((string)last_text(), 'TRC20') && str_contains((string)last_text(), 'USDT'), 'trc20 history');
reset_calls();
cb($ex[1]['callback_data'], 90, -700);
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendDocument'), 'tokens file');

$GLOBALS['http']['publicnode.com'] = function ($r) {
    if (!isset($r['json'][0])) {
        return null;
    }
    $res = [];
    foreach ($r['json'] as $c) {
        $v = '0x0';
        if ($c['method'] === 'eth_getBalance' && str_contains($r['url'], 'ethereum')) {
            $v = '0xde0b6b3a7640000';
        } elseif ($c['method'] === 'eth_getCode') {
            $v = '0x';
        }
        $res[] = ['jsonrpc' => '2.0', 'id' => $c['id'], 'result' => $v];
    }
    return $res;
};
reset_calls();
feed(msg('0xdAC17F958D2ee523a2206206994597C13D831ec7'));
check(str_contains((string)last_text(), 'Ethereum') && str_contains((string)last_text(), '4,000'), 'evm wallet');

$GLOBALS['http']['mempool.space/api/tx/'] = fn($r) => ['txid' => str_repeat('c', 64), 'status' => ['confirmed' => true, 'block_height' => 100, 'block_time' => 1700000000], 'fee' => 1000,
    'vin' => [['prevout' => ['scriptpubkey_address' => 'bc1qsender', 'value' => 300000000]]], 'vout' => [['scriptpubkey_address' => 'bc1qrecv', 'value' => 250000000]]];
$GLOBALS['http']['mempool.space/api/blocks/tip/height'] = '105';
unset($GLOBALS['http']['tronscanapi.com/api/transaction-info']);
reset_calls();
feed(msg(str_repeat('c', 64)));
check(str_contains((string)last_text(), 'Bitcoin') && str_contains((string)last_text(), '2.5 BTC'), 'btc tx');

reset_calls();
$GLOBALS['http_seen'] = null;
feed(msg('wallet TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', -800, 95));
check(str_contains((string)last_text(), 'TRON'), 'wallet prefix in group');

Config::set('INSTAGRAM_API_KEY', 'K');
$GLOBALS['http']['api.codesazan.ir/Instagram'] = ['status' => 200, 'result' => ['medias' => [['url' => 'https://x/1.jpg', 'extension' => 'jpg'], ['url' => 'https://x/2.mp4', 'extension' => 'mp4']]]];
reset_calls();
feed(msg('https://www.instagram.com/reel/ABC123/', -900, 96));
$vid = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendVideo'));
$pho = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendPhoto' && ($c[1]['photo'] ?? '') === 'https://x/1.jpg'));
check($vid && $pho && str_contains($vid[0][1]['caption'], '(2/2)'), 'instagram download');

cb('adm:power', 999, 999);
check(Settings::get('bot_enabled') === false, 'power off');
reset_calls();
feed(msg('10 تتر', 31, 31));
check(str_contains((string)last_text(), 'خاموش'), 'bot off message');
cb('adm:power', 999, 999);
cb('adm:ban', 999, 999);
feed(msg('32', 999, 999));
check(DB::isBanned(32), 'ban');
reset_calls();
feed(msg('10 تتر', 32, 32));
check(str_contains((string)last_text(), 'مسدود'), 'banned message');
cb('adm:ban', 999, 999);
feed(msg('32', 999, 999));
check(!DB::isBanned(32), 'unban');
cb('adm:pm', 999, 999);
reset_calls();
feed(msg("33\nسلام", 999, 999));
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMessage' && $c[1]['chat_id'] == 33 && str_contains($c[1]['text'], 'سلام')), 'admin pm');

foreach (['/500 درهم' => 'درهم امارات', '/درهم 500' => 'درهم امارات', '/درهم' => 'درهم امارات', '/aed 500' => 'درهم امارات', '/aed@TestBot 500' => 'درهم امارات', '/۱۰۰۰ لیر' => 'لیر ترکیه', '/eur' => 'یورو'] as $q => $needle) {
    reset_calls();
    feed(msg($q, -950, 97));
    $p = sent(['sendPhoto']);
    check($p && str_contains($p[0][1]['caption'], $needle), "slash fiat $q");
}
reset_calls();
foreach (['/10 تتر', '/500000 تومان', '/btc', '/randomcommand', '/aed@OtherBot 5'] as $q) {
    feed(msg($q));
}
check(!sent(), 'slash only fiat');

reset_calls();
$GLOBALS['user_gifts'] = ['total_count' => 0, 'gifts' => []];
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -1234, 'type' => 'supergroup', 'title' => 'New'], 'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'x'],
    'date' => time(), 'old_chat_member' => ['status' => 'left', 'user' => BOT], 'new_chat_member' => ['status' => 'member', 'user' => BOT]]]);
check(str_contains((string)last_text(), 'اضافه شد'), 'bot added message');
reset_calls();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -1234, 'type' => 'supergroup', 'title' => 'New'], 'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'x'],
    'date' => time(), 'old_chat_member' => ['status' => 'member', 'user' => BOT], 'new_chat_member' => ['status' => 'administrator', 'user' => BOT]]]);
check(str_contains((string)last_text(), 'ادمین شد'), 'bot promoted message');

reset_calls();
$now = time();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'edited_message' => ['message_id' => 5000, 'date' => $now, 'edit_date' => $now, 'chat' => ['id' => -1300, 'type' => 'supergroup'], 'from' => ['id' => 300, 'is_bot' => false, 'first_name' => 'S'], 'text' => 'x']]);
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'edited_message' => ['message_id' => 5001, 'date' => $now, 'edit_date' => $now, 'chat' => ['id' => -1300, 'type' => 'supergroup'], 'from' => ['id' => 300, 'is_bot' => false, 'first_name' => 'S'], 'text' => 'x']]);
check(str_contains((string)last_text(), 'سلف'), 'anti self warn');
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'edited_message' => ['message_id' => 5002, 'date' => $now, 'edit_date' => $now, 'chat' => ['id' => -1300, 'type' => 'supergroup'], 'from' => ['id' => 300, 'is_bot' => false, 'first_name' => 'S'], 'text' => 'x']]);
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'edited_message' => ['message_id' => 5003, 'date' => $now, 'edit_date' => $now, 'chat' => ['id' => -1300, 'type' => 'supergroup'], 'from' => ['id' => 300, 'is_bot' => false, 'first_name' => 'S'], 'text' => 'x']]);
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'restrictChatMember' && $c[1]['user_id'] == 300), 'anti self mute');

reset_calls();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'message' => ['message_id' => 6000, 'date' => time(), 'chat' => ['id' => -1400, 'type' => 'supergroup'], 'from' => ['id' => 400, 'is_bot' => false, 'first_name' => 'Ad'],
    'new_chat_members' => [['id' => 400, 'is_bot' => false, 'first_name' => 'Ad']]]]);
feed(msg('عضو شو تو کانال ما https://t.me/+abcdef', -1400, 400));
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'restrictChatMember' && $c[1]['user_id'] == 400) && str_contains((string)last_text(), 'تبچی'), 'anti tabchi');

reset_calls();
feed(msg('سلام قیمت دلار چنده', -1400, 401));
check(!array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'restrictChatMember'), 'normal user not muted');

@unlink($dbFile);
echo "passed: $pass, failed: $fail\n";
exit($fail ? 1 : 0);
