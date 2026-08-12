from __future__ import annotations

from aiogram import F
from aiogram.filters import StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import (
    SETTINGS_CATEGORIES,
    cancel_keyboard,
    indicators_keyboard,
    settings_category_keyboard,
    settings_menu_keyboard,
    templates_keyboard,
)
from app.bot.routers import admin_router
from app.bot.states import SettingEdit, TemplateEdit
from app.services import settings_service
from config import DEFAULT_SIGNAL_MESSAGE_TEMPLATE

CATEGORY_TITLES = {
    "scoring": "🎯 Scoring & Thresholds",
    "risk": "🛡 Risk & Leverage",
    "scanner": "🔁 Scanner & Cooldown",
    "monitoring": "👁 Monitoring",
}


# -- Indicators ------------------------------------------------------------
@admin_router.callback_query(F.data == "menu:indicators")
async def show_indicators(callback: CallbackQuery) -> None:
    params = await settings_service.get_all()
    await callback.message.edit_text("📊 <b>Indicators</b>\n\nToggle indicators on/off:", reply_markup=indicators_keyboard(params))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("ind:toggle:"))
async def toggle_indicator(callback: CallbackQuery) -> None:
    key = callback.data.split(":", 2)[-1]
    params = await settings_service.get_all()
    await settings_service.set(key, not params[key])
    params = await settings_service.get_all(force_reload=True)
    await callback.message.edit_text("📊 <b>Indicators</b>\n\nToggle indicators on/off:", reply_markup=indicators_keyboard(params))
    await callback.answer("Updated")


# -- Settings ---------------------------------------------------------------
@admin_router.callback_query(F.data == "menu:settings")
async def show_settings_menu(callback: CallbackQuery) -> None:
    await callback.message.edit_text("⚙️ <b>Settings</b>\n\nChoose a category:", reply_markup=settings_menu_keyboard())
    await callback.answer()


@admin_router.callback_query(F.data.startswith("set:cat:"))
async def show_settings_category(callback: CallbackQuery) -> None:
    category = callback.data.split(":", 2)[-1]
    params = await settings_service.get_all()
    await callback.message.edit_text(f"⚙️ <b>{CATEGORY_TITLES[category]}</b>", reply_markup=settings_category_keyboard(category, params))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("set:bool:"))
async def toggle_bool_setting(callback: CallbackQuery) -> None:
    key = callback.data.split(":", 2)[-1]
    params = await settings_service.get_all()
    await settings_service.set(key, not params[key])
    params = await settings_service.get_all(force_reload=True)
    category = next(cat for cat, items in SETTINGS_CATEGORIES.items() if any(k == key for _, k in items))
    await callback.message.edit_text(f"⚙️ <b>{CATEGORY_TITLES[category]}</b>", reply_markup=settings_category_keyboard(category, params))
    await callback.answer("Updated")


@admin_router.callback_query(F.data.startswith("set:edit:"))
async def start_edit_setting(callback: CallbackQuery, state: FSMContext) -> None:
    key = callback.data.split(":", 2)[-1]
    category = next(cat for cat, items in SETTINGS_CATEGORIES.items() if any(k == key for _, k in items))
    current = await settings_service.get(key)
    await state.set_state(SettingEdit.waiting_value)
    await state.update_data(key=key, category=category)
    await callback.message.edit_text(f"Current value of <b>{key}</b>: {current}\n\nSend the new numeric value:", reply_markup=cancel_keyboard("settings"))
    await callback.answer()


@admin_router.message(StateFilter(SettingEdit.waiting_value))
async def receive_setting_value(message: Message, state: FSMContext) -> None:
    data = await state.get_data()
    key, category = data["key"], data["category"]
    value_type = settings_service.value_type(key)
    try:
        parsed = value_type(message.text.strip())
    except (ValueError, TypeError):
        await message.answer(f"Invalid value for a {value_type.__name__}. Try again or press Back.")
        return
    await settings_service.set(key, parsed)
    await state.clear()
    params = await settings_service.get_all(force_reload=True)
    await message.answer(f"✅ {key} = {parsed}", reply_markup=settings_category_keyboard(category, params))


# -- Message templates --------------------------------------------------------
@admin_router.callback_query(F.data == "menu:templates")
async def show_templates(callback: CallbackQuery) -> None:
    template = await settings_service.get("signal_message_template")
    await callback.message.edit_text(
        f"📝 <b>Message Templates</b>\n\nCurrent template:\n<code>{template}</code>", reply_markup=templates_keyboard()
    )
    await callback.answer()


@admin_router.callback_query(F.data == "tpl:edit")
async def start_edit_template(callback: CallbackQuery, state: FSMContext) -> None:
    await state.set_state(TemplateEdit.waiting_template)
    await callback.message.edit_text(
        "Send the new signal template. Use these placeholders:\n"
        "{symbol} {side} {side_emoji} {score} {entry} {stop_loss} {tp1} {tp2} {tp3} "
        "{leverage} {rr} {timeframe} {trend} {regime}",
        reply_markup=cancel_keyboard("templates"),
    )
    await callback.answer()


@admin_router.message(StateFilter(TemplateEdit.waiting_template))
async def receive_template(message: Message, state: FSMContext) -> None:
    await state.clear()
    try:
        message.text.format(
            symbol="BTCUSDT", side="LONG", side_emoji="🟢", score=90, entry=1, stop_loss=1, tp1=1, tp2=1, tp3=1,
            leverage=20, rr=2, timeframe="5M/15M", trend="BULLISH", regime="TRENDING",
        )
    except (KeyError, IndexError, ValueError) as exc:
        await message.answer(f"Template has an invalid placeholder: {exc}. Not saved.")
        return
    await settings_service.set("signal_message_template", message.text)
    await message.answer("✅ Template saved.", reply_markup=templates_keyboard())


@admin_router.callback_query(F.data == "tpl:reset")
async def reset_template(callback: CallbackQuery) -> None:
    await settings_service.set("signal_message_template", DEFAULT_SIGNAL_MESSAGE_TEMPLATE)
    await callback.message.edit_text(
        f"📝 <b>Message Templates</b>\n\nCurrent template:\n<code>{DEFAULT_SIGNAL_MESSAGE_TEMPLATE}</code>",
        reply_markup=templates_keyboard(),
    )
    await callback.answer("Reset to default")
