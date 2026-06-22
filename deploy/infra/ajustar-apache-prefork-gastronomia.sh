#!/bin/bash
# anitaERP corre con Apache prefork + mod_php 8.3 (NO php-fpm para requests web).
# Más procesos spare = menos cola cuando varios POS facturan a la vez.
set -euo pipefail

MPM="/etc/apache2/mods-available/mpm_prefork.conf"

cp -a "$MPM" "${MPM}.bak.$(date +%Y%m%d%H%M%S)"

sed -i 's/^StartServers .*/StartServers            10/' "$MPM"
sed -i 's/^MinSpareServers .*/MinSpareServers         10/' "$MPM"
sed -i 's/^MaxSpareServers .*/MaxSpareServers         20/' "$MPM"
# MaxRequestWorkers ya está en 150 — no tocar salvo falta de RAM bajo carga extrema

echo "=== Apache prefork (nuevo) ==="
grep -E '^(StartServers|MinSpareServers|MaxSpareServers|MaxRequestWorkers)' "$MPM"

apache2ctl configtest
systemctl reload apache2
echo "apache2 recargado."
