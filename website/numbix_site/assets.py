"""Bundle, fingerprint and copy the front-end assets."""

from __future__ import annotations

import hashlib
import re
import shutil
from dataclasses import dataclass
from pathlib import Path

_SVG_BODY = re.compile(r"<svg[^>]*>(.*?)</svg>", re.S)


@dataclass(frozen=True)
class Assets:
    css: str
    js: str
    icons: dict[str, str]  # icon name -> inner SVG markup


def _write_hashed(out: Path, stem: str, suffix: str, data: bytes) -> str:
    """Write ``data`` under a content-hashed name and return its public URL."""
    name = f"{stem}.{hashlib.sha256(data).hexdigest()[:10]}.{suffix}"
    (out / "assets" / name).write_bytes(data)
    return f"/assets/{name}"


def _minify_css(css: str) -> str:
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    css = re.sub(r"\s+", " ", css)
    css = re.sub(r"\s*([{};,>])\s*", r"\1", css)
    return css.replace(";}", "}").strip()


def _bundle(folder: Path, pattern: str) -> str:
    return "\n".join(p.read_text(encoding="utf-8") for p in sorted(folder.glob(pattern)))


def _load_icons(folder: Path) -> dict[str, str]:
    icons = {}
    for svg in sorted(folder.glob("*.svg")):
        body = _SVG_BODY.search(svg.read_text(encoding="utf-8"))
        icons[svg.stem] = re.sub(r"\s+", " ", body.group(1)).strip()
    return icons


def build_assets(src: Path, out: Path) -> Assets:
    """Copy static files and write the hashed CSS/JS bundles."""
    (out / "assets").mkdir(parents=True)
    shutil.copytree(src / "fonts", out / "assets" / "fonts")
    for file in (src / "img").iterdir():
        shutil.copy2(file, out / "assets" / file.name)

    css = _minify_css(_bundle(src / "css", "*.css")).encode()
    js = ("(() => {\n'use strict';\n" + _bundle(src / "js", "*.js") + "\n})();\n").encode()
    return Assets(
        css=_write_hashed(out, "app", "css", css),
        js=_write_hashed(out, "app", "js", js),
        icons=_load_icons(src / "icons"),
    )


def write_sprite(out: Path, icons: dict[str, str], used: set[str]) -> str:
    """Write an SVG sprite containing only the icons the pages use."""
    symbols = "".join(f'<symbol id="{name}" viewBox="0 0 24 24">{icons[name]}</symbol>' for name in sorted(used))
    sprite = f'<svg xmlns="http://www.w3.org/2000/svg">{symbols}</svg>'.encode()
    return _write_hashed(out, "icons", "svg", sprite)
