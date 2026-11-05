<?php

require __DIR__ . '/../src/bootstrap.php';

$dbFile = sys_get_temp_dir() . '/mybot_test_' . getmypid() . '.db';
@unlink($dbFile);
Config::set('BOT_TOKEN', '42:TEST');
Config::set('KASHIDA', '0');
Config::set('ADMIN_IDS', '999');
Config::set('DB_PATH', $dbFile);
Config::set('CACHE_DIR', sys_get_temp_dir() . '/mybot_cache_' . getmypid());
Config::set('CARDINFO_API_URL', 'http://cardinfo.test/api/');
Config::set('PHONE_CHECK_API_URL', 'https://phone.test/check.php');
DB::connect();
SpotDir::reset(['at' => time(), 's' => []]);
Market::reset([]);

$GLOBALS['calls'] = [];
$GLOBALS['mid'] = 100;
$GLOBALS['reject_premium'] = false;
$GLOBALS['user_gifts'] = ['total_count' => 0, 'gifts' => []];
$GLOBALS['http'] = [];
const BOT = ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'TestBot'];
const NON_MEMBER = -1001;
const TESTMAP = ['👥' => '5100000000000000001', '👋' => '5100000000000000002', '👤' => '5100000000000000003', '🔴' => '5100000000000000004',
    '✅' => '5100000000000000005', '⭐️' => '5100000000000000006', '🏷' => '5100000000000000007', '💸' => '5100000000000000008'];

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
        case 'getChatAdministrators':
            return ['ok' => true, 'result' => [['status' => 'creator', 'user' => ['id' => 777, 'is_bot' => false, 'first_name' => 'adm']]]];
        case 'setMessageReaction':
            if (($p['reaction'][0]['emoji'] ?? '') === '🩵' && !empty($GLOBALS['reject_blue'])) {
                return ['ok' => false, 'description' => 'Bad Request: REACTION_INVALID'];
            }
            return ['ok' => true, 'result' => true];
        case 'getUserProfilePhotos':
            return ['ok' => true, 'result' => ['total_count' => 0, 'photos' => []]];
        case 'getUserGifts':
            return ['ok' => true, 'result' => $GLOBALS['user_gifts']];
        case 'getCustomEmojiStickers':
            return ['ok' => true, 'result' => array_values(array_filter(array_map(fn($id) => isset($GLOBALS['alts'][$id]) ? ['custom_emoji_id' => $id, 'emoji' => $GLOBALS['alts'][$id]] : null, $p['custom_emoji_ids'])))];
        case 'getStickerSet':
            return $p['name'] === 'HashPack' ? ['ok' => true, 'result' => ['name' => 'HashPack', 'stickers' => [
                ['custom_emoji_id' => '5200000000000000001', 'emoji' => '🦚'], ['custom_emoji_id' => '5200000000000000002', 'emoji' => '👁'],
                ['custom_emoji_id' => '5200000000000000003', 'emoji' => '🦜']]]] : ['ok' => false, 'description' => 'Bad Request: STICKERSET_INVALID'];
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

function hook_run(array $update): ?array
{
    $GLOBALS['hook_body'] = null;
    TG::$hookSink = function ($b) { $GLOBALS['hook_body'] = $b; };
    TG::$hook = true;
    Bot::handle($update);
    TG::closeHook();
    TG::$hookSink = null;
    $b = $GLOBALS['hook_body'];
    return $b && $b !== 'ok' ? json_decode($b, true) : null;
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
$tk = $prompt[1]['reply_markup']['inline_keyboard'];
check(array_column($tk[0], 'style') === ['primary', 'primary', 'primary'] && array_column($tk[1], 'style') === ['success', 'success']
    && $tk[0][0]['icon_custom_emoji_id'] === '5143448346001671955' && $tk[1][1]['icon_custom_emoji_id'] === '5143404335471790574' && $tk[0][0]['text'] === '𝗙𝗔' && $tk[0][1]['text'] === '𝗘𝗡' && $tk[1][0]['text'] === '𝗭𝗛' && ($tk[1][0]['icon_custom_emoji_id'] ?? '') === '5294068833277990704', 'translate flag + bold code buttons');
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
Settings::set('emoji_map', TESTMAP + Settings::defaults()['emoji_map']);

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

foreach (['home', 'stats', 'groups:0', 'startbtn', 'user:5', 'texts:0', 'texts:1', 'text:conv_crypto', 'btns', 'btn:btn_channel', 'bstyle:btn_channel:danger', 'fj', 'set:1', 'set:2', 'texts:price', 'text:conv_crypto:price', 'tog:anti_self:set', 'scams', 'stats', 'bcmenu'] as $d) {
    reset_calls();
    cb("adm:$d", 999, 999);
    check($GLOBALS['calls'] && !array_filter($GLOBALS['calls'], fn($c) => ($c[1]['text'] ?? '') === '⛔️'), "admin nav $d");
}
check((Settings::get('buttons')['btn_channel']['style'] ?? '') === 'danger' && Settings::get('anti_self') === false, 'admin toggles');
Settings::set('anti_self', true);
reset_calls();
feed(msg('/start', 6, 6));
$add = array_values(array_filter(buttons(sent(['sendMessage'])[0]), fn($b) => str_contains($b['url'] ?? '', 'startgroup')))[0];
check(($add['icon_custom_emoji_id'] ?? '') === '5100000000000000001' && !str_contains($add['text'], '👥'), 'button premium emoji');
check(str_contains(sent(['sendMessage'])[0][1]['text'], '<tg-emoji emoji-id="5100000000000000002">'), 'text premium emoji');
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
$ask = sent(['sendMessage']);
check(!sent(['sendPhoto']) && ($ask[0][1]['reply_markup']['force_reply'] ?? false) === true && ($ask[0][1]['reply_markup']['selective'] ?? false) === true, 'bare /gift asks with force reply');
reset_calls();
feed(msg('من', -500, 70, ['message_id' => 1, 'date' => time(), 'chat' => ['id' => -500, 'type' => 'supergroup'], 'from' => BOT, 'text' => 'x'], null, 'Sina'));
$ph = sent(['sendPhoto']);
$cap = $ph[0][1]['caption'] ?? '';
check(str_contains($cap, 'https://t.me/nft/ToyBear-55') && str_contains($cap, 'https://t.me/nft/PlushPepe-7'), 'gift links in caption');
check(str_contains($cap, '5,020') && str_contains($cap, 'ضرر'), 'gift value and loss');
check(str_starts_with($ph[0][1]['photo']['__bytes'] ?? '', "\xFF\xD8"), 'gift card image');

Config::set('API_ID', '12345');
Config::set('API_HASH', 'hash');
mt_fake();
reset_calls();
feed(msg('/gift @tofan', -500, 71, null, null, 'Sina'));
$ph = sent(['sendPhoto']);
$cap = $ph[0][1]['caption'] ?? '';
check(str_contains($cap, 'https://t.me/nft/PlushPepe-1') && str_contains($cap, 'Tofan'), 'gift by username via mtproto');
reset_calls();
feed(msg('/gift @nobody_x', -500, 72, null, null, 'Sina'));
check(str_contains((string)last_text(), 'نتوانستم'), 'gift unknown username');
reset_calls();
feed(msg('/gift @nobody_x', -500, 72, null, null, 'Sina'));
check(!sent(), 'gift command throttled');
MTProto::$fake = null;
Config::set('API_ID', '');

reset_calls();
$h = hook_run(['update_id' => ++$GLOBALS['mid'], 'message' => msg('200+79', 9, 9)]);
check(($h['method'] ?? '') === 'sendMessage' && str_contains($h['text'] ?? '', '279') && !sent(['sendMessage']), 'quick reply via webhook');
check(str_contains(Config::data('texts')['calc_result']['text'], "</code>\n<blockquote>") && !str_contains(Config::data('texts')['calc_result']['text'], "\n\n"), 'calculator result without blank line');
reset_calls();
$h = hook_run(['update_id' => ++$GLOBALS['mid'], 'callback_query' => ['id' => 'cbq', 'from' => ['id' => 9, 'is_bot' => false, 'first_name' => 'x'], 'data' => 'noop',
    'message' => ['message_id' => 3, 'chat' => ['id' => 9, 'type' => 'private'], 'date' => time()]]]);
check(($h['method'] ?? '') === 'answerCallbackQuery' && ($h['callback_query_id'] ?? '') === 'cbq', 'callback answer via webhook');
reset_calls();
$h = hook_run(['update_id' => ++$GLOBALS['mid'], 'message' => msg('733 درهم', 9, 9)]);
check($h === null && sent(['sendPhoto']), 'card upload is a normal call');
reset_calls();
$h = hook_run(['update_id' => ++$GLOBALS['mid'], 'message' => msg('733 درهم', 9, 9)]);
check(($h['method'] ?? '') === 'sendPhoto' && str_starts_with((string)($h['photo'] ?? ''), 'AgAC') && !sent(['sendPhoto']), 'cached card via webhook');
Cache::forget('admins:-4400');
reset_calls();
feed(msg('سلام', -4400, 801));
feed(msg('خوبی', -4400, 802));
check(!sent(['getChatAdministrators']) && !sent(['getChatMember']), 'plain group chatter needs no admin lookup');
Bot::isChatAdmin(-4400, 801);
Bot::isChatAdmin(-4400, 802);
check(count(sent(['getChatAdministrators'])) === 1 && !sent(['getChatMember']), 'admin list cached per chat');
DB::touchUser(['id' => 4242, 'first_name' => 's'], true, true);
DB::touchUser(['id' => 4242, 'first_name' => 's']);
check((int)DB::user(4242)['started'] === 1, 'started flag survives later updates');

cb('adm:xbadd', 999, 999);
feed(msg("💎 کانال دوم\nhttps://t.me/second", 999, 999));
reset_calls();
feed(msg('/start', 6, 6));
$bs = buttons(sent(['sendMessage'])[0]);
check((bool)array_filter($bs, fn($b) => ($b['url'] ?? '') === 'https://t.me/second' && str_contains($b['text'] ?? '', 'کانال دوم')), 'extra footer button');
cb('adm:xbdel:0', 999, 999);
check(!(Settings::get('extra_buttons') ?: []), 'extra button removed');
reset_calls();
feed(msg('/panel', -4400, 777));
check(str_contains((string)last_text(), 'تنظیمات گروه'), 'panel in group opens group settings');

reset_calls();
feed(msg('الماس', -7100, 501, null, null, 'Ali'));
$r1 = Diamonds::points(501);
check($r1 >= Diamonds::MIN && str_contains((string)last_text(), 'الماس'), 'diamond hit');
reset_calls();
feed(msg('الماس', -7100, 501, null, null, 'Ali'));
check(Diamonds::points(501) === $r1 && str_contains((string)last_text(), 'زود'), 'diamond cooldown');
reset_calls();
feed(msg('الماس', 501, 501));
check(str_contains((string)last_text(), 'فقط داخل گروه'), 'games group only');
DB::touchUser(['id' => 502, 'first_name' => 'Reza', 'username' => 'reza_t'], true, true);
cb('adm:dmadj', 999, 999);
feed(msg('502 +1000', 999, 999));
check(Diamonds::points(502) === 1000.0, 'admin add diamonds');
cb('adm:dmadj', 999, 999);
feed(msg('502 ـ200', 999, 999));
check(Diamonds::points(502) === 800.0, 'admin remove diamonds');
reset_calls();
feed(msg('الماس', -7100, 502, null, null, 'Reza'));
feed(msg('تاپ الماسی', -7100, 501, null, null, 'Ali'));
$tp = sent(['sendPhoto']);
$cap = $tp[0][1]['caption'] ?? '';
check($tp && str_starts_with($tp[0][1]['photo']['__bytes'] ?? '', "\xFF\xD8") && str_contains($cap, 'برترین‌های این گروه') && (str_contains($cap, '@reza_t') || str_contains($cap, 'Reza')) && str_contains($cap, 'رتبه‌ی شما'), 'diamond leaderboard card with group and rank');
reset_calls();
feed(msg('برترین‌ها', -7100, 501, null, null, 'Ali'));
check(($tp2 = sent(['sendPhoto'])) && is_string($tp2[0][1]['photo']) , 'leaderboard card reused by file id');
foreach (['لیدربورد', 'لیدر بورد', 'لیدر‌بورد', 'تاپ'] as $w) {
    reset_calls();
    feed(msg($w, -7100, 501, null, null, 'Ali'));
    check((bool)sent(['sendPhoto']), "leaderboard word $w");
}
reset_calls();
feed(msg('حساب', -7100, 502, null, null, 'Reza'));
$acc = sent(['sendMessage']);
$ab = buttons($acc[0] ?? [0, []]);
$p502 = Fmt::int(Diamonds::points(502));
check($acc && str_contains($ab[0]['text'] ?? '', $p502) && str_starts_with($ab[0]['callback_data'] ?? '', 'gb:'), 'account shows diamonds on glass button');
reset_calls();
cb($ab[0]['callback_data'], 502, -7100);
check(!empty($GLOBALS['calls'][0][1]['show_alert']) && str_contains($GLOBALS['calls'][0][1]['text'] ?? '', $p502), 'account button alert');
Diamonds::adjust(501, 500);
$a0 = Diamonds::points(501);
reset_calls();
feed(msg('دوز 100', -7100, 501, null, null, 'Ali'));
$gid = DB::one("SELECT id FROM games WHERE kind='dooz' AND host=501 AND status='open'")['id'] ?? '';
check($gid !== '' && Diamonds::points(501) === $a0 - 100, 'dooz created and stake taken');
$b502 = Diamonds::points(502);
cb("gd:j:$gid", 502, -7100);
$g = Games::load($gid);
check($g['status'] === 'playing' && Diamonds::points(502) === $b502 - 100, 'dooz joined');
foreach ([[501, 0], [502, 3], [501, 1], [502, 4], [501, 2]] as [$who, $cell]) {
    cb("gd:m:$gid:$cell", $who, -7100);
}
$g = Games::load($gid);
check($g['status'] === 'done' && (int)$g['winner'] === 501 && Diamonds::points(501) === $a0 - 100 + 180, 'dooz win pays prize');
reset_calls();
cb("gd:m:$gid:5", 502, -7100);
check(str_contains(json_encode($GLOBALS['calls'], JSON_UNESCAPED_UNICODE), 'تمام شده'), 'dooz finished');
$b0 = Diamonds::points(501);
feed(msg('مین 100', -7100, 501, null, null, 'Ali'));
$mg = DB::one("SELECT data FROM games WHERE kind='mine' AND host=501 AND status='waiting'");
$mg = json_decode($mg['data'] ?? '{}', true);
check(!empty($mg['id']) && Diamonds::points(501) === $b0, 'mine preview does not charge');
cb('gn:j:' . $mg['id'], 501, -7100);
check(Diamonds::points(501) === $b0 - 100, 'mine join charges entry');
$safe = array_values(array_diff(range(1, 9), [(int)$mg['mine']]));
cb('gn:p:' . $mg['id'] . ':' . $safe[0], 501, -7100);
cb('gn:p:' . $mg['id'] . ':' . $safe[1], 501, -7100);
$mg2 = Games::load($mg['id']);
check((int)$mg2['safe'] === 2 && $mg2['reward'] > 0, 'mine safe picks');
cb('gn:c:' . $mg['id'], 501, -7100);
check(abs(Diamonds::points(501) - ($b0 - 100 + $mg2['reward'])) < 0.001 && Games::load($mg['id'])['status'] === 'cashed', 'mine cashout');
cb('gn:p:' . $mg['id'] . ':' . $safe[2], 777, -7100);
check(str_contains(json_encode(end($GLOBALS['calls']), JSON_UNESCAPED_UNICODE), 'مال تو نیست'), 'mine not yours');

reset_calls();
feed(msg('600 درهم', -8800, 61));
$rx = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction'));
$sidx = array_search('sendPhoto', array_column($GLOBALS['calls'], 0), true);
$ridx = array_search('setMessageReaction', array_column($GLOBALS['calls'], 0), true);
check($rx && $rx[0][1]['reaction'][0]['emoji'] === '🩵' && $sidx !== false && $ridx > $sidx, 'blue heart reaction after price reply');
$GLOBALS['reject_blue'] = true;
reset_calls();
feed(msg('601 درهم', -8801, 61));
$rx = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction'));
check(count($rx) === 2 && $rx[1][1]['reaction'][0]['emoji'] === '🐳', 'reaction falls back to blue whale');
reset_calls();
feed(msg('602 درهم', -8801, 61));
$rx = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction'));
check(count($rx) === 1 && $rx[0][1]['reaction'][0]['emoji'] === '🐳', 'fallback remembered per chat');
$GLOBALS['reject_blue'] = false;

$prevMap = Settings::get('emoji_map');
Settings::set('emoji_map', TESTMAP + Settings::defaults()['emoji_map']);
$qm = UI::emojiMap("<blockquote>👤    سازنده: 🔴 @ali\n✅    دارد ⭐️</blockquote>");
check(substr_count($qm, '<tg-emoji') === 4 && str_contains($qm, '🔴</tg-emoji>    @ali') && str_contains($qm, '⭐️</tg-emoji></blockquote>') && str_contains($qm, '✅</tg-emoji>    دارد'), 'all quote emojis premium with 4 spaces');
$qm2 = UI::emojiMap("<blockquote>🏷    وضعیت: ✅ <b>موفق</b>\n💸    مقدار: <code>1</code></blockquote>");
check(str_contains($qm2, '✅</tg-emoji>    <b>موفق') && !str_contains($qm2, '💸</tg-emoji>     '), 'quote pad looks across tags');
Settings::set('emoji_map', $prevMap);
reset_calls();
Start::registerCommands();
$cmds = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMyCommands'));
check(count($cmds) === 3 && array_column($cmds[2][1]['commands'], 'command') === ['account', 'emoji'] && array_column($cmds[0][1]['commands'], 'command') === ['account', 'emoji'] && (int)DB::kvGet('commands_v') === Start::COMMANDS_V, 'command menu registered');

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
check(count($ex) === 1 && str_starts_with($ex[0]['callback_data'], 'tt:'), 'tron single transactions button');
$acct = $GLOBALS['http']['api.trongrid.io/v1/accounts'];
unset($GLOBALS['http']['api.trongrid.io/v1/accounts']);
$GLOBALS['http']['/transactions'] = '{"success":false}';
$GLOBALS['http']['api.trongrid.io/v1/accounts'] = $acct;
reset_calls();
cb($ex[0]['callback_data'], 90, -700);
check(str_contains((string)last_text(), 'تراکنش‌های ولت') && str_contains((string)last_text(), 'USDT'), 'tron history via tronscan fallback');
unset($GLOBALS['http']['api.trongrid.io/v1/accounts'], $GLOBALS['http']['/transactions']);
DB::q("DELETE FROM cache WHERE k LIKE 'th:%'");
$hx = '41' . str_repeat('ab', 20);
$GLOBALS['http']['/transactions/trc20'] = fn($r) => ['success' => true, 'data' => array_fill(0, 10, ['transaction_id' => 'aa11', 'token_info' => ['symbol' => 'USDT', 'decimals' => 6], 'block_timestamp' => 1700000000000, 'from' => 'TFrom', 'to' => 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8', 'value' => '2500000']), 'meta' => ['fingerprint' => empty($r['params']['fingerprint']) ? 'FP2' : null]];
$GLOBALS['http']['/transactions'] = ['success' => true, 'data' => [['txID' => 'bb22', 'block_timestamp' => 1700000500000, 'raw_data' => ['contract' => [['type' => 'TransferContract', 'parameter' => ['value' => ['amount' => 3000000, 'owner_address' => '41' . bin2hex(Chain::b58decode('TJRabPrwbZy45sbavfcjinPJC18kjpRTv8') ? substr(Chain::b58decode('TJRabPrwbZy45sbavfcjinPJC18kjpRTv8'), 1, 20) : ''), 'to_address' => $hx]]]]]]], 'meta' => []];
$GLOBALS['http']['api.trongrid.io/v1/accounts'] = $acct;
reset_calls();
cb($ex[0]['callback_data'], 90, -700);
$ht = (string)last_text();
$nb = array_values(array_filter(buttons(end($GLOBALS['calls'])), fn($b) => str_starts_with($b['callback_data'] ?? '', 'tp:')));
check(str_contains($ht, '2.5 USDT') && str_contains($ht, '3 TRX') && str_contains($ht, Chain::tronB58($hx)) && count($nb) === 1 && substr_count($ht, '<blockquote>') === 3
    && str_contains($ht, 'صفحه <b>1</b> از <b>4</b>') && ($nb[0]['style'] ?? '') === 'success', 'tron history 3 per page with green next');
$n0 = count($GLOBALS['http_log'] ?? []);
reset_calls();
cb(str_replace('tt:', 'tp:', $nb[0]['callback_data']), 90, -700);
$p2 = end($GLOBALS['calls']);
$nb2 = buttons($p2);
check($p2[0] === 'editMessageText' && str_contains($p2[1]['text'], 'صفحه <b>2</b> از <b>4</b>') && count($nb2) === 2 && ($nb2[0]['style'] ?? '') === 'danger' && ($nb2[1]['style'] ?? '') === 'success'
    && str_contains($nb2[1]['callback_data'] ?? '', ':3') && str_contains($nb2[0]['callback_data'] ?? '', ':1'), 'tron history page 2: prev left, next right');
unset($GLOBALS['http']['/transactions/trc20'], $GLOBALS['http']['/transactions']);

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
$grp = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMediaGroup'));
$media = $grp[0][1]['media'] ?? [];
check(count($grp) === 1 && ($media[0]['media'] ?? '') === 'https://x/1.jpg' && ($media[1]['type'] ?? '') === 'video' && str_contains($media[0]['caption'] ?? '', '@TestBot')
    && !array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMessage' || $c[0] === 'editMessageText'), 'instagram album in one go, no extra messages');

Config::set('INSTAGRAM_API_KEY', '');
$GLOBALS['http']['instagram.com/graphql/query'] = ['data' => ['xdt_shortcode_media' => ['is_video' => true, 'video_url' => 'https://cdn/v.mp4', 'display_url' => 'https://cdn/v.jpg']]];
reset_calls();
feed(msg('https://www.instagram.com/reel/XYZ987/?igsh=abc', -900, 97));
$vid = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendVideo' && ($c[1]['video'] ?? '') === 'https://cdn/v.mp4'));
check(count($vid) === 1 && str_contains($vid[0][1]['caption'], 'ریلز اینستاگرام') && buttons($vid[0]) && !array_filter($GLOBALS['calls'], fn($c) => in_array($c[0], ['sendMessage', 'editMessageText'], true)), 'instagram single video with caption and button only');
unset($GLOBALS['http']['instagram.com/graphql/query']);
$GLOBALS['http']['/embed/captioned/'] = '<div><img class="EmbeddedMediaImage" alt="x" src="https://cdn/p.jpg?a=1&amp;b=2"></div>';
reset_calls();
feed(msg('https://www.instagram.com/p/QQQ111/', -900, 98));
$pho = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendPhoto' && ($c[1]['photo'] ?? '') === 'https://cdn/p.jpg?a=1&b=2'));
check(count($pho) === 1, 'instagram embed fallback');
unset($GLOBALS['http']['/embed/captioned/']);
$GLOBALS['http']['/embed/captioned/'] = '<div><img class="EmbeddedMediaImage" alt="x" src="https://cdn/thumb.jpg"></div>';
$GLOBALS['http']['instagram.com/graphql/query'] = ['data' => ['xdt_shortcode_media' => ['is_video' => true, 'video_url' => 'https://cdn/reel.mp4', 'display_url' => 'https://cdn/thumb.jpg', 'product_type' => 'clips',
    'owner' => ['username' => 'coach'], 'like_count' => 5, 'edge_media_to_caption' => ['edges' => [['node' => ['text' => "🚨 Deadlifts All day 🏆✨ ---\n- - - - - - - - - -\n#gym"]]]]]]];
reset_calls();
feed(msg('https://www.instagram.com/reel/RRR555/', -900, 101));
$rv = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendVideo'));
$rcap = $rv[0][1]['caption'] ?? '';
check(($rv[0][1]['video'] ?? '') === 'https://cdn/reel.mp4' && !array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendPhoto') && str_contains($rcap, 'Deadlifts All day #gym') && !str_contains($rcap, '🏆') && !str_contains($rcap, '- - -'), 'reel prefers video over thumbnail, clean caption');
unset($GLOBALS['http']['/embed/captioned/'], $GLOBALS['http']['instagram.com/graphql/query']);
$GLOBALS['http']['i.instagram.com/api/v1/media/' . Instagram::mediaId('MOB123') . '/info/'] = ['items' => [['carousel_media' => [
    ['video_versions' => [['url' => 'https://cdn/small.mp4', 'width' => 320, 'height' => 240], ['url' => 'https://cdn/big.mp4', 'width' => 1080, 'height' => 1920]], 'image_versions2' => ['candidates' => [['url' => 'https://cdn/t.jpg']]]],
    ['image_versions2' => ['candidates' => [['url' => 'https://cdn/c2.jpg', 'width' => 1080, 'height' => 1080], ['url' => 'https://cdn/c2s.jpg', 'width' => 320, 'height' => 320]]]]]]]];
reset_calls();
feed(msg('https://www.instagram.com/p/MOB123/', -900, 99));
$grp = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendMediaGroup'));
$media = $grp[0][1]['media'] ?? [];
check(($media[0]['media'] ?? '') === 'https://cdn/big.mp4' && ($media[1]['media'] ?? '') === 'https://cdn/c2.jpg', 'instagram mobile api carousel');
$ctx = json_encode(['gql_data' => ['shortcode_media' => ['is_video' => true, 'video_url' => 'https://cdn/e.mp4']]]);
$GLOBALS['http']['/p/EMB777/embed/captioned/'] = '<script>s.handle({"require":[["X","init",[],[' . json_encode(['contextJSON' => $ctx]) . ']],1]})</script>';
reset_calls();
feed(msg('https://www.instagram.com/reel/EMB777/', -900, 100));
check(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'sendVideo' && ($c[1]['video'] ?? '') === 'https://cdn/e.mp4'), 'instagram embed init context');
unset($GLOBALS['http']['/p/EMB777/embed/captioned/']);

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
feed(msg('/eur', -950, 97));
$p = sent(['sendPhoto']);
check($p && str_starts_with($p[0][1]['caption'], "\u{200F}" . '<tg-emoji emoji-id="5143540580424354040">') && str_contains($p[0][1]['caption'], '<tg-emoji emoji-id="5143224307622610764">'), 'fiat currency badge and dollar flag');
reset_calls();
feed(msg('500000 ریال', 98, 98));
$p = sent(['sendPhoto']);
check($p && str_contains($p[0][1]['caption'], '5143575421199059367') && str_contains($p[0][1]['caption'], '5143159333357357518'), 'rial badge and usdt badge');
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
reset_calls();
feed(msg('/emoji', 5, 5));
$ask = sent(['sendMessage']);
check(($ask[0][1]['reply_markup']['force_reply'] ?? false) === true, 'bare /emoji asks with force reply');
reset_calls();
feed(msg('😎', 5, 5, ['message_id' => 2, 'date' => time(), 'chat' => ['id' => 5, 'type' => 'private'], 'from' => BOT, 'text' => 'x', 'entities' => [['type' => 'custom_emoji', 'offset' => 0, 'length' => 1, 'custom_emoji_id' => '111']]],
    [['type' => 'custom_emoji', 'offset' => 0, 'length' => 2, 'custom_emoji_id' => '5368324170671202286']]));
$t = (string)last_text();
check(str_contains($t, '5368324170671202286') && !str_contains($t, '>111<'), 'emoji reply to prompt returns code');
reset_calls();
feed(msg('سلام', 5, 5, ['message_id' => 3, 'date' => time(), 'chat' => ['id' => 5, 'type' => 'private'], 'from' => BOT, 'text' => 'x']));
check(!str_contains((string)last_text(), 'ایموجی پریمیومی'), 'prompt answered only once');

$GLOBALS['alts'] = [];
$pool = Config::data('emoji_pool');
foreach (array_slice($pool, 0, 30) as $i => $id) {
    $GLOBALS['alts'][$id] = ['🔥', '👁', '🎂', '🟰', '📝', '❤️', '🎬', '⌛️', '🌐', '💎'][$i % 10] . ($i >= 10 ? '' : '');
}
$GLOBALS['alts'][$pool[0]] = '🔥'; $GLOBALS['alts'][$pool[1]] = '👁'; $GLOBALS['alts'][$pool[2]] = '🦩'; $GLOBALS['alts'][$pool[3]] = '⌛️';
for ($i = 4; $i < 30; $i++) { $GLOBALS['alts'][$pool[$i]] = mb_chr(0x1F600 + $i); }
$prevMap = Settings::get('emoji_map');
Settings::set('emoji_map', []);
$v = UI::validateEmoji();
check($v !== null && $v[2] >= 25 && UI::emojiId('🔥') === (string)$pool[0] && UI::emojiId('🦩') === (string)$pool[2], 'auto map by pack emoji');
check(UI::emojiId('⏱') === (string)$pool[3] && UI::emojiId('⏳') === (string)$pool[3], 'synonym mapping');
check(UI::emojiId('🧾') === null, 'no guessed mapping without pack emoji');
reset_calls();
feed(msg('https://t.me/addemoji/HashPack', 999, 999));
$pk = array_values(array_filter(sent(['sendMessage']), fn($c) => str_contains($c[1]['text'], 'پک HashPack')));
$pkb = buttons($pk[0] ?? [0, []]);
check($pk && str_starts_with($pkb[0]['callback_data'] ?? '', 'adm:epa:'), 'emoji pack preview');
cb($pkb[0]['callback_data'], 999, 999);
check(UI::emojiId('🦚') === '5200000000000000001' && UI::emojiId('🦜') === '5200000000000000003', 'emoji pack applied');
cb('adm:epr', 999, 999);
check(UI::emojiId('🦚') === null, 'emoji pack reset');
reset_calls();
$pairText = "🎨 X 🖼 Y";
feed(msg($pairText, 999, 999, null, [['type' => 'custom_emoji', 'offset' => 3, 'length' => 1, 'custom_emoji_id' => '5300000000000000001'], ['type' => 'custom_emoji', 'offset' => 8, 'length' => 1, 'custom_emoji_id' => '5300000000000000002']]));
check(UI::emojiId('🎨') === '5300000000000000001' && UI::emojiId('🖼') === '5300000000000000002' && str_contains((string)last_text(), '2 ایموجی تنظیم شد'), 'admin sets emojis by plain+premium pairs');
reset_calls();
feed(msg('/emojis', 999, 999));
check(str_contains(json_encode($GLOBALS['calls'], JSON_UNESCAPED_UNICODE), 'بدون پریمیوم'), 'emoji status list');
$plain = UI::emojiMap("🧪 تست\n<blockquote>🎨    مدل: X\n🦄    یونیکورن</blockquote>");
check(!str_contains($plain, '🧪') && !str_contains($plain, '🦄') && str_contains($plain, '5300000000000000001') && str_contains($plain, "<blockquote><tg-emoji") && str_contains($plain, "\nیونیکورن"), 'unmapped plain emojis are removed');
$kb = UI::iconize(['text' => '🦄 دکمه', 'callback_data' => 'x']);
check($kb['text'] === 'دکمه' && empty($kb['icon_custom_emoji_id']), 'unmapped button emoji removed');
DB::kvSet('emoji_auto', null);
Settings::set('emoji_map', $prevMap);
Config::set('KASHIDA', '1');
check(UI::stretch('لینک') === 'لـیـنـک' && UI::stretch('دارد') === 'دارد' && UI::kashida('🔗 لینک <code>آدرس</code> {name}') === '🔗 لـیـنـک <code>آدرس</code> {name}', 'kashida stretch');
Config::set('KASHIDA', '0');
check(UI::quoteGaps("سر\n\n<blockquote>a</blockquote>\n\nجمع\n\n<blockquote>b</blockquote>\n<blockquote>c</blockquote>") === "سر\n\n<blockquote>a</blockquote>\nجمع\n<blockquote>b</blockquote>\n<blockquote>c</blockquote>"
    && UI::quoteGaps("سر\n\n<blockquote>a</blockquote>") === "سر\n\n<blockquote>a</blockquote>", 'only first quote keeps blank line above');
$E = fn($id) => '<tg-emoji emoji-id="' . $id . '">x</tg-emoji>';
$al = UI::align($E(1) . " <b>عنوان</b>\n" . $E(2) . "<code>UQB1</code>\n<blockquote>" . $E(3) . " NFT: 5\n" . $E(4) . "        موجودی: 1</blockquote>\n" . $E(5) . "   1405/08/14");
check($al === "\u{200F}" . $E(1) . " <b>عنوان</b>\n" . $E(2) . " <code>UQB1</code>\n<blockquote>" . $E(3) . " NFT: 5\n\u{200F}" . $E(4) . "    موجودی: 1</blockquote>\n\u{200F}" . $E(5) . " 1405/08/14"
    && UI::align('<blockquote>' . $E(6) . '    <b>370,185</b></blockquote>') === "<blockquote>\u{200F}" . $E(6) . '    <b>370,185</b></blockquote>'
    && UI::align($E(7) . '   TIME: 1405/08/14 – 18:21') === $E(7) . ' TIME: 1405/08/14 – 18:21' && UI::align($E(8) . ' @HashpriceBot') === $E(8) . ' @HashpriceBot'
    && UI::align('<b>hello</b> ' . $E(1)) === '<b>hello</b> ' . $E(1), 'emoji lines right-aligned with exact spacing');
$dm2 = Settings::get('emoji_map');
Settings::set('emoji_invalid', []);
Settings::set('emoji_map', ['🇮🇷' => '5143448346001671955']);
$ps = (new ReflectionMethod(TG::class, 'stripPremium'));
$ps->setAccessible(true);
$fb = $ps->invoke(null, ['reply_markup' => ['inline_keyboard' => [[['text' => '𝗳𝗮', 'icon_custom_emoji_id' => '5143448346001671955', 'callback_data' => 'tr:fa']]]]]);
check($fb['reply_markup']['inline_keyboard'][0][0]['text'] === '🇮🇷 𝗳𝗮' && !isset($fb['reply_markup']['inline_keyboard'][0][0]['icon_custom_emoji_id']), 'flag kept when premium icon rejected');
Settings::set('emoji_map', $dm2);
base_http();
$hx = 0;
$prevFake = Http::$fake;
Http::$fake = function (array $r) use (&$hx, $prevFake) {
    if (str_contains($r['url'], 'ticker')) {
        $hx++;
    }
    return $prevFake($r);
};
reset_calls();
feed(msg('ok 571', -4401, 803));
check(!sent(['sendMessage']) && !sent(['sendPhoto']) && $hx === 0, 'group chatter with unknown ticker makes no lookup');
feed(msg('571 zzq', 4402, 4402));
$n = $hx;
check($n > 0, 'private unknown ticker is looked up');
feed(msg('571 zzq', 4402, 4402));
check($hx === $n, 'unknown ticker miss is cached');
Http::$fake = $prevFake;
$lean = sys_get_temp_dir() . '/mybot_lean_' . getmypid() . '.db';
DB::connect($lean);
DB::connect($lean);
check((int)DB::pdo()->query('PRAGMA user_version')->fetchColumn() === 6 && array_column(DB::all('PRAGMA table_info(users)'), 'name') === ['id', 'started', 'banned', 'first_seen']
    && array_column(DB::all('PRAGMA table_info(diamonds)'), 'name') === ['id', 'points', 'total', 'last', 'jailed_until'], 'lean schema');
foreach (['', '-wal', '-shm'] as $x) {
    @unlink($lean . $x);
}
$old = new PDO('sqlite:' . $lean);
$old->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, first_name TEXT, last_name TEXT, is_premium INTEGER DEFAULT 0, first_seen INTEGER, last_seen INTEGER, msg_count INTEGER DEFAULT 0, xp INTEGER DEFAULT 0, balance REAL DEFAULT 0, started INTEGER DEFAULT 0, banned INTEGER DEFAULT 0);
CREATE INDEX idx_users_username ON users(username);
CREATE TABLE diamonds (id INTEGER PRIMARY KEY, points REAL NOT NULL DEFAULT 0, total INTEGER NOT NULL DEFAULT 0, last INTEGER NOT NULL DEFAULT 0, jailed_until INTEGER NOT NULL DEFAULT 0, name TEXT, username TEXT, joined INTEGER);
CREATE INDEX idx_diamonds_points ON diamonds(points DESC);
INSERT INTO users(id, username, first_name, first_seen, started, banned, balance) VALUES (11, 'aa', 'Ali', 100, 1, 0, 5), (12, 'bb', 'Reza', 200, 0, 1, 0);
INSERT INTO diamonds(id, points, total, last, jailed_until, name, username) VALUES (11, 42.5, 3, 7, 0, 'Ali', 'aa');
PRAGMA user_version=5;");
$old = null;
DB::connect($lean);
check(array_column(DB::all('PRAGMA table_info(users)'), 'name') === ['id', 'started', 'banned', 'first_seen'] && DB::all('SELECT * FROM users ORDER BY id') === [['id' => 11, 'started' => 1, 'banned' => 0, 'first_seen' => 100], ['id' => 12, 'started' => 0, 'banned' => 1, 'first_seen' => 200]]
    && DB::one('SELECT * FROM diamonds') === ['id' => 11, 'points' => 42.5, 'total' => 3, 'last' => 7, 'jailed_until' => 0] && DB::one("SELECT 1 FROM sqlite_master WHERE name='idx_diamonds_points'"), 'old data migrated to lean schema');
foreach (['', '-wal', '-shm'] as $x) {
    @unlink($lean . $x);
}
$spotFile = Config::$root . '/data/spot.json';
$spotPrev = is_file($spotFile) ? file_get_contents($spotFile) : null;
SpotDir::reset(['at' => 0, 's' => []]);
base_http();
$GLOBALS['http'] += [
    'api.binance.com/api/v3/ticker/price' => [['symbol' => 'BTCUSDT', 'price' => '100000'], ['symbol' => 'ZZXBTC', 'price' => '0.1'], ['symbol' => 'DEADUSDT', 'price' => '0']],
    'okx.com/api/v5/public/instruments' => ['code' => '0', 'data' => [['instId' => 'BTC-USDT', 'baseCcy' => 'BTC', 'quoteCcy' => 'USDT', 'state' => 'live'], ['instId' => 'OLD-USDT', 'baseCcy' => 'OLD', 'quoteCcy' => 'USDT', 'state' => 'suspend']]],
    'api.bybit.com/v5/market/instruments-info' => ['retCode' => 0, 'result' => ['list' => [['symbol' => 'OKUSDT', 'baseCoin' => 'OK', 'quoteCoin' => 'USDT', 'status' => 'Trading']]]],
    'api.kucoin.com/api/v2/symbols' => ['code' => '200000', 'data' => [['symbol' => 'ZZX-USDT', 'baseCurrency' => 'ZZX', 'quoteCurrency' => 'USDT', 'enableTrading' => true]]],
    'api.gateio.ws/api/v4/spot/currency_pairs' => array_merge([['id' => 'ZZX_USDT', 'base' => 'ZZX', 'quote' => 'USDT', 'trade_status' => 'tradable']], array_map(fn($i) => ['id' => "T{$i}X_USDT", 'base' => "T{$i}X", 'quote' => 'USDT', 'trade_status' => 'tradable'], range(1, 60))),
    'api.mexc.com/api/v3/ticker/price' => fn($r) => empty($r['params']) ? [['symbol' => 'MXONLYUSDT', 'price' => '2']] : null,
    'api.gateio.ws/api/v4/spot/tickers' => fn($r) => ($r['params']['currency_pair'] ?? '') === 'ZZX_USDT' ? [['currency_pair' => 'ZZX_USDT', 'last' => '0.00001234', 'change_percentage' => '5.2']] : null,
    'api.kucoin.com/api/v1/market/stats' => fn($r) => ($r['params']['symbol'] ?? '') === 'ZZX-USDT' ? ['code' => '200000', 'data' => ['last' => '0.00001236', 'changeRate' => '0.05']] : null,
];
$n = SpotDir::refresh();
check($n === 64 && SpotDir::mask('ZZX') === (8 | 16) && SpotDir::mask('BTC') === (1 | 2) && SpotDir::mask('OLD') === 0 && SpotDir::mask('DEAD') === 0 && SpotDir::mask('MXONLY') === 32
    && is_file($spotFile), 'spot directory from exchange lists');
check(Currencies::resolve('zzx') === ['crypto', 'ZZX'] && Currencies::resolve('qqq9') === ['crypto_guess', 'QQQ9'], 'listed spot coin resolves as crypto');
$hits = [];
$prevFake = Http::$fake;
Http::$fake = function (array $r) use (&$hits, $prevFake) {
    if (preg_match('/"(?:ZZX|OK)[-_]?USDT"|"(?:zzx|ok)"/i', json_encode($r['params'] ?? []))) {
        $hits[] = parse_url($r['url'], PHP_URL_HOST);
    }
    return $prevFake($r);
};
reset_calls();
feed(msg('1000 zzx', -4500, 811));
$zz = sent(['sendPhoto']) ?: sent(['sendMessage']);
$cap = (string)($zz[0][1]['caption'] ?? $zz[0][1]['text'] ?? '');
check($zz && str_contains($cap, '1,000 ZZX') && str_contains($cap, '1,236') && $hits === ['api.kucoin.com', 'api.gateio.ws'], 'group spot coin priced only from its exchanges');
$hits = [];
reset_calls();
feed(msg('ok 571', -4500, 812));
check(!sent(['sendMessage']) && !sent(['sendPhoto']) && !$hits, 'chat word ticker ignored in groups');
reset_calls();
feed(msg('zzx', 4501, 4501));
check((bool)(sent(['sendPhoto']) ?: sent(['sendMessage'])), 'bare spot coin answered in private');
Http::$fake = $prevFake;
$GLOBALS['http'] = [];
check(SpotDir::refresh() === 0 && SpotDir::mask('ZZX') === (8 | 16), 'spot directory kept when exchanges fail');
@unlink($spotFile);
if ($spotPrev !== null) {
    file_put_contents($spotFile, $spotPrev);
}
foreach (glob(Config::$root . '/data/compiled/spot-*.php') ?: [] as $f) {
    @unlink($f);
}
SpotDir::reset(['at' => time(), 's' => []]);
$own = DB::kvHas('emoji_map') ? DB::kvGet('emoji_map') : [];
Settings::set('emoji_invalid', []);
Settings::set('emoji_map', ['🎨' => '5300000000000000009']);
$fileId = Settings::defaults()['emoji_map']['🧮'] ?? null;
check($fileId && UI::emojiId('🧮') === (string)$fileId && UI::emojiId('🎨') === '5300000000000000009', 'file emoji map stays active next to panel pairs');
Settings::set('emoji_map', $own);
echo "passed: $pass, failed: $fail\n";
exit($fail ? 1 : 0);
