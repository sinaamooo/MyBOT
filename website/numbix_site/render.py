"""Render every page with Jinja and write the finished static site."""

from __future__ import annotations

import json
import shutil
from dataclasses import dataclass
from pathlib import Path

from jinja2 import Environment, FileSystemLoader, StrictUndefined, select_autoescape
from markupsafe import Markup, escape

from .assets import Assets, build_assets, write_sprite
from .config import Site, load_config, to_en, to_fa
from .pages import BY_SLUG, MOBILE_NAV, NAV_GROUPS, PAGES, SITEMAP
from .security import content_security_policy, script_hash

SPRITE_URL = "__NX_SPRITE__"  # swapped for the hashed sprite URL once all pages are rendered


@dataclass(frozen=True)
class BuildReport:
    out: Path
    files: tuple[Path, ...]
    missing: tuple[str, ...]
    nginx: Path


def _structured_data(site: Site) -> Markup:
    """schema.org Organization for search engines, safe to embed in a <script> block."""
    data = {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": f"{site.brand_fa} ({site.brand_en})",
        "url": f"https://{site.domain}/",
        "description": site.services_line,
        "email": site.email,
        "telephone": site.mobile_tel,
        "address": {
            "@type": "PostalAddress",
            "streetAddress": site.address,
            "postalCode": to_en(site.postal_code),
            "addressCountry": "IR",
        },
    }
    text = json.dumps(data, ensure_ascii=False)
    safe = text.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026")
    return Markup(safe)  # noqa: S704 (<, > and & are escaped, so the block cannot be closed early)


def _environment(root: Path, site: Site, assets: Assets, used_icons: set[str]) -> Environment:
    def icon(name: str, cls: str = "") -> Markup:
        if name not in assets.icons:
            raise ValueError(f"Unknown icon {name!r}; add assets/icons/{name}.svg")
        used_icons.add(name)
        classes = escape(f"i {cls}".strip())
        return Markup(  # noqa: S704 (name is a known icon, classes are escaped)
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
    boot = Markup((root / "templates" / "partials" / "boot.js").read_text(encoding="utf-8").strip())  # noqa: S704
    env.globals.update(
        boot_script=boot,
        csp=content_security_policy([script_hash(boot)]),
        structured_data=_structured_data(site),
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


def _nginx_config(env: Environment, site: Site) -> str:
    boot = str(env.globals["boot_script"])
    return env.get_template("deploy/nginx.conf").render(
        site=site, csp_header=content_security_policy([script_hash(boot)], header=True)
    )


def build_site(root: Path, config: Path, out: Path, services: list[dict] | None = None) -> BuildReport:
    site = load_config(config, services)
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
    extras = [("robots.txt", _robots(site)), ("sitemap.xml", _sitemap(site))]
    if site.enamad_meta:  # Enamad's "upload a file" check: https://<domain>/<code>.txt (code is [A-Za-z0-9_-] only)
        extras.append((f"{site.enamad_meta}.txt", site.enamad_meta + "\n"))
    for name, text in extras:
        (out / name).write_text(text, encoding="utf-8")
        files.append(out / name)
    # Server config lives next to the site, never inside the public folder.
    nginx = out.parent / f"{out.name}-nginx.conf"
    nginx.write_text(_nginx_config(env, site), encoding="utf-8")
    return BuildReport(out=out, files=tuple(files), missing=site.missing, nginx=nginx)
