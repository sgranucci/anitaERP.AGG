#!/bin/bash
# Corte HTTPS El Bierzo — anitaerp.elbierzo.com.ar
# Correr en el servidor con sudo interactivo disponible:
#   cd /var/www/html/anitaERP && ./deploy/apache/corte-https-anitaerp-elbierzo.sh
#
# Pasos:
#   1) Verifica NAT / DNS
#   2) Prepara vhost HTTP (ServerName) para ACME
#   3) Emite certificado Let's Encrypt (HTTP-01)
#   4) Aplica anitaERP-anitaerp-elbierzo-com-ar-ssl.conf
#   5) Recuerda cambios .env (no los aplica solo)
set -euo pipefail

DOMAIN="anitaerp.elbierzo.com.ar"
LEGACY_HOST="ip.elbierzo.com.ar"
PREVIOUS_HOST="anita.elbierzo.com.ar"
EXPECTED_IP="192.168.59.122"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APACHE_DIR="${REPO_ROOT}/deploy/apache"
LIVE_HTTP="/etc/apache2/sites-available/anitaERP.conf"
CERT_FULL="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"

HOST_IP="$(hostname -I | awk '{print $1}')"
if [[ "${HOST_IP}" != "${EXPECTED_IP}" ]]; then
	echo "Solo para ${EXPECTED_IP} (IP actual: ${HOST_IP})" >&2
	exit 1
fi

echo "==> 1) Preflight DNS / NAT"
echo "    DNS ${DOMAIN}: $(getent hosts "${DOMAIN}" | awk '{print $1}' || true)"
echo "    DNS ${LEGACY_HOST}: $(getent hosts "${LEGACY_HOST}" | awk '{print $1}' || true)"
echo "    Probando puerto 80 público (Let's Encrypt lo necesita)..."
if curl -sI -m 8 "http://${DOMAIN}/" >/tmp/anitaerp-http80.hdr 2>/tmp/anitaerp-http80.err; then
	echo "    OK: puerto 80 responde"
	head -3 /tmp/anitaerp-http80.hdr | sed 's/^/      /'
else
	echo "    FALLO: puerto 80 no alcanzable desde Internet para ${DOMAIN}." >&2
	echo "    Abrí NAT/firewall 80/tcp y 443/tcp → ${EXPECTED_IP} y reintentá." >&2
	echo "    DNS público de ${DOMAIN} debe apuntar a la IP pública del router." >&2
	echo "    (Hoy suele estar solo 12280; sin 80 no hay HTTP-01.)" >&2
	exit 1
fi

echo "    Probando puerto 12280 (bookmarks legacy)..."
curl -sI -m 8 "http://${LEGACY_HOST}:12280/anitaERP/public/" | head -5 | sed 's/^/      /' || true

echo ""
echo "==> 2) Módulos Apache"
sudo a2enmod ssl rewrite headers

echo ""
echo "==> 3) ServerName HTTP para ACME (backup + patch)"
if [[ -f "${LIVE_HTTP}" ]]; then
	TS="$(date +%y%m%d%H%M)"
	sudo cp -a "${LIVE_HTTP}" "${LIVE_HTTP}.${TS}.pre-https"
fi

# Vhost mínimo :80/:12280 con DocumentRoot public y ServerName correcto (sin redirect HTTPS
# todavía, para que certbot pueda validar .well-known).
sudo tee "${LIVE_HTTP}" >/dev/null <<EOF
# Temporal pre-certbot — ${DOMAIN} — generado por corte-https-anitaerp-elbierzo.sh
<VirtualHost *:80>
	ServerName ${DOMAIN}
	ServerAlias ${DOMAIN} ${PREVIOUS_HOST} ${LEGACY_HOST} ${EXPECTED_IP} anitaERP bie-svrv-sis2 ipelbierzo
	ServerAdmin sergiogranucci@gmail.com
	DocumentRoot /var/www/html/anitaERP/public

	<Directory /var/www/html/anitaERP/public>
		Options -Indexes +FollowSymLinks
		AllowOverride All
		Require all granted
	</Directory>

	# Legacy path → raíz HTTP (no tocar .well-known para ACME)
	<IfModule mod_rewrite.c>
		RewriteEngine On
		RewriteRule ^/\.well-known/acme-challenge/ - [L]
		RewriteRule ^/anitaERP/public/?(.*)$ /$1 [R=301,L]
	</IfModule>

	ErrorLog \${APACHE_LOG_DIR}/anitaERP-prehttps-error.log
	CustomLog \${APACHE_LOG_DIR}/anitaERP-prehttps-access.log combined
</VirtualHost>

<VirtualHost *:12280>
	ServerName ${DOMAIN}
	ServerAlias ${DOMAIN} ${PREVIOUS_HOST} ${LEGACY_HOST} ${EXPECTED_IP} anitaERP bie-svrv-sis2 ipelbierzo
	ServerAdmin sergiogranucci@gmail.com
	DocumentRoot /var/www/html/anitaERP/public

	<Directory /var/www/html/anitaERP/public>
		Options -Indexes +FollowSymLinks
		AllowOverride All
		Require all granted
	</Directory>

	# Legacy path → raíz (HTTP) hasta activar SSL canónico
	RedirectMatch 301 ^/anitaERP/public/?(.*)$ /$1

	ErrorLog \${APACHE_LOG_DIR}/anitaERP-prehttps-12280-error.log
	CustomLog \${APACHE_LOG_DIR}/anitaERP-prehttps-12280-access.log combined
</VirtualHost>
EOF

sudo a2ensite anitaERP >/dev/null
sudo apache2ctl configtest
sudo systemctl reload apache2
echo "    HTTP listo para ACME + redirect legacy en :12280"

echo ""
echo "==> 4) Certificado Let's Encrypt"
if [[ -f "${CERT_FULL}" ]]; then
	echo "    Ya existe ${CERT_FULL} — salteo emisión"
else
	sudo certbot --apache -d "${DOMAIN}" --non-interactive --agree-tos \
		-m sergiogranucci@gmail.com --redirect || {
		echo "" >&2
		echo "certbot falló. Si el 80 no es público, usá DNS-01:" >&2
		echo "  sudo certbot certonly --manual --preferred-challenges dns -d ${DOMAIN} \\" >&2
		echo "    --agree-tos -m sergiogranucci@gmail.com" >&2
		exit 1
	}
fi

if [[ ! -f "${CERT_FULL}" ]]; then
	echo "No quedó certificado en ${CERT_FULL}" >&2
	exit 1
fi

echo ""
echo "==> 5) Aplicar vhost canónico SSL (redirects /anitaERP/public → HTTPS)"
sudo "${APACHE_DIR}/aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh"

echo ""
echo "==> 6) .env (manual / confirmar)"
echo "    Editar ${REPO_ROOT}/.env:"
echo "      APP_ENV=production"
echo "      APP_DEBUG=false"
echo "      APP_URL=https://${DOMAIN}"
echo "      APP_CARPETA="
echo "      EMPRESA_LINK=/"
echo "      SESSION_SECURE_COOKIE=true"
echo ""
echo "    Luego:"
echo "      cd ${REPO_ROOT}"
echo "      php artisan config:clear && php artisan config:cache && php artisan route:clear && php artisan view:cache"
echo ""
echo "==> 7) Probar"
echo "      curl -sI http://${LEGACY_HOST}:12280/anitaERP/public/"
echo "      # Location: https://${DOMAIN}/"
echo "      curl -sI https://${DOMAIN}/"
echo ""
echo "OK Apache HTTPS. Completá .env y cache Laravel."
