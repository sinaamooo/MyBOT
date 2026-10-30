"""Account helpers: estimated creation date from user id, DC from file_id."""
from __future__ import annotations

import base64
import bisect
import struct

# (user_id, unix time) anchors collected from public data; interpolated linearly
_ANCHORS = [
    (1_000_000, 1380326400),     # 2013-09
    (2_768_409, 1383264000),     # 2013-11
    (7_679_610, 1388448000),     # 2013-12
    (11_538_514, 1391212000),    # 2014-02
    (15_835_244, 1392940000),
    (23_646_077, 1393459000),
    (38_015_510, 1393632000),
    (44_634_663, 1399334000),
    (46_145_305, 1400198000),
    (54_845_238, 1411257000),
    (63_263_518, 1414454000),
    (101_260_938, 1425600000),   # 2015-03
    (111_220_210, 1429574000),
    (124_872_445, 1439856000),
    (133_909_606, 1444176000),
    (157_242_073, 1446768000),
    (171_295_414, 1457481000),   # 2016-03
    (181_783_990, 1460246000),
    (222_021_233, 1465344000),
    (225_034_354, 1466208000),
    (278_941_742, 1473465000),
    (285_253_072, 1476835000),
    (294_851_037, 1479600000),
    (328_594_461, 1482969000),   # 2016-12
    (337_808_429, 1487707000),
    (369_669_043, 1490918000),
    (400_169_472, 1501459000),   # 2017-07
    (616_816_630, 1529625600),   # 2018-06
    (805_158_066, 1563208000),   # 2019-07
    (1_000_000_000, 1577836800), # 2020-01
    (1_493_000_000, 1606780800), # 2020-12
    (1_974_255_900, 1634000000), # 2021-10
    (2_147_483_647, 1640995200), # 2022-01 (32-bit limit)
    (5_000_000_000, 1643673600), # 2022-02 (new id range)
    (5_500_000_000, 1661990400), # 2022-09
    (6_000_000_000, 1677628800), # 2023-03
    (6_500_000_000, 1696118400), # 2023-10
    (7_000_000_000, 1712016000), # 2024-04
    (7_500_000_000, 1727740800), # 2024-10
    (8_000_000_000, 1740787200), # 2025-03
    (8_500_000_000, 1756684800), # 2025-09
    (9_000_000_000, 1772323200), # 2026-03
]
_IDS = [a[0] for a in _ANCHORS]


def estimate_creation(user_id: int) -> int:
    if user_id <= _IDS[0]:
        return _ANCHORS[0][1]
    if user_id >= _IDS[-1]:
        (i0, t0), (i1, t1) = _ANCHORS[-2], _ANCHORS[-1]
    else:
        i = bisect.bisect_right(_IDS, user_id)
        (i0, t0), (i1, t1) = _ANCHORS[i - 1], _ANCHORS[i]
    return int(t0 + (user_id - i0) * (t1 - t0) / (i1 - i0))


DC_LOCATIONS = {1: "Miami 🇺🇸", 2: "Amsterdam 🇳🇱", 3: "Miami 🇺🇸", 4: "Amsterdam 🇳🇱", 5: "Singapore 🇸🇬"}


def _rle_decode(data: bytes) -> bytes:
    out = bytearray()
    i = 0
    while i < len(data):
        b = data[i]
        if b == 0 and i + 1 < len(data):
            out.extend(b"\x00" * data[i + 1])
            i += 2
        else:
            out.append(b)
            i += 1
    return bytes(out)


def dc_from_file_id(file_id: str) -> int | None:
    try:
        raw = base64.urlsafe_b64decode(file_id + "=" * (-len(file_id) % 4))
        data = _rle_decode(raw)
        _type, dc = struct.unpack("<ii", data[:8])
        return dc if 1 <= dc <= 5 else None
    except Exception:  # noqa: BLE001
        return None


def level_from_xp(xp: int) -> int:
    level = 1
    need = 50
    while xp >= need and level < 100:
        xp -= need
        level += 1
        need = int(need * 1.35)
    return level
