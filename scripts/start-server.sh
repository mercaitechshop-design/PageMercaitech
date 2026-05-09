#!/bin/bash
# Mercaitech — Servidor de desarrollo PHP
# Uso: bash scripts/start-server.sh  (desde la raíz del proyecto)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PORT=8080

cd "$PROJECT_ROOT"

# Matar cualquier servidor PHP anterior en el mismo puerto
OLD_PID=$(lsof -ti :$PORT 2>/dev/null)
if [ -n "$OLD_PID" ]; then
  echo "Deteniendo servidor anterior (PID $OLD_PID)..."
  kill "$OLD_PID" 2>/dev/null
  sleep 1
fi

echo "================================"
echo "  Mercaitech Dev Server"
echo "  http://localhost:$PORT"
echo "  Ctrl+C para detener"
echo "================================"

php -S localhost:$PORT routes/router.php
