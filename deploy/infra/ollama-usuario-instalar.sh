#!/usr/bin/env bash
# Instala Ollama en ~/opt/ollama sin sudo (tar.zst oficial).
# Uso: ./deploy/infra/ollama-usuario-instalar.sh
set -euo pipefail

INSTALL_DIR="${OLLAMA_INSTALL_DIR:-$HOME/opt/ollama}"
WORKDIR="${OLLAMA_WORKDIR:-$HOME/tmp-ollama}"
ARCHIVE_URL="https://ollama.com/download/ollama-linux-amd64.tar.zst"

mkdir -p "$INSTALL_DIR" "$WORKDIR"

if [[ -x "$INSTALL_DIR/bin/ollama" ]]; then
  echo "Ollama ya instalado en $INSTALL_DIR/bin/ollama"
  "$INSTALL_DIR/bin/ollama" --version || true
  exit 0
fi

echo "Descargando Ollama..."
curl -fsSL "$ARCHIVE_URL" -o "$WORKDIR/ollama-linux-amd64.tar.zst"
zstd -d -f "$WORKDIR/ollama-linux-amd64.tar.zst" -o "$WORKDIR/ollama-linux-amd64.tar"
tar -xf "$WORKDIR/ollama-linux-amd64.tar" -C "$INSTALL_DIR"

echo "Instalado: $INSTALL_DIR/bin/ollama"
"$INSTALL_DIR/bin/ollama" --version

echo "Agregue a PATH (opcional): export PATH=\"\$PATH:$INSTALL_DIR/bin\""
