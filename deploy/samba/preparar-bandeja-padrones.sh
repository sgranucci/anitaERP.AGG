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
EMPRESA_RAW="$(grep -E '^EMPRESA=' "$ROOT/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr '[:lower:]' '[:upper:]')"
SOLO_CABA=0
if [[ "$EMPRESA_RAW" == *FERLI* ]]; then
    SOLO_CABA=1
fi

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Hay que correrlo como root: sudo bash $0" >&2
    exit 1
fi

if ! command -v smbd >/dev/null 2>&1; then
    apt-get update
    apt-get install -y samba
fi

if [[ "$SOLO_CABA" -eq 1 ]]; then
    SUBS=(caba procesando procesando/caba ok ok/caba error error/caba)
    COMMENT="Padrones IIBB CABA (AGIP)"
    AYUDA="Pegar el archivo en la carpeta caba (ARDJU….TXT o el RAR de AGIP)."
else
    SUBS=(caba cordoba entrerios misiones santafe tucuman/tasas tucuman/coeficientes \
        procesando procesando/caba procesando/cordoba procesando/entrerios procesando/misiones \
        procesando/santafe procesando/tucuman/tasas procesando/tucuman/coeficientes \
        ok ok/caba ok/cordoba ok/entrerios ok/misiones ok/santafe ok/tucuman/tasas ok/tucuman/coeficientes \
        error error/caba error/cordoba error/entrerios error/misiones error/santafe error/tucuman/tasas error/tucuman/coeficientes)
    COMMENT="Padrones IIBB (CABA y provincias)"
    AYUDA="Pegar cada archivo en la carpeta de la provincia (caba, cordoba, entrerios, misiones, santafe, tucuman/tasas, tucuman/coeficientes)."
fi

install -d -o "$USUARIO" -g "$GRUPO" -m 2770 "$DIR"
for sub in "${SUBS[@]}"; do
    install -d -o "$USUARIO" -g "$GRUPO" -m 2770 "$DIR/$sub"
done
find "$DIR" -type d -exec chmod 2770 {} \;

if ! grep -q "^\[${SHARE}\]" /etc/samba/smb.conf; then
    cat >> /etc/samba/smb.conf <<EOF

[${SHARE}]
   comment = ${COMMENT}
   path = ${DIR}
   browseable = yes
   read only = no
   guest ok = no
   valid users = ${USUARIO}
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
