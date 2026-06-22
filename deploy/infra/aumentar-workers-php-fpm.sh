#!/bin/bash
# Aumenta workers PHP-FPM 8.3 (cola Laravel, otros vhosts que usen FPM).
# anitaERP web usa Apache mod_php 8.3 — ver también ajustar-apache-prefork-gastronomia.sh
set -euo pipefail

POOL="/etc/php/8.3/fpm/pool.d/www.conf"

if [[ ! -f "$POOL" ]]; then
    echo "No existe $POOL"
    exit 1
fi

cp -a "$POOL" "${POOL}.bak.$(date +%Y%m%d%H%M%S)"

sed -i 's/^pm.max_children = .*/pm.max_children = 20/' "$POOL"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 5/' "$POOL"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 3/' "$POOL"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 10/' "$POOL"

echo "=== PHP-FPM 8.3 pool www (nuevo) ==="
grep -E '^pm\.' "$POOL" | head -6

php-fpm8.3 -t
systemctl reload php8.3-fpm
echo "php8.3-fpm recargado."
