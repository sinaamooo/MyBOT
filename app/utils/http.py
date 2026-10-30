from __future__ import annotations

import asyncio
import functools
import json
import logging
import time
from typing import Any, Awaitable, Callable

import aiohttp

from ..config import config

log = logging.getLogger(__name__)

UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0 Safari/537.36"
)

_session: aiohttp.ClientSession | None = None


async def session() -> aiohttp.ClientSession:
    global _session
    if _session is None or _session.closed:
        connector: aiohttp.BaseConnector = aiohttp.TCPConnector(limit=100, ttl_dns_cache=300)
        if config.proxy.startswith("socks"):
            from aiohttp_socks import ProxyConnector

            connector = ProxyConnector.from_url(config.proxy, limit=100)
        _session = aiohttp.ClientSession(
            timeout=aiohttp.ClientTimeout(total=15, connect=8),
            headers={"User-Agent": UA, "Accept": "application/json, text/plain, */*"},
            connector=connector,
            trust_env=True,
        )
    return _session


async def close() -> None:
    if _session and not _session.closed:
        await _session.close()


class HTTPError(Exception):
    pass


async def request(
    method: str,
    url: str,
    *,
    params: dict | None = None,
    json_body: Any = None,
    data: Any = None,
    headers: dict | None = None,
    timeout: float = 12,
    as_text: bool = False,
) -> Any:
    s = await session()
    kwargs: dict[str, Any] = {
        "params": params,
        "headers": headers,
        "timeout": aiohttp.ClientTimeout(total=timeout),
    }
    if json_body is not None:
        kwargs["json"] = json_body
    if data is not None:
        kwargs["data"] = data
    if config.proxy and config.proxy.startswith("http"):
        kwargs["proxy"] = config.proxy
    async with s.request(method, url, **kwargs) as resp:
        body = await resp.text()
        if resp.status >= 400:
            raise HTTPError(f"{resp.status} {url}: {body[:200]}")
        if as_text:
            return body
        try:
            return json.loads(body)
        except json.JSONDecodeError as exc:
            raise HTTPError(f"invalid JSON from {url}: {body[:120]}") from exc


async def get_json(url: str, **kw) -> Any:
    return await request("GET", url, **kw)


async def post_json(url: str, body: Any = None, **kw) -> Any:
    return await request("POST", url, json_body=body, **kw)


async def get_text(url: str, **kw) -> str:
    return await request("GET", url, as_text=True, **kw)


async def get_bytes(url: str, timeout: float = 15, headers: dict | None = None) -> bytes:
    s = await session()
    kwargs: dict[str, Any] = {"timeout": aiohttp.ClientTimeout(total=timeout), "headers": headers}
    if config.proxy and config.proxy.startswith("http"):
        kwargs["proxy"] = config.proxy
    async with s.get(url, **kwargs) as resp:
        if resp.status >= 400:
            raise HTTPError(f"{resp.status} {url}")
        return await resp.read()


async def safe(coro: Awaitable, default: Any = None, label: str = "") -> Any:
    try:
        return await coro
    except Exception as exc:
        log.debug("request failed %s: %s", label, exc)
        return default


async def first_ok(*coros: Awaitable, default: Any = None) -> Any:
    for coro in coros:
        result = await safe(coro)
        if result is not None:
            return result
    return default


def ttl_cache(seconds: float, maxsize: int = 2048):

    def deco(fn: Callable):
        store: dict[Any, tuple[float, Any]] = {}
        locks: dict[Any, asyncio.Lock] = {}

        @functools.wraps(fn)
        async def wrapper(*args, **kwargs):
            key = (args, tuple(sorted(kwargs.items())))
            now = time.monotonic()
            hit = store.get(key)
            if hit and now - hit[0] < seconds:
                return hit[1]
            lock = locks.setdefault(key, asyncio.Lock())
            async with lock:
                hit = store.get(key)
                if hit and time.monotonic() - hit[0] < seconds:
                    return hit[1]
                value = await fn(*args, **kwargs)
                if len(store) >= maxsize:
                    for k in list(store)[: maxsize // 4]:
                        store.pop(k, None)
                        locks.pop(k, None)
                store[key] = (time.monotonic(), value)
                return value

        wrapper.cache_clear = store.clear
        return wrapper

    return deco
