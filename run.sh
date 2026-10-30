#!/bin/bash
cd "$(dirname "$0")" || exit 1
if [ -f bot.pid ] && kill -0 "$(cat bot.pid)" 2>/dev/null; then
  exit 0
fi
PY="$PYTHON"
if [ -z "$PY" ]; then
  PY="$(ls -d "$HOME"/virtualenv/"$(basename "$PWD")"/*/bin/python 2>/dev/null | sort -V | tail -1)"
fi
if [ -z "$PY" ]; then
  PY="$(command -v python3)"
fi
nohup "$PY" main.py >> bot.log 2>&1 &
echo $! > bot.pid
echo "started pid $(cat bot.pid) with $PY"
