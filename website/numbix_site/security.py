"""Security helpers for the static site: CSP and validation of the Enamad snippets."""

from __future__ import annotations

import base64
import hashlib
import re
from html.parser import HTMLParser
from urllib.parse import urlsplit

from markupsafe import Markup, escape

ENAMAD_HOST = "trustseal.enamad.ir"
_META_CODE = re.compile(r"[A-Za-z0-9_-]{1,100}")


def script_hash(source: str) -> str:
    """CSP hash source for an inline <script> body."""
    return "sha256-" + base64.b64encode(hashlib.sha256(source.encode()).digest()).decode()


def content_security_policy(script_hashes: list[str], *, header: bool = False) -> str:
    """Policy for the public pages. ``header`` adds directives that only work as an HTTP header."""
    directives = [
        "default-src 'self'",
        "script-src 'self' " + " ".join(f"'{h}'" for h in script_hashes),
        "style-src 'self' 'unsafe-inline'",  # style="" attributes carry a few CSS variables
        f"img-src 'self' data: https://{ENAMAD_HOST}",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'none'",
        "form-action 'self'",
    ]
    if header:  # HTTP-header-only directives (the header is served over HTTPS)
        directives += ["frame-ancestors 'none'", "upgrade-insecure-requests"]
    return "; ".join(directives)


def check_enamad_meta(code: str) -> str:
    if code and not _META_CODE.fullmatch(code):
        raise ValueError("enamad.meta_code فقط می‌تواند حروف و اعداد انگلیسی، - و _ داشته باشد")
    return code


class _SealParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.tags: list[tuple[str, dict[str, str]]] = []
        self.closed: list[str] = []
        self.text = ""

    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, {k: v or "" for k, v in attrs}))

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)

    def handle_endtag(self, tag):
        self.closed.append(tag)

    def handle_data(self, data):
        self.text += data

    def handle_comment(self, data):
        self.text += "<!--"  # comments are not part of a genuine seal


def _enamad_url(value: str) -> str:
    parts = urlsplit(value)
    if parts.scheme != "https" or parts.hostname != ENAMAD_HOST or parts.username or parts.port:
        raise ValueError(f"نشانی نماد اینماد باید با https://{ENAMAD_HOST}/ شروع شود")
    return value


def clean_enamad_seal(snippet: str) -> Markup:
    """Accept only the official <a><img></a> seal and rebuild it from scratch.

    Anything else (scripts, event handlers, other hosts, extra markup) is rejected,
    so a pasted snippet can never inject code into the site.
    """
    if not snippet.strip():
        return Markup("")
    parser = _SealParser()
    parser.feed(snippet)
    parser.close()
    if [t for t, _ in parser.tags] != ["a", "img"] or parser.text.strip() or parser.closed not in (["a"], ["img", "a"]):
        raise ValueError("enamad.seal_html باید دقیقاً همان کد <a><img></a> نماد اینماد باشد")
    href = _enamad_url(parser.tags[0][1].get("href", ""))
    src = _enamad_url(parser.tags[1][1].get("src", ""))
    return Markup(  # noqa: S704 (rebuilt from validated, escaped URLs only)
        f'<a href="{escape(href)}" target="_blank" rel="noopener" referrerpolicy="origin">'
        f'<img src="{escape(src)}" alt="نماد اعتماد الکترونیکی" referrerpolicy="origin" loading="lazy"></a>'
    )
