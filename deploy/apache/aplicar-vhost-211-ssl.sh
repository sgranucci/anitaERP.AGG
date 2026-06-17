#!/bin/bash
# HTTPS test en 10.20.30.211 — requiere certificado y DNS previos (ver README-HTTPS.md).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"
DOMAIN="${ANITAERP_TEST_DOMAIN:-anitaerp-test.agg.com}"
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"

if [[ "${HOST_IP}" != "10.20.30.211" ]]; then
	echo "aplicar-vhost-211-ssl: solo para 10.20.30.211 (IP actual: ${HOST_IP})" >&2
	exit 1
fi

if [[ ! -f "${CERT_DIR}/fullchain.pem" || ! -f "${CERT_DIR}/privkey.pem" ]]; then
	echo "Falta certificado en ${CERT_DIR}" >&2
	echo "Obtener primero (ver deploy/apache/README-HTTPS.md):" >&2
	echo "  sudo certbot certonly --manual --preferred-challenges dns -d ${DOMAIN}" >&2
	exit 1
fi

DEST="/etc/apache2/sites-available/anitaERP-test-ssl.conf"
CONF_SRC="${SCRIPT_DIR}/anitaERP-test-211-ssl.conf"

# Sustituir dominio si se pasó otro
sed "s/anitaerp-test\.agg\.com/${DOMAIN}/g" "${CONF_SRC}" | sudo tee "${DEST}" >/dev/null

sudo a2enmod ssl rewrite headers 2>/dev/null || true
sudo a2dissite 000-default anitaERP anitaERP-test 2>/dev/null || true
sudo a2ensite anitaERP-test-ssl
sudo apache2ctl configtest
sudo systemctl reload apache2

echo ""
echo "OK: HTTPS activo en https://${DOMAIN}/"
echo "Cert: ${CERT_DIR}"
echo ""
echo "Siguiente: en /var/www/html/anitaERP/.env"
echo "  APP_URL=https://${DOMAIN}"
echo "  APP_CARPETA="
echo "  EMPRESA_LINK=/"
echo "  php artisan config:clear"
echo ""
apache2ctl -S 2>/dev/null | grep -E "${DOMAIN}|443" || true
