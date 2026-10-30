"""Admin panel: texts, buttons, premium emoji, forced channels, settings, scam reports, stats, broadcast."""
from __future__ import annotations

import asyncio
import html
import logging
import re

from aiogram import Bot, F, Router
from aiogram.filters import Command, CommandObject, StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import CallbackQuery, InlineKeyboardButton, Message

from .. import ui
from ..config import config
from ..db import get_db
from ..settings import DEFAULTS, GROUP_FEATURES, gget, gset
from ..texts import BUTTONS, TEXTS
from ..utils import fmt

log = logging.getLogger(__name__)
router = Router(name="admin")
router.message.filter(F.from_user.id.in_(set(config.admin_ids)))
router.callback_query.filter(F.from_user.id.in_(set(config.admin_ids)))

PAGE = 8
TOGGLES = {**GROUP_FEATURES, "force_join_private": "عضویت اجباری پیوی", "auto_ban_scammers": "بن خودکار کلاهبردار",
           "fa_digits": "اعداد فارسی"}
NUMBERS = {
    "usdt_toman_manual": "نرخ دستی تتر (تومان، 0 = خودکار)",
    "star_usd": "قیمت هر استار (دلار)",
    "whale_min_usd": "حداقل مبلغ هشدار جابجایی ($)",
    "whale_btc_min": "حداقل BTC برای هشدار",
    "self_mute_minutes": "مدت سکوت سلف (دقیقه، 0 = دائمی)",
    "tabchi_mute_minutes": "مدت سکوت تبچی (دقیقه، 0 = دائمی)",
}
STRINGS = {"channel_url": "لینک کانال ربات", "channel_title": "نام کانال ربات"}
STYLES = {"primary": "🔵 آبی", "success": "🟢 سبز", "danger": "🔴 قرمز", "none": "⚪️ پیش‌فرض"}


class AdminFSM(StatesGroup):
    waiting = State()


def B(text: str, data: str, style: str | None = None) -> InlineKeyboardButton:
    kw = {"style": style} if style else {}
    return InlineKeyboardButton(text=text, callback_data=data, **kw)


BACK = [B("🔙 بازگشت", "adm:home")]


def home_kb():
    return ui.kb([
        [B("📝 متن‌ها", "adm:texts:0", "primary"), B("🔘 دکمه‌ها", "adm:btns", "primary")],
        [B("😎 ایموجی پریمیوم", "adm:emo"), B("📢 عضویت اجباری", "adm:fj")],
        [B("⚙️ تنظیمات", "adm:set"), B("🚨 گزارش‌ها", "adm:scams")],
        [B("📊 آمار", "adm:stats"), B("📣 ارسال همگانی", "adm:bcmenu")],
    ])


HOME_TEXT = (
    "🛠 <b>پنل مدیریت ربات</b>\n"
    "از منوی زیر بخش موردنظر را انتخاب کنید.\n\n"
    "💡 نکته: برای استفاده از ایموجی پریمیوم کافیست هنگام ویرایش متن‌ها، ایموجی پریمیوم بفرستید؛ "
    "یا در بخش «ایموجی پریمیوم» برای هر ایموجی معمولی یک ایموجی پریمیوم تعریف کنید تا در همه متن‌ها و دکمه‌ها "
    "خودکار جایگزین شود."
)


@router.message(Command("panel", "admin", "پنل", ignore_case=True), F.chat.type == "private")
async def panel(message: Message, state: FSMContext):
    await state.clear()
    await message.answer(HOME_TEXT, reply_markup=home_kb())


@router.callback_query(F.data == "adm:home")
async def home(call: CallbackQuery, state: FSMContext):
    await state.clear()
    await call.answer()
    await call.message.edit_text(HOME_TEXT, reply_markup=home_kb())


async def wait_for(call: CallbackQuery, state: FSMContext, action: str, prompt: str, **extra) -> None:
    await state.set_state(AdminFSM.waiting)
    await state.update_data(action=action, **extra)
    await call.answer()
    await call.message.answer(prompt + "\n\n❌ لغو: /cancel")


@router.message(Command("cancel"), StateFilter(AdminFSM.waiting))
async def cancel(message: Message, state: FSMContext):
    await state.clear()
    await message.answer("❌ لغو شد.", reply_markup=home_kb())


# ------------------------------------------------------------------ texts
@router.callback_query(F.data.startswith("adm:texts:"))
async def texts_list(call: CallbackQuery):
    page = int(call.data.rsplit(":", 1)[1])
    keys = list(TEXTS)
    chunk = keys[page * PAGE:(page + 1) * PAGE]
    db = get_db()
    rows = [[B(("✏️ " if db.text_override(k) is not None else "") + TEXTS[k][1].split("(")[0].strip()[:40],
               f"adm:text:{k}")] for k in chunk]
    nav = []
    if page > 0:
        nav.append(B("⬅️ قبلی", f"adm:texts:{page - 1}"))
    if (page + 1) * PAGE < len(keys):
        nav.append(B("بعدی ➡️", f"adm:texts:{page + 1}"))
    rows.append(nav)
    rows.append(BACK)
    await call.answer()
    await call.message.edit_text(f"📝 <b>ویرایش متن‌ها</b> (صفحه {page + 1})\n✏️ = ویرایش شده", reply_markup=ui.kb(rows))


def _placeholders(desc: str) -> str:
    found = re.findall(r"\{[a-z_]+\}", desc)
    return " ".join(f"<code>{p}</code>" for p in dict.fromkeys(found)) or "—"


@router.callback_query(F.data.startswith("adm:text:"))
async def text_view(call: CallbackQuery):
    key = call.data.split(":", 2)[2]
    if key not in TEXTS:
        await call.answer()
        return
    desc = TEXTS[key][1]
    current = ui.template(key)
    info = (
        f"🔑 <code>{key}</code>\n📄 {html.escape(desc.split('(')[0])}\n🧩 متغیرها: {_placeholders(desc)}\n"
        "ℹ️ اگر مقدار یک متغیر خالی باشد، کل آن خط خودکار حذف می‌شود.\n\n⬇️ متن فعلی:"
    )
    kb = ui.kb([[B("✏️ ویرایش", f"adm:tedit:{key}", "primary"), B("♻️ پیش‌فرض", f"adm:treset:{key}", "danger")],
                [B("🔙 بازگشت", "adm:texts:0")]])
    await call.answer()
    await call.message.answer(info)
    try:
        await call.message.answer(ui.apply_emoji_map(current), reply_markup=kb)
    except Exception:  # noqa: BLE001
        await call.message.answer(f"<pre>{html.escape(current)}</pre>", reply_markup=kb)


@router.callback_query(F.data.startswith("adm:tedit:"))
async def text_edit(call: CallbackQuery, state: FSMContext):
    key = call.data.split(":", 2)[2]
    await wait_for(call, state, "text", f"✏️ متن جدید برای <code>{key}</code> را بفرستید.\n"
                   "می‌توانید از فرمت‌بندی تلگرام (بولد، نقل‌قول، ...) و ایموجی پریمیوم استفاده کنید.\n"
                   f"🧩 متغیرها: {_placeholders(TEXTS[key][1])}", key=key)


@router.callback_query(F.data.startswith("adm:treset:"))
async def text_reset(call: CallbackQuery):
    key = call.data.split(":", 2)[2]
    await get_db().reset_text(key)
    await call.answer("♻️ به پیش‌فرض برگشت", show_alert=True)


def message_html(message: Message) -> str:
    text = message.text or message.caption or ""
    has_entities = bool(message.entities or message.caption_entities)
    if not has_entities and re.search(r"</?(?:b|i|u|s|code|pre|a|blockquote|tg-emoji|tg-spoiler)\b", text):
        return text  # raw HTML typed by the admin
    return message.html_text if message.text else (message.html_text or "")


# ------------------------------------------------------------------ buttons
@router.callback_query(F.data == "adm:btns")
async def buttons_list(call: CallbackQuery):
    conf = gget("buttons") or {}
    rows = [[B(("✏️ " if k in conf else "") + v[2][:40], f"adm:btn:{k}")] for k, v in BUTTONS.items()]
    rows.append(BACK)
    await call.answer()
    await call.message.edit_text("🔘 <b>دکمه‌های شیشه‌ای</b>\nدکمه موردنظر را انتخاب کنید:", reply_markup=ui.kb(rows))


async def _button_view(message: Message, key: str, edit: bool = True):
    conf = (gget("buttons") or {}).get(key, {})
    default_text, default_style, desc = BUTTONS[key]
    style = conf.get("style", default_style) or "none"
    text = (
        f"🔘 <b>{html.escape(desc)}</b>\n"
        f"📝 متن: <code>{html.escape(conf.get('text') or default_text)}</code>\n"
        f"😎 ایموجی پریمیوم: <code>{conf.get('emoji_id') or '—'}</code>\n"
        f"🎨 رنگ: {STYLES.get(style, style)}\n\n⬇️ پیش‌نمایش:"
    )
    preview = ui.button(key, callback_data="noop", title="نمونه")
    kb = ui.kb([
        [preview],
        [B("✏️ متن", f"adm:btext:{key}"), B("😎 ایموجی", f"adm:bemoji:{key}")],
        [B(label, f"adm:bstyle:{key}:{s}") for s, label in STYLES.items()],
        [B("♻️ پیش‌فرض", f"adm:breset:{key}", "danger"), B("🔙 بازگشت", "adm:btns")],
    ])
    if edit:
        await message.edit_text(text, reply_markup=kb)
    else:
        await message.answer(text, reply_markup=kb)


@router.callback_query(F.data.startswith("adm:btn:"))
async def button_view(call: CallbackQuery):
    await call.answer()
    await _button_view(call.message, call.data.split(":", 2)[2])


async def _set_button(key: str, **changes) -> None:
    conf = dict(gget("buttons") or {})
    item = dict(conf.get(key, {}))
    for k, v in changes.items():
        if v is None:
            item.pop(k, None)
        else:
            item[k] = v
    conf[key] = item
    await gset("buttons", conf)


@router.callback_query(F.data.startswith("adm:bstyle:"))
async def button_style(call: CallbackQuery):
    _, _, key, style = call.data.split(":", 3)
    await _set_button(key, style=style)
    await call.answer("✅")
    await _button_view(call.message, key)


@router.callback_query(F.data.startswith("adm:breset:"))
async def button_reset(call: CallbackQuery):
    key = call.data.split(":", 2)[2]
    conf = dict(gget("buttons") or {})
    conf.pop(key, None)
    await gset("buttons", conf)
    await call.answer("♻️")
    await _button_view(call.message, key)


@router.callback_query(F.data.startswith("adm:btext:"))
async def button_text(call: CallbackQuery, state: FSMContext):
    key = call.data.split(":", 2)[2]
    await wait_for(call, state, "btn_text", "✏️ متن جدید دکمه را بفرستید (متن ساده).", key=key)


@router.callback_query(F.data.startswith("adm:bemoji:"))
async def button_emoji(call: CallbackQuery, state: FSMContext):
    key = call.data.split(":", 2)[2]
    await wait_for(call, state, "btn_emoji", "😎 یک ایموجی پریمیوم بفرستید (یا آیدی عددی آن را).\nبرای حذف: <code>-</code>",
                   key=key)


# ------------------------------------------------------------------ premium emoji map
@router.callback_query(F.data == "adm:emo")
async def emoji_menu(call: CallbackQuery):
    emap: dict = gget("emoji_map") or {}
    lines = [f'{k} ← <tg-emoji emoji-id="{v}">{k}</tg-emoji> <code>{v}</code>' for k, v in list(emap.items())[:40]]
    text = (
        "😎 <b>ایموجی‌های پریمیوم</b>\n"
        "هر ایموجی معمولی که اینجا به ایموجی پریمیوم وصل شود، در همه متن‌ها و دکمه‌ها خودکار پریمیوم نمایش داده می‌شود.\n"
        "⚠️ شرط: صاحب ربات باید تلگرام پریمیوم داشته باشد.\n\n" + ("\n".join(lines) or "— خالی —")
    )
    kb = ui.kb([[B("➕ افزودن", "adm:emoadd", "success"), B("➖ حذف", "adm:emodel", "danger")],
                [B("🗑 حذف همه", "adm:emoclear"), *BACK]])
    await call.answer()
    await call.message.edit_text(text, reply_markup=kb)


@router.callback_query(F.data == "adm:emoadd")
async def emoji_add(call: CallbackQuery, state: FSMContext):
    await wait_for(call, state, "emo_add", "➕ ایموجی‌های پریمیوم را بفرستید (چند تا با هم هم می‌شود).\n"
                   "هر ایموجی پریمیوم جایگزین ایموجی معمولی معادل خودش می‌شود.")


@router.callback_query(F.data == "adm:emodel")
async def emoji_del(call: CallbackQuery, state: FSMContext):
    await wait_for(call, state, "emo_del", "➖ ایموجی(های) معمولی یا پریمیومی که می‌خواهید حذف شوند را بفرستید.")


@router.callback_query(F.data == "adm:emoclear")
async def emoji_clear(call: CallbackQuery):
    await gset("emoji_map", {})
    await call.answer("🗑", show_alert=True)


# ------------------------------------------------------------------ forced channels
async def _fj_view(message: Message, edit: bool = True):
    channels = gget("force_channels") or []
    lines = [f"{i + 1}. {html.escape(c.get('title') or '')} — <code>{c['id']}</code>\n   {html.escape(c.get('url') or '')}"
             for i, c in enumerate(channels)]
    text = (
        "📢 <b>عضویت اجباری</b>\n"
        "⚠️ ربات باید در هر کانال <b>ادمین</b> باشد تا بتواند عضویت را بررسی کند.\n\n" + ("\n".join(lines) or "— کانالی ثبت نشده —")
    )
    rows = [[B(f"❌ حذف {i + 1}", f"adm:fjdel:{i}")] for i in range(len(channels))]
    rows.append([B(f"{'✅' if gget('force_join_groups') else '❌'} در گروه‌ها", "adm:tog:force_join_groups:fj"),
                 B(f"{'✅' if gget('force_join_private') else '❌'} در پیوی", "adm:tog:force_join_private:fj")])
    rows.append([B("➕ افزودن کانال", "adm:fjadd", "success"), *BACK])
    if edit:
        await message.edit_text(text, reply_markup=ui.kb(rows))
    else:
        await message.answer(text, reply_markup=ui.kb(rows))


@router.callback_query(F.data == "adm:fj")
async def fj_menu(call: CallbackQuery):
    await call.answer()
    await _fj_view(call.message)


@router.callback_query(F.data == "adm:fjadd")
async def fj_add(call: CallbackQuery, state: FSMContext):
    await wait_for(call, state, "fj_add", "📢 یوزرنیم کانال (@channel)، آیدی عددی (-100...) یا یک پیام فوروارد شده از کانال را بفرستید.\n"
                   "برای کانال خصوصی می‌توانید بعد از آیدی، لینک دعوت را هم بنویسید:\n<code>-100123456 https://t.me/+abc</code>")


@router.callback_query(F.data.startswith("adm:fjdel:"))
async def fj_del(call: CallbackQuery):
    idx = int(call.data.rsplit(":", 1)[1])
    channels = list(gget("force_channels") or [])
    if 0 <= idx < len(channels):
        channels.pop(idx)
        await gset("force_channels", channels)
    await call.answer("✅")
    await _fj_view(call.message)


# ------------------------------------------------------------------ settings
async def _settings_view(message: Message, edit: bool = True):
    rows = []
    row: list = []
    for key, label in TOGGLES.items():
        on = bool(gget(key))
        row.append(B(f"{'✅' if on else '❌'} {label}", f"adm:tog:{key}:set"))
        if len(row) == 2:
            rows.append(row)
            row = []
    if row:
        rows.append(row)
    for key, label in {**NUMBERS, **STRINGS}.items():
        value = gget(key)
        rows.append([B(f"✏️ {label}: {value if value not in (None, '') else '—'}", f"adm:val:{key}")])
    rows.append([B("📝 کلمات اضافه ضدکلاهبرداری", "adm:val:scam_extra_keywords")])
    rows.append(BACK)
    text = "⚙️ <b>تنظیمات کلی</b>\n(تنظیمات هر گروه را ادمین‌های آن گروه با /settings تغییر می‌دهند)"
    if edit:
        await message.edit_text(text, reply_markup=ui.kb(rows))
    else:
        await message.answer(text, reply_markup=ui.kb(rows))


@router.callback_query(F.data == "adm:set")
async def settings_menu(call: CallbackQuery):
    await call.answer()
    await _settings_view(call.message)


@router.callback_query(F.data.startswith("adm:tog:"))
async def toggle(call: CallbackQuery):
    _, _, key, back = call.data.split(":", 3)
    await gset(key, not bool(gget(key)))
    await call.answer("✅")
    if back == "fj":
        await _fj_view(call.message)
    else:
        await _settings_view(call.message)


@router.callback_query(F.data.startswith("adm:val:"))
async def value_edit(call: CallbackQuery, state: FSMContext):
    key = call.data.split(":", 2)[2]
    if key == "scam_extra_keywords":
        current = "\n".join(gget(key) or []) or "—"
        await wait_for(call, state, "value", f"📝 هر کلمه/عبارت در یک خط بفرستید (برای پاک کردن: <code>-</code>)\nفعلی:\n{html.escape(current)}",
                       key=key)
        return
    label = NUMBERS.get(key) or STRINGS.get(key) or key
    await wait_for(call, state, "value", f"✏️ مقدار جدید «{label}» را بفرستید.\nفعلی: <code>{html.escape(str(gget(key)))}</code>",
                   key=key)


# ------------------------------------------------------------------ scams
@router.callback_query(F.data == "adm:scams")
async def scams_menu(call: CallbackQuery):
    db = get_db()
    pending = await db.list_scams("pending", 15)
    approved = await db.list_scams("approved", 15)
    lines = ["🚨 <b>گزارش‌های در انتظار</b>"]
    lines += [f"#{r['id']} — {html.escape(r.get('target_username') or str(r.get('target_id')))}" for r in pending] or ["—"]
    lines += ["", "⛔️ <b>کلاهبرداران تایید شده</b>"]
    lines += [f"#{r['id']} — {html.escape(r.get('target_username') or str(r.get('target_id')))}" for r in approved] or ["—"]
    lines += ["", "ℹ️ لغو تایید: <code>/unscam ID</code>"]
    rows = [[B(f"✅ #{r['id']}", f"scamadm:approve:{r['id']}", "success"),
             B(f"❌ #{r['id']}", f"scamadm:reject:{r['id']}", "danger")] for r in pending[:8]]
    rows.append(BACK)
    await call.answer()
    await call.message.edit_text("\n".join(lines), reply_markup=ui.kb(rows))


@router.message(Command("unscam"))
async def unscam(message: Message, command: CommandObject):
    if not (command.args or "").strip().isdigit():
        await message.answer("/unscam ID")
        return
    await get_db().update_scam(int(command.args.strip()), status="revoked")
    await message.answer("✅ لغو شد.")


# ------------------------------------------------------------------ stats
@router.callback_query(F.data == "adm:stats")
async def stats(call: CallbackQuery):
    from ..services import prices

    db = get_db()
    users = await db.count_users()
    groups = await db.count_groups()
    pending = len(await db.list_scams("pending", 1000))
    approved = len(await db.list_scams("approved", 100000))
    rate = await prices.usdt_toman()
    text = (
        "📊 <b>آمار ربات</b>\n"
        f"👤 کاربران: <b>{fmt.integer(users)}</b>\n"
        f"👥 گروه‌های فعال: <b>{fmt.integer(groups)}</b>\n"
        f"🚨 گزارش در انتظار: <b>{pending}</b>\n"
        f"⛔️ کلاهبرداران ثبت شده: <b>{approved}</b>\n"
        f"💹 نرخ تتر: <b>{fmt.toman(rate) if rate else '—'}</b> تومان"
    )
    await call.answer()
    await call.message.edit_text(text, reply_markup=ui.kb([BACK]))


# ------------------------------------------------------------------ broadcast
@router.callback_query(F.data == "adm:bcmenu")
async def bc_menu(call: CallbackQuery):
    await call.answer()
    await call.message.edit_text("📣 <b>ارسال همگانی</b>\nمقصد را انتخاب کنید:", reply_markup=ui.kb([
        [B("👤 کاربران", "adm:bc:users"), B("👥 گروه‌ها", "adm:bc:groups"), B("🌐 همه", "adm:bc:all")], BACK]))


@router.callback_query(F.data.startswith("adm:bc:"))
async def bc_target(call: CallbackQuery, state: FSMContext):
    target = call.data.split(":", 2)[2]
    await wait_for(call, state, "broadcast", "📣 پیام موردنظر را بفرستید (هر نوع پیامی؛ کپی می‌شود).", target=target)


async def _broadcast(bot: Bot, source: Message, target: str, report: Message) -> None:
    db = get_db()
    ids: list[int] = []
    if target in ("users", "all"):
        ids += await db.user_ids()
    if target in ("groups", "all"):
        ids += [g["id"] for g in await db.active_groups()]
    ok = fail = 0
    for chat_id in ids:
        try:
            await bot.copy_message(chat_id, source.chat.id, source.message_id)
            ok += 1
        except Exception:  # noqa: BLE001
            fail += 1
        await asyncio.sleep(0.04)
    await report.edit_text(f"✅ ارسال همگانی تمام شد.\nموفق: {ok}\nناموفق: {fail}")


# ------------------------------------------------------------------ FSM input
@router.message(StateFilter(AdminFSM.waiting), F.chat.type == "private")
async def admin_input(message: Message, state: FSMContext, bot: Bot):
    data = await state.get_data()
    action = data.get("action")
    key = data.get("key")

    if action == "text":
        new = message_html(message)
        if not new.strip():
            await message.answer("⚠️ متن خالی است.")
            return
        preview = re.sub(r'href="\{[a-z_]+\}"', 'href="https://t.me"', new)
        try:
            await message.answer("👁 پیش‌نمایش:\n\n" + ui.apply_emoji_map(preview))
        except Exception as exc:  # noqa: BLE001
            await message.answer(f"❌ متن نامعتبر است: {html.escape(str(exc))}")
            return
        await get_db().set_text(key, new)
        await state.clear()
        await message.answer("✅ متن ذخیره شد.", reply_markup=home_kb())
        return

    if action == "btn_text":
        await _set_button(key, text=(message.text or "").strip()[:64] or None)
        await state.clear()
        await message.answer("✅ ذخیره شد.")
        await _button_view(message, key, edit=False)
        return

    if action == "btn_emoji":
        emoji_id = None
        if (message.text or "").strip() == "-":
            emoji_id = None
        else:
            for e in message.entities or []:
                if e.type == "custom_emoji":
                    emoji_id = e.custom_emoji_id
                    break
            if not emoji_id and (message.text or "").strip().isdigit():
                emoji_id = message.text.strip()
            if not emoji_id and message.sticker and message.sticker.custom_emoji_id:
                emoji_id = message.sticker.custom_emoji_id
            if not emoji_id:
                await message.answer("⚠️ ایموجی پریمیوم پیدا نشد. دوباره بفرستید.")
                return
        await _set_button(key, emoji_id=emoji_id)
        await state.clear()
        await message.answer("✅ ذخیره شد.")
        await _button_view(message, key, edit=False)
        return

    if action in ("emo_add", "emo_del"):
        emap = dict(gget("emoji_map") or {})
        text = message.text or message.caption or ""
        count = 0
        for e in message.entities or message.caption_entities or []:
            if e.type == "custom_emoji":
                char = e.extract_from(text).replace("️", "")
                if action == "emo_add":
                    emap[char] = e.custom_emoji_id
                else:
                    emap.pop(char, None)
                count += 1
        if action == "emo_del" and not count:
            for char in list(emap):
                if char in text.replace("️", ""):
                    emap.pop(char)
                    count += 1
        if not count:
            await message.answer("⚠️ ایموجی پیدا نشد.")
            return
        await gset("emoji_map", emap)
        await state.clear()
        await message.answer(f"✅ {count} ایموجی {'اضافه' if action == 'emo_add' else 'حذف'} شد.", reply_markup=home_kb())
        return

    if action == "fj_add":
        await _add_channel(message, bot, state)
        return

    if action == "value":
        raw = (message.text or "").strip()
        if key == "scam_extra_keywords":
            value = [] if raw == "-" else [line.strip() for line in raw.splitlines() if line.strip()]
        elif key in NUMBERS:
            try:
                value = float(fmt.en_digits(raw).replace(",", ""))
                if value < 0:
                    raise ValueError
                if key not in ("star_usd",):
                    value = int(value)
            except ValueError:
                await message.answer("⚠️ عدد معتبر بفرستید.")
                return
        else:
            value = raw
        await gset(key, value)
        if key == "usdt_toman_manual":
            from ..services import prices

            getattr(prices.usdt_toman, "cache_clear", lambda: None)()
        await state.clear()
        await message.answer("✅ ذخیره شد.")
        await _settings_view(message, edit=False)
        return

    if action == "broadcast":
        await state.clear()
        report = await message.answer("⏳ در حال ارسال...")
        asyncio.create_task(_broadcast(bot, message, data.get("target", "users"), report))
        return

    await state.clear()


async def _add_channel(message: Message, bot: Bot, state: FSMContext) -> None:
    ref: str | int | None = None
    invite = None
    origin = message.forward_origin
    if origin is not None and getattr(origin, "chat", None):
        ref = origin.chat.id
    else:
        parts = (message.text or "").split()
        if parts:
            first = parts[0].replace("https://t.me/", "@").replace("t.me/", "@")
            ref = int(first) if first.lstrip("-").isdigit() else (first if first.startswith("@") else "@" + first)
        if len(parts) > 1 and "t.me/" in parts[1]:
            invite = parts[1]
    if ref is None:
        await message.answer("⚠️ ورودی نامعتبر.")
        return
    try:
        chat = await bot.get_chat(ref)
    except Exception as exc:  # noqa: BLE001
        await message.answer(f"❌ کانال پیدا نشد. ربات را در کانال عضو و ادمین کنید.\n<code>{html.escape(str(exc))}</code>")
        return
    try:
        me = await bot.get_chat_member(chat.id, bot.id)
        is_admin = me.status in ("administrator", "creator")
    except Exception:  # noqa: BLE001
        is_admin = False
    url = invite or (f"https://t.me/{chat.username}" if chat.username else chat.invite_link)
    if not url and is_admin:
        try:
            url = (await bot.create_chat_invite_link(chat.id, name="force-join")).invite_link
        except Exception:  # noqa: BLE001
            url = None
    if not url:
        await message.answer("⚠️ لینک دعوت پیدا نشد. آیدی و لینک را با هم بفرستید: <code>-100123 https://t.me/+abc</code>")
        return
    channels = [c for c in (gget("force_channels") or []) if c["id"] != chat.id]
    channels.append({"id": chat.id, "title": chat.title or chat.username or str(chat.id), "url": url})
    await gset("force_channels", channels)
    await state.clear()
    warn = "" if is_admin else "\n⚠️ ربات در این کانال ادمین نیست! حتما ادمینش کنید."
    await message.answer(f"✅ کانال «{html.escape(chat.title or '')}» اضافه شد.{warn}")
    await _fj_view(message, edit=False)


# ------------------------------------------------------------------ misc admin commands
@router.message(Command("addbalance"))
async def add_balance(message: Message, command: CommandObject):
    parts = (command.args or "").split()
    if len(parts) != 2 or not parts[0].isdigit():
        await message.answer("/addbalance USER_ID AMOUNT")
        return
    try:
        amount = float(parts[1])
    except ValueError:
        await message.answer("/addbalance USER_ID AMOUNT")
        return
    new = await get_db().add_balance(int(parts[0]), amount)
    await message.answer(f"✅ موجودی جدید: {fmt.num(new)}")


@router.message(Command("setrate"))
async def set_rate(message: Message, command: CommandObject):
    from ..services import prices

    raw = fmt.en_digits((command.args or "").replace(",", "")).strip()
    if not raw.replace(".", "").isdigit():
        await message.answer("/setrate 0  (0 = خودکار)")
        return
    await gset("usdt_toman_manual", float(raw))
    getattr(prices.usdt_toman, "cache_clear", lambda: None)()
    await message.answer("✅ ذخیره شد.")


_ = DEFAULTS  # keep import for discoverability of setting keys
