"""Rebuild the public site from the products table and put it live."""

from __future__ import annotations

import os
import shutil
import tempfile
from pathlib import Path

from numbix_site import build_site

from . import products
from .settings import Settings

_HASHED = ("app.*.css", "app.*.js", "icons.*.svg")  # bundles that a new build replaces


class PublishError(Exception):
    pass


def _copy(src: Path, dst: Path) -> None:
    """Write next to the target, then rename: visitors never see a half-written file."""
    dst.parent.mkdir(parents=True, exist_ok=True)
    tmp = dst.with_name(f".{dst.name}.tmp")
    shutil.copyfile(src, tmp)
    tmp.chmod(0o644)
    os.replace(tmp, dst)


def _sync(build: Path, webroot: Path) -> int:
    files = sorted(p for p in build.rglob("*") if p.is_file())
    # Assets first, pages last, so a fresh page never points at a missing bundle.
    files.sort(key=lambda p: p.suffix == ".html")
    for file in files:
        _copy(file, webroot / file.relative_to(build))
    fresh = {p.name for p in (build / "assets").iterdir()}
    for pattern in _HASHED:
        for old in (webroot / "assets").glob(pattern):
            if old.name not in fresh:
                old.unlink()
    return len(files)


def publish(settings: Settings, db) -> int:
    """Build into a private temp folder, refuse incomplete data, then sync into the webroot."""
    rows = products.all_products(db)
    with tempfile.TemporaryDirectory(dir=settings.data_dir) as tmp:
        out = Path(tmp) / "site"
        report = build_site(settings.site_root, settings.config_path, out, products.for_site(rows))
        if report.missing:
            raise PublishError("این موارد هنوز خالی‌اند: " + "، ".join(report.missing))
        settings.webroot.mkdir(parents=True, exist_ok=True)
        return _sync(out, settings.webroot)
