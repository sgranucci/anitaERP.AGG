#!/bin/bash
# Genera certificado autofirmado para 10.20.30.210.
# No toca Apache ni el .env. Idempotente: si el cert ya existe, no lo pisa
# salvo que se pase --force.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CNF="${SCRIPT_DIR}/openssl-ip-210.cnf"
DEST_DIR="/etc/ssl/anitaERP/ip-210"
DAYS="${ANITAERP_CERT_DAYS:-825}"
FORCE=0

if [[ "${1:-}" == "--force" ]]; then
	FORCE=1
fi

if [[ ! -f "${CNF}" ]]; then
	echo "Falta ${CNF}" >&2
	exit 1
fi

if [[ -f "${DEST_DIR}/cert.pem" && -f "${DEST_DIR}/privkey.pem" && "${FORCE}" -ne 1 ]]; then
	echo "Ya existe certificado en ${DEST_DIR} (usar --force para regenerar)."
	openssl x509 -in "${DEST_DIR}/cert.pem" -noout -subject -dates -ext subjectAltName
	exit 0
fi

sudo mkdir -p "${DEST_DIR}"
sudo openssl req -x509 -nodes -newkey rsa:2048 \
	-keyout "${DEST_DIR}/privkey.pem" \
	-out "${DEST_DIR}/cert.pem" \
	-days "${DAYS}" \
	-config "${CNF}"

sudo chmod 640 "${DEST_DIR}/privkey.pem"
sudo chmod 644 "${DEST_DIR}/cert.pem"
sudo chown root:ssl-cert "${DEST_DIR}/privkey.pem" 2>/dev/null \
	|| sudo chown root:root "${DEST_DIR}/privkey.pem"

echo ""
echo "OK: certificado en ${DEST_DIR}"
openssl x509 -in "${DEST_DIR}/cert.pem" -noout -subject -dates -ext subjectAltName
