from __future__ import annotations

import asyncio
from dataclasses import dataclass, field

from PIL import Image, ImageDraw

from ..config import FLAGS_DIR
from ..utils import render as R
from ..utils.fmt import jalali_date

BG_TOP = (10, 14, 32)
BG_BOTTOM = (22, 28, 58)
WHITE = (255, 255, 255)
MUTED = (170, 180, 210)
GREEN = (52, 211, 153)
RED = (248, 113, 113)
GOLD = (251, 191, 36)


def _flag_image(cc: str | None, code: str) -> Image.Image:
    if cc:
        path = FLAGS_DIR / f"{cc}.png"
        if path.exists():
            return Image.open(path).convert("RGBA")
    color = {"XAU": (212, 175, 55), "XAG": (192, 192, 200)}.get(code, (70, 90, 160))
    img = Image.new("RGBA", (320, 240), (*color, 255))
    d = ImageDraw.Draw(img)
    R.draw_text(d, (160, 120), code, R.font(96, "Black"), fill=(20, 20, 30), anchor="mm")
    return img


@dataclass
class ConvRow:
    label: str
    value: str
    unit: str


def fiat_card(code: str, name_fa: str, name_en: str, cc: str | None, amount_text: str,
              rows: list[ConvRow], unit_line: str, bot_username: str) -> bytes:
    W, H = 1280, 760
    canvas = R.vertical_gradient((W, H), BG_TOP, BG_BOTTOM)
    flag = _flag_image(cc, code)
    accent = R.average_color(flag)
    R.glow(canvas, (W - 160, 120), 260, accent, 90)
    R.glow(canvas, (120, H - 80), 240, (99, 102, 241), 80)
    d = ImageDraw.Draw(canvas)

    fw, fh = 210, 158
    fx, fy = W - 60 - fw, 50
    R.shadow(canvas, (fx, fy, fx + fw, fy + fh), radius=26)
    canvas.alpha_composite(R.rounded_image(flag, (fw, fh), 26), (fx, fy))
    d.rounded_rectangle((fx, fy, fx + fw, fy + fh), radius=26, outline=(255, 255, 255, 90), width=3)

    title_right = fx - 36
    R.draw_text(d, (title_right, 62), name_fa, R.fit_font(name_fa, 640, 64, "Black"), fill=WHITE, anchor="ra")
    R.draw_text(d, (title_right, 150), f"{name_en} • {code}", R.font(30, "Medium"), fill=MUTED, anchor="ra")

    pill_text = f"{amount_text} {code}"
    pf = R.fit_font(pill_text, 420, 60, "Black")
    pw = int(R.text_width(pill_text, pf)) + 70
    R.glass_box(canvas, (60, 70, 60 + pw, 190), radius=60, fill_alpha=40, tint=accent, highlight=False)
    d = ImageDraw.Draw(canvas)
    R.draw_text(d, (60 + pw // 2, 130), pill_text, pf, fill=WHITE, anchor="mm")

    cols, gap, top = 3, 26, 250
    bw = (W - 120 - gap * (cols - 1)) // cols
    bh = 180
    for i, row in enumerate(rows[:6]):
        r, c = divmod(i, cols)
        x1 = W - 60 - c * (bw + gap)
        x0 = x1 - bw
        y0 = top + r * (bh + gap)
        R.glass_box(canvas, (x0, y0, x1, y0 + bh), radius=30)
        d = ImageDraw.Draw(canvas)
        R.draw_text(d, (x1 - 28, y0 + 22), row.label, R.font(30, "Bold"), fill=MUTED, anchor="ra")
        vf = R.fit_font(row.value, bw - 56, 52, "Black", min_size=22)
        R.draw_text(d, (x0 + bw // 2, y0 + 105), row.value, vf, fill=WHITE, anchor="mm")
        R.draw_text(d, (x0 + 28, y0 + bh - 22), row.unit, R.font(24, "Medium"), fill=GOLD, anchor="ls")

    fy0 = H - 92
    R.glass_box(canvas, (60, fy0, W - 60, H - 30), radius=24, fill_alpha=16)
    d = ImageDraw.Draw(canvas)
    R.draw_text(d, (W - 90, fy0 + 31), unit_line, R.fit_font(unit_line, 700, 28, "Bold"), fill=WHITE, anchor="rm")
    left = f"{jalali_date()}"
    if bot_username:
        left = f"@{bot_username}  •  {left}"
    R.draw_text(d, (90, fy0 + 31), left, R.font(24, "Medium"), fill=MUTED, anchor="lm")
    return R.to_png_bytes(canvas)


@dataclass
class GiftTile:
    title: str
    subtitle: str
    value_ton: float | None
    center_color: tuple = (90, 110, 200)
    edge_color: tuple = (40, 50, 110)
    image: Image.Image | None = None
    extra: dict = field(default_factory=dict)


def gift_card(owner_name: str, owner_username: str | None, tiles: list[GiftTile], total_ton: float,
              total_usd: float | None, total_toman: float | None, pl_ton: float | None, pl_percent: float | None,
              total_count: int, bot_username: str, fmt_num) -> bytes:
    W = 1280
    cols = 2
    shown = tiles[:20]
    rows = max(1, (len(shown) + cols - 1) // cols)
    tile_h, gap = 130, 18
    header_h = 380
    H = header_h + rows * (tile_h + gap) + 120
    canvas = R.vertical_gradient((W, H), BG_TOP, BG_BOTTOM)
    R.glow(canvas, (W - 200, 120), 300, (124, 58, 237), 90)
    R.glow(canvas, (160, 260), 260, (14, 165, 233), 70)
    d = ImageDraw.Draw(canvas)

    R.draw_text(d, (W - 60, 48), "پرتفوی گیفت‌های تلگرام", R.font(30, "Bold"), fill=MUTED, anchor="ra")
    R.draw_text(d, (W - 60, 92), owner_name, R.fit_font(owner_name, 760, 62, "Black"), fill=WHITE, anchor="ra")
    if owner_username:
        R.draw_text(d, (W - 60, 176), f"@{owner_username}", R.font(28, "Medium"), fill=MUTED, anchor="ra")
    R.draw_text(d, (60, 60), "GIFT PORTFOLIO", R.font(26, "Black"), fill=GOLD, anchor="la")

    stats = [
        ("تعداد گیفت", fmt_num(total_count), WHITE),
        ("ارزش کل (TON)", fmt_num(total_ton), WHITE),
        ("ارزش (تومان)", fmt_num(total_toman) if total_toman else "—", WHITE),
    ]
    if pl_ton is not None:
        color = GREEN if pl_ton >= 0 else RED
        sign = "+" if pl_ton >= 0 else ""
        pct = f" ({sign}{pl_percent:.1f}%)" if pl_percent is not None else ""
        stats.append(("سود/ضرر ۷ روز", f"{sign}{fmt_num(pl_ton)}{pct}", color))
    else:
        stats.append(("ارزش (دلار)", fmt_num(total_usd) if total_usd else "—", WHITE))
    bw = (W - 120 - 3 * 20) // 4
    for i, (label, value, color) in enumerate(stats):
        x1 = W - 60 - i * (bw + 20)
        x0 = x1 - bw
        y0 = 230
        R.glass_box(canvas, (x0, y0, x1, y0 + 120), radius=26)
        d = ImageDraw.Draw(canvas)
        R.draw_text(d, (x1 - 22, y0 + 16), label, R.font(24, "Bold"), fill=MUTED, anchor="ra")
        R.draw_text(d, (x0 + bw // 2, y0 + 80), value, R.fit_font(value, bw - 30, 40, "Black", 18), fill=color,
                    anchor="mm")

    tw = (W - 120 - gap) // cols
    for i, tile in enumerate(shown):
        r, c = divmod(i, cols)
        x1 = W - 60 - c * (tw + gap)
        x0 = x1 - tw
        y0 = header_h + r * (tile_h + gap)
        R.glass_box(canvas, (x0, y0, x1, y0 + tile_h), radius=26, fill_alpha=18)
        disc = 100
        dx, dy = x1 - 18 - disc, y0 + (tile_h - disc) // 2
        canvas.alpha_composite(R.radial_disc(disc, tile.center_color, tile.edge_color), (dx, dy))
        if tile.image is not None:
            img = tile.image.convert("RGBA")
            img.thumbnail((disc - 10, disc - 10))
            canvas.alpha_composite(img, (dx + (disc - img.width) // 2, dy + (disc - img.height) // 2))
        d = ImageDraw.Draw(canvas)
        text_right = dx - 18
        val = f"{fmt_num(tile.value_ton)} TON" if tile.value_ton else "—"
        vf = R.fit_font(val, 200, 30, "Black", 16)
        R.draw_text(d, (x0 + 24, y0 + tile_h // 2), val, vf, fill=GOLD, anchor="lm")
        room = text_right - (x0 + 24 + R.text_width(val, vf) + 22)
        R.draw_text(d, (text_right, y0 + 22), tile.title, R.fit_font(tile.title, room, 32, "Black", 14),
                    fill=WHITE, anchor="ra")
        R.draw_text(d, (text_right, y0 + 72), tile.subtitle, R.fit_font(tile.subtitle, room, 24, "Medium", 12),
                    fill=MUTED, anchor="ra")

    more = len(tiles) - len(shown)
    footer = jalali_date()
    if bot_username:
        footer = f"@{bot_username}  •  {footer}"
    d = ImageDraw.Draw(canvas)
    R.draw_text(d, (60, H - 60), footer, R.font(24, "Medium"), fill=MUTED, anchor="lm")
    if more > 0:
        R.draw_text(d, (W - 60, H - 60), f"+{more} گیفت دیگر", R.font(26, "Bold"), fill=WHITE, anchor="rm")
    return R.to_png_bytes(canvas)


async def render(fn, *args, **kwargs) -> bytes:
    return await asyncio.to_thread(fn, *args, **kwargs)
