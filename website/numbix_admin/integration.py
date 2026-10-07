"""Read-only access for the Telegram bot to each product's provider API settings.

    from numbix_admin.integration import product_api
    config = product_api(product_id)   # None when the product has no active API

The provider call itself is added once the provider's API documentation is available.
"""

from __future__ import annotations

import json

from . import db
from .crypto import Vault
from .settings import Settings


def product_api(product_id: int, settings: Settings | None = None) -> dict | None:
    settings = settings or Settings.from_env()
    conn = db.connect(settings.db_path)
    try:
        row = conn.execute(
            "SELECT id, name, api_provider, api_base_url, api_key, api_service, api_params, api_enabled"
            " FROM products WHERE id = ?",
            (product_id,),
        ).fetchone()
    finally:
        conn.close()
    if row is None or not row["api_enabled"] or not row["api_base_url"]:
        return None
    return {
        "product_id": row["id"],
        "name": row["name"],
        "provider": row["api_provider"],
        "base_url": row["api_base_url"],
        "api_key": Vault(settings.fernet_key).open(row["api_key"]),
        "service": row["api_service"],
        "params": json.loads(row["api_params"] or "{}"),
    }
