"""Runtime settings, read from environment variables (see manage.py init)."""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from numbix_site.config import load_config

SITE_ROOT = Path(__file__).resolve().parent.parent


@dataclass(frozen=True)
class Settings:
    site_root: Path
    data_dir: Path
    secret_key: bytes
    fernet_key: bytes
    webroot: Path
    url_prefix: str
    cookie_secure: bool = True

    @property
    def db_path(self) -> Path:
        return self.data_dir / "admin.sqlite3"

    @property
    def config_path(self) -> Path:
        return self.site_root / "site.toml"

    @classmethod
    def from_env(cls) -> Settings:
        """NX_SECRET_KEY and NX_FERNET_KEY are required; everything else has a default."""
        missing = [name for name in ("NX_SECRET_KEY", "NX_FERNET_KEY") if not os.environ.get(name)]
        if missing:
            raise RuntimeError(f"Missing environment variables: {', '.join(missing)} (run: python3 manage.py init)")
        site = load_config(SITE_ROOT / "site.toml")
        return cls(
            site_root=SITE_ROOT,
            data_dir=Path(os.environ.get("NX_DATA_DIR", SITE_ROOT / "instance")),
            secret_key=os.environ["NX_SECRET_KEY"].encode(),
            fernet_key=os.environ["NX_FERNET_KEY"].encode(),
            webroot=Path(os.environ.get("NX_WEBROOT", site.webroot)),
            url_prefix=site.admin_path,
            cookie_secure=os.environ.get("NX_COOKIE_SECURE", "1") != "0",
        )
