from __future__ import annotations

import io
import logging

from aiogram import Bot, Router
from aiogram.filters import Command, CommandObject
from aiogram.types import BufferedInputFile, Message, OwnedGiftRegular, OwnedGiftUnique
from PIL import Image

from .. import ui
from ..db import get_db
from ..services import cards, gifts, prices, resolver
from ..utils import fmt
from ..utils.http import safe
from ..utils.render import int_to_rgb
from .common import reply_params, send

log = logging.getLogger(__name__)
router = Router(name="gifts")


def _ton_rates(snap) -> tuple[float | None, float | None]:
    usd = snap.ton_usd
    return usd, (usd * snap.usdt_toman if usd and snap.usdt_toman else None)


async def answer_gift_link(message: Message, slug: str, number: int) -> None:
    meta = await gifts.gift_meta(slug, number)
    name = meta.name if meta else gifts._split_name(slug)
    val = await gifts.value_of(name, meta.model if meta else None, meta.backdrop if meta else None,
                               meta.symbol if meta else None)
    if not meta and not val.collection:
        await send(message, ui.t("gift_not_found"))
        return
    snap = await prices.snapshot()
    ton_usd, ton_toman = _ton_rates(snap)
    est = val.estimate
    link = f"https://t.me/nft/{slug}-{number}"
    text = ui.t(
        "gift_link",
        name=ui.esc(val.collection or name),
        number=fmt.integer(number),
        model=ui.esc(meta.model) if meta and meta.model else None,
        model_rarity=ui.esc(f"({meta.model_rarity})") if meta and meta.model_rarity else "",
        backdrop=ui.esc(meta.backdrop) if meta and meta.backdrop else None,
        backdrop_rarity=ui.esc(f"({meta.backdrop_rarity})") if meta and meta.backdrop_rarity else "",
        symbol=ui.esc(meta.symbol) if meta and meta.symbol else None,
        symbol_rarity=ui.esc(f"({meta.symbol_rarity})") if meta and meta.symbol_rarity else "",
        owner=ui.esc(meta.owner) if meta and meta.owner else None,
        quantity=ui.esc(meta.quantity) if meta and meta.quantity else None,
        floor_ton=fmt.num(val.floor) if val.floor else None,
        model_floor=fmt.num(val.model_floor) if val.model_floor else None,
        backdrop_floor=fmt.num(val.backdrop_floor) if val.backdrop_floor else None,
        symbol_floor=fmt.num(val.symbol_floor) if val.symbol_floor else None,
        est_ton=fmt.num(est) if est else None,
        est_usd=fmt.usd(est * ton_usd) if est and ton_usd else None,
        est_toman=fmt.toman(est * ton_toman) if est and ton_toman else None,
        est_stars=fmt.integer(est * ton_usd / prices.star_usd()) if est and ton_usd else None,
        link=link,
    )
    await send(message, text, markup=ui.footer_kb([[ui.button("btn_gift_open", url=link)]]), preview_url=link)


async def _fetch_owned(bot: Bot, target: resolver.Target) -> tuple[list, int]:
    items: list = []
    total = 0
    offset = None
    for _ in range(6):
        if target.kind == "chat":
            res = await bot.get_chat_gifts(chat_id=target.id, offset=offset, limit=100)
        else:
            res = await bot.get_user_gifts(user_id=target.id, offset=offset, limit=100)
        items.extend(res.gifts or [])
        total = res.total_count or len(items)
        offset = res.next_offset
        if not offset:
            break
    return items, total


async def _target_from_message(message: Message, bot: Bot, args: str) -> resolver.Target | None:
    if message.reply_to_message and message.reply_to_message.from_user and not args:
        u = message.reply_to_message.from_user
        return resolver.Target("user", u.id, u.full_name, u.username)
    arg = args.strip().split()[0] if args.strip() else ""
    if not arg:
        u = message.from_user
        return resolver.Target("user", u.id, u.full_name, u.username)
    if arg.lstrip("-").isdigit():
        uid = int(arg)
        return resolver.Target("chat" if uid < 0 else "user", uid, arg, None)
    arg = arg.replace("https://t.me/", "").replace("t.me/", "")
    if message.from_user.username and arg.lstrip("@").lower() == message.from_user.username.lower():
        u = message.from_user
        return resolver.Target("user", u.id, u.full_name, u.username)
    return await resolver.resolve(bot, arg)


@router.message(Command("gift", "gifts", "گیفت", ignore_case=True))
async def gift_cmd(message: Message, bot: Bot, command: CommandObject):
    args = command.args or ""
    links = gifts.find_links(args)
    if links:
        await answer_gift_link(message, *links[0])
        return
    target = await _target_from_message(message, bot, args)
    if not target:
        await send(message, ui.t("gift_user_unknown"))
        return
    wait = await send(message, ui.t("loading"), markup=None)
    try:
        await _portfolio(message, bot, target)
    finally:
        from ..utils.tg import safe_delete

        await safe_delete(wait)


async def _portfolio(message: Message, bot: Bot, target: resolver.Target) -> None:
    try:
        owned, total_count = await _fetch_owned(bot, target)
    except Exception as exc:
        log.info("get gifts failed for %s: %s", target.id, exc)
        await send(message, ui.t("gift_user_unknown"))
        return
    if not owned:
        await send(message, ui.t("gift_user_none", name=ui.esc(target.name)))
        return

    snap = await prices.snapshot()
    ton_usd, ton_toman = _ton_rates(snap)
    ds = await gifts.dataset()
    star_ton = (prices.star_usd() / ton_usd) if ton_usd else None

    tiles: list[cards.GiftTile] = []
    links: list[str] = []
    total_now = 0.0
    total_then = 0.0
    unique_count = 0
    for og in owned:
        if isinstance(og, OwnedGiftUnique):
            g = og.gift
            unique_count += 1
            val = await gifts.value_of(g.base_name, g.model.name if g.model else None,
                                       g.backdrop.name if g.backdrop else None, g.symbol.name if g.symbol else None)
            value = val.estimate
            if value:
                total_now += value
                ratio = (val.floor_7d / val.floor) if val.floor and val.floor_7d else 1.0
                total_then += value * ratio
            colors = g.backdrop.colors if g.backdrop else None
            tiles.append(cards.GiftTile(
                title=f"{g.base_name} #{g.number}",
                subtitle=" • ".join(filter(None, [g.model.name if g.model else None,
                                                  g.backdrop.name if g.backdrop else None])),
                value_ton=value,
                center_color=int_to_rgb(colors.center_color if colors else None),
                edge_color=int_to_rgb(colors.edge_color if colors else None, (30, 30, 60)),
                extra={"name": g.name},
            ))
            links.append(f"https://t.me/nft/{g.name}")
        elif isinstance(og, OwnedGiftRegular):
            g = og.gift
            info = ds["unupgraded"].get(str(g.id))
            value = float(info["price_ton"]) if info and info.get("price_ton") else None
            title = info.get("full_name") if info else None
            if value is None and star_ton:
                stars = og.convert_star_count or g.star_count or 0
                value = stars * star_ton if stars else None
            if value:
                total_now += value
                total_then += value
            tiles.append(cards.GiftTile(
                title=title or f"Gift ⭐️{g.star_count}",
                subtitle=f"{g.star_count} Stars" + (" • Limited" if g.total_count else ""),
                value_ton=value,
            ))

    tiles.sort(key=lambda t: t.value_ton or 0, reverse=True)
    thumbs = await gifts.gather_limited(
        [gifts.gift_image(t.extra["name"]) if t.extra.get("name") else _none() for t in tiles[:20]], 8
    )
    for tile, data in zip(tiles, thumbs):
        if data:
            try:
                tile.image = Image.open(io.BytesIO(data))
            except Exception:
                pass

    pl_ton = total_now - total_then if total_then else None
    pl_pct = (pl_ton / total_then * 100) if pl_ton is not None and total_then else None
    owner_key = target.id
    db = get_db()
    last = await db.last_gift_snapshot(owner_key)
    await db.add_gift_snapshot(owner_key, total_now)

    png = await cards.render(
        cards.gift_card, target.name, target.username, tiles, total_now,
        total_now * ton_usd if ton_usd else None, total_now * ton_toman if ton_toman else None,
        pl_ton if pl_ton is not None and abs(pl_ton) > 0.0001 else None, pl_pct, total_count, ui.BOT_USERNAME, fmt.num,
    )

    pl_line = None
    if pl_ton is not None and abs(pl_ton) > 0.0001:
        key = "gift_profit" if pl_ton >= 0 else "gift_loss"
        pl_line = ui.fill(ui.template(key), ton=fmt.num(abs(pl_ton)), percent=f"{abs(pl_pct or 0):.2f}",
                          toman=fmt.toman(abs(pl_ton) * ton_toman) if ton_toman else "—")
    change_line = None
    if last and last.get("value_ton"):
        diff = total_now - last["value_ton"]
        pct = diff / last["value_ton"] * 100 if last["value_ton"] else 0
        sign = "+" if diff >= 0 else "-"
        change_line = ui.fill(ui.template("gift_since_last"), sign=sign, ton=fmt.num(abs(diff)), percent=f"{abs(pct):.2f}")

    def caption_with(n: int) -> str:
        shown = links[:n]
        extra = f"\n➕ {len(links) - n} لینک دیگر" if len(links) > n else ""
        return ui.t(
            "gift_user_caption",
            name=ui.esc(target.name),
            count=fmt.integer(total_count),
            unique_count=fmt.integer(unique_count),
            total_ton=fmt.num(total_now),
            total_usd=fmt.usd(total_now * ton_usd) if ton_usd else "—",
            total_toman=fmt.toman(total_now * ton_toman) if ton_toman else "—",
            pl_line=pl_line,
            change_line=change_line,
            links=("\n".join(f"🔗 {link}" for link in shown) + extra) if shown else None,
        )

    n = len(links)
    caption = caption_with(n)
    while len(caption) > 1024 and n > 0:
        n -= 1
        caption = caption_with(n)
    rp = None if message.chat.type == "private" else reply_params(message)
    await message.answer_photo(BufferedInputFile(png, "gifts.png"), caption=caption[:1024], reply_markup=ui.footer_kb(),
                               reply_parameters=rp)
    if n < len(links):
        rest = links[n:]
        chunk: list[str] = []
        for link in rest:
            chunk.append(f"🔗 {link}")
            if len("\n".join(chunk)) > 3800:
                await message.answer("\n".join(chunk[:-1]))
                chunk = chunk[-1:]
        if chunk:
            await message.answer("\n".join(chunk))


async def _none():
    return None


async def safe_gift_link(message: Message, slug: str, number: int) -> None:
    await safe(answer_gift_link(message, slug, number), label="gift_link")
