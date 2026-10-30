import asyncio
import os
import tempfile

import pytest

from app import db as dbmod
from app import ui
from app.services import account, calc, gifts
from app.services.chain_common import classify, extract, tron_hex_to_base58
from app.services.currencies import FIAT, flag_emoji, is_known_crypto_word, resolve_unit
from app.services.guard import TabchiDetector, clock_in_name, fully_formatted, is_scammy
from app.utils.fmt import gregorian_to_jalali, num, toman
from app.utils.parse import looks_like_expression, parse_amount


@pytest.fixture(autouse=True, scope="module")
def database():
    path = os.path.join(tempfile.mkdtemp(), "t.db")
    loop = asyncio.new_event_loop()
    loop.run_until_complete(dbmod.init_db(path))
    yield dbmod.get_db()
    loop.run_until_complete(dbmod.get_db().close())
    loop.close()


# ------------------------------------------------------------------ amounts
@pytest.mark.parametrize("text,expected", [
    ("10 تتر", (10, "تتر")),
    ("۱۰ تتر", (10, "تتر")),
    ("500000 تومان", (500000, "تومان")),
    ("500,000 تومن", (500000, "تومن")),
    ("2 میلیون تومان", (2_000_000, "تومان")),
    ("2.5m ریال", (2_500_000, "ریال")),
    ("500 درهم", (500, "درهم")),
    ("500 تون", (500, "تون")),
    ("$100", (100, "$")),
    ("100$", (100, "$")),
    ("usdt 25", (25, "usdt")),
    ("10 btc", (10, "btc")),
    ("3 bnb", (3, "bnb")),
    ("1000 استارز", (1000, "استارز")),
])
def test_parse_amount(text, expected):
    amount, unit = parse_amount(text)
    assert amount == pytest.approx(expected[0])
    assert unit == expected[1]


@pytest.mark.parametrize("text", ["سلام خوبی", "200+79", "5 میلیون", "", "hello world how are you"])
def test_parse_amount_rejects(text):
    assert parse_amount(text) is None


@pytest.mark.parametrize("unit,kind,code", [
    ("تتر", "crypto", "USDT"),
    ("تون", "crypto", "TON"),
    ("ton", "crypto", "TON"),
    ("ترون", "crypto", "TRX"),
    ("trx", "crypto", "TRX"),
    ("بیت کوین", "crypto", "BTC"),
    ("تومان", "toman", "IRT"),
    ("تومن", "toman", "IRT"),
    ("ریال", "rial", "IRR"),
    ("درهم", "fiat", "AED"),
    ("دلار", "fiat", "USD"),
    ("$", "fiat", "USD"),
    ("یورو", "fiat", "EUR"),
    ("لیر", "fiat", "TRY"),
    ("ریال عربستان", "fiat", "SAR"),
    ("دلار کانادا", "fiat", "CAD"),
    ("aed", "fiat", "AED"),
    ("استارز", "stars", "STARS"),
    ("pepe", "crypto", "PEPE"),
    ("xyzcoin", "crypto_guess", "XYZCOIN"),
])
def test_resolve_unit(unit, kind, code):
    assert resolve_unit(unit) == (kind, code)


def test_known_crypto_word():
    assert is_known_crypto_word("btc") == "BTC"
    assert is_known_crypto_word("تتر") == "USDT"
    assert is_known_crypto_word("salam") is None


def test_flags_exist_for_all_fiat():
    from app.config import FLAGS_DIR

    missing = [code for code, v in FIAT.items() if v[2] and not (FLAGS_DIR / f"{v[2]}.png").exists()]
    assert not missing
    assert flag_emoji("ae") == "🇦🇪"


# ------------------------------------------------------------------ calculator
@pytest.mark.parametrize("text,result", [
    ("200+79", "279"),
    ("698*789", "550,722"),
    ("698×789", "550,722"),
    ("797÷788", "1.0114213198"),
    ("(2+3)*4", "20"),
    ("2^10", "1,024"),
    ("۱۰۰-۲۵", "75"),
    ("10/4", "2.5"),
])
def test_calculator(text, result):
    expr = looks_like_expression(text)
    assert expr
    assert calc.format_result(calc.evaluate(expr)) == result


@pytest.mark.parametrize("text", ["0912-345-6789", "1403/05/12", "سلام", "123", "+98"])
def test_calculator_rejects(text):
    assert looks_like_expression(text) is None


def test_calculator_errors():
    with pytest.raises(calc.CalcError):
        calc.evaluate("1/0")
    with pytest.raises(calc.CalcError):
        calc.evaluate("9**99999")
    with pytest.raises(calc.CalcError):
        calc.evaluate("__import__('os')")


# ------------------------------------------------------------------ chains
@pytest.mark.parametrize("token,kind", [
    ("0xdAC17F958D2ee523a2206206994597C13D831ec7", "evm"),
    ("TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", "tron"),
    ("bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh", "btc"),
    ("1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa", "btc"),
    ("ltc1qg82tfxk6c0wnvsfqy9mvxkkt0gkz9a2ut8sksa", "ltc"),
    ("DH5yaieqoZN36fDVciNyRueRGvGLR3mr7L", "doge"),
    ("EQCD39VS5jcptHL8vMjEXrzGaRcCVYto7HUn4bpAOg8xqB2N", "ton"),
    ("UQCD39VS5jcptHL8vMjEXrzGaRcCVYto7HUn4bpAOg8xqLNK", "ton"),
    ("rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh", "xrp"),
    ("9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM", "sol"),
    ("0x" + "a" * 64, "hex64x"),
    ("f" * 64, "hex64"),
    ("5" * 88, "solsig"),
])
def test_classify(token, kind):
    assert classify(token) == kind


def test_extract_strict_and_links():
    assert extract("TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", strict=True) == ("tron", "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t")
    assert extract("check this TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t please", strict=True) is None
    assert extract("check this TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t please", strict=False)[0] == "tron"
    h = "0x" + "b" * 64
    assert extract(f"https://etherscan.io/tx/{h}", strict=True) == ("hex64x", h)
    assert extract("سلام دوستان", strict=True) is None


def test_tron_hex_to_base58():
    assert tron_hex_to_base58("41a614f803b6fd780986a42c78ec9c7f77e6ded13c") == "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"


# ------------------------------------------------------------------ templates
def test_fill_drops_lines_and_keeps_tags():
    tpl = "<b>T</b>\n<blockquote>A: {a}\nB: {b}\nC: {c}</blockquote>\nend {d}"
    out = ui.fill(tpl, a=None, b="2", c=None, d="x")
    assert out == "<b>T</b>\n<blockquote>B: 2</blockquote>\nend x"


def test_fill_keeps_unknown_placeholders():
    assert ui.fill("x {unknown} {a}", a=1) == "x {unknown} 1"


def test_emoji_map(database):
    asyncio.get_event_loop_policy()
    loop = asyncio.new_event_loop()
    loop.run_until_complete(database.set("emoji_map", {"💎": "123"}))
    loop.close()
    out = ui.apply_emoji_map("<b>💎 hi</b> <code>💎</code>")
    assert out == '<b><tg-emoji emoji-id="123">💎</tg-emoji> hi</b> <code>💎</code>'
    btn = ui.button("btn_add_group", url="https://t.me/x")
    assert btn.text == "➕ افزودن به گروه"
    assert ui.split_leading_emoji("💎 Hello") == ("💎", "Hello")
    loop = asyncio.new_event_loop()
    loop.run_until_complete(database.set("emoji_map", {"➕": "777"}))
    loop.close()
    btn = ui.button("btn_add_group", url="https://t.me/x")
    assert btn.icon_custom_emoji_id == "777" and btn.text == "افزودن به گروه"


def test_all_default_texts_render():
    from app.texts import TEXTS

    for key in TEXTS:
        assert ui.t(key)


# ------------------------------------------------------------------ misc
def test_jalali():
    assert gregorian_to_jalali(2026, 10, 30) == (1405, 8, 8)
    assert gregorian_to_jalali(2024, 3, 20) == (1403, 1, 1)


def test_number_format():
    assert num(1234567.891) == "1,234,568"
    assert num(0.0000123456) == "0.00001235"
    assert toman(1050000.4) == "1,050,000"


def test_account_helpers():
    assert account.estimate_creation(100_000_000) < account.estimate_creation(6_000_000_000)
    assert account.estimate_creation(9_500_000_000) > account.estimate_creation(9_000_000_000)
    assert account.level_from_xp(0) == 1 and account.level_from_xp(10_000) > 5


def test_guard_heuristics():
    assert is_scammy("بیا پیوی حرف بزنیم")
    assert is_scammy("بیا پی")
    assert is_scammy("بدون واسطه معامله کنیم")
    assert is_scammy("pv bia")
    assert not is_scammy("سلام قیمت تتر چنده؟")
    assert clock_in_name("Ali | 12:45") == (12, 45)
    assert clock_in_name("𝟏𝟐:𝟑𝟎 Sara") == (12, 30)
    assert clock_in_name("Reza") is None


class _E:
    def __init__(self, type_, offset, length):
        self.type, self.offset, self.length = type_, offset, length


def test_fully_formatted():
    assert fully_formatted("hello", [_E("bold", 0, 5)])
    assert not fully_formatted("hello", [_E("bold", 0, 2)])


class _U:
    id = 42
    first_name = "x"
    last_name = None


class _C:
    id = -100


class _M:
    def __init__(self, text):
        self.from_user = _U()
        self.chat = _C()
        self.text = text
        self.caption = None
        self.entities = None
        self.caption_entities = None
        self.reply_to_message = None
        self.forward_origin = None
        self.via_bot = None
        self.reply_markup = None


def test_tabchi_detector():
    det = TabchiDetector()
    det.joined(-100, 42)
    score, reasons = det.evaluate(_M("عضو شو تو کانال ما https://t.me/+abcdef"))
    assert score >= det.THRESHOLD, reasons
    det2 = TabchiDetector()
    score2, _ = det2.evaluate(_M("سلام قیمت دلار چنده"))
    assert score2 < det2.THRESHOLD


def test_gift_link_parse():
    assert gifts.parse_link("https://t.me/nft/PlushPepe-123") == ("PlushPepe", 123)
    assert gifts.parse_link("t.me/nft/DurovsCap-9 nice") == ("DurovsCap", 9)
    assert gifts.parse_link("hello") is None
    assert gifts.norm("Durov's Cap") == "durovscap"


def test_dc_decode():
    assert account.dc_from_file_id("garbage") is None
