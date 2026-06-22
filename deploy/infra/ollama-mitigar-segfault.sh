#!/usr/bin/env bash
# Mitigaciones Ollama segfault (AMX en VM + swap lleno). Ejecutar con sudo.
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Ejecute con sudo: sudo $0" >&2
  exit 1
fi

echo "=== Memoria actual ==="
free -h

if ! swapon --show | grep -q swapfile2; then
  echo "=== Creando swap adicional 4G ==="
  fallocate -l 4G /swapfile2
  chmod 600 /swapfile2
  mkswap /swapfile2
  swapon /swapfile2
  grep -q '/swapfile2' /etc/fstab || echo '/swapfile2 none swap sw 0 0' >> /etc/fstab
fi

echo "=== Override systemd ollama ==="
mkdir -p /etc/systemd/system/ollama.service.d
cat > /etc/systemd/system/ollama.service.d/override.conf <<'EOF'
[Service]
Environment="OLLAMA_LLM_LIBRARY=cpu_x64"
Environment="OLLAMA_FLASH_ATTENTION=0"
Environment="OLLAMA_NUM_PARALLEL=1"
EOF

systemctl daemon-reload
systemctl restart ollama
sleep 2

echo "=== Estado ==="
systemctl is-active ollama
free -h
echo "Probar: ollama run qwen2.5:3b-instruct 'hola'"
echo "Si sigue segfault, ver deploy/infra/ollama-diagnostico-segfault.md (bajar versión ollama o Ollama remoto)"
