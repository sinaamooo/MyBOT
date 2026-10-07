"""Static site generator for the Numbix website."""

from .render import build_site
from .server import serve

__all__ = ["build_site", "serve"]
