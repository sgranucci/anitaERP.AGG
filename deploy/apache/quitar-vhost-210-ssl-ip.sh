#!/bin/bash
# Saca el HTTPS de prueba por IP. HTTP queda como estaba.
# No borra el certificado en /etc/ssl/anitaERP/ip-210/.
set -euo pipefail

HOST_IP="$(hostname -I | awk '{print $1}')"
SITE_NAME="anitaERP-prod-210-ssl-ip"

if [[ "${HOST_IP}" != "10.20.30.210" ]]; then
	echo "quitar-vhost-210-ssl-ip: solo para 10.20.30.210 (IP actual: ${HOST_IP})" >&2
	exit 1
fi

sudo a2dissite "${SITE_NAME}" 2>/dev/null || true
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "OK: sitio ${SITE_NAME} deshabilitado. HTTP intacto."
echo "El certificado quedó en /etc/ssl/anitaERP/ip-210/ (borrar a mano si ya no hace falta)."
