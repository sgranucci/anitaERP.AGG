#!/usr/bin/env bash
# Wrapper legacy: imprime solo la cotización venta BNA (stdout).
# Parser real: cotizacionbna.py
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
exec python3 "$DIR/cotizacionbna.py" "$@"
