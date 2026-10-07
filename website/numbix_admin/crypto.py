"""Encryption at rest for secrets (provider API keys, the TOTP seed)."""

from __future__ import annotations

from cryptography.fernet import Fernet, InvalidToken


class Vault:
    def __init__(self, key: bytes) -> None:
        self._fernet = Fernet(key)

    def seal(self, plain: str) -> str:
        return self._fernet.encrypt(plain.encode()).decode() if plain else ""

    def open(self, token: str) -> str:
        if not token:
            return ""
        try:
            return self._fernet.decrypt(token.encode()).decode()
        except InvalidToken as error:
            raise RuntimeError("Stored secret cannot be decrypted: NX_FERNET_KEY changed?") from error


def new_key() -> str:
    return Fernet.generate_key().decode()
