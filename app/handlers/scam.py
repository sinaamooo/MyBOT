"""/scam report flow (evidence -> admin review -> announce in all groups) and /check lookups."""
from __future__ import annotations

import asyncio
import logging
import time

from aiogram import Bot, F, Router
from aiogram.filters import Command, CommandObject, StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import CallbackQuery, Message

from .. import ui
from ..config import config
from ..db import get_db
from ..services import resolver
from ..utils import tg
from .common import send

log = logging.getLogger(__name__)
router = Router(name="scam")


class ScamFSM(StatesGroup):
    collecting = State()


def _target_label(row: dict) -> str:
    parts = []
    if row.get("target_name"):
        parts.append(ui.esc(row["target_name"]))
    if row.get("target_username"):
        parts.append(f"@{ui.esc(row['target_username'])}")
    if row.get("target_id") and not parts:
        parts.append(f'<a href="tg://user?id={row["target_id"]}">{row["target_id"]}</a>')
    return " ".join(parts) or "—"


def _evidence_kb(scam_id: int):
    return ui.kb([[ui.button("btn_scam_submit", callback_data=f"scam:submit:{scam_id}"),
                   ui.button("btn_scam_cancel", callback_data=f"scam:cancel:{scam_id}")]])


async def _parse_target(message: Message, bot: Bot, args: str) -> tuple[int | None, str | None, str | None] | None:
    if message.reply_to_message and message.reply_to_message.from_user and not args.strip():
        u = message.reply_to_message.from_user
        if u.is_bot:
            return None
        return u.id, u.username, u.full_name
    arg = args.strip().split()[0] if args.strip() else ""
    if not arg:
        return None
    if arg.isdigit():
        return int(arg), None, None
    username = arg.lstrip("@").replace("https://t.me/", "")
    found = await resolver.resolve(bot, username)
    if found and found.kind == "user":
        return found.id, username, found.name
    return None, username, None


@router.message(Command("scam", "report", "کلاهبردار", ignore_case=True))
async def scam_cmd(message: Message, bot: Bot, command: CommandObject, state: FSMContext):
    target = await _parse_target(message, bot, command.args or "")
    if not target:
        await send(message, ui.t("scam_usage"), markup=None)
        return
    target_id, username, name = target
    if target_id == message.from_user.id:
        await send(message, ui.t("scam_usage"), markup=None)
        return
    db = get_db()
    scam_id = await db.create_scam(target_id, username, name, message.from_user.id,
                                   message.chat.id if message.chat.type != "private" else None)
    if message.chat.type == "private":
        await begin_evidence(message, state, scam_id)
        return
    row = await db.get_scam(scam_id)
    url = f"https://t.me/{ui.BOT_USERNAME}?start=scam_{scam_id}"
    await send(message, ui.t("scam_go_private", target=_target_label(row)),
               markup=ui.kb([[ui.button("btn_scam_private", url=url)]]))


async def begin_evidence(message: Message, state: FSMContext, scam_id: int) -> None:
    row = await get_db().get_scam(scam_id) if scam_id else None
    if not row or row["reporter_id"] != message.from_user.id or row["status"] != "draft":
        await message.answer(ui.t("scam_usage"))
        return
    await state.set_state(ScamFSM.collecting)
    await state.update_data(scam_id=scam_id, evidence=[], reason="")
    await message.answer(ui.t("scam_ask_evidence", target=_target_label(row)), reply_markup=_evidence_kb(scam_id))


@router.message(StateFilter(ScamFSM.collecting), F.chat.type == "private")
async def collect_evidence(message: Message, state: FSMContext):
    if message.text and message.text.startswith("/"):
        await state.clear()
        await message.answer(ui.t("scam_cancelled"))
        return
    data = await state.get_data()
    evidence = data.get("evidence", [])
    evidence.append(message.message_id)
    reason = data.get("reason", "")
    text = message.text or message.caption or ""
    if text:
        reason = (reason + " " + text).strip()[:500]
    await state.update_data(evidence=evidence, reason=reason)
    await message.answer(ui.t("scam_evidence_saved", count=len(evidence)), reply_markup=_evidence_kb(data["scam_id"]))


@router.callback_query(F.data.startswith("scam:"))
async def scam_buttons(call: CallbackQuery, state: FSMContext, bot: Bot):
    _, action, sid = call.data.split(":", 2)
    scam_id = int(sid)
    db = get_db()
    row = await db.get_scam(scam_id)
    if not row or row["reporter_id"] != call.from_user.id:
        await call.answer(ui.plain("translate_not_yours"), show_alert=True)
        return
    data = await state.get_data()
    if action == "cancel":
        await db.update_scam(scam_id, status="cancelled")
        await state.clear()
        await call.answer()
        await call.message.edit_text(ui.t("scam_cancelled"))
        return
    if row["status"] != "draft":
        await call.answer(ui.plain("translate_used"), show_alert=True)
        return
    evidence = data.get("evidence", []) if data.get("scam_id") == scam_id else []
    if not evidence:
        await call.answer(ui.plain("scam_need_evidence"), show_alert=True)
        return
    reason = data.get("reason") or "—"
    await db.update_scam(scam_id, status="pending", evidence=evidence, reason=reason)
    await state.clear()
    await call.answer()
    await call.message.edit_text(ui.t("scam_submitted", id=scam_id))
    row = await db.get_scam(scam_id)
    await notify_admins(bot, row, call.from_user)


async def notify_admins(bot: Bot, row: dict, reporter) -> None:
    text = ui.t(
        "scam_admin", id=row["id"], target=_target_label(row), target_id=row.get("target_id") or "—",
        reporter=ui.mention(reporter), count=len(row["evidence"]), reason=ui.esc(row.get("reason") or "—"),
    )
    kb = ui.kb([[ui.button("btn_approve", callback_data=f"scamadm:approve:{row['id']}"),
                 ui.button("btn_reject", callback_data=f"scamadm:reject:{row['id']}")]])
    for admin in config.admin_ids:
        try:
            await bot.send_message(admin, text)
            for mid in row["evidence"][:30]:
                try:
                    await bot.copy_message(admin, reporter.id, mid)
                except Exception:  # noqa: BLE001
                    pass
            await bot.send_message(admin, text, reply_markup=kb)
        except Exception as exc:  # noqa: BLE001
            log.warning("cannot notify admin %s: %s", admin, exc)


@router.callback_query(F.data.startswith("scamadm:"))
async def admin_decision(call: CallbackQuery, bot: Bot):
    if not tg.is_bot_admin_user(call.from_user.id):
        await call.answer("⛔️", show_alert=True)
        return
    _, action, sid = call.data.split(":", 2)
    db = get_db()
    row = await db.get_scam(int(sid))
    if not row:
        await call.answer("❌", show_alert=True)
        return
    if row["status"] not in ("pending",):
        await call.answer(f"status: {row['status']}", show_alert=True)
        return
    approved = action == "approve"
    await db.update_scam(row["id"], status="approved" if approved else "rejected", decided_at=int(time.time()),
                         decided_by=call.from_user.id)
    await call.answer("✅")
    status = "✅ تایید شد و در گروه‌ها اعلام می‌شود" if approved else "❌ رد شد"
    try:
        await call.message.edit_text(f"{call.message.html_text}\n\n<b>{status}</b>")
    except Exception:  # noqa: BLE001
        pass
    try:
        await bot.send_message(row["reporter_id"], ui.t("scam_approved_user" if approved else "scam_rejected_user",
                                                        id=row["id"]))
    except Exception:  # noqa: BLE001
        pass
    if approved:
        asyncio.create_task(broadcast_scammer(bot, row))


async def broadcast_scammer(bot: Bot, row: dict) -> int:
    from ..settings import feature_on

    text = ui.t("scam_broadcast", target=_target_label(row), target_id=row.get("target_id") or "—",
                reason=ui.esc(row.get("reason") or "—"), id=row["id"])
    db = get_db()
    sent = 0
    for g in await db.active_groups():
        if not await feature_on(g["id"], "anti_scam"):
            continue
        try:
            await bot.send_message(g["id"], text, reply_markup=ui.footer_kb())
            sent += 1
        except Exception as exc:  # noqa: BLE001
            if "kicked" in str(exc).lower() or "not found" in str(exc).lower():
                await db.set_chat_active(g["id"], False)
        await asyncio.sleep(0.05)
    log.info("scam #%s announced in %s groups", row["id"], sent)
    return sent


@router.message(Command("check", "estelam", "استعلام", ignore_case=True))
async def check_cmd(message: Message, bot: Bot, command: CommandObject):
    target = await _parse_target(message, bot, command.args or "")
    if not target:
        await send(message, ui.t("scam_usage"), markup=None)
        return
    target_id, username, name = target
    label = " ".join(filter(None, [ui.esc(name) if name else None, f"@{ui.esc(username)}" if username else None,
                                   f"<code>{target_id}</code>" if target_id and not (name or username) else None]))
    row = await get_db().find_scammer(target_id, username)
    if row:
        await send(message, ui.t("scam_check_found", target=label, reason=ui.esc(row.get("reason") or "—"), id=row["id"]))
    else:
        await send(message, ui.t("scam_check_clean", target=label))
