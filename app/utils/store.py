from __future__ import annotations

import secrets
import time
from typing import Any

_items: dict[str, tuple[float, Any]] = {}


def put(value: Any, ttl: int = 86400) -> str:
    now = time.time()
    if len(_items) > 50000:
        for k in [k for k, (exp, _) in _items.items() if exp < now]:
            _items.pop(k, None)
    key = secrets.token_urlsafe(6)
    _items[key] = (now + ttl, value)
    return key


def get(key: str) -> Any:
    hit = _items.get(key)
    if not hit or hit[0] < time.time():
        return None
    return hit[1]
