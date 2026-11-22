#!/usr/bin/env bash
# Control post-deploy: vulnerabilidades PHP (avisos tipo DigitalOcean / PHP changelog).
# En Ubuntu/Debian el número de versión (ej. 8.3.6) NO alcanza: Canonical backportea
# en el sufijo del paquete sin subir a 8.3.37. Verificar changelog + runtime.
#
# Uso (en cada host / empresa tras deploy o aviso de seguridad):
#   ./scripts/check-php-security.sh
#   ./scripts/check-php-security.sh --strict   # exit 1 si algo crítico falla
#
# Solo lectura: no escribe ni reinicia servicios.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

STRICT=0
for arg in "$@"; do
  case "$arg" in
    --strict|-s) STRICT=1 ;;
    -h|--help)
      sed -n '2,12p' "$0"
      exit 0
      ;;
  esac
done

PASS=0
WARN=0
FAIL=0
CRITICAL_FAIL=0

ok()   { PASS=$((PASS + 1)); printf '  [OK]   %s\n' "$*"; }
warn() { WARN=$((WARN + 1)); printf '  [WARN] %s\n' "$*"; }
fail() {
  FAIL=$((FAIL + 1))
  CRITICAL_FAIL=$((CRITICAL_FAIL + 1))
  printf '  [FAIL] %s\n' "$*"
}
info() { printf '  [..]   %s\n' "$*"; }

read_env() {
  local key="$1" line
  if [[ -f .env ]]; then
    line="$(grep -E "^${key}=" .env 2>/dev/null | awk 'NR==1 {print; exit}')" || true
    printf '%s' "${line#*=}" | tr -d '"' | tr -d "'"
  fi
}

# CVEs críticos del aviso DigitalOcean nov/2026 (y lotes Ubuntu asociados).
# Actualizar esta lista cuando llegue un aviso nuevo.
REQUIRED_CVES=(
  CVE-2026-129289  # FastCGI connection reuse → RCE
  CVE-2026-115560  # session upload progress
)

EMPRESA_VAL="$(read_env EMPRESA)"
HOST_NAME="$(hostname -f 2>/dev/null || hostname)"
PHP_BIN="$(command -v php || true)"

echo "=== check-php-security ==="
echo "Host:    ${HOST_NAME}"
echo "EMPRESA: ${EMPRESA_VAL:-"(sin .env EMPRESA)"}"
echo "Fecha:   $(date '+%Y-%m-%d %H:%M:%S %z')"
echo

# --- 1) PHP presente ---
echo "1) PHP runtime"
if [[ -z "$PHP_BIN" ]]; then
  fail "php no está en PATH"
  echo
  echo "Resumen: FAIL=$FAIL WARN=$WARN OK=$PASS"
  exit 1
fi

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
PHP_SAPI="$("$PHP_BIN" -r 'echo PHP_SAPI;')"
PHP_MAJOR_MINOR="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
ok "CLI: PHP ${PHP_VERSION} (sapi=${PHP_SAPI})"

case "$PHP_MAJOR_MINOR" in
  8.2|8.3|8.4|8.5) ok "Rama soportada: ${PHP_MAJOR_MINOR}" ;;
  8.1|8.0|7.*)
    fail "PHP ${PHP_MAJOR_MINOR} ya no recibe security fixes. Planear migración."
    ;;
  *)
    warn "Rama PHP ${PHP_MAJOR_MINOR} no reconocida; verificar soporte."
    ;;
esac

if [[ "$PHP_MAJOR_MINOR" == "8.5" ]]; then
  INI_LOADED="$("$PHP_BIN" -r 'echo php_ini_loaded_file() ?: "";')"
  if [[ -z "$INI_LOADED" ]]; then
    fail "PHP 8.5 sin php.ini cargado (CVE-2026-129474 / imágenes Docker oficiales)."
  else
    ok "php.ini cargado: ${INI_LOADED}"
  fi
fi
echo

# --- 2) Paquete distro vs versión vanilla ---
echo "2) Paquete / backport de seguridad"
PKG_VERSION=""
PKG_SOURCE="desconocido"
CHANGELOG=""

if command -v dpkg-query >/dev/null 2>&1; then
  for pkg in php"${PHP_MAJOR_MINOR}"-common php"${PHP_MAJOR_MINOR/./}"-common php-common; do
    if dpkg-query -W -f='${Version}' "$pkg" >/dev/null 2>&1; then
      PKG_VERSION="$(dpkg-query -W -f='${Version}' "$pkg" 2>/dev/null || true)"
      PKG_SOURCE="dpkg:${pkg}"
      break
    fi
  done
  # Preferir el paquete de la minor exacta
  if dpkg-query -W -f='${Version}' "php${PHP_MAJOR_MINOR}-common" >/dev/null 2>&1; then
    PKG_VERSION="$(dpkg-query -W -f='${Version}' "php${PHP_MAJOR_MINOR}-common")"
    PKG_SOURCE="dpkg:php${PHP_MAJOR_MINOR}-common"
  fi
fi

if [[ -n "$PKG_VERSION" ]]; then
  ok "Paquete ${PKG_SOURCE} = ${PKG_VERSION}"
  info "No comparar solo con 8.3.37 de PHP.org: en Ubuntu el fix va en el sufijo (.14, etc.)."
else
  warn "No se detectó paquete dpkg de PHP. Si es Docker/compilación propia, verificar tag ≥ 8.2.36 / 8.3.37 / 8.4.28 / 8.5.13"
fi

# Changelog Ubuntu/Debian
for candidate in \
  "/usr/share/doc/php${PHP_MAJOR_MINOR}-common/changelog.Debian.gz" \
  "/usr/share/doc/php${PHP_MAJOR_MINOR}-cli/changelog.Debian.gz" \
  "/usr/share/doc/php-common/changelog.Debian.gz"
do
  if [[ -f "$candidate" ]]; then
    CHANGELOG="$candidate"
    break
  fi
done

MISSING_CVES=()
if [[ -n "$CHANGELOG" ]]; then
  ok "Changelog: ${CHANGELOG}"
  for cve in "${REQUIRED_CVES[@]}"; do
    # zgrep directo: evitar pipefail+SIGPIPE de `printf | grep -q` con changelog grande
    if zgrep -qF "$cve" "$CHANGELOG" 2>/dev/null; then
      ok "Backport presente: ${cve}"
    else
      MISSING_CVES+=("$cve")
      fail "Falta en changelog del paquete: ${cve} → apt update && apt upgrade 'php${PHP_MAJOR_MINOR}*'"
    fi
  done
else
  warn "Sin changelog.Debian.gz: no se pudo verificar backport de CVEs. Usar php -v / tag de imagen."
fi

# ¿Hay upgrade pendiente de seguridad?
# No usar `awk … exit` / `head` en el pipe: con pipefail apt-cache recibe SIGPIPE y el script aborta.
if command -v apt-cache >/dev/null 2>&1 && [[ -n "$PKG_VERSION" ]]; then
  CANDIDATE="$(apt-cache policy "php${PHP_MAJOR_MINOR}-common" 2>/dev/null | awk '/^[[:space:]]*Candidate:/ {print $2}')"
  CANDIDATE="$(printf '%s\n' "$CANDIDATE" | awk 'NR==1 {print; exit}')"
  if [[ -n "$CANDIDATE" && "$CANDIDATE" != "$PKG_VERSION" && "$CANDIDATE" != "(none)" ]]; then
    warn "Hay candidato más nuevo: instalado=${PKG_VERSION} candidato=${CANDIDATE}"
  elif [[ -n "$CANDIDATE" && "$CANDIDATE" == "$PKG_VERSION" ]]; then
    ok "Sin upgrade pendiente de php${PHP_MAJOR_MINOR}-common (${PKG_VERSION})"
  fi
fi
echo

# --- 3) Mitigaciones runtime ---
echo "3) Mitigaciones runtime (FPM/CLI)"

check_ini() {
  local sapi_label="$1"
  local php_cmd="$2"
  local upload
  local ignore_args
  upload="$($php_cmd -r 'echo ini_get("session.upload_progress.enabled") ? "1" : "0";' 2>/dev/null || echo "?")"
  ignore_args="$($php_cmd -r 'echo ini_get("zend.exception_ignore_args") ? "1" : "0";' 2>/dev/null || echo "?")"

  if [[ "$upload" == "0" ]]; then
    ok "${sapi_label}: session.upload_progress.enabled=Off"
  elif [[ "$upload" == "1" ]]; then
    fail "${sapi_label}: session.upload_progress.enabled=On (CVE-2026-115560). Poner Off en php.ini / pool y reiniciar."
  else
    warn "${sapi_label}: no se pudo leer session.upload_progress.enabled"
  fi

  if [[ "$ignore_args" == "1" ]]; then
    ok "${sapi_label}: zend.exception_ignore_args=On"
  elif [[ "$ignore_args" == "0" ]]; then
    warn "${sapi_label}: zend.exception_ignore_args=Off (mitigación temporal recomendada)"
  fi
}

check_ini "CLI" "$PHP_BIN"

FPM_BIN=""
for b in "php-fpm${PHP_MAJOR_MINOR}" php-fpm8.3 php-fpm8.4 php-fpm8.2 php-fpm; do
  if command -v "$b" >/dev/null 2>&1; then
    FPM_BIN="$(command -v "$b")"
    break
  fi
done

if [[ -n "$FPM_BIN" ]]; then
  FPM_I="$(mktemp)"
  if "$FPM_BIN" -i >"$FPM_I" 2>/dev/null; then
    if grep -q 'session.upload_progress.enabled => Off' "$FPM_I"; then
      ok "FPM: session.upload_progress.enabled=Off"
    elif grep -q 'session.upload_progress.enabled => On' "$FPM_I"; then
      fail "FPM: session.upload_progress.enabled=On"
    else
      warn "FPM: no se encontró session.upload_progress.enabled en php-fpm -i"
    fi
    if grep -q 'zend.exception_ignore_args => On' "$FPM_I"; then
      ok "FPM: zend.exception_ignore_args=On"
    fi
  else
    warn "FPM presente (${FPM_BIN}) pero php-fpm -i falló"
  fi
  rm -f "$FPM_I"

  POOL_DIR="/etc/php/${PHP_MAJOR_MINOR}/fpm/pool.d"
  if [[ -d "$POOL_DIR" ]] && grep -RhsE '^[[:space:]]*catch_workers_output[[:space:]]*=[[:space:]]*yes' "$POOL_DIR" >/dev/null 2>&1; then
    warn "FPM pool con catch_workers_output=yes (mitigación temporal: no)"
  elif [[ -d "$POOL_DIR" ]]; then
    ok "FPM pools: catch_workers_output no está en yes"
  fi
else
  info "php-fpm no encontrado en PATH (puede ser solo mod_php)"
fi
echo

# --- 4) Web server: reuso FastCGI ---
echo "4) Reuso de conexiones FastCGI (CVE-2026-129289)"
KEEP_ON=0
if [[ -d /etc/nginx ]]; then
  if grep -RhsE '^[[:space:]]*fastcgi_keep_conn[[:space:]]+on' /etc/nginx/ >/dev/null 2>&1; then
    fail "nginx: fastcgi_keep_conn on (riesgo si el PHP no está parcheado)"
    KEEP_ON=1
  else
    ok "nginx: fastcgi_keep_conn no está en on (o nginx no usado)"
  fi
fi
if [[ -d /etc/apache2 ]]; then
  if grep -RhsE '^[[:space:]]*enablereuse[[:space:]]*=[[:space:]]*on' /etc/apache2/ >/dev/null 2>&1; then
    fail "Apache: enablereuse=on (riesgo si el PHP no está parcheado)"
    KEEP_ON=1
  else
    ok "Apache: enablereuse no está en on"
  fi
fi
if [[ "$KEEP_ON" -eq 0 ]]; then
  info "Con PHP ya parcheado el reuso off es defensa en profundidad; el fix real es el paquete."
fi
echo

# --- 5) Servicios activos (informativo) ---
echo "5) Servicios (informativo)"
for svc in "php${PHP_MAJOR_MINOR}-fpm" nginx apache2; do
  if systemctl cat "$svc" >/dev/null 2>&1; then
    state="$(systemctl is-active "$svc" 2>/dev/null || echo unknown)"
    info "${svc}: ${state}"
  fi
done
echo

# --- Resumen ---
echo "=== Resumen ==="
echo "OK=${PASS}  WARN=${WARN}  FAIL=${FAIL}"
if [[ "$FAIL" -eq 0 ]]; then
  echo "Resultado: CUMPLE (host listo respecto al control actual)."
  echo "Referencia: docs/deploy/php-seguridad.md"
  exit 0
fi

echo "Resultado: NO CUMPLE — revisar FAIL arriba."
echo "Mitigación temporal (si no se puede actualizar ya): docs/deploy/php-seguridad.md"
if [[ "$STRICT" -eq 1 || "$CRITICAL_FAIL" -gt 0 ]]; then
  exit 1
fi
exit 1
