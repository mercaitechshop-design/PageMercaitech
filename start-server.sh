#!/bin/bash
# ============================================================
#  Mercaitech — Iniciar servidor de desarrollo local
# ============================================================
set -e

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
PORT=8080

echo ""
echo "  ███╗   ███╗███████╗██████╗  ██████╗ █████╗ ██╗████████╗███████╗ ██████╗██╗  ██╗"
echo ""
echo "  Iniciando Mercaitech en modo desarrollo..."
echo ""

# ── 1. Iniciar MariaDB ───────────────────────────────────────
echo "[1/3] Iniciando MariaDB..."
if systemctl is-active --quiet mariadb; then
  echo "  ✓ MariaDB ya está corriendo"
else
  sudo systemctl start mariadb && echo "  ✓ MariaDB iniciado" || echo "  ⚠ No se pudo iniciar MariaDB (verifica con: sudo systemctl start mariadb)"
fi

# ── 2. Crear base de datos si no existe ─────────────────────
echo "[2/3] Verificando base de datos..."
if mysql -u root -e "USE mercaitech;" 2>/dev/null; then
  echo "  ✓ Base de datos 'mercaitech' existe"
else
  echo "  Creando base de datos 'mercaitech'..."
  mysql -u root < "$PROJECT_DIR/api/database/mercaitech.sql" 2>/dev/null && \
    echo "  ✓ Base de datos creada" || \
    echo "  ⚠ No se pudo crear la BD. Ejecuta manualmente: mysql -u root < api/database/mercaitech.sql"
fi

# ── 3. Iniciar servidor PHP ──────────────────────────────────
echo "[3/3] Iniciando servidor PHP en puerto $PORT..."
echo ""
echo "  ┌─────────────────────────────────────────────┐"
echo "  │                                             │"
echo "  │   🌐  http://localhost:$PORT                 │"
echo "  │                                             │"
echo "  │   Ctrl+C para detener                       │"
echo "  │                                             │"
echo "  └─────────────────────────────────────────────┘"
echo ""

cd "$PROJECT_DIR"
php -S localhost:$PORT -t "$PROJECT_DIR" router.php

