import asyncio
import json
import os
import tempfile

import pytest

from app import db as dbmod
from app.services import banking, instagram, phone, tgju, txs, wallets
from app.services.chain_common import b58check_valid, classify


@pytest.fixture(scope="module", autouse=True)
def database():
    loop = asyncio.new_event_loop()
    loop.run_until_complete(dbmod.init_db(os.path.join(tempfile.mkdtemp(), "x.db")))
    yield
    loop.run_until_complete(dbmod.get_db().close())
    loop.close()


def run(coro):
    return asyncio.new_event_loop().run_until_complete(coro)


def test_card_helpers():
    assert banking.clean_card("6037-9975-9950-3209") == "6037997599503209"
    assert banking.clean_card("۶۰۳۷ ۹۹۷۵ ۹۹۵۰ ۳۲۰۹") == "6037997599503209"
    assert banking.clean_card("1234") is None
    assert banking.luhn_ok("6037997599503209") is True
    assert banking.luhn_ok("6037997599503201") is False
    assert banking.bank_of_card("6037997599503209") == "ملی"
    assert banking.bank_of_card("6104337712345678") == "ملت"
    assert banking.format_card("6037997599503209") == "6037-9975-9950-3209"


def test_iban_helpers():
    iban = banking.clean_iban("IR06 0170 0000 0012 3456 7890 01")
    assert iban == "IR060170000000123456789001"
    assert banking.bank_of_iban(iban) == "ملی"
    assert banking.iban_ok("IR820540102680020817909002")
    assert not banking.iban_ok("IR820540102680020817909003")


def test_cardinfo_api_parsing(monkeypatch):
    async def fake_get_json(url, **kw):
        assert kw["headers"]["Authorization"] == "Bearer codesazan"
        assert kw["params"] == {"number": "6037997599503209"}
        return {"status": 200, "result": {"name": "علی رضایی", "bank": "بانک ملی", "iban": "IR06017...", "type": "debit"}}

    monkeypatch.setattr(banking, "get_json", fake_get_json)
    info = run(banking.lookup_card("6037997599503209"))
    assert info.owner == "علی رضایی"
    assert info.bank == "ملی"
    assert info.iban == "IR06017..."
    assert info.extra == {"type": "debit"}
    assert info.valid


def test_phone_helpers(monkeypatch):
    assert phone.clean_phone("+1 500 555 0006") == "+15005550006"
    assert phone.clean_phone("09121234567") == "+989121234567"
    assert phone.clean_phone("hello") is None

    async def fake(url, **kw):
        return {"status": "⚪️"}

    monkeypatch.setattr(phone, "get_json", fake)
    assert run(phone.check_phone("+15005550006")) == ("⚪", phone.STATUS["⚪"])


TGJU_HTML = """
<table><tbody>
<tr id="f-diff_usd"><th>USD دلار</th><td>1,050,000</td><td>1,000</td><td>0.1%</td><td>1,040,000</td><td>1,060,000</td><td>۱۴:۲۰</td></tr>
<tr id="f-diff_aed"><th><span>AED</span> درهم</th><td>285,900</td><td>100</td><td>0.03%</td><td>1</td><td>2</td><td>x</td></tr>
<tr id="f-diff_try"><th>لیر ترکیه</th><td>24,800</td><td>0</td><td>0%</td><td>1</td><td>2</td><td>x</td></tr>
<tr id="other"><th>XYZ</th><td>1</td></tr>
</tbody></table>
"""


def test_tgju_parse_and_sanity(monkeypatch):
    rates = tgju.parse(TGJU_HTML)
    assert rates["USD"]["toman"] == 105000
    assert rates["AED"]["toman"] == 28590
    assert rates["TRY"]["toman"] == 2480
    assert "XYZ" not in rates

    async def fake_rates():
        return rates

    monkeypatch.setattr(tgju, "market_rates", fake_rates)
    assert run(tgju.toman_rate("AED", 28000)) == 28590
    assert run(tgju.toman_rate("AED", 2800)) == 2859
    assert run(tgju.toman_rate("AED", 100)) is None


def test_tron_tx_trc20(monkeypatch):
    sample = {
        "hash": "a" * 64, "contractRet": "SUCCESS", "confirmed": True, "block": 1, "confirmations": 20,
        "timestamp": 1700000000000,
        "contractData": {"owner_address": "TXaaa", "contract_address": "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"},
        "trc20TransferInfo": [{"amount_str": "1500000", "decimals": 6, "symbol": "USDT", "name": "Tether USD",
                               "from_address": "TXaaa", "to_address": "TYbbb",
                               "contract_address": "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"}],
        "cost": {"fee": 0, "energy_usage_total": 64285},
    }

    async def fake(url, **kw):
        return sample

    monkeypatch.setattr(txs, "get_json", fake)
    r = run(txs.check_tron_tx("a" * 64))
    assert r.amount == 1.5 and r.symbol == "USDT" and r.token_type == "TRC20"
    assert r.contract == "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t" and r.receiver == "TYbbb"


def test_tron_tx_trc10_and_trx(monkeypatch):
    trc10 = {
        "hash": "b" * 64, "contractRet": "SUCCESS", "confirmed": True, "timestamp": 1700000000000,
        "contractData": {"owner_address": "TXa", "to_address": "TYb", "asset_name": "1002000", "amount": 2500000,
                         "tokenInfo": {"tokenId": "1002000", "tokenAbbr": "BTT", "tokenName": "BitTorrent",
                                       "tokenDecimal": 6}},
    }
    trx = {"hash": "c" * 64, "contractRet": "SUCCESS", "confirmed": True, "timestamp": 1700000000000,
           "contractData": {"owner_address": "TXa", "to_address": "TYb", "amount": 12000000}}
    payload = {"value": trc10}

    async def fake(url, **kw):
        return payload["value"]

    monkeypatch.setattr(txs, "get_json", fake)
    r = run(txs.check_tron_tx("b" * 64))
    assert r.token_type == "TRC10" and r.amount == 2.5 and r.symbol == "BTT" and r.contract == "1002000"
    payload["value"] = trx
    r = run(txs.check_tron_tx("c" * 64))
    assert r.token_type == "COIN" and r.amount == 12 and r.symbol == "TRX"


def test_tron_tokens_and_transfers(monkeypatch):
    async def fake(url, **kw):
        if "account/tokens" in url:
            return {"total": 2, "data": [
                {"tokenId": "_", "tokenType": "trc10", "quantity": 15.5, "tokenName": "trx", "tokenAbbr": "trx"},
                {"tokenId": "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", "tokenType": "trc20", "quantity": 99.5,
                 "tokenName": "Tether USD", "tokenAbbr": "USDT", "amountInUsd": 99.5},
            ]}
        return {"total": 23, "token_transfers": [
            {"quant": "5000000", "tokenInfo": {"tokenDecimal": 6, "tokenAbbr": "USDT"}, "from_address": "TA",
             "to_address": "TB", "block_ts": 1700000000000, "transaction_id": "d" * 64}]}

    monkeypatch.setattr(wallets, "get_json", fake)
    res = run(wallets.tron_all_tokens("TB"))
    assert res["total"] == 2
    assert res["token"][0]["TokenType"] == "COIN"
    assert res["token"][1]["Contract_address"] == "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"
    json.dumps(res)
    page = run(wallets.tron_trc20_transfers("TB", 2))
    assert page["pages"] == 3 and page["items"][0]["amount"] == 5 and page["page"] == 2


def test_tron_checksum():
    assert b58check_valid("TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t")
    assert not b58check_valid("TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u")
    assert classify("TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u") is None


def test_instagram_parsing(monkeypatch):
    import dataclasses

    monkeypatch.setattr(instagram, "config", dataclasses.replace(instagram.config, instagram_api_key="K"))
    calls = []

    async def fake(url, params=None, **kw):
        calls.append(params)
        t = params["type"]
        if t == "postdownloader":
            return {"status": 200, "result": {"medias": [{"url": "https://x/1.jpg", "extension": "jpg"},
                                                         {"url": "https://x/2.mp4", "extension": "mp4"}]}}
        if t == "instainfo":
            return {"status": 200, "result": [{"Id": 1, "username": "nasa", "is_private": "❌"}]}
        if t == "pagestory":
            return {"status": 452}
        return {"status": 200, "result": [{"Title": "Trip", "hightlightIDS": "17"}]}

    monkeypatch.setattr(instagram, "get_json", fake)
    assert instagram.find_link("look https://www.instagram.com/reel/Cx12_ab/?igsh=1") == "https://www.instagram.com/reel/Cx12_ab/?igsh=1"
    assert instagram.find_link("hello") is None
    medias = run(instagram.post_medias("https://instagram.com/p/x"))
    assert [m["kind"] for m in medias] == ["photo", "video"]
    assert calls[0]["key"] == "K"
    assert run(instagram.profile("nasa"))["username"] == "nasa"
    with pytest.raises(instagram.InstaError) as e:
        run(instagram.stories("nasa"))
    assert e.value.code == 452
    assert run(instagram.highlights("nasa")) == [{"title": "Trip", "id": "17"}]
