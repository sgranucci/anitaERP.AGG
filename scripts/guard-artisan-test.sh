#!/usr/bin/env bash
# Bloquea tests contra la BD operativa sin autorización explícita del operador.
# Uso (tras confirmación en chat):
#   ALLOW_TEST_EXECUTION=1 ./scripts/guard-artisan-test.sh --filter=MiTest

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

die() {
  echo "guard-artisan-test: $*" >&2
  exit 1
}

read_env() {
  local key="$1"
  if [[ -f .env ]]; then
    grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
  fi
}

APP_ENV_VAL="${APP_ENV:-$(read_env APP_ENV)}"
DB_ACTUAL="${DB_DATABASE:-$(read_env DB_DATABASE)}"
DB_PROTEGIDA="${DB_DATABASE_PROTEGIDA:-$(read_env DB_DATABASE_PROTEGIDA)}"

if [[ ! -f phpunit.xml ]]; then
  die "No existe phpunit.xml en la raíz del proyecto."
fi

if ! grep -q 'name="DB_CONNECTION" value="sqlite"' phpunit.xml; then
  die "phpunit.xml debe usar DB_CONNECTION=sqlite. Abortado."
fi

if ! grep -q 'name="DB_DATABASE" value=":memory:"' phpunit.xml; then
  die "phpunit.xml debe usar DB_DATABASE=:memory:. Abortado."
fi

if rg -n '^\s*use\s+.*RefreshDatabase|^\s*use\s+.*DatabaseMigrations' tests/ -g '*Test.php' -q 2>/dev/null; then
  die "Hay tests con RefreshDatabase o DatabaseMigrations. Revisar antes de ejecutar."
fi

if [[ "${ALLOW_TEST_EXECUTION:-}" != "1" ]]; then
  die "Tests bloqueados. El operador debe autorizar en el chat y luego ejecutar: ALLOW_TEST_EXECUTION=1 $0 $*"
fi

if [[ "${APP_ENV_VAL}" == "production" ]]; then
  echo "guard-artisan-test: aviso — APP_ENV=production; PHPUnit usará SQLite :memory: según phpunit.xml." >&2
fi

if [[ -n "${DB_PROTEGIDA}" && "${DB_ACTUAL}" == "${DB_PROTEGIDA}" ]]; then
  echo "guard-artisan-test: BD operativa protegida=${DB_PROTEGIDA}; PHPUnit aislado en SQLite." >&2
fi

exec php artisan test "$@"
