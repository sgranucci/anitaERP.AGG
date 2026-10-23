#!/bin/bash
# Publica /var/www/padrones por Samba para pegar los padrones IIBB desde Windows.
# CABA y provincias. ARBA no usa esta carpeta.
# Ejecutar como root: sudo bash deploy/samba/preparar-bandeja-padrones.sh
set -euo pipefail

DIR="${PADRON_IIBB_BANDEJA_DIR:-/var/www/padrones}"
USUARIO="${PADRON_IIBB_BANDEJA_USUARIO:-sergio}"
GRUPO="www-data"
SHARE="padrones"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
EMPRESA_RAW="$(grep -E '^EMPRESA=' "$ROOT/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "'\"" | tr '[:lower:]' '[:upper:]')"
# Interforming: fherber (Fabián Herber) copia el padrón desde Windows. Sergio queda para soporte.
if [[ "$EMPRESA_RAW" == *INTERFORMING* && "$USUARIO" == "sergio" ]]; then
    USUARIO="fherber"
    USUARIOS_SHARE="fherber,sergio"
else
    USUARIOS_SHARE="$USUARIO"
fi

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Hay que correrlo como root: sudo bash $0" >&2
    exit 1
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
    useradd --create-home --shell /usr/sbin/nologin "$USUARIO"
    echo "Usuario Linux ${USUARIO} creado (sin login de consola)."
fi

if ! command -v smbd >/dev/null 2>&1; then
    apt-get update
    apt-get install -y samba
fi

COMMENT="Padrones IIBB (jurisdicciones tildadas como agente)"
AYUDA="Las carpetas son las jurisdicciones tildadas en Agentes IIBB. Pegar el archivo en la de la provincia, no en la raíz."

install -d -o "$USUARIO" -g "$GRUPO" -m 2770 "$DIR"
if ! sudo -u www-data php "$ROOT/artisan" padron-iibb:vigilar-bandeja --preparar; then
    echo "No se pudieron armar las carpetas ahora. El scheduler las crea al vigilar la bandeja." >&2
fi
find "$DIR" -type d -exec chmod 2770 {} \;
chgrp -R "$GRUPO" "$DIR" || true

if grep -q "^\[${SHARE}\]" /etc/samba/smb.conf; then
    awk -v share="$SHARE" -v users="$USUARIOS_SHARE" '
        $0 ~ "^\\[" share "\\]" { inshare = 1 }
        inshare && /^\[/ && $0 !~ "^\\[" share "\\]" { inshare = 0 }
        inshare && $0 ~ /^[[:space:]]*valid users[[:space:]]*=/ {
            print "   valid users = " users
            next
        }
        { print }
    ' /etc/samba/smb.conf > /etc/samba/smb.conf.padrones
    mv /etc/samba/smb.conf.padrones /etc/samba/smb.conf
else
    cat >> /etc/samba/smb.conf <<EOF

[${SHARE}]
   comment = ${COMMENT}
   path = ${DIR}
   browseable = yes
   read only = no
   guest ok = no
   valid users = ${USUARIOS_SHARE}
   force user = www-data
   force group = ${GRUPO}
   create mask = 0664
   directory mask = 2770
   veto files = /procesando/
EOF
fi

systemctl enable smbd nmbd
systemctl restart smbd nmbd

echo
echo "Share listo: \\\\\\\\$(hostname)\\\\${SHARE}"
echo "Si ${USUARIO} todavía no tiene clave de Samba:"
echo "  sudo smbpasswd -a ${USUARIO}"
echo "Desde Windows: Explorador → \\\\\\\\$(hostname)\\\\${SHARE}"
echo "$AYUDA"
