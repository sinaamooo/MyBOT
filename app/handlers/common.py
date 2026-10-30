from __future__ import annotations

from aiogram.types import BufferedInputFile, InlineKeyboardMarkup, LinkPreviewOptions, Message, ReplyParameters

from .. import ui
from ..services.convert import Answer


def reply_params(message: Message) -> ReplyParameters:
    return ReplyParameters(message_id=message.message_id, allow_sending_without_reply=True)


async def send(message: Message, text: str, markup: InlineKeyboardMarkup | None = None, *,
               photo: bytes | None = None, reply: bool = True, preview_url: str | None = None) -> Message:
    if markup is None:
        markup = ui.footer_kb()
    rp = reply_params(message) if reply and message.chat.type != "private" else None
    if photo:
        if len(text) > 1024:
            sent = await message.answer_photo(BufferedInputFile(photo, "card.png"), reply_parameters=rp)
            await message.answer(text, reply_markup=markup)
            return sent
        return await message.answer_photo(BufferedInputFile(photo, "card.png"), caption=text, reply_markup=markup,
                                          reply_parameters=rp)
    lp = LinkPreviewOptions(url=preview_url, prefer_large_media=True, show_above_text=True) if preview_url else None
    return await message.answer(text, reply_markup=markup, reply_parameters=rp, link_preview_options=lp)


async def send_answer(message: Message, answer: Answer, markup: InlineKeyboardMarkup | None = None) -> Message:
    return await send(message, answer.text, markup, photo=answer.photo)
