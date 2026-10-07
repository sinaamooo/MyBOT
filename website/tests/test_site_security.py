"""Attacks against the static site build: injected content must never become markup."""

from __future__ import annotations

import re
from html.parser import HTMLParser

import pytest
from conftest import FILLED, write_config

from numbix_site import build_site
from numbix_site.security import clean_enamad_seal, script_hash

PAYLOAD = "\"'><script>alert(1)</script><img src=x onerror=alert(1)></textarea></title><svg onload=alert(1)>"
REAL_SEAL = (
    "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=123&Code=abc'>"
    "<img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=123&Code=abc' alt='' "
    "style='cursor:pointer' code='abc'></a>"
)


class _Tags(HTMLParser):
    """Collects every real tag and attribute the browser would create."""

    def __init__(self):
        super().__init__()
        self.tags, self.handlers, self.scripts = [], [], []
        self._in_script = False

    def handle_starttag(self, tag, attrs):
        self.tags.append(tag)
        self.handlers += [name for name, _ in attrs if name.startswith("on")]
        # JSON-LD is data, not code (its "<" is escaped and checked separately)
        self._in_script = tag == "script" and dict(attrs).get("type") != "application/ld+json"

    def handle_endtag(self, tag):
        self._in_script = False

    def handle_data(self, data):
        if self._in_script:
            self.scripts.append(data)


def _build(site_root, tmp_path, services=None):
    return build_site(site_root, site_root / "site.toml", tmp_path / "out", services)


def test_injected_text_is_escaped_on_every_page(site_root, tmp_path):
    values = {k: v for k, v in FILLED.items() if k not in ("domain", "email")}
    write_config(site_root, {**FILLED, **{k: PAYLOAD for k in values}})
    services = [{"name": PAYLOAD, "price": "1000", "category": PAYLOAD, "description": PAYLOAD, "icon": "package"}]
    report = _build(site_root, tmp_path, services)
    pages = list(report.out.rglob("*.html"))
    assert len(pages) == 8
    for page in pages:
        html = page.read_text(encoding="utf-8")
        parsed = _Tags()
        parsed.feed(html)
        assert parsed.handlers == [], page  # no injected on*= attribute became real
        assert "textarea" not in parsed.tags and parsed.tags.count("title") == 1, page
        assert not any("alert" in script for script in parsed.scripts), page
        # nothing can close the JSON-LD block early
        for block in re.findall(r'<script type="application/ld\+json">(.*?)</script>', html, re.S):
            assert "<" not in block


@pytest.mark.parametrize(
    "field,value",
    [
        ("domain", "evil.com; include /etc/passwd"),
        ("domain", "numbix.ir\nserver_name evil"),
        ("email", "a@b.c<script>"),
        ("email", "javascript:alert(1)"),
    ],
)
def test_values_used_in_urls_or_server_config_are_validated(site_root, tmp_path, field, value):
    write_config(site_root, {**FILLED, field: value})
    with pytest.raises(ValueError):
        _build(site_root, tmp_path)


def test_server_settings_are_validated(site_root, tmp_path):
    write_config(site_root, FILLED, '\n[server]\nadmin_path = "/admin; return 200"\n')
    with pytest.raises(ValueError):
        _build(site_root, tmp_path)


def test_genuine_enamad_seal_is_kept():
    html = str(clean_enamad_seal(REAL_SEAL))
    assert html.startswith('<a href="https://trustseal.enamad.ir/?id=123&amp;Code=abc"')
    assert 'src="https://trustseal.enamad.ir/logo.aspx?id=123&amp;Code=abc"' in html


@pytest.mark.parametrize(
    "snippet",
    [
        "<script>alert(1)</script>",
        "<a href='javascript:alert(1)'><img src='https://trustseal.enamad.ir/x'></a>",
        "<a href='https://trustseal.enamad.ir.evil.com/'><img src='https://trustseal.enamad.ir/x'></a>",
        "<a href='https://evil.com/'><img src='https://trustseal.enamad.ir/x'></a>",
        "<a href='http://trustseal.enamad.ir/'><img src='https://trustseal.enamad.ir/x'></a>",
        "<a href='https://trustseal.enamad.ir/'><img src='https://trustseal.enamad.ir/x'></a><script>x</script>",
        "<a href='https://trustseal.enamad.ir/'>text<img src='https://trustseal.enamad.ir/x'></a>",
        "<a href='https://trustseal.enamad.ir/'><img src='https://trustseal.enamad.ir/x'></a><!-- -->",
        "<a href='https://u:p@trustseal.enamad.ir/'><img src='https://trustseal.enamad.ir/x'></a>",
    ],
)
def test_tampered_enamad_seal_is_rejected(snippet):
    with pytest.raises(ValueError):
        clean_enamad_seal(snippet)


def test_seal_event_handlers_are_dropped():
    html = str(clean_enamad_seal(REAL_SEAL.replace("<img ", "<img onerror='alert(1)' ")))
    assert "onerror" not in html and "style" not in html


def test_csp_allows_only_the_known_inline_script(site_root, tmp_path):
    report = _build(site_root, tmp_path)
    html = (report.out / "index.html").read_text(encoding="utf-8")
    inline = re.findall(r"<script>(.*?)</script>", html, re.S)
    assert len(inline) == 1
    csp = re.search(r'http-equiv="Content-Security-Policy" content="([^"]+)"', html).group(1).replace("&#39;", "'")
    assert f"'{script_hash(inline[0])}'" in csp
    assert "'unsafe-inline'" not in csp.split("script-src")[1].split(";")[0]
    nginx = report.nginx.read_text(encoding="utf-8")
    assert f"'{script_hash(inline[0])}'" in nginx and "frame-ancestors 'none'" in nginx


def test_public_pages_have_no_inline_event_handlers(site_root, tmp_path):
    report = _build(site_root, tmp_path)
    for page in report.out.rglob("*.html"):
        assert not re.search(r"\son[a-z]+=", page.read_text(encoding="utf-8")), page
