"""Render every page with Jinja and write the finished static site."""

from __future__ import annotations

import shutil
from dataclasses import dataclass
from pathlib import Path

from jinja2 import Environment, FileSystemLoader, StrictUndefined, select_autoescape
from markupsafe import Markup

from .assets import Assets, build_assets, write_sprite
from .config import Site, load_config, to_fa
from .pages import BY_SLUG, MOBILE_NAV, NAV_GROUPS, PAGES, SITEMAP

SPRITE_URL = "__NX_SPRITE__"  # swapped for the hashed sprite URL once all pages are rendered


@dataclass(frozen=True)
class BuildReport:
    out: Path
    files: tuple[Path, ...]
    missing: tuple[str, ...]


def _environment(root: Path, site: Site, assets: Assets, used_icons: set[str]) -> Environment:
    def icon(name: str, cls: str = "") -> Markup:
        if name not in assets.icons:
            raise ValueError(f"Unknown icon {name!r}; add assets/icons/{name}.svg")
        used_icons.add(name)
        classes = f"i {cls}".strip()
        return Markup(
            f'<svg class="{classes}" aria-hidden="true" focusable="false"><use href="{SPRITE_URL}#{name}"></use></svg>'
        )

    env = Environment(
        loader=FileSystemLoader(root / "templates"),
        autoescape=select_autoescape(("html",)),
        undefined=StrictUndefined,
        trim_blocks=True,
        lstrip_blocks=True,
    )
    env.filters["fa"] = to_fa
    env.globals.update(
        site=site,
        assets=assets,
        icon=icon,
        pages=BY_SLUG,
        nav_groups=[(label, [BY_SLUG[s] for s in slugs]) for label, slugs in NAV_GROUPS],
        mobile_nav=[BY_SLUG[s] for s in MOBILE_NAV],
    )
    return env


def _robots(site: Site) -> str:
    return f"User-agent: *\nAllow: /\n\nSitemap: https://{site.domain}/sitemap.xml\n"


def _sitemap(site: Site) -> str:
    urls = "".join(f"  <url><loc>https://{site.domain}{BY_SLUG[s].url}</loc></url>\n" for s in SITEMAP)
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        f"{urls}</urlset>\n"
    )


def build_site(root: Path, config: Path, out: Path) -> BuildReport:
    site = load_config(config)
    if out.exists():
        shutil.rmtree(out)
    assets = build_assets(root / "assets", out)

    used_icons: set[str] = set()
    env = _environment(root, site, assets, used_icons)
    rendered = {page: env.get_template(page.template).render(page=page) for page in PAGES}
    sprite = write_sprite(out, assets.icons, used_icons)

    files = []
    for page, html in rendered.items():
        target = out / page.output
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(html.replace(SPRITE_URL, sprite), encoding="utf-8")
        files.append(target)
    for name, text in (("robots.txt", _robots(site)), ("sitemap.xml", _sitemap(site))):
        (out / name).write_text(text, encoding="utf-8")
        files.append(out / name)
    return BuildReport(out=out, files=tuple(files), missing=site.missing)
