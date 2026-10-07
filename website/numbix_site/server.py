"""Local preview server that behaves like the production host (404 page included)."""

from __future__ import annotations

from contextlib import suppress
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


class _Handler(SimpleHTTPRequestHandler):
    def send_error(self, code, message=None, explain=None):
        page = Path(self.directory, "404.html")
        if code != 404 or not page.exists():
            return super().send_error(code, message, explain)
        body = page.read_bytes()
        self.send_response(404)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(body)


def serve(directory: Path, port: int) -> None:
    handler = partial(_Handler, directory=str(directory))
    with ThreadingHTTPServer(("127.0.0.1", port), handler) as httpd:
        print(f"پیش‌نمایش: http://127.0.0.1:{port}/  (برای توقف Ctrl+C)")
        with suppress(KeyboardInterrupt):
            httpd.serve_forever()
