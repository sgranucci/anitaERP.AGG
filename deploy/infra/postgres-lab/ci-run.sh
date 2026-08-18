#!/usr/bin/env bash
# Orquesta migrate + smoke (+ seed opcional) del laboratorio PostgreSQL.
# Pensado para GitHub Actions y para corridas locales del compose en deploy/infra/postgres-lab.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if docker compose version >/dev/null 2>&1; then
  DC=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose)
else
  echo "ERROR: se necesita docker compose o docker-compose." >&2
  exit 1
fi

CON_SEED="${POSTGRES_LAB_CON_SEED:-1}"
EMPRESA_LAB="${EMPRESA:-LAB_PG}"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if grep -q 'cambiar-por-clave-aleatoria\|generar-clave-aleatoria' .env 2>/dev/null; then
  PG_PASS="$(openssl rand -hex 16)"
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  sed -i.bak \
    -e "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=${PG_PASS}|" \
    -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    .env
  rm -f .env.bak
fi

if [[ ! -e app ]]; then
  echo "ERROR: falta el directorio/symlink app/ (código Laravel montado en /app)." >&2
  exit 1
fi

if [[ ! -d app/vendor ]]; then
  echo "ERROR: falta app/vendor. Correr composer install en el árbol montado antes." >&2
  exit 1
fi

echo "==> Levantando PostgreSQL (${DC[*]})"
"${DC[@]}" up -d postgres
"${DC[@]}" build migrator

echo "==> migrate --force (EMPRESA=${EMPRESA_LAB})"
"${DC[@]}" run --rm -e "EMPRESA=${EMPRESA_LAB}" migrator php artisan migrate --force

echo "==> smoke base"
"${DC[@]}" run --rm -e "EMPRESA=${EMPRESA_LAB}" migrator php scripts/postgres-lab-smoke.php

if [[ "${CON_SEED}" == "1" ]]; then
  echo "==> seed mínimo"
  "${DC[@]}" run --rm -e "EMPRESA=${EMPRESA_LAB}" migrator php scripts/postgres-lab-seed-minimo.php
  echo "==> smoke con seed"
  "${DC[@]}" run --rm -e "EMPRESA=${EMPRESA_LAB}" -e SMOKE_EXPECT_SEED=1 migrator php scripts/postgres-lab-smoke.php
  echo "==> smoke HTTP login"
  "${DC[@]}" run --rm -e "EMPRESA=${EMPRESA_LAB}" -e SMOKE_EXPECT_SEED=1 migrator php scripts/postgres-lab-smoke-http.php
fi

echo "CI lab PostgreSQL: OK"
