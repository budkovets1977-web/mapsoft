# -*- coding: utf-8 -*-
"""Загрузчик переменных окружения из .env (рабочая папка проекта).

Использование в любом скрипте проекта:
    import env_loader
    base_url = env_loader.get("ANTHROPIC_BASE_URL")
    api_key = env_loader.get("ANTHROPIC_API_KEY")

Переменные уже установленные в системе имеют приоритет над .env
(например, через export в Git Bash или set в PowerShell).
"""

import os
from pathlib import Path

ENV_FILE = Path(__file__).resolve().parent / ".env"


def _load(dotenv_path: Path = ENV_FILE) -> dict:
    """Прочитать .env и вернуть найденные пары ключ=значение."""
    parsed = {}
    if not dotenv_path.exists():
        return parsed
    for raw_line in dotenv_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        # Пропускаем пустые строки и комментарии
        if not line or line.startswith("#"):
            continue
        if "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip("'\"")
        if key:
            parsed[key] = value
    return parsed


def install(scope=None) -> None:
    """Установить переменные из .env в os.environ (системные главнее)."""
    scope = scope if scope is not None else os.environ
    for key, value in _load().items():
        scope.setdefault(key, value)


def get(key: str, default=None):
    """Взять переменную: сначала os.environ, потом .env, потом default."""
    if key in os.environ:
        return os.environ[key]
    return _load().get(key, default)


# При простом «import env_loader» переменные сразу попадают в os.environ,
# чтобы их подхватили anthropic / openai клиенты без лишних вызовов.
install()
