"""Entry point for gunicorn: gunicorn -w 2 -b 127.0.0.1:8001 numbix_admin.wsgi:app"""

from . import create_app

app = create_app()
