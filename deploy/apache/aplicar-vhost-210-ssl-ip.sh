#!/bin/bash
# Suma HTTPS :443 en 10.20.30.210 sin tocar HTTP ni el .env.
#
#   sudo ./deploy/apache/aplicar-vhost-210-ssl-ip.sh
#
# No deshabilita 000-default ni anitaERP.
# No redirige HTTP→HTTPS.
# No cambia APP_URL / APP_CARPETA.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"
CONF_SRC="${SCRIPT_DIR}/anitaERP-prod-210-ssl-ip.conf"
DEST="/etc/apache2/sites-available/anitaERP-prod-210-ssl-ip.conf"
SITE_NAME="anitaERP-prod-210-ssl-ip"
CERT_DIR="/etc/ssl/anitaERP/ip-210"

if [[ "${HOST_IP}" != "10.20.30.210" ]]; then
	echo "aplicar-vhost-210-ssl-ip: solo para 10.20.30.210 (IP actual: ${HOST_IP})" >&2
	exit 1
fi

if [[ ! -f "${CONF_SRC}" ]]; then
	echo "Falta ${CONF_SRC}" >&2
	exit 1
fi

echo "==> Certificado autofirmado"
"${SCRIPT_DIR}/generar-cert-ip-210.sh"

if [[ ! -f "${CERT_DIR}/cert.pem" || ! -f "${CERT_DIR}/privkey.pem" ]]; then
	echo "No quedó certificado en ${CERT_DIR}" >&2
	exit 1
fi

echo ""
echo "==> Vhost SSL (solo :443)"
sudo cp "${CONF_SRC}" "${DEST}"
sudo a2enmod ssl headers 2>/dev/null || true
sudo a2ensite "${SITE_NAME}"
sudo apache2ctl configtest
sudo systemctl reload apache2

echo ""
echo "OK: HTTPS de prueba activo. HTTP no se tocó."
echo "    https://10.20.30.210/anitaERP/public/stock/transferencia-mercaderia"
echo "    El teléfono va a pedir aceptar el certificado."
echo ""
echo "Quitar: sudo ./deploy/apache/quitar-vhost-210-ssl-ip.sh"
echo ""
apache2ctl -S 2>/dev/null | grep -E '443|anitaERP-prod-210-ssl-ip|10.20.30.210' || true
