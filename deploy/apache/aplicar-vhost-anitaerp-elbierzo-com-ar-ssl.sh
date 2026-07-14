#!/bin/bash
# Aplica vhost HTTPS anitaerp.elbierzo.com.ar en 192.168.59.122 (bie-svrv-sis2).
# NO emite el certificado: hacerlo antes con certbot (ver README-HTTPS-EL-BIERZO.md).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"
DOMAIN="anitaerp.elbierzo.com.ar"
EXPECTED_IP="192.168.59.122"
CERT_FULL="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
CERT_KEY="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"
CONF_SRC="${SCRIPT_DIR}/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf"
DEST="/etc/apache2/sites-available/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf"

if [[ "${HOST_IP}" != "${EXPECTED_IP}" ]]; then
	echo "Solo para ${EXPECTED_IP} (IP actual: ${HOST_IP})" >&2
	exit 1
fi

if [[ ! -f "${CERT_FULL}" || ! -f "${CERT_KEY}" ]]; then
	echo "Faltan certificados Let's Encrypt:" >&2
	echo "  ${CERT_FULL}" >&2
	echo "  ${CERT_KEY}" >&2
	echo "" >&2
	echo "Primero emitir con certbot (ver deploy/apache/README-HTTPS-EL-BIERZO.md)." >&2
	exit 1
fi

if [[ ! -f /etc/letsencrypt/options-ssl-apache.conf ]]; then
	echo "Falta /etc/letsencrypt/options-ssl-apache.conf (instalar python3-certbot-apache)." >&2
	exit 1
fi

sudo cp "${CONF_SRC}" "${DEST}"

echo "Habilitando módulos Apache..."
sudo a2enmod ssl
sudo a2enmod rewrite
sudo a2enmod headers

echo "Deshabilitando sitios viejos (si existen)..."
sudo a2dissite 000-default 2>/dev/null || true
sudo a2dissite anitaERP 2>/dev/null || true
sudo a2dissite anitaERP-ipelbierzo-ssl 2>/dev/null || true
sudo a2dissite anitaERP-ip-elbierzo-com-ar-ssl 2>/dev/null || true
sudo a2dissite anitaERP-anita-elbierzo-com-ar-ssl 2>/dev/null || true

echo "Habilitando sitio ${DOMAIN}..."
sudo a2ensite anitaERP-anitaerp-elbierzo-com-ar-ssl

sudo apache2ctl configtest
sudo systemctl reload apache2

echo ""
echo "OK: vhost HTTPS aplicado."
echo "URL: https://${DOMAIN}/"
echo ""
echo "Siguiente — editar /var/www/html/anitaERP/.env (ver README-HTTPS-EL-BIERZO.md):"
echo "  APP_ENV=production"
echo "  APP_DEBUG=false"
echo "  APP_URL=https://${DOMAIN}"
echo "  APP_CARPETA="
echo "  EMPRESA_LINK=/"
echo "  SESSION_SECURE_COOKIE=true"
echo ""
echo "  cd /var/www/html/anitaERP && php artisan config:clear && php artisan config:cache && php artisan route:clear"
echo ""
echo "Probar:"
echo "  curl -sI http://ip.elbierzo.com.ar:12280/anitaERP/public/"
echo "  curl -sI https://${DOMAIN}/"
apache2ctl -S 2>/dev/null | grep -E "${DOMAIN}|443|12280" || true
