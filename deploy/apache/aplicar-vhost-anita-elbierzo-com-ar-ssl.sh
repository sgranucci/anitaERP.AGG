#!/bin/bash
# Wrapper obsoleto: el dominio canónico pasó a anitaerp.elbierzo.com.ar
echo "AVISO: usar aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh (anitaerp.elbierzo.com.ar)" >&2
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh" "$@"
