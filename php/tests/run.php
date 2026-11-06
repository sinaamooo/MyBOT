<?php

require __DIR__ . '/../src/bootstrap.php';

$dbFile = sys_get_temp_dir() . '/mybot_test_' . getmypid() . '.db';
@unlink($dbFile);
Config::set('BOT_TOKEN', '42:TEST');
Config::set('KASHIDA', '0');
Config::set('ADMIN_IDS', '999');
Config::set('DB_PATH', $dbFile);
Config::set('CACHE_DIR', sys_get_temp_dir() . '/mybot_cache_' . getmypid());
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
            if (!empty($GLOBALS['reject_blank']) && str_contains(json_encode($p['reply_markup'] ?? [], JSON_UNESCAPED_UNICODE), "\u{200B}")) {
                return ['ok' => false, 'description' => 'Bad Request: BUTTON_TEXT_INVALID'];
            }
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
            if ($p['chat_id'] == -100777 || $p['chat_id'] == -100778) {
                $st = $u == BOT['id'] ? ($p['chat_id'] == -100777 ? 'administrator' : 'member') : ($u == 6060 && empty($GLOBALS['joined6060']) ? 'left' : 'member');
                return ['ok' => true, 'result' => ['status' => $st, 'user' => ['id' => $u, 'is_bot' => false, 'first_name' => 'u']]];
            }
            if ($p['chat_id'] == NON_MEMBER) {
                return ['ok' => true, 'result' => ['status' => 'left', 'user' => ['id' => $u, 'is_bot' => false, 'first_name' => 'u']]];
            }
            return ['ok' => true, 'result' => ['status' => $u == 777 ? 'administrator' : 'member', 'user' => ['id' => $u, 'is_bot' => false, 'first_name' => 'u']]];
        case 'getChat':
            if (in_array($p['chat_id'], ['@mychan', '@otherchan'], true)) {
                return ['ok' => true, 'result' => ['id' => $p['chat_id'] === '@mychan' ? -100777 : -100778, 'type' => 'channel', 'title' => $p['chat_id'] === '@mychan' ? 'My Chan' : 'Other', 'username' => ltrim($p['chat_id'], '@')]];
            }
            return ['ok' => false, 'description' => 'Bad Request: chat not found'];
        case 'getChatAdministrators':
            return ['ok' => true, 'result' => [['status' => 'creator', 'user' => ['id' => 777, 'is_bot' => false, 'first_name' => 'adm']]]];
        case 'setMessageReaction':
            if (in_array($p['reaction'][0]['emoji'] ?? '', ['💱', '🔰', '🔍'], true) || (($p['reaction'][0]['emoji'] ?? '') === '⚡' && !empty($GLOBALS['reject_blue']))) {
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
check(Guard::scammy('بیا پیوی حرف بزنیم') && Guard::scammy('pv bia') && !Guard::scammy('سلام قیمت تتر چنده؟'), 'scam phrases');
check(Guard::clockInName('Ali | 12:45') === [12, 45] && Guard::clockInName('𝟏𝟐:𝟑𝟎 Sara') === [12, 30], 'clock in name');
$tg = Tgju::parse('<tr id="f-diff_usd"><th>USD دلار</th><td>1,050,000</td><td>1</td><td>0.1%</td></tr><tr id="f-diff_try"><th>لیر ترکیه</th><td>24,800</td></tr>');
check(($tg['USD']['toman'] ?? 0) == 105000 && ($tg['TRY']['toman'] ?? 0) == 2480, 'tgju parse');
check(Entities::html('Hi bold 😎', [['type' => 'bold', 'offset' => 3, 'length' => 4], ['type' => 'custom_emoji', 'offset' => 8, 'length' => 2, 'custom_emoji_id' => '55']]) === 'Hi <b>bold</b> <tg-emoji emoji-id="55">😎</tg-emoji>', 'entities html');
check(Persian::visual('ارزش کل (TON)') !== '' && Persian::shape('سلام') !== 'سلام', 'persian shaping');
check(Account::created(100000000) < Account::created(6000000000) && Account::level(10000) > 5, 'account helpers');

reset_calls();
feed(msg('/start'));
$lp = sent()[0] ?? null;
check($lp && str_contains($lp[1]['text'], 'Choose the bot language') && array_column(buttons($lp), 'callback_data') === ['ul:fa', 'ul:en', 'ul:ru'], 'language asked on first start');
reset_calls();
cb('ul:fa', 5, 5);
$s = sent(['sendMessage'])[0] ?? null;
check(Lang::of(5) === 'fa' && $s && str_contains($s[1]['text'], 'خوش اومدی'), 'start text');
Lang::set(6, 'fa');
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
$ed = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'editMessageText' && $c[1]['chat_id'] == 999));
check($ed && in_array("scamadm:revoke:$sid", array_column(buttons($ed[0]), 'callback_data'), true), 'approved scam message has revoke button');
reset_calls();
feed(msg('/check', -300, 64, $scammer));
check(str_contains(last_text(), 'لیست کلاهبرداران'), 'scam check');
cb("scamadm:revoke:$sid", 999, 999);
check(DB::scam($sid)['status'] === 'revoked', 'admin revokes an approved scam with the button');
DB::updateScam($sid, ['status' => 'approved']);

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
feed(msg('/panel', -400, 777));
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
$names = array_column($GLOBALS['calls'], 0);
$we = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'editMessageText' && ($c[1]['message_id'] ?? 0) === 1));
$wd = array_search('deleteMessage', $names, true);
check($we && str_contains($we[0][1]['text'], '5386367538735104399') && str_contains($we[0][1]['text'], 'لطفاً کمی منتظر بمانید') && (bool)array_filter(buttons($we[0]), fn($b) => str_contains($b['url'] ?? '', 'startgroup'))
    && $wd === false && str_contains((string)(end($we)[1]['text'] ?? ''), 'یوزرنیم'), 'prompt turns into the waiting message and goes back to the question after the card, never deleted');
$wr = new ReflectionProperty('Waiter', 'frames');
$fr = $wr->getValue(Waiter::start(msg('x', -500, 70), 'gift_wait', 1));
check(count($fr) === 1 && str_starts_with($fr[0], '<tg-emoji emoji-id="5386367538735104399">') && str_ends_with($fr[0], 'لطفاً کمی منتظر بمانید'), 'waiting text is shown whole, exactly as written');

Config::set('API_ID', '12345');
Config::set('API_HASH', 'hash');
mt_fake();
reset_calls();
feed(msg('/gift @tofan', -500, 71, null, null, 'Sina'));
$ph = sent(['sendPhoto']);
$cap = $ph[0][1]['caption'] ?? '';
check(str_contains($cap, 'https://t.me/nft/PlushPepe-1') && str_contains($cap, 'Tofan'), 'gift by username via mtproto');
$ws = array_values(array_filter(sent(['sendMessage']), fn($c) => str_contains($c[1]['text'], 'کمی منتظر بمانید')));
check($ws && sent(['deleteMessage']), 'direct /gift shows a waiting message and removes it');
reset_calls();
feed(msg('/gift', -500, 73, null, null, 'Reza'));
reset_calls();
feed(msg('@nobody_y', -500, 73, ['message_id' => 2, 'date' => time(), 'chat' => ['id' => -500, 'type' => 'supergroup'], 'from' => BOT, 'text' => 'x'], null, 'Reza'));
$ue = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'editMessageText' && ($c[1]['message_id'] ?? 0) === 2));
check(count($ue) >= 2 && str_contains(end($ue)[1]['text'], 'نتوانستم') && !sent(['sendMessage']), 'unknown user turns the waiting message into the error');
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
$rx = sent(['setMessageReaction']);
check(($h['method'] ?? '') === 'sendPhoto' && str_starts_with((string)($h['photo'] ?? ''), 'AgAC') && !sent(['sendPhoto']) && $rx && ($rx[0][1]['reaction'][0]['emoji'] ?? '') === '⚡',
    'cached card goes in the webhook reply, known reaction is sent in the background');
feed(msg('سلام', -4400, 800));
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
feed(msg("💎 کانال دوم\nhttps://t.me/second", 999, 999, null, [['type' => 'custom_emoji', 'offset' => 0, 'length' => 2, 'custom_emoji_id' => '5400000000000000001']]));
$xb = (Settings::get('extra_buttons') ?: [])[0] ?? [];
check(($xb['text'] ?? '') === 'کانال دوم' && ($xb['icon'] ?? '') === '5400000000000000001' && str_contains((string)last_text(), 'دکمه تبلیغ 1'), 'ad button keeps sent premium emoji');
cb('adm:xbstyle:0:success', 999, 999);
check(((Settings::get('extra_buttons') ?: [])[0]['style'] ?? '') === 'success', 'ad button color');
reset_calls();
feed(msg('/start', 6, 6));
$kbr = sent(['sendMessage'])[0][1]['reply_markup']['inline_keyboard'] ?? [];
$xi = array_key_first(array_filter($kbr, fn($r) => ($r[0]['url'] ?? '') === 'https://t.me/second'));
$gi = array_key_first(array_filter($kbr, fn($r) => str_contains($r[0]['url'] ?? '', 'startgroup=true')));
check($xi !== null && $gi !== null && $xi < $gi && ($kbr[$xi][0]['icon_custom_emoji_id'] ?? '') === '5400000000000000001' && ($kbr[$xi][0]['style'] ?? '') === 'success'
    && ($kbr[$xi][0]['text'] ?? '') === 'کانال دوم', 'ad button above add-to-group with emoji and color');
cb('adm:xbdel:0', 999, 999);
check(!(Settings::get('extra_buttons') ?: []), 'extra button removed');
reset_calls();
feed(msg('/panel', -4400, 777));
check(str_contains((string)last_text(), 'تنظیمات گروه'), 'panel in group opens group settings');

reset_calls();
feed(msg('الماس', -7100, 501, null, null, 'Ali'));
$r1 = Diamonds::points(501);
check($r1 >= Diamonds::MIN && str_contains((string)last_text(), 'الماس'), 'diamond hit');
$dmb = buttons(end($GLOBALS['calls']));
check(str_contains($dmb[0]['url'] ?? '', 'startgroup=true'), 'diamond reply has add-to-group button');
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
    check(($GLOBALS['calls'][0][0] ?? '') === 'setMessageReaction' ? true : (bool)array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction' && ($c[1]['reaction'][0]['emoji'] ?? '') === '🏆'), "leaderboard reaction 🏆 for $w");
}
reset_calls();
feed(msg('حساب', -7100, 502, null, null, 'Reza'));
$acc = sent(['sendMessage']);
$ab = buttons($acc[0] ?? [0, []]);
$p502 = Fmt::int(Diamonds::points(502));
$rows = $acc[0][1]['reply_markup']['inline_keyboard'] ?? [];
check($acc && count($rows) >= 2 && count($rows[0]) === 1 && count($rows[1]) === 1 && str_contains($ab[0]['text'] ?? '', 'سطح') && str_contains($ab[1]['text'] ?? '', $p502)
    && str_starts_with($ab[1]['callback_data'] ?? '', 'gb:'), 'account buttons stacked: level on top, diamonds below');
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
check($rx && $rx[0][1]['reaction'][0]['emoji'] === '💱' && end($rx)[1]['reaction'][0]['emoji'] === '⚡' && $sidx !== false && $ridx < $sidx, 'price reaction tries 💱 then closest allowed, before the reply');
$GLOBALS['reject_blue'] = true;
reset_calls();
feed(msg('601 درهم', -8801, 61));
$rx = array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction'));
check(count($rx) === 3 && $rx[2][1]['reaction'][0]['emoji'] === '🐳', 'reaction falls back to blue whale');
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
check(count($cmds) === 3 && array_column($cmds[2][1]['commands'], 'command') === ['gift', 'account', 'emoji', 'help'] && array_column($cmds[0][1]['commands'], 'command') === ['gift', 'account', 'emoji', 'help'] && (int)DB::kvGet('commands_v') === Start::COMMANDS_V, 'command menu registered');

reset_calls();
foreach (['6037-9975-9950-3209', 'IR820540102680020817909002', 'شماره 09121234567', '/phone +15005550006', '/card 6037997599503209'] as $i => $ex) {
    feed(msg($ex, -600, 80));
    feed(msg($ex, 80 + $i, 80 + $i));
}
$gone = array_filter($GLOBALS['calls'], fn($c) => in_array($c[0], ['sendMessage', 'sendPhoto'], true));
check(!$gone && Parse::expression('6037-9975-9950-3209') === null && Parse::expression('6037 9975 9950 3209') === null, 'card, iban and phone checkers removed: ' . implode(' | ', array_map(fn($c) => mb_substr(strip_tags((string)($c[1]['text'] ?? $c[1]['caption'] ?? '')), 0, 60), $gone)));

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
$rxh = array_column(array_column(array_column(array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction')), 1), 'reaction'), 0);
check(array_column($rxh, 'emoji') === ['🔍', '👀'], 'hash reaction tries 🔍 then 👀');
$wb = array_values(array_filter(buttons($txm ?? [0, []]), fn($b) => str_starts_with($b['callback_data'] ?? '', 'wl:')));
$txb = buttons($txm ?? [0, []]);
check(count($wb) === 1 && str_contains($txb[0]['text'] ?? '', 'مقصد') && ($txb[0]['style'] ?? '') === 'primary' && !str_contains($txm[1]['text'], 'کانترکت'), 'tx shows only the receiver wallet button, blue, and no contract line');
reset_calls();
cb($wb[0]['callback_data'], 91, -700);
check(!empty($GLOBALS['calls'][0][1]['show_alert']), 'wallet button owner only');
reset_calls();
cb($wb[0]['callback_data'], 90, -700);
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
$GLOBALS['http']['publicnode.com'] = function ($r) {
    if (!isset($r['json'][0])) {
        return null;
    }
    $res = [];
    foreach ($r['json'] as $c) {
        $v = match ($c['method']) {
            'eth_getBalance' => '0x' . dechex(123456789012345678),
            'eth_getTransactionCount' => '0x4d2',
            'eth_getCode' => '0x',
            'eth_call' => '0x' . str_pad(dechex(987654321000), 64, '0', STR_PAD_LEFT),
            default => '0x0',
        };
        $res[] = ['jsonrpc' => '2.0', 'id' => $c['id'], 'result' => $v];
    }
    return $res;
};
Cache::forget('wlr:' . md5('evm|0x28c6c06298d514db089934071355e5743bf21d60'));
reset_calls();
feed(msg('0x28c6c06298d514db089934071355e5743bf21d60', -4600, 841));
$bw = array_values(array_filter(sent(['sendMessage']), fn($c) => str_contains($c[1]['text'], 'شبکه: ')));
$bt = (string)($bw[0][1]['text'] ?? '');
check($bw && substr_count($bt, '<blockquote') === substr_count($bt, '</blockquote>') && substr_count($bt, 'شبکه: ') === 1 && str_contains($bt, '<b>1</b>'), 'evm wallet shows one network like ton and tron');
$wm = UI::t('wallet_chain', ['chain_icon' => '🔷', 'chain' => 'X', 'balance' => '1', 'symbol' => 'X', 'explorer' => 'https://x']);
check(UI::textLen(str_repeat($wm, 3)) < strlen(str_repeat($wm, 3)) && UI::textLen('<b>a</b>&amp;😀') === 4, 'text length counts what telegram counts');
$fm = Prices::fetchMany(['BTC', 'ETH', 'NOPE']);
check(round($fm['BTC']['price'] ?? 0) == 100000 && round($fm['ETH']['price'] ?? 0) == 4000 && $fm['NOPE'] === null, 'several coins priced in one round');
$rxw = array_column(array_column(array_column(array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === 'setMessageReaction')), 1), 'reaction'), 0);
check(array_slice(array_column($rxw, 'emoji'), -2) === ['🔰', '👌'], 'wallet reaction tries 🔰 then 👌');

$GLOBALS['http']['mempool.space/api/tx/'] = fn($r) => ['txid' => str_repeat('c', 64), 'status' => ['confirmed' => true, 'block_height' => 100, 'block_time' => 1700000000], 'fee' => 1000,
    'vin' => [['prevout' => ['scriptpubkey_address' => 'bc1qsender', 'value' => 300000000]]], 'vout' => [['scriptpubkey_address' => 'bc1qrecv', 'value' => 250000000]]];
$GLOBALS['http']['mempool.space/api/blocks/tip/height'] = '105';
unset($GLOBALS['http']['tronscanapi.com/api/transaction-info']);
reset_calls();
feed(msg(str_repeat('c', 64)));
check(str_contains((string)last_text(), 'Bitcoin') && str_contains((string)last_text(), '2.5 BTC'), 'btc tx');

$GLOBALS['http']['api.trongrid.io/wallet/gettransactionbyid'] = fn($r) => ($r['json']['value'] ?? '') === str_repeat('9', 64) ? ['txID' => str_repeat('9', 64), 'ret' => [['contractRet' => 'SUCCESS']],
    'raw_data' => ['contract' => [['type' => 'TransferContract', 'parameter' => ['value' => ['owner_address' => '41' . str_repeat('a', 40), 'to_address' => '41' . str_repeat('b', 40), 'amount' => 7000000]]]]]] : null;
$GLOBALS['http']['api.trongrid.io/wallet/gettransactioninfobyid'] = fn($r) => ['blockNumber' => 123, 'blockTimeStamp' => 1700000000000, 'fee' => 1100000];
$GLOBALS['http_seen'] = [];
reset_calls();
feed(msg(str_repeat('9', 64)));
$gt = (string)last_text();
check(str_contains($gt, 'TRON') && str_contains($gt, '7 TRX') && str_contains($gt, '1.1 TRX'), 'tron tx from trongrid with info fetched in the same round');
unset($GLOBALS['http']['api.trongrid.io/wallet/gettransactionbyid'], $GLOBALS['http']['api.trongrid.io/wallet/gettransactioninfobyid']);
$evmHash = '0x' . str_repeat('d', 64);
$GLOBALS['rpc_log'] = [];
$prevNode = $GLOBALS['http']['publicnode.com'];
$GLOBALS['http']['publicnode.com'] = function ($r) use ($evmHash, $prevNode) {
    if (!isset($r['json'][0])) {
        return null;
    }
    $GLOBALS['rpc_log'][] = parse_url($r['url'], PHP_URL_HOST) . ':' . implode(',', array_column($r['json'], 'method'));
    $eth = str_contains($r['url'], 'ethereum');
    $out = [];
    foreach ($r['json'] as $c) {
        $v = match ($c['method']) {
            'eth_getTransactionByHash' => $eth ? ['hash' => $evmHash, 'blockNumber' => '0x10', 'from' => '0x' . str_repeat('1', 40), 'to' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'value' => '0x0', 'gasPrice' => '0x3b9aca00', 'input' => '0x'] : null,
            'eth_getTransactionReceipt' => $eth ? ['status' => '0x1', 'gasUsed' => '0x5208', 'effectiveGasPrice' => '0x3b9aca00', 'logs' => [['address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'topics' => [Chain::TRANSFER, '0x' . str_repeat('0', 24) . str_repeat('1', 40), '0x' . str_repeat('0', 24) . str_repeat('2', 40)], 'data' => '0x' . str_pad(dechex(25000000), 64, '0', STR_PAD_LEFT)]]] : null,
            'eth_blockNumber' => '0x20',
            'eth_getBlockByNumber' => ['timestamp' => '0x6553f100'],
            default => null,
        };
        $out[] = ['jsonrpc' => '2.0', 'id' => $c['id'], 'result' => $v];
    }
    return $out;
};
reset_calls();
feed(msg($evmHash));
$et = (string)last_text();
$ethRounds = array_values(array_filter($GLOBALS['rpc_log'], fn($l) => str_starts_with($l, 'ethereum')));
check(str_contains($et, 'Ethereum') && str_contains($et, '25 USDT') && str_contains($et, '17') && !str_contains($et, 'کانترکت')
    && count($ethRounds) === 2 && str_contains($ethRounds[0], 'eth_getTransactionByHash,eth_getTransactionReceipt,eth_blockNumber') && $ethRounds[1] === 'ethereum-rpc.publicnode.com:eth_getBlockByNumber', 'evm token tx in two rounds: ' . implode(' | ', $ethRounds));
$GLOBALS['http']['publicnode.com'] = $prevNode;

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
check(count($vid) === 1 && str_contains($vid[0][1]['caption'], '@TestBot') && !str_contains($vid[0][1]['caption'], 'ریلز اینستاگرام') && buttons($vid[0]) && !array_filter($GLOBALS['calls'], fn($c) => in_array($c[0], ['sendMessage', 'editMessageText'], true)), 'instagram single video with caption and button only');
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

reset_calls();
feed(msg('1 eur', -950, 97));
$p = sent(['sendPhoto']);
check($p && str_starts_with($p[0][1]['caption'], '<tg-emoji emoji-id="5143540580424354040">') && str_contains($p[0][1]['caption'], '<tg-emoji emoji-id="5143224307622610764">'), 'fiat currency badge and dollar flag');
reset_calls();
feed(msg('500000 ریال', 98, 98));
$p = sent(['sendPhoto']);
check($p && str_contains($p[0][1]['caption'], '5143575421199059367') && str_contains($p[0][1]['caption'], '5143159333357357518'), 'rial badge and usdt badge');
reset_calls();
foreach (['/10 تتر', '/500000 تومان', '/btc', '/randomcommand', '/aed@OtherBot 5', '/500 درهم', '/درهم 500', '/aed 500', '/eur', '/arz', '/dollar', '/p btc', '/price ton', '/قیمت تون',
    '/wallet TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', '/tx abc', '/uid', '/calc 2+2', '/tr', '/lang', '/زبان', '/settings', '/تنظیمات', '/insta @x', '/راهنما', '/acc', '/me'] as $q) {
    feed(msg($q));
    feed(msg($q, -950, 97));
}
foreach (['/emojis', '/unscam 1', '/setrate 1', '/admin', '/پنل'] as $q) {
    feed(msg($q, 999, 999));
}
check(!sent(), 'only gift, account, help, emoji, panel, check and scam commands remain');

reset_calls();
$GLOBALS['user_gifts'] = ['total_count' => 0, 'gifts' => []];
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -1234, 'type' => 'supergroup', 'title' => 'New'], 'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'x'],
    'date' => time(), 'old_chat_member' => ['status' => 'left', 'user' => BOT], 'new_chat_member' => ['status' => 'member', 'user' => BOT]]]);
check(str_contains((string)last_text(), 'اضافه شد') && !str_contains((string)last_text(), 'Choose the bot language'), 'bot added message without language picker');
reset_calls();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -1234, 'type' => 'supergroup', 'title' => 'New'], 'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'x'],
    'date' => time(), 'old_chat_member' => ['status' => 'member', 'user' => BOT], 'new_chat_member' => ['status' => 'administrator', 'user' => BOT]]]);
check(str_contains((string)last_text(), 'ادمین شد'), 'bot promoted message');
reset_calls();
feed(msg('/start', 3001, 3001));
$lp = sent(['sendMessage'])[0] ?? null;
$lpb = buttons($lp ?? [0, []]);
check($lp && str_contains($lp[1]['text'], 'Choose the bot language') && array_column($lpb, 'callback_data') === ['ul:fa', 'ul:en', 'ul:ru']
    && ($lpb[1]['icon_custom_emoji_id'] ?? '') !== '' && str_contains($lpb[1]['text'] ?? '', '𝗘𝗡'), 'language picker in private start');
reset_calls();
cb('ul:en', 3001, 3001);
$texts = implode("\n", array_map(fn($c) => (string)($c[1]['text'] ?? ''), array_filter($GLOBALS['calls'], fn($c) => in_array($c[0], ['sendMessage', 'editMessageText'], true))));
check(Lang::of(3001) === 'en' && str_contains($texts, 'English') && str_contains($texts, 'welcome') && (bool)array_filter(buttons(end($GLOBALS['calls'])), fn($b) => str_contains($b['url'] ?? '', 'startgroup')), 'english start after choosing');
reset_calls();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -2234, 'type' => 'supergroup', 'title' => 'EN'], 'from' => ['id' => 3001, 'is_bot' => false, 'first_name' => 'x'],
    'date' => time(), 'old_chat_member' => ['status' => 'left', 'user' => BOT], 'new_chat_member' => ['status' => 'administrator', 'user' => BOT]]]);
check(Lang::of(-2234) === 'en' && str_contains((string)last_text(), 'The bot is now an admin in this group'), 'group takes the language of the user who added the bot');
reset_calls();
feed(msg('10 usdt', -2234, 77));
$en = sent(['sendPhoto']) ?: sent(['sendMessage']);
$ent = (string)($en[0][1]['caption'] ?? $en[0][1]['text'] ?? '');
check($en && str_contains($ent, 'Toman') && !preg_match('/\p{Arabic}/u', strip_tags(preg_replace('/<tg-emoji[^>]*>.*?<\/tg-emoji>/u', '', $ent))) && !str_contains($ent, '  '), 'english group price reply has no persian and single spaces');
reset_calls();
feed(msg('/help', -2234, 77));
check(str_contains((string)last_text(), 'Bot guide'), 'english help');
$miss = [];
foreach (['price ton', '500 try usd', '2 eur to try', 'stars', 'premium', 'xo 100', 'mine 100', 'top', 'balance'] as $i => $ex) {
    reset_calls();
    feed(msg($ex, -2234, 860 + $i));
    if (!sent(['sendMessage', 'sendPhoto'])) {
        $miss[] = $ex;
    }
}
check(!$miss, 'english help examples get a reply: ' . implode(' | ', $miss));
reset_calls();
feed(msg('/panel', -2234, 999));
$gp = sent(['sendMessage'])[0] ?? null;
$gpb = array_merge(...($gp[1]['reply_markup']['inline_keyboard'] ?? [[]]));
check($gp && str_contains($gp[1]['text'], 'Group settings') && !array_filter($gpb, fn($b) => str_contains($b['callback_data'] ?? '', 'lang')) && (bool)array_filter($gpb, fn($b) => str_contains($b['text'] ?? '', 'Anti-scam')), 'english group settings without language buttons');
reset_calls();
feed(msg('/start', 3002, 3002));
cb('ul:ru', 3002, 3002);
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'my_chat_member' => ['chat' => ['id' => -2235, 'type' => 'supergroup', 'title' => 'RU'], 'from' => ['id' => 3002, 'is_bot' => false, 'first_name' => 'y'],
    'date' => time(), 'old_chat_member' => ['status' => 'left', 'user' => BOT], 'new_chat_member' => ['status' => 'member', 'user' => BOT]]]);
check(Lang::of(-2235) === 'ru' && str_contains((string)last_text(), 'Бот добавлен'), 'russian group from russian adder');
reset_calls();
feed(msg('алмаз', -2235, 78));
check(str_contains((string)last_text(), 'Вы получили'), 'russian diamond word and reply');
reset_calls();
feed(msg('/lang', 3002, 3002));
cb('ul:en', 3002, 3002);
reset_calls();
feed(msg('/help', -2235, 78));
check(Lang::of(-2235) === 'en' && str_contains((string)last_text(), 'Bot guide'), 'group follows when the adder changes language in private');
DB::upsertChat(['id' => -2236, 'type' => 'supergroup', 'title' => 'Old']);
DB::setChatSetting(-2236, 'lang', 'fa');
reset_calls();
feed(msg('/help', -2236, 79));
check(Lang::owner(-2236) === 777 && count(sent(['getChatAdministrators'])) === 1 && !str_contains((string)last_text(), 'Bot guide'), 'old group finds its owner once');
cb('ul:ru', 777, 777);
reset_calls();
feed(msg('/help', -2236, 79));
check(Lang::of(-2236) === 'ru' && !sent(['getChatAdministrators']) && !str_contains((string)last_text(), 'راهنما'), 'old group follows its owner language');
cb('ul:fa', 777, 777);
UI::$lang = 'fa';

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
$D = '<tg-emoji emoji-id="5143130625795951140">x</tg-emoji>';
$al = UI::align($E(1) . " <b>عنوان</b>\n" . $E(2) . "<code>UQB1</code>\n<blockquote>" . $E(3) . "    NFT: 5\n" . $D . "    موجودی: 1\n" . $E(9) . " الماس: 1</blockquote>\n" . $E(5) . "   1405/08/14");
check($al === $E(1) . " <b>عنوان</b>\n" . $E(2) . " <code>UQB1</code>\n<blockquote>" . $E(3) . " NFT: 5\n" . $D . "  موجودی: 1\n" . $E(9) . "    الماس: 1</blockquote>\n\u{200E}" . $E(5) . " 1405/08/14"
    && UI::align('<blockquote>' . $D . ' موجودی: 1</blockquote>', 4) === '<blockquote>' . $D . '    موجودی: 1</blockquote>'
    && UI::align('<blockquote>' . $D . ' موجودی: 1</blockquote>', 3) === '<blockquote>' . $D . '   موجودی: 1</blockquote>'
    && UI::align('<blockquote>' . $E(6) . '    <b>370,185</b></blockquote>') === '<blockquote>' . $E(6) . ' <b>370,185</b></blockquote>'
    && UI::align("\u{200F}" . $E(7) . '   <b>800 POL</b>') === "\u{200F}" . $E(7) . '  <b>800 POL</b>' && UI::align($E(8) . '    @HashpriceBot') === $E(8) . ' @HashpriceBot'
    && UI::align($E(1) . "    نام: x\n" . $E(2) . "  یوزرنیم\n" . $E(3) . ' لول') === $E(1) . "  نام: x\n" . $E(2) . "  یوزرنیم\n" . $E(3) . ' لول'
    && UI::align('<blockquote>' . $E(6) . '     ترون: 1</blockquote>', 4, true) === '<blockquote>' . $E(6) . '     ترون: 1</blockquote>'
    && UI::align('<b>hello</b> ' . $E(1)) === '<b>hello</b> ' . $E(1), 'emoji spacing: dots 2 and icons 4 in message quotes, 4 in caption quotes, 1 elsewhere');
$ft = UI::align("<blockquote>" . $E(1) . " a</blockquote>\n\n" . $E(2) . " رتبه‌ی شما: <b>5</b> " . $E(3));
check($ft === "<blockquote>" . $E(1) . " a</blockquote>\n\n\u{200E}" . $E(2) . " رتبه‌ی شما: <b>5</b> " . $E(3) . "\u{200F}" && UI::align($ft) === $ft
    && UI::align("سلام\n" . $E(2) . ' دنیا') === "سلام\n" . $E(2) . ' دنیا', 'lines after the last quote stay on the left');
check(UI::align($E(1) . " تاپ\n\n" . $E(2) . ' رتبه', 2, true) === $E(1) . " تاپ\n\n\u{200E}" . $E(2) . " رتبه\u{200F}" && UI::align($E(1) . ' تاپ', 2, true) === $E(1) . ' تاپ', 'caption without quote keeps last line on the left');
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
base_http();
check(Parse::pair('500 تتر تون') === [500.0, ['crypto', 'USDT'], ['crypto', 'TON']] && Parse::pair('800 تتر بیت کوین') === [800.0, ['crypto', 'USDT'], ['crypto', 'BTC']]
    && Parse::pair('500 لیر دلار') === [500.0, ['fiat', 'TRY'], ['fiat', 'USD']] && Parse::pair('500 لیر به دلار') === [500.0, ['fiat', 'TRY'], ['fiat', 'USD']]
    && Parse::pair('500 لیر چند دلار میشه؟') === [500.0, ['fiat', 'TRY'], ['fiat', 'USD']] && Parse::pair('100 دلار کانادا') === null && Parse::pair('500 تتر') === null
    && Parse::pair('2 تا دلار') === null && Parse::pair('100 دلار خریدم') === null && Parse::pair('5000 استارز تون') === [5000.0, ['stars', 'STARS'], ['crypto', 'TON']], 'pair parsing');
reset_calls();
feed(msg('500 تتر تون', -4600, 821));
$pc = sent(['sendPhoto']) ?: sent(['sendMessage']);
$pt = (string)($pc[0][1]['caption'] ?? $pc[0][1]['text'] ?? '');
check($pc && str_contains($pt, '166.6667') && str_contains($pt, "<code>\u{200E}1 USDT = 0.3333 TON</code>") && !str_contains($pt, '1 TON = 3 USDT') && !str_contains($pt, 'معکوس'), 'usdt to ton conversion without inverse line');
reset_calls();
feed(msg('800 تتر بیت کوین', -4600, 822));
$pc = sent(['sendPhoto']) ?: sent(['sendMessage']);
check($pc && str_contains((string)($pc[0][1]['caption'] ?? $pc[0][1]['text'] ?? ''), '0.008'), 'usdt to btc conversion');
reset_calls();
feed(msg('500 لیر دلار', -4600, 823));
$pc = sent(['sendPhoto']) ?: sent(['sendMessage']);
$pt = (string)($pc[0][1]['caption'] ?? $pc[0][1]['text'] ?? '');
check($pc && str_contains($pt, '12.1951') && !str_contains($pt, 'ارزش دلاری'), 'lira to dollar conversion');
reset_calls();
feed(msg('قیمت 2 یورو به لیر', -4600, 824));
$pc = sent(['sendPhoto']) ?: sent(['sendMessage']);
check($pc && str_contains((string)($pc[0][1]['caption'] ?? $pc[0][1]['text'] ?? ''), '91.1111'), 'euro to lira with price prefix');
check(!str_contains((string)($pc[0][1]['caption'] ?? $pc[0][1]['text'] ?? ''), '<b>'), 'price replies without bold');
$miss = [];
foreach (['btc', 'قیمت تون', '10 تتر', '500 لیر دلار', '500000 تومان', '500 درهم', 'نرخ ارز', 'قیمت پریمیوم', 'استارز', 'پریمیوم', '698*789'] as $i => $ex) {
    reset_calls();
    feed(msg($ex, -4600, 830 + $i));
    if (!sent(['sendMessage', 'sendPhoto'])) {
        $miss[] = $ex;
    }
}
check(!$miss && Currencies::bareAsset('طلا') === ['gold', 'GOLD18'] && Currencies::resolve('سکه')[0] === 'gold', 'every help example gets a reply: ' . implode(' | ', $miss));
reset_calls();
feed(msg('/help', -4600, 850));
$hp = (string)last_text();
check(substr_count($hp, '<blockquote>') === 24 && str_contains($hp, 'پینترست') && !str_contains($hp, 'شبا') && !str_contains($hp, 'شماره') && str_contains($hp, 'دوز') && str_contains($hp, 'اینستاگرام')
    && !preg_match('~/(lang|arz|panel|settings|insta|p )~', $hp) && !str_contains($hp, 'محافظ گروه'), 'help lists every feature');
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

$G = -9900;
$tgt = fn($id, $name = 'T') => ['message_id' => ++$GLOBALS['mid'], 'date' => time(), 'chat' => ['id' => $G, 'type' => 'supergroup'], 'from' => ['id' => $id, 'is_bot' => false, 'first_name' => $name], 'text' => 'hi'];
$mcall = fn($name) => array_values(array_filter($GLOBALS['calls'], fn($c) => $c[0] === $name));
reset_calls();
feed(msg('بن', $G, 6001, $tgt(5001)));
check(!$mcall('banChatMember'), 'members cannot ban by reply');
reset_calls();
feed(msg('بن', $G, 777, $tgt(5001, 'Ali')));
$bn = $mcall('banChatMember');
$br = sent(['sendMessage']);
check($bn && $bn[0][1]['user_id'] === 5001 && $br && str_contains($br[0][1]['text'], 'بـن شـد') && str_contains($br[0][1]['text'], '<blockquote><tg-emoji')
    && in_array('md:unban:5001', array_column(buttons($br[0]), 'callback_data'), true), 'admin bans by reply with quote, premium emoji and unban button');
reset_calls();
cb('md:unban:5001', 6001, $G);
check(!$mcall('unbanChatMember'), 'unban button is admin only');
reset_calls();
cb('md:unban:5001', 777, $G);
check($mcall('unbanChatMember') && str_contains((string)(end($GLOBALS['calls'])[1]['text'] ?? ''), 'بـرداشـتـه'), 'unban button lifts the ban');
reset_calls();
feed(msg('رفع بن', $G, 777, $tgt(5001)));
check($mcall('unbanChatMember'), 'unban by reply');
reset_calls();
feed(msg('سکوت', $G, 777, $tgt(5001)));
$mu = $mcall('restrictChatMember');
check($mu && ($mu[0][1]['permissions']['can_send_messages'] ?? true) === false && ($mu[0][1]['until_date'] ?? 1) === 0 && str_contains((string)last_text(), 'دائمی'), 'admin mute is permanent');
reset_calls();
feed(msg('حذف سکوت', $G, 777, $tgt(5001)));
check(($mcall('restrictChatMember')[0][1]['permissions']['can_send_messages'] ?? false) === true, 'unmute by reply');
reset_calls();
feed(msg('اخطار', $G, 777, $tgt(5001)));
feed(msg('اخطار', $G, 777, $tgt(5001)));
check(!$mcall('restrictChatMember') && str_contains((string)last_text(), '<b>2</b>'), 'two warnings, no mute yet');
reset_calls();
feed(msg('اخطار', $G, 777, $tgt(5001)));
$wm = $mcall('restrictChatMember');
check($wm && abs(($wm[0][1]['until_date'] ?? 0) - (time() + 840 * 60)) < 5 && str_contains((string)last_text(), '14'), 'third warning mutes for 14 hours');
reset_calls();
feed(msg('اخطار', $G, 777, $tgt(5001)));
feed(msg('حذف اخطار', $G, 777, $tgt(5001)));
check(!DB::one("SELECT 1 FROM strikes WHERE chat_id=? AND user_id=? AND kind='warn'", [$G, 5001]), 'clear warnings by reply');
reset_calls();
feed(msg('تنظیم مدیر', $G, 777, $tgt(5004)));
check(!$mcall('promoteChatMember') && str_contains((string)last_text(), 'فـقـط بـرای مـدیـر ربـات'), 'group admins cannot set admins');
reset_calls();
feed(msg('ادمین', $G, 999, $tgt(5004)));
$pr = $mcall('promoteChatMember');
$ti = $mcall('setChatAdministratorCustomTitle');
check($pr && ($pr[0][1]['can_restrict_members'] ?? false) === true && empty($pr[0][1]['can_promote_members']) && ($ti[0][1]['custom_title'] ?? '') === '𝗔𝗗𝗠𝗜𝗡' && str_contains((string)last_text(), '𝗔𝗗𝗠𝗜𝗡'), 'set admin promotes with bold ADMIN title');
reset_calls();
feed(msg('تنظیم ویژه', $G, 777, $tgt(5002, 'Vip')));
check(!Moderation::isVip($G, 5002), 'group admins cannot set special members');
reset_calls();
feed(msg('تنظیم ویژه', $G, 999, $tgt(5002, 'Vip')));
$vt = $mcall('setChatMemberTag');
check(Moderation::isVip($G, 5002) && str_contains((string)last_text(), 'ویـژه') && ($vt[0][1]['tag'] ?? '') === '𝗩𝗜𝗣' && ($vt[0][1]['user_id'] ?? 0) === 5002
    && !$mcall('promoteChatMember') && str_contains((string)last_text(), '𝗩𝗜𝗣'), 'special member gets the bold VIP tag without becoming an admin');
reset_calls();
feed(msg('بن', $G, 777, $tgt(5002)));
check(!$mcall('banChatMember') && str_contains((string)last_text(), 'مـدیـر یـا عـضـو ویـژه'), 'special members cannot be banned');
reset_calls();
feed(msg('سکوت', $G, 5002, $tgt(5003)));
$vm = $mcall('restrictChatMember');
check($vm && abs(($vm[0][1]['until_date'] ?? 0) - (time() + 600)) < 5, 'special member mutes only for 10 minutes');
reset_calls();
feed(msg('بن', $G, 5002, $tgt(5003)));
check(!$mcall('banChatMember'), 'special member cannot ban');
reset_calls();
feed(msg('اخطار', $G, 5002, $tgt(5003)));
$w1 = (string)last_text();
feed(msg('اخطار', $G, 5002, $tgt(5003)));
check(str_contains($w1, '<b>1</b>') && str_contains((string)last_text(), 'فـقـط 1 اخـطـار'), 'special member gives one warning per user');
reset_calls();
reset_calls();
cb('adm:vips', 999, 999);
$vl = (string)(end($GLOBALS['calls'])[1]['text'] ?? '');
reset_calls();
cb("adm:gvip:$G", 999, 999);
$gl = (string)(end($GLOBALS['calls'])[1]['text'] ?? '');
check(str_contains($vl, 'کاربران ویژه') && str_contains($vl, '5002') && str_contains($gl, '5002'), 'panel lists special members, all groups and per group');
reset_calls();
feed(msg('حذف ویژه', $G, 999, $tgt(5002)));
check(!Moderation::isVip($G, 5002) && ($mcall('setChatMemberTag')[0][1]['tag'] ?? null) === '', 'remove special member and its tag');
$ib = UI::iconize(['text' => '🔴', 'callback_data' => 'x']);
check($ib['text'] === "\u{200B}" && !empty($ib['icon_custom_emoji_id']), 'icon-only button uses a zero-width label so the icon is centered');
$GLOBALS['reject_blank'] = true;
reset_calls();
TG::send(-9900, 'board', UI::kb([[['text' => '🔴', 'callback_data' => 'x']]]));
$bs = sent(['sendMessage']);
check(count($bs) === 2 && (end($bs)[1]['reply_markup']['inline_keyboard'][0][0]['text'] ?? '') === "\u{3164}" && UI::blank() === "\u{3164}", 'falls back to the wide blank when telegram rejects the zero-width label');
$GLOBALS['reject_blank'] = false;
Cache::forget('btn_blank_wide');

$prevHttp = Http::$fake;
$GLOBALS['http']['api.blockchair.com/dash/dashboards/address/'] = fn($r) => ['data' => [basename($r['url']) => ['address' => ['balance' => 250000000, 'transaction_count' => 7]]]];
$dw = Wallets::check('dash', 'XdashTestAddr1');
$GLOBALS['http']['api.haskoin.com/bch/address/'] = ['address' => 'bitcoincash:qtest', 'confirmed' => 150000000, 'unconfirmed' => 50000000, 'txs' => 3];
$bw = Wallets::check('bch', 'qtestaddr');
check(($dw[0]['balance'] ?? 0) == 2.5 && ($dw[0]['txs'] ?? 0) == 7 && ($bw[0]['balance'] ?? 0) == 2.0 && ($bw[0]['symbol'] ?? '') === 'BCH', 'dash falls back to blockchair and bch to haskoin');
unset($GLOBALS['http']['api.blockchair.com/dash/dashboards/address/'], $GLOBALS['http']['api.haskoin.com/bch/address/']);
$gHits = 0;
Http::$fake = function ($r) use ($prevHttp, &$gHits) {
    if (str_contains($r['url'], 'translate.googleapis.com')) {
        $gHits++;
        return ['status' => 429, 'body' => '<html>Sorry...</html>'];
    }
    if (str_contains($r['url'], 'mymemory')) {
        return ['status' => 200, 'body' => json_encode(['responseData' => ['translatedText' => 'Bonjour']])];
    }
    return $prevHttp($r);
};
Cache::forget('tr_google_cool');
$t1 = Translate::run('Hello', 'fa');
$t2 = Translate::run('Hello again', 'fa');
Http::$fake = $prevHttp;
Cache::forget('tr_google_cool');
check($t1 === 'Bonjour' && $t2 === 'Bonjour' && $gHits === 1, 'translate skips google for a while after a 429 and uses the fallback');

check(Pinterest::link('see https://pin.it/4AbCdEf ok') === 'https://pin.it/4AbCdEf' && Pinterest::link('https://www.pinterest.com/pin/123456789012/') !== null
    && Pinterest::link('https://uk.pinterest.co.uk/pin/funny-cat--98765432101/') !== null && Pinterest::link('https://pinterest.com/user/board/') === null
    && Pinterest::id('https://uk.pinterest.co.uk/pin/funny-cat--98765432101/') === '98765432101', 'pinterest links and ids');
$pinApi = function ($r) {
    $id = json_decode($r['params']['data'] ?? '{}', true)['options']['id'] ?? '';
    $base = ['id' => $id, 'title' => 'Sunset <b>', 'description' => 'nice view', 'pinner' => ['username' => 'artist'], 'created_at' => 'Tue, 01 Sep 2025 01:10:33 +0000',
        'aggregated_pin_data' => ['aggregated_stats' => ['saves' => 1234]]];
    $pin = match ($id) {
        '111111111' => $base + ['videos' => ['video_list' => ['V_HLSV4' => ['url' => 'https://v.pinimg.com/a.m3u8', 'width' => 1080], 'V_720P' => ['url' => 'https://v.pinimg.com/720.mp4', 'width' => 720], 'V_480P' => ['url' => 'https://v.pinimg.com/480.mp4', 'width' => 480]]]],
        '222222222' => $base + ['images' => ['orig' => ['url' => 'https://i.pinimg.com/originals/x.jpg'], '736x' => ['url' => 'https://i.pinimg.com/736x/x.jpg']]],
        '333333333' => $base + ['story_pin_data' => ['pages' => [['blocks' => [['image' => ['images' => ['originals' => ['url' => 'https://i.pinimg.com/originals/p1.jpg']]]]]],
            ['blocks' => [['video' => ['video_list' => ['V_EXP7' => ['url' => 'https://v.pinimg.com/p2.mp4', 'width' => 720]]]]]]]]],
        '555555555' => $base + ['videos' => ['video_list' => ['V_HLSV3_MOBILE' => ['url' => 'https://v1.pinimg.com/videos/mc/hls/ab/cd/ef/abc123.m3u8', 'width' => 720]]],
            'story_pin_data' => ['pages' => [['blocks' => [['image' => ['images' => ['originals' => ['url' => 'https://i.pinimg.com/originals/cover.jpg']]]]]]]]],
        '666666666' => $base + ['videos' => null, 'story_pin_data' => ['pages' => [
            ['blocks' => [['type' => 'story_pin_video_block', 'video' => ['video_list' => ['V_HLSV3_MOBILE' => ['url' => 'https://v1.pinimg.com/videos/iht/hls/11/22/33/clip1.m3u8']]],
                'image' => ['images' => ['originals' => ['url' => 'https://i.pinimg.com/originals/c1.png']]]]]],
            ['blocks' => [['type' => 'story_pin_video_block', 'video' => ['video_list' => ['V_EXP4' => ['url' => 'https://v1.pinimg.com/videos/iht/expMp4/44/55/66/clip2_t2.mp4', 'width' => 360],
                'V_EXP7' => ['url' => 'https://v1.pinimg.com/videos/iht/expMp4/44/55/66/clip2_t4.mp4', 'width' => 720]]], 'image' => ['images' => ['originals' => ['url' => 'https://i.pinimg.com/originals/c2.png']]]]]]]]],
        default => null,
    };
    return $pin ? ['resource_response' => ['data' => $pin]] : ['resource_response' => ['data' => null]];
};
$GLOBALS['http']['pinterest.com/resource/PinResource/get/'] = $pinApi;
$GLOBALS['http']['pin.it/vid1'] = '<html><a href="https://www.pinterest.com/pin/111111111/sent/?x=1">x</a></html>';
reset_calls();
feed(msg('https://pin.it/vid1', -9100, 901));
$pv = sent(['sendVideo']);
check($pv && $pv[0][1]['video'] === 'https://v.pinimg.com/720.mp4' && !str_contains($pv[0][1]['caption'], '@artist') && str_contains($pv[0][1]['caption'], '1,234')
    && str_contains($pv[0][1]['caption'], 'Sunset &lt;b&gt;') && array_filter(buttons($pv[0]), fn($b) => str_contains($b['url'] ?? '', 'startgroup')) && empty($pv[0][1]['reply_parameters']),
    'pinterest short link video in best mp4 with caption, add-to-group button and no creator');
reset_calls();
feed(msg('https://www.pinterest.com/pin/222222222/', -9100, 902));
$pp = sent(['sendPhoto']);
check($pp && $pp[0][1]['photo'] === 'https://i.pinimg.com/originals/x.jpg', 'pinterest photo in original size');
reset_calls();
feed(msg('https://www.pinterest.com/pin/333333333/', -9100, 903));
$pg = sent(['sendMediaGroup']);
$pc = sent(['sendMessage']);
check($pg && count($pg[0][1]['media']) === 2 && $pg[0][1]['media'][1]['type'] === 'video' && empty($pg[0][1]['reply_parameters']) && empty($pg[0][1]['media'][0]['caption']), 'pinterest story pin as an album without reply');
check($pc && str_contains($pc[0][1]['text'], '1,234') && array_filter(buttons($pc[0]), fn($b) => str_contains($b['url'] ?? '', 'startgroup')) && ($pc[0][1]['reply_parameters']['message_id'] ?? 0) > 0,
    'pinterest album caption comes with the add-to-group button');
reset_calls();
feed(msg('https://www.pinterest.com/pin/555555555/', -9100, 905));
$pv = sent(['sendVideo']);
check($pv && $pv[0][1]['video'] === 'https://v1.pinimg.com/videos/mc/720p/ab/cd/ef/abc123.mp4' && !sent(['sendPhoto', 'sendMediaGroup']), 'pinterest stream-only video sent as one mp4');
reset_calls();
feed(msg('https://www.pinterest.com/pin/666666666/', -9100, 906));
$pg = sent(['sendMediaGroup']);
check($pg && array_column($pg[0][1]['media'], 'type') === ['video', 'video'] && $pg[0][1]['media'][0]['media'] === 'https://v1.pinimg.com/videos/iht/720p/11/22/33/clip1.mp4'
    && $pg[0][1]['media'][1]['media'] === 'https://v1.pinimg.com/videos/iht/expMp4/44/55/66/clip2_t4.mp4', 'pinterest video pages sent as videos, not their cover pictures');
reset_calls();
feed(msg('https://www.pinterest.com/pin/444444444/', -9100, 904));
check(str_contains((string)last_text(), 'پین پیدا نشد'), 'pinterest pin not found');
unset($GLOBALS['http']['pinterest.com/resource/PinResource/get/'], $GLOBALS['http']['pin.it/vid1']);

reset_calls();
feed(msg('733 درهم', -4510, 811));
$names = array_column($GLOBALS['calls'], 0);
$ph = sent(['sendPhoto']);
check(($ri = array_search('setMessageReaction', $names, true)) !== false && $ph && $ri < array_search('sendPhoto', $names, true) && empty($ph[0][1]['reply_parameters']),
    'group price reaction comes first and the card photo is not sent as a reply');
$FG = -9300;
reset_calls();
feed(msg('/panel', $FG, 777));
$gpb = array_merge(...(sent(['sendMessage'])[0][1]['reply_markup']['inline_keyboard'] ?? [[]]));
check((bool)array_filter($gpb, fn($b) => ($b['callback_data'] ?? '') === 'gs:fj'), 'group panel has required channels button');
reset_calls();
cb('gs:fj', 777, $FG);
check(str_contains((string)(end($GLOBALS['calls'])[1]['text'] ?? ''), 'کانالی ثبت نشده'), 'required channels view starts empty');
$labels = implode('|', array_column($gpb, 'text'));
check(str_contains($labels, 'ضد اسکم') && str_contains($labels, 'اینستا') && !str_contains($labels, 'دانلود اینستاگرام') && !str_contains($labels, 'قیمت و تبدیل'), 'group panel uses short labels');
$delIds = fn() => array_column(array_column(sent(['deleteMessage']), 1), 'message_id');
reset_calls();
cb('gs:fjadd', 777, $FG, 950);
$e = sent(['editMessageText']);
check(!sent(['sendMessage']) && $e && $e[0][1]['message_id'] === 950 && str_contains($e[0][1]['text'], '@mychannel'), 'add channel edits the panel instead of sending a prompt');
reset_calls();
$lm = msg('@otherchan', $FG, 777);
feed($lm);
$e = sent(['editMessageText']);
check(!sent(['sendMessage']) && $e && $e[0][1]['message_id'] === 950 && str_contains($e[0][1]['text'], 'ادمین نیست') && !(DB::chatSettings($FG)['fj_channels'] ?? []), 'channel where the bot is not admin is refused in the same panel');
check(in_array($lm['message_id'], $delIds(), true), 'refused link message removed');
reset_calls();
$lm = msg('https://t.me/mychan', $FG, 777);
feed($lm);
$e = sent(['editMessageText']);
check(!sent(['sendMessage']) && $e && $e[0][1]['message_id'] === 950 && str_contains($e[0][1]['text'], 'My Chan') && (DB::chatSettings($FG)['fj_channels'][0]['id'] ?? 0) === -100777, 'channel added by editing the panel');
check(in_array($lm['message_id'], $delIds(), true), 'link message removed after adding');
reset_calls();
feed(msg('@otherchan', $FG, 777));
check(!array_filter(sent(['editMessageText']), fn($c) => $c[1]['message_id'] === 950) && count(DB::chatSettings($FG)['fj_channels']) === 1, 'links are not captured after the channel is added');
reset_calls();
cb('gs:fjadd', 777, $FG, 951);
cb('gs:fj', 777, $FG, 951);
reset_calls();
feed(msg('@otherchan', $FG, 777));
check(!array_filter(sent(['editMessageText']), fn($c) => $c[1]['message_id'] === 951), 'cancel stops waiting for a link');
reset_calls();
$hm = msg('سلام', $FG, 6060);
feed($hm);
$fjw = sent(['sendMessage']);
$wid = (int)Cache::get("fjw:$FG:6060");
check(in_array($hm['message_id'], $delIds(), true) && $fjw && in_array('https://t.me/mychan', array_column(buttons($fjw[0]), 'url'), true), 'non-member message removed with join button for the group channel');
check($wid > 0 && !DB::one('SELECT 1 FROM delete_queue WHERE chat_id=? AND message_id=?', [$FG, $wid]), 'join warning stays until the user joins');
reset_calls();
feed(msg('سلام دوباره', $FG, 6060));
check(count(sent(['deleteMessage'])) === 1 && !sent(['sendMessage']), 'repeat message right after is only removed');
reset_calls();
cb('fj:6060', 6061, $FG, $wid);
check(str_contains((string)(sent(['answerCallbackQuery'])[0][1]['text'] ?? ''), 'این دکمه مربوط به شما نیست'), 'other user tapping the join button gets the right alert');
reset_calls();
cb('fj:6060', 6060, $FG, $wid);
check(str_contains((string)(sent(['answerCallbackQuery'])[0][1]['text'] ?? ''), 'هنوز') && !sent(['editMessageText', 'deleteMessage']), 'join button before joining keeps the warning');
$GLOBALS['joined6060'] = true;
$u6060 = ['id' => 6060, 'is_bot' => false, 'first_name' => 'Reza'];
reset_calls();
Bot::handle(['update_id' => ++$GLOBALS['mid'], 'chat_member' => ['chat' => ['id' => -100777, 'type' => 'channel', 'title' => 'My Chan', 'username' => 'mychan'], 'from' => $u6060, 'date' => time(),
    'old_chat_member' => ['status' => 'left', 'user' => $u6060], 'new_chat_member' => ['status' => 'member', 'user' => $u6060]]]);
$d = sent(['editMessageText']);
check($d && $d[0][1]['message_id'] === $wid && str_contains($d[0][1]['text'], 'href="https://t.me/mychan"') && str_contains($d[0][1]['text'], '<blockquote>') && str_contains($d[0][1]['text'], 'Reza') && empty($d[0][1]['reply_markup']['inline_keyboard']), 'joining the channel edits the warning by itself');
check((bool)DB::one('SELECT 1 FROM delete_queue WHERE chat_id=? AND message_id=?', [$FG, $wid]) && !Cache::get("fjw:$FG:6060") && !sent(['sendMessage']), 'joined notice is removed later and nothing is sent to the channel');
reset_calls();
feed(msg('سلام', $FG, 6060));
check(!sent(['deleteMessage']), 'joined user can chat right away');
$GLOBALS['joined6060'] = false;
Cache::forget("mem:-100777:6060");
reset_calls();
feed(msg('سلام', $FG, 6060));
$wid = (int)Cache::get("fjw:$FG:6060");
$GLOBALS['joined6060'] = true;
reset_calls();
cb('fj:6060', 6060, $FG, $wid);
$d = sent(['editMessageText']);
check($wid && $d && $d[0][1]['message_id'] === $wid && str_contains($d[0][1]['text'], 'My Chan') && !sent(['deleteMessage']), 'join button after joining edits the warning');
reset_calls();
feed(msg('سلام', $FG, 6061));
check(!sent(['deleteMessage']), 'member of the group channel can chat');
cb('gs:fjdel:0', 777, $FG);
Cache::forget("mem:-100777:6060");
reset_calls();
feed(msg('سلام', $FG, 6060));
check(!sent(['deleteMessage']) && !(DB::chatSettings($FG)['fj_channels'] ?? []), 'removed channel is no longer required');
echo "passed: $pass, failed: $fail\n";
exit($fail ? 1 : 0);
