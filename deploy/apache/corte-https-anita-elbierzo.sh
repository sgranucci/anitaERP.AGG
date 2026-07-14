#!/bin/bash
# Wrapper obsoleto: el dominio canónico pasó a anitaerp.elbierzo.com.ar
echo "AVISO: usar corte-https-anitaerp-elbierzo.sh (anitaerp.elbierzo.com.ar)" >&2
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/corte-https-anitaerp-elbierzo.sh" "$@"
