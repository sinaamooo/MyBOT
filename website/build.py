#!/usr/bin/env python3
"""Build the Numbix website into dist/.

python3 build.py            # build
python3 build.py --serve    # build, then preview at http://127.0.0.1:8000
python3 build.py --strict   # fail while any business detail is still empty
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from numbix_site import build_site, serve

ROOT = Path(__file__).resolve().parent


def main() -> int:
    parser = argparse.ArgumentParser(description="Build the Numbix static website.")
    parser.add_argument("--config", type=Path, default=ROOT / "site.toml")
    parser.add_argument("--out", type=Path, default=ROOT / "dist")
    parser.add_argument("--strict", action="store_true", help="exit with an error if fields are empty")
    parser.add_argument("--serve", action="store_true", help="preview the build locally")
    parser.add_argument("--port", type=int, default=8000)
    args = parser.parse_args()

    report = build_site(ROOT, args.config.resolve(), args.out.resolve())
    size = sum(f.stat().st_size for f in report.out.rglob("*") if f.is_file())
    print(f"ساخته شد: {report.out}  ({len(report.files)} فایل اصلی، {size / 1024:.0f} KB)")

    if report.missing:
        print("این موارد در site.toml هنوز خالی‌اند:", "، ".join(report.missing))
        if args.strict:
            return 1
    else:
        print("همه‌ی اطلاعات پر شده است؛ محتوای پوشه‌ی dist آماده‌ی آپلود است.")

    if args.serve:
        serve(report.out, args.port)
    return 0


if __name__ == "__main__":
    sys.exit(main())
