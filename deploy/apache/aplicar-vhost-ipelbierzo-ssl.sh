#!/bin/bash
# HTTPS ipelbierzo — 192.168.59.122 (bie-svrv-sis2).
# Requiere certificado CA interna antes de ejecutar (ver mensajes de error).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"
DOMAIN="${ANITAERP_DOMAIN:-ipelbierzo}"
CERT_DIR="${ANITAERP_SSL_DIR:-/etc/ssl/anitaERP}"
CERT_FULL="${CERT_DIR}/${DOMAIN}-fullchain.pem"
CERT_KEY="${CERT_DIR}/${DOMAIN}-privkey.pem"
EXPECTED_IP="192.168.59.122"

if [[ "${HOST_IP}" != "${EXPECTED_IP}" ]]; then
	echo "aplicar-vhost-ipelbierzo-ssl: solo para ${EXPECTED_IP} (IP actual: ${HOST_IP})" >&2
	exit 1
fi

if [[ ! -f "${CERT_FULL}" || ! -f "${CERT_KEY}" ]]; then
	echo "Faltan certificados:" >&2
	echo "  ${CERT_FULL}" >&2
	echo "  ${CERT_KEY}" >&2
	echo "" >&2
	echo "Emitir certificado CA interna para CN=${DOMAIN} con SAN DNS:${DOMAIN},IP:${EXPECTED_IP}" >&2
	echo "y copiar fullchain + privkey con esos nombres, o exportar:" >&2
	echo "  ANITAERP_DOMAIN=mi-host ANITAERP_SSL_DIR=/ruta/certs $0" >&2
	exit 1
fi

DEST="/etc/apache2/sites-available/anitaERP-ipelbierzo-ssl.conf"
CONF_SRC="${SCRIPT_DIR}/anitaERP-ipelbierzo-ssl.conf"

# Sustituir dominio si difiere del default ipelbierzo
sed "s/ipelbierzo/${DOMAIN}/g" "${CONF_SRC}" | sudo tee "${DEST}" >/dev/null

sudo a2enmod ssl rewrite headers 2>/dev/null || true
sudo a2dissite 000-default anitaERP 2>/dev/null || true
sudo a2ensite anitaERP-ipelbierzo-ssl
sudo apache2ctl configtest
sudo systemctl reload apache2

echo ""
echo "OK: HTTPS activo en https://${DOMAIN}/"
echo "Cert: ${CERT_FULL}"
echo ""
echo "Siguiente — editar /var/www/html/anitaERP/.env:"
echo "  APP_URL=https://${DOMAIN}"
echo "  APP_CARPETA="
echo "  EMPRESA_LINK=/"
echo "  SESSION_SECURE_COOKIE=true"
echo ""
echo "  cd /var/www/html/anitaERP && php artisan config:clear && php artisan route:clear"
echo ""
echo "Probar:"
echo "  curl -sI http://${DOMAIN}:12280/anitaERP/public/ventas/cliente"
echo "  curl -sIk https://${DOMAIN}/"
echo ""
apache2ctl -S 2>/dev/null | grep -E "${DOMAIN}|443|12280" || true
