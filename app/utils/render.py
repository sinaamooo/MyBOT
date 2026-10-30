from __future__ import annotations

import io
import re
from functools import lru_cache

from PIL import Image, ImageDraw, ImageFilter, ImageFont, features

from ..config import FONTS_DIR

RAQM = features.check("raqm")
_RTL = re.compile(r"[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿]")

if not RAQM:
    try:
        import arabic_reshaper
        from bidi.algorithm import get_display
    except ImportError:
        arabic_reshaper = None
        get_display = None


@lru_cache(maxsize=64)
def font(size: int, weight: str = "Bold") -> ImageFont.FreeTypeFont:
    path = FONTS_DIR / f"Vazirmatn-{weight}.ttf"
    layout = ImageFont.Layout.RAQM if RAQM else ImageFont.Layout.BASIC
    try:
        return ImageFont.truetype(str(path), size, layout_engine=layout)
    except OSError:
        return ImageFont.load_default(size)


def is_rtl(text: str) -> bool:
    return bool(_RTL.search(text or ""))


def _prepare(text: str) -> tuple[str, dict]:
    if not is_rtl(text):
        return text, {}
    if RAQM:
        return text, {"direction": "rtl", "language": "fa"}
    if arabic_reshaper and get_display:
        return get_display(arabic_reshaper.reshape(text)), {}
    return text, {}


def text_width(text: str, fnt: ImageFont.FreeTypeFont) -> float:
    prepared, kw = _prepare(text)
    try:
        return fnt.getlength(prepared, **kw)
    except Exception:
        box = fnt.getbbox(prepared)
        return box[2] - box[0]


def fit_font(text: str, max_width: float, size: int, weight: str = "Bold", min_size: int = 14):
    while size > min_size:
        f = font(size, weight)
        if text_width(text, f) <= max_width:
            return f
        size -= 2
    return font(min_size, weight)


def draw_text(draw: ImageDraw.ImageDraw, xy, text: str, fnt, fill=(255, 255, 255), anchor: str = "la", **kw):
    prepared, extra = _prepare(str(text))
    extra.update(kw)
    try:
        draw.text(xy, prepared, font=fnt, fill=fill, anchor=anchor, **extra)
    except (KeyError, ValueError, TypeError):
        draw.text(xy, prepared, font=fnt, fill=fill, anchor=anchor)


def vertical_gradient(size: tuple[int, int], top: tuple, bottom: tuple) -> Image.Image:
    w, h = size
    base = Image.new("RGB", (1, h))
    px = base.load()
    for y in range(h):
        t = y / max(h - 1, 1)
        px[0, y] = tuple(int(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
    return base.resize((w, h)).convert("RGBA")


def glow(canvas: Image.Image, center: tuple[int, int], radius: int, color: tuple, alpha: int = 120) -> None:
    layer = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    x, y = center
    d.ellipse((x - radius, y - radius, x + radius, y + radius), fill=(*color[:3], alpha))
    layer = layer.filter(ImageFilter.GaussianBlur(radius // 2))
    canvas.alpha_composite(layer)


def glass_box(canvas: Image.Image, box: tuple[int, int, int, int], radius: int = 28, fill_alpha: int = 22,
              outline_alpha: int = 55, tint: tuple = (255, 255, 255), highlight: bool = True) -> None:
    layer = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    d.rounded_rectangle(box, radius=radius, fill=(*tint, fill_alpha), outline=(*tint, outline_alpha), width=2)
    x0, y0, x1, y1 = box
    if highlight:
        d.rounded_rectangle((x0 + 3, y0 + 3, x1 - 3, y0 + (y1 - y0) // 2), radius=max(radius - 3, 4),
                            fill=(255, 255, 255, 8))
    canvas.alpha_composite(layer)


def shadow(canvas: Image.Image, box: tuple[int, int, int, int], radius: int = 24, offset: int = 10, alpha: int = 110):
    layer = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    x0, y0, x1, y1 = box
    d.rounded_rectangle((x0, y0 + offset, x1, y1 + offset), radius=radius, fill=(0, 0, 0, alpha))
    canvas.alpha_composite(layer.filter(ImageFilter.GaussianBlur(14)))


def rounded_image(img: Image.Image, size: tuple[int, int], radius: int) -> Image.Image:
    img = img.convert("RGBA")
    img = cover(img, size)
    mask = Image.new("L", size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, size[0] - 1, size[1] - 1), radius=radius, fill=255)
    out = Image.new("RGBA", size, (0, 0, 0, 0))
    out.paste(img, (0, 0), mask)
    return out


def cover(img: Image.Image, size: tuple[int, int]) -> Image.Image:
    w, h = size
    scale = max(w / img.width, h / img.height)
    img = img.resize((max(1, int(img.width * scale)), max(1, int(img.height * scale))), Image.LANCZOS)
    left = (img.width - w) // 2
    top = (img.height - h) // 2
    return img.crop((left, top, left + w, top + h))


def radial_disc(size: int, center_color: tuple, edge_color: tuple) -> Image.Image:
    grad = Image.radial_gradient("L").resize((size, size))
    inner = Image.new("RGB", (size, size), tuple(center_color[:3]))
    outer = Image.new("RGB", (size, size), tuple(edge_color[:3]))
    img = Image.composite(outer, inner, grad).convert("RGBA")
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, size - 1, size - 1), fill=255)
    img.putalpha(mask)
    return img


def average_color(img: Image.Image) -> tuple[int, int, int]:
    return tuple(img.convert("RGB").resize((1, 1), Image.LANCZOS).getpixel((0, 0)))


def to_png_bytes(img: Image.Image) -> bytes:
    buf = io.BytesIO()
    img.convert("RGB").save(buf, format="PNG", optimize=True)
    return buf.getvalue()


def int_to_rgb(value: int | None, default=(60, 70, 110)) -> tuple[int, int, int]:
    if value is None:
        return default
    return (value >> 16) & 255, (value >> 8) & 255, value & 255
