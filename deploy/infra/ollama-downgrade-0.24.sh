#!/usr/bin/env bash
# Downgrade Ollama 0.30.x → 0.24.0 (evita segfault AMX en VM).
# Ejecutar: sudo /var/www/html/anitaERP/deploy/infra/ollama-downgrade-0.24.sh
set -euo pipefail

OLLAMA_VERSION="${OLLAMA_VERSION:-0.24.0}"
INSTALL_SCRIPT="/tmp/ollama-install-${OLLAMA_VERSION}.sh"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Ejecute con sudo: sudo $0" >&2
  exit 1
fi

echo "=== Deteniendo Ollama 0.30 ==="
systemctl stop ollama || true
pkill -f '/home/sergio/opt/ollama' 2>/dev/null || true
pkill -f '127.0.0.1:11436' 2>/dev/null || true

echo "=== Instalando Ollama ${OLLAMA_VERSION} ==="
curl -fsSL https://ollama.com/install.sh -o "$INSTALL_SCRIPT"
OLLAMA_VERSION="$OLLAMA_VERSION" sh "$INSTALL_SCRIPT"
rm -f "$INSTALL_SCRIPT"

echo "=== Override systemd (CPU conservador) ==="
mkdir -p /etc/systemd/system/ollama.service.d
cat > /etc/systemd/system/ollama.service.d/override.conf <<'EOF'
[Service]
Environment="OLLAMA_FLASH_ATTENTION=0"
Environment="OLLAMA_NUM_PARALLEL=1"
EOF

systemctl daemon-reload
systemctl enable ollama
systemctl restart ollama
sleep 3

VER=$(ollama --version 2>/dev/null | head -1 || true)
echo "Versión instalada: $VER"

if ! curl -fsS http://127.0.0.1:11434/api/version >/dev/null; then
  echo "ERROR: Ollama no responde en 11434" >&2
  journalctl -u ollama -n 20 --no-pager
  exit 1
fi

echo "=== Descargando modelo qwen2.5:3b-instruct (si falta) ==="
sudo -u ollama OLLAMA_HOST=127.0.0.1:11434 ollama pull qwen2.5:3b-instruct || ollama pull qwen2.5:3b-instruct

echo "=== Prueba rápida ==="
ollama run qwen2.5:3b-instruct 'responde solo JSON: {"ok":true}' 2>&1 | head -5

echo ""
echo "OK. Ollama ${OLLAMA_VERSION} activo en http://127.0.0.1:11434"
echo "ERP: COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_MODEL=qwen2.5:3b-instruct"
