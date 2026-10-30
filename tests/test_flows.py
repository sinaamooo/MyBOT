import asyncio
import os
import tempfile
from datetime import datetime, timezone

import pytest
from aiogram import Bot
from aiogram.client.session.base import BaseSession
from aiogram.exceptions import TelegramBadRequest
from aiogram.methods import (
    AnswerCallbackQuery,
    CopyMessage,
    DeleteMessage,
    EditMessageReplyMarkup,
    EditMessageText,
    GetChat,
    GetChatMember,
    GetCustomEmojiStickers,
    GetMe,
    GetUserGifts,
    GetUserProfilePhotos,
    RestrictChatMember,
    SendMessage,
    SendPhoto,
)
from aiogram.types import (
    CallbackQuery,
    Chat,
    ChatMemberAdministrator,
    ChatMemberLeft,
    ChatMemberMember,
    Message,
    MessageEntity,
    MessageId,
    OwnedGifts,
    Update,
    User,
    UserProfilePhotos,
)

os.environ.setdefault("BOT_TOKEN", "42:TEST")
os.environ["ADMIN_IDS"] = "999"

from app import config as config_mod

config_mod.config = config_mod.Config()
import app.handlers.admin as admin_mod

assert admin_mod
from app import db as dbmod
from app import ui
from app.bot import PremiumEmojiFallback, build_dispatcher
from app.services import prices, translate

BOT_USER = User(id=1, is_bot=True, first_name="Bot", username="TestBot")
NON_MEMBER_CHANNEL = -1001


class FakeSession(BaseSession):
    def __init__(self):
        super().__init__()
        self.calls = []
        self.counter = 100
        self.reject_premium = False

    async def close(self):
        pass

    async def stream_content(self, *a, **kw):
        yield b""

    def _msg(self, chat_id, text=None, caption=None, reply_markup=None):
        self.counter += 1
        return Message(message_id=self.counter, date=datetime.now(timezone.utc),
                       chat=Chat(id=chat_id, type="private" if chat_id > 0 else "supergroup"),
                       from_user=BOT_USER, text=text, caption=caption, reply_markup=reply_markup)

    async def make_request(self, bot, method, timeout=None):
        result = await self._make_request(bot, method, timeout)
        if hasattr(result, "as_"):
            result = result.as_(bot)
        return result

    async def _make_request(self, bot, method, timeout=None):
        self.calls.append(method)
        if isinstance(method, GetMe):
            return BOT_USER
        if isinstance(method, (SendMessage, EditMessageText)):
            if self.reject_premium and "<tg-emoji" in (method.text or ""):
                raise TelegramBadRequest(method=method, message="Bad Request: DOCUMENT_INVALID")
            if isinstance(method, EditMessageText):
                return self._msg(method.chat_id or 0, text=method.text)
            return self._msg(method.chat_id, text=method.text, reply_markup=method.reply_markup)
        if isinstance(method, SendPhoto):
            return self._msg(method.chat_id, caption=method.caption)
        if isinstance(method, GetChatMember):
            uid = method.user_id
            if method.chat_id == NON_MEMBER_CHANNEL:
                return ChatMemberLeft(user=User(id=uid, is_bot=False, first_name="u"))
            if uid == 777:
                return ChatMemberAdministrator(user=User(id=uid, is_bot=False, first_name="adm"), can_be_edited=False,
                                               is_anonymous=False, can_manage_chat=True, can_delete_messages=True,
                                               can_manage_video_chats=True, can_restrict_members=True,
                                               can_promote_members=False, can_change_info=True, can_invite_users=True,
                                               can_post_stories=False, can_edit_stories=False, can_delete_stories=False,
                                               can_send_welcome_messages=False)
            return ChatMemberMember(user=User(id=uid, is_bot=False, first_name="u"))
        if isinstance(method, GetUserProfilePhotos):
            return UserProfilePhotos(total_count=0, photos=[])
        if isinstance(method, GetUserGifts):
            return OwnedGifts(total_count=0, gifts=[])
        if isinstance(method, GetCustomEmojiStickers):
            return []
        if isinstance(method, CopyMessage):
            return MessageId(message_id=1)
        if isinstance(method, GetChat):
            raise TelegramBadRequest(method=method, message="Bad Request: chat not found")
        if isinstance(method, (AnswerCallbackQuery, DeleteMessage, RestrictChatMember, EditMessageReplyMarkup)):
            return True
        return True

    def sent(self, kind=(SendMessage, SendPhoto, EditMessageText)):
        return [c for c in self.calls if isinstance(c, kind)]

    def last_text(self):
        for c in reversed(self.calls):
            if isinstance(c, (SendMessage, EditMessageText)):
                return c.text
            if isinstance(c, SendPhoto):
                return c.caption
        return None


_DP = None


@pytest.fixture()
def env(monkeypatch):
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    path = os.path.join(tempfile.mkdtemp(), "flow.db")
    loop.run_until_complete(dbmod.init_db(path))

    async def quote(sym):
        table = {"USDT": 1.0, "TON": 3.0, "TRX": 0.25, "BTC": 100000.0, "ETH": 4000.0}
        price = table.get(sym.upper())
        return prices.Quote(sym.upper(), price, 1.5, ["test"]) if price else None

    async def toman():
        return 100_000.0

    async def rates():
        return {"USD": 1.0, "AED": 3.6725, "EUR": 0.9, "GBP": 0.8}

    async def fake_translate(text, lang):
        return f"[{lang}] {text}"

    monkeypatch.setattr(prices, "crypto_quote", quote)
    monkeypatch.setattr(prices, "usdt_toman", toman)
    monkeypatch.setattr(prices, "fiat_rates", rates)
    monkeypatch.setattr(translate, "translate", fake_translate)

    session = FakeSession()
    session.middleware(PremiumEmojiFallback())
    bot = Bot("42:TEST", session=session, default=__import__("aiogram").client.default.DefaultBotProperties(parse_mode="HTML"))
    global _DP
    if _DP is None:
        _DP = build_dispatcher()
    dp = _DP
    ui.BOT_USERNAME = "TestBot"
    yield loop, bot, dp, session
    loop.run_until_complete(dbmod.get_db().close())
    loop.close()


_uid = 0


def msg(text, chat_id=5, user_id=5, reply_to=None, entities=None, first_name="Ali"):
    global _uid
    _uid += 1
    chat = Chat(id=chat_id, type="private" if chat_id > 0 else "supergroup", title=None if chat_id > 0 else "G")
    return Message(message_id=_uid, date=datetime.now(timezone.utc), chat=chat,
                   from_user=User(id=user_id, is_bot=False, first_name=first_name), text=text,
                   reply_to_message=reply_to, entities=entities)


def feed(env, obj):
    loop, bot, dp, _ = env
    global _uid
    _uid += 1
    if isinstance(obj, Message):
        upd = Update(update_id=_uid, message=obj)
    else:
        upd = Update(update_id=_uid, callback_query=obj)
    loop.run_until_complete(dp.feed_update(bot, upd))


def test_start_and_footer(env):
    feed(env, msg("/start"))
    sent = env[3].sent()[-1]
    assert "خوش اومدی" in sent.text
    buttons = [b for row in sent.reply_markup.inline_keyboard for b in row]
    assert any(b.url and "startgroup" in b.url for b in buttons)


def test_crypto_conversion(env):
    feed(env, msg("10 تتر"))
    text = env[3].last_text()
    assert "1,000,000" in text
    assert "3.3333" in text
    assert "40" in text
    assert "{" not in text


def test_toman_conversion(env):
    feed(env, msg("500000 تومان"))
    text = env[3].last_text()
    assert "500,000" in text and "<code>5</code> USDT" in text


def test_fiat_card(env):
    feed(env, msg("500 درهم"))
    photo = env[3].sent(SendPhoto)[-1]
    assert "درهم امارات" in photo.caption
    assert photo.reply_markup is not None


def test_stars(env):
    feed(env, msg("1000 استارز"))
    assert "استارز" in env[3].last_text()


def test_calculator_and_uid(env):
    feed(env, msg("698*789"))
    assert "550,722" in env[3].last_text()
    target = msg("hello", user_id=12345, first_name="Target")
    feed(env, msg("UID", reply_to=target))
    sent = env[3].sent()[-1]
    assert "12345" in sent.text
    assert sent.reply_markup.inline_keyboard[0][0].copy_text.text == "12345"


def test_translate_once(env):
    loop, bot, dp, session = env
    src = msg("Hello world", chat_id=-100, user_id=8)
    feed(env, msg("ترجمه", chat_id=-100, user_id=7, reply_to=src))
    prompt = session.sent(SendMessage)[-1]
    rows = prompt.reply_markup.inline_keyboard
    assert [len(r) for r in rows] == [3, 2]
    bot_msg = Message(message_id=session.counter, date=datetime.now(timezone.utc),
                      chat=Chat(id=-100, type="supergroup"), from_user=BOT_USER, text="x")
    other = CallbackQuery(id="1", from_user=User(id=8, is_bot=False, first_name="o"), chat_instance="c",
                          data="tr:fa", message=bot_msg)
    feed(env, other)
    assert isinstance(session.calls[-1], AnswerCallbackQuery) and session.calls[-1].show_alert
    mine = CallbackQuery(id="2", from_user=User(id=7, is_bot=False, first_name="m"), chat_instance="c",
                         data="tr:fa", message=bot_msg)
    feed(env, mine)
    edits = session.sent(EditMessageText)
    assert edits and "[fa] Hello world" in edits[-1].text
    again = CallbackQuery(id="3", from_user=User(id=7, is_bot=False, first_name="m"), chat_instance="c",
                          data="tr:en", message=bot_msg)
    n = len(session.sent(EditMessageText))
    feed(env, again)
    assert len(session.sent(EditMessageText)) == n


def test_emoji_command(env):
    text = "/emoji 😎"
    ent = MessageEntity(type="custom_emoji", offset=7, length=2, custom_emoji_id="5368324170671202286")
    feed(env, msg(text, entities=[ent]))
    out = env[3].last_text()
    assert "5368324170671202286" in out and "tg-emoji" in out


def test_premium_fallback(env):
    loop, bot, dp, session = env
    loop.run_until_complete(dbmod.get_db().set("emoji_map", {"💎": "111"}))
    session.reject_premium = True
    feed(env, msg("10 تتر"))
    last_two = session.sent(SendMessage)[-2:]
    assert "<tg-emoji" in last_two[0].text and "<tg-emoji" not in last_two[1].text
    loop.run_until_complete(dbmod.get_db().set("emoji_map", {}))


def test_force_join_group(env):
    loop, bot, dp, session = env
    loop.run_until_complete(dbmod.get_db().set("force_channels", [
        {"id": NON_MEMBER_CHANNEL, "title": "Main", "url": "https://t.me/main"}]))
    feed(env, msg("salam", chat_id=-200, user_id=55))
    assert any(isinstance(c, DeleteMessage) for c in session.calls)
    warn = session.sent(SendMessage)[-1]
    assert "کانال" in warn.text
    assert warn.reply_markup.inline_keyboard[0][0].url == "https://t.me/main"
    n = len(session.calls)
    feed(env, msg("salam", chat_id=-200, user_id=777))
    assert not any(isinstance(c, DeleteMessage) for c in session.calls[n:])
    loop.run_until_complete(dbmod.get_db().set("force_channels", []))


def test_scam_warning_and_report(env):
    loop, bot, dp, session = env
    feed(env, msg("بیا پیوی", chat_id=-300, user_id=61))
    assert "هشدار" in session.sent(SendMessage)[-1].text
    scammer = msg("hi", chat_id=-300, user_id=62, first_name="Bad")
    feed(env, msg("/scam", chat_id=-300, user_id=63, reply_to=scammer))
    go = session.sent(SendMessage)[-1]
    url = go.reply_markup.inline_keyboard[0][0].url
    assert "start=scam_" in url
    scam_id = int(url.rsplit("_", 1)[1])
    feed(env, msg(f"/start scam_{scam_id}", chat_id=63, user_id=63))
    assert "مدارک" in session.last_text()
    feed(env, msg("he took my money", chat_id=63, user_id=63))
    submit = CallbackQuery(id="9", from_user=User(id=63, is_bot=False, first_name="r"), chat_instance="c",
                           data=f"scam:submit:{scam_id}",
                           message=Message(message_id=1, date=datetime.now(timezone.utc),
                                           chat=Chat(id=63, type="private"), from_user=BOT_USER, text="x"))
    feed(env, submit)
    row = loop.run_until_complete(dbmod.get_db().get_scam(scam_id))
    assert row["status"] == "pending" and row["evidence"]
    assert any(isinstance(c, SendMessage) and c.chat_id == 999 for c in session.calls)
    approve = CallbackQuery(id="10", from_user=User(id=999, is_bot=False, first_name="admin"), chat_instance="c",
                            data=f"scamadm:approve:{scam_id}",
                            message=Message(message_id=2, date=datetime.now(timezone.utc),
                                            chat=Chat(id=999, type="private"), from_user=BOT_USER, text="report"))
    feed(env, approve)
    loop.run_until_complete(asyncio.sleep(0.2))
    row = loop.run_until_complete(dbmod.get_db().get_scam(scam_id))
    assert row["status"] == "approved"
    assert any(isinstance(c, SendMessage) and c.chat_id == -300 and "کلاهبردار" in c.text for c in session.calls)
    feed(env, msg("/check", chat_id=-300, user_id=64, reply_to=scammer))
    assert "لیست کلاهبرداران" in session.last_text()


def test_admin_edit_text(env):
    loop, bot, dp, session = env
    feed(env, msg("/panel", chat_id=999, user_id=999))
    assert "پنل مدیریت" in session.last_text()
    cb = CallbackQuery(id="20", from_user=User(id=999, is_bot=False, first_name="a"), chat_instance="c",
                       data="adm:tedit:calc_result",
                       message=Message(message_id=3, date=datetime.now(timezone.utc),
                                       chat=Chat(id=999, type="private"), from_user=BOT_USER, text="x"))
    feed(env, cb)
    feed(env, msg("حاصل {expr} میشه {result}", chat_id=999, user_id=999))
    feed(env, msg("2+2", chat_id=5, user_id=5))
    assert session.last_text() == "حاصل <code>2+2</code> میشه 4".replace("<code>2+2</code>", "2+2")


def test_non_admin_cannot_open_panel(env):
    feed(env, msg("/panel", chat_id=5, user_id=5))
    assert "پنل مدیریت" not in (env[3].last_text() or "")


def test_group_settings_toggle(env):
    loop, bot, dp, session = env
    feed(env, msg("/settings", chat_id=-400, user_id=777))
    assert "تنظیمات گروه" in session.last_text()
    cb = CallbackQuery(id="30", from_user=User(id=777, is_bot=False, first_name="a"), chat_instance="c",
                       data="gs:calculator",
                       message=Message(message_id=4, date=datetime.now(timezone.utc),
                                       chat=Chat(id=-400, type="supergroup"), from_user=BOT_USER, text="x"))
    feed(env, cb)
    n = len(session.sent())
    feed(env, msg("2+2", chat_id=-400, user_id=778))
    assert len(session.sent()) == n


def _unique(base, slug, number, model, backdrop, symbol):
    from aiogram.types import (OwnedGiftUnique, Sticker, UniqueGift, UniqueGiftBackdrop, UniqueGiftBackdropColors,
                               UniqueGiftModel, UniqueGiftSymbol)

    st = Sticker(file_id="f", file_unique_id="u", type="custom_emoji", width=100, height=100, is_animated=True,
                 is_video=False)
    return OwnedGiftUnique(
        send_date=0,
        gift=UniqueGift(
            gift_id="1", base_name=base, name=f"{slug}-{number}", number=number,
            model=UniqueGiftModel(name=model, sticker=st, rarity_per_mille=10),
            symbol=UniqueGiftSymbol(name=symbol, sticker=st, rarity_per_mille=10),
            backdrop=UniqueGiftBackdrop(name=backdrop, rarity_per_mille=10, colors=UniqueGiftBackdropColors(
                center_color=0x5B8DEF, edge_color=0x1E3A8A, symbol_color=0, text_color=0xFFFFFF)),
        ),
    )


def test_gift_portfolio(env, monkeypatch):
    from app.services import gifts as gift_service

    loop, bot, dp, session = env
    owned = [_unique("Toy Bear", "ToyBear", 55, "Wizard", "Amber", "Rose"),
             _unique("Plush Pepe", "PlushPepe", 7, "Cozy Galaxy", "Black", "Rose")]

    async def fake_dataset():
        return {"index": {"toybear": {"full_name": "Toy Bear", "short_name": "toy_bear", "floor_price_ton": 14.0},
                          "plushpepe": {"full_name": "Plush Pepe", "short_name": "plush_pepe", "floor_price_ton": 4000.0}},
                "by_id": {}, "unupgraded": {}, "updated": 0}

    async def fake_attrs(short):
        return {"models": {"wizard": 20.0}, "backdrops": {"black": 5000.0}, "symbols": {}}

    async def fake_old(days=7):
        return {"toybear": 10.0, "plushpepe": 4400.0}

    async def no_image(name):
        return None

    monkeypatch.setattr(gift_service, "dataset", fake_dataset)
    monkeypatch.setattr(gift_service, "attribute_floors", fake_attrs)
    monkeypatch.setattr(gift_service, "floors_days_ago", fake_old)
    monkeypatch.setattr(gift_service, "gift_image", no_image)

    orig = session._make_request

    async def make_request(bot_, method, timeout=None):
        if isinstance(method, GetUserGifts):
            session.calls.append(method)
            return OwnedGifts(total_count=2, gifts=owned)
        return await orig(bot_, method, timeout)

    monkeypatch.setattr(session, "_make_request", make_request)
    feed(env, msg("/gift", chat_id=-500, user_id=70, first_name="Sina"))
    photo = session.sent(SendPhoto)[-1]
    assert "https://t.me/nft/ToyBear-55" in photo.caption and "https://t.me/nft/PlushPepe-7" in photo.caption
    assert "5,020" in photo.caption
    assert "ضرر" in photo.caption


def _admin_cb(data, cid="40"):
    return CallbackQuery(id=cid, from_user=User(id=999, is_bot=False, first_name="a"), chat_instance="c", data=data,
                         message=Message(message_id=5, date=datetime.now(timezone.utc),
                                         chat=Chat(id=999, type="private"), from_user=BOT_USER, text="x"))


def test_admin_panel_navigation(env):
    loop, bot, dp, session = env
    for data in ["adm:home", "adm:texts:0", "adm:texts:1", "adm:text:conv_crypto", "adm:btns", "adm:btn:btn_channel",
                 "adm:bstyle:btn_channel:danger", "adm:emo", "adm:fj", "adm:set", "adm:tog:anti_self:set",
                 "adm:scams", "adm:stats", "adm:bcmenu"]:
        n = len(session.calls)
        feed(env, _admin_cb(data))
        new = session.calls[n:]
        assert new, data
        assert not any(isinstance(c, AnswerCallbackQuery) and c.show_alert and c.text == "⛔️" for c in new), data
    conf = dbmod.get_db().get("buttons")
    assert conf["btn_channel"]["style"] == "danger"
    assert dbmod.get_db().get("anti_self") is False
    feed(env, _admin_cb("adm:emoadd"))
    ent = MessageEntity(type="custom_emoji", offset=0, length=2, custom_emoji_id="555")
    feed(env, msg("💎", chat_id=999, user_id=999, entities=[ent]))
    assert dbmod.get_db().get("emoji_map") == {"💎": "555"}
    feed(env, _admin_cb("adm:bemoji:btn_add_group"))
    feed(env, msg("😎", chat_id=999, user_id=999,
                  entities=[MessageEntity(type="custom_emoji", offset=0, length=2, custom_emoji_id="777")]))
    assert dbmod.get_db().get("buttons")["btn_add_group"]["emoji_id"] == "777"
    feed(env, msg("/start", chat_id=6, user_id=6))
    start = session.sent(SendMessage)[-1]
    add_btn = [b for row in start.reply_markup.inline_keyboard for b in row if b.url and "startgroup" in b.url][0]
    assert add_btn.icon_custom_emoji_id == "777"
    feed(env, _admin_cb("adm:val:usdt_toman_manual"))
    feed(env, msg("۱۰۵۰۰۰", chat_id=999, user_id=999))
    assert dbmod.get_db().get("usdt_toman_manual") == 105000
    loop.run_until_complete(dbmod.get_db().set("emoji_map", {}))
    loop.run_until_complete(dbmod.get_db().set("anti_self", True))


def test_card_and_iban_messages(env, monkeypatch):
    from app.services import banking

    async def fake(url, **kw):
        return {"result": {"name": "علی رضایی"}}

    monkeypatch.setattr(banking, "get_json", fake)
    loop, bot, dp, session = env
    feed(env, msg("6037-9975-9950-3209", chat_id=-600, user_id=80))
    out = session.last_text()
    assert "ملی" in out and "علی رضایی" in out and "6037-9975-9950-3209" in out
    n = len(session.sent())
    feed(env, msg("6037-9975-9950-3201", chat_id=-600, user_id=80))
    assert len(session.sent()) == n
    feed(env, msg("IR820540102680020817909002", chat_id=-600, user_id=80))
    assert "پارسیان" in session.last_text()


def test_phone_command(env, monkeypatch):
    from app.services import phone

    async def fake(url, **kw):
        return {"status": "⚫️"}

    monkeypatch.setattr(phone, "get_json", fake)
    feed(env, msg("/phone +15005550006"))
    assert "بن شده" in env[3].last_text()


def test_tron_tx_buttons_and_wallet_extras(env, monkeypatch):
    from app.services import txs as tx_service
    from app.services import wallets as w

    loop, bot, dp, session = env
    sender, receiver = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", "TKHuVq1oKVruCGLvqVexFs6dawKv6fQgFs"

    async def fake_tx(url, **kw):
        return {"hash": "e" * 64, "contractRet": "SUCCESS", "confirmed": True, "timestamp": 1700000000000,
                "contractData": {"owner_address": sender, "to_address": receiver, "amount": 5000000}}

    async def fake_wallet(url, **kw):
        if "v1/accounts" in url:
            return {"success": True, "data": [{"balance": 7000000, "create_time": 1600000000000, "trc20": []}]}
        if "accountv2" in url:
            return {"totalTransactionCount": 3}
        if "account/tokens" in url:
            return {"total": 1, "data": [{"tokenId": "_", "tokenType": "trc10", "quantity": 7, "tokenAbbr": "trx"}]}
        return {"total": 1, "token_transfers": [{"quant": "1000000", "tokenInfo": {"tokenDecimal": 6, "tokenAbbr": "USDT"},
                                                 "from_address": "TA", "to_address": receiver,
                                                 "block_ts": 1700000000000, "transaction_id": "f" * 64}]}

    monkeypatch.setattr(tx_service, "get_json", fake_tx)
    monkeypatch.setattr(w, "get_json", fake_wallet)
    feed(env, msg("e" * 64, chat_id=-700, user_id=90))
    tx_msg = session.sent(SendMessage)[-1]
    assert "5 TRX" in tx_msg.text and "COIN" in tx_msg.text
    btns = [b for row in tx_msg.reply_markup.inline_keyboard for b in row if b.callback_data and b.callback_data.startswith("wl:")]
    assert len(btns) == 2
    bot_msg = Message(message_id=900, date=datetime.now(timezone.utc), chat=Chat(id=-700, type="supergroup"),
                      from_user=BOT_USER, text="x")
    stranger = CallbackQuery(id="50", from_user=User(id=91, is_bot=False, first_name="s"), chat_instance="c",
                             data=btns[1].callback_data, message=bot_msg)
    feed(env, stranger)
    assert session.calls[-1].show_alert
    mine = CallbackQuery(id="51", from_user=User(id=90, is_bot=False, first_name="m"), chat_instance="c",
                         data=btns[1].callback_data, message=bot_msg)
    feed(env, mine)
    wallet_msg = [c for c in session.sent(SendMessage) if "TRON" in (c.text or "") and "موجودی" in c.text][-1]
    assert "تاریخ ساخت" in wallet_msg.text
    extra = [b for row in wallet_msg.reply_markup.inline_keyboard for b in row if b.callback_data and b.callback_data[:3] in ("tt:", "tj:")]
    assert len(extra) == 2
    feed(env, CallbackQuery(id="52", from_user=User(id=90, is_bot=False, first_name="m"), chat_instance="c",
                            data=extra[0].callback_data, message=bot_msg))
    assert "TRC20" in session.last_text() and "USDT" in session.last_text()
    from aiogram.methods import SendDocument

    feed(env, CallbackQuery(id="53", from_user=User(id=90, is_bot=False, first_name="m"), chat_instance="c",
                            data=extra[1].callback_data, message=bot_msg))
    assert any(isinstance(c, SendDocument) for c in session.calls)


def test_wallet_prefix_in_group(env, monkeypatch):
    from app.handlers import tools as tools_mod

    seen = []

    async def fake_checker(message, kind, token, owner_id=None):
        seen.append((kind, token))

    monkeypatch.setattr(tools_mod, "run_checker", fake_checker)
    feed(env, msg("wallet TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", chat_id=-800, user_id=95))
    assert seen == [("tron", "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t")]


def test_instagram_link_flow(env, monkeypatch):
    import dataclasses

    from aiogram.methods import SendVideo

    from app.services import instagram

    async def fake(url, params=None, **kw):
        return {"status": 200, "result": {"medias": [{"url": "https://x/1.jpg", "extension": "jpg"},
                                                     {"url": "https://x/2.mp4", "extension": "mp4"}]}}

    monkeypatch.setattr(instagram, "config", dataclasses.replace(instagram.config, instagram_api_key="K"))
    monkeypatch.setattr(instagram, "get_json", fake)
    loop, bot, dp, session = env
    feed(env, msg("https://www.instagram.com/reel/ABC123/", chat_id=-900, user_id=96))
    photos = [c for c in session.calls if isinstance(c, SendPhoto) and c.photo == "https://x/1.jpg"]
    videos = [c for c in session.calls if isinstance(c, SendVideo)]
    assert photos and videos and "(2/2)" in videos[-1].caption


def test_admin_power_and_ban(env):
    loop, bot, dp, session = env
    feed(env, _admin_cb("adm:power"))
    assert dbmod.get_db().get("bot_enabled") is False
    feed(env, msg("10 تتر", chat_id=31, user_id=31))
    assert "خاموش" in session.last_text()
    feed(env, msg("/panel", chat_id=999, user_id=999))
    assert "پنل مدیریت" in session.last_text()
    feed(env, _admin_cb("adm:power"))
    assert dbmod.get_db().get("bot_enabled") is True
    feed(env, _admin_cb("adm:ban"))
    feed(env, msg("32", chat_id=999, user_id=999))
    assert dbmod.get_db().is_banned(32)
    feed(env, msg("10 تتر", chat_id=32, user_id=32))
    assert "مسدود" in session.last_text()
    feed(env, _admin_cb("adm:ban"))
    feed(env, msg("32", chat_id=999, user_id=999))
    assert not dbmod.get_db().is_banned(32)
    feed(env, _admin_cb("adm:pm"))
    feed(env, msg("33\nسلام", chat_id=999, user_id=999))
    assert any(isinstance(c, SendMessage) and c.chat_id == 33 and "سلام" in c.text for c in session.calls)


@pytest.mark.parametrize("text,needle", [
    ("/500 درهم", "درهم امارات"),
    ("/درهم 500", "درهم امارات"),
    ("/درهم", "درهم امارات"),
    ("/aed 500", "درهم امارات"),
    ("/aed@TestBot 500", "درهم امارات"),
    ("/۱۰۰۰ لیر", "لیر ترکیه"),
    ("/eur", "یورو"),
])
def test_slash_fiat(env, text, needle):
    async def rates():
        return {"USD": 1.0, "AED": 3.6725, "EUR": 0.9, "GBP": 0.8, "TRY": 41.0}

    import app.services.prices as p

    env_rates = p.fiat_rates
    p.fiat_rates = rates
    try:
        feed(env, msg(text, chat_id=-950, user_id=97))
    finally:
        p.fiat_rates = env_rates
    photo = env[3].sent(SendPhoto)[-1]
    assert needle in photo.caption


def test_slash_only_for_fiat(env):
    n = len(env[3].sent())
    feed(env, msg("/10 تتر"))
    feed(env, msg("/500000 تومان"))
    feed(env, msg("/btc"))
    feed(env, msg("/randomcommand"))
    feed(env, msg("/hello", chat_id=-951, user_id=98))
    assert len(env[3].sent()) == n
