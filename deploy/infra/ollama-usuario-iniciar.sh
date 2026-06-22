#!/usr/bin/env bash
# Ollama local para PDF IA de comprobantes proveedor (sin root).
# Uso: ./deploy/infra/ollama-usuario-iniciar.sh
set -euo pipefail

OLLAMA_BIN="${OLLAMA_BIN:-$HOME/opt/ollama/bin/ollama}"
OLLAMA_HOST="${OLLAMA_HOST:-127.0.0.1:11434}"
OLLAMA_MODEL="${OLLAMA_MODEL:-qwen2.5:7b-instruct}"
LOG="${OLLAMA_LOG:-$HOME/opt/ollama/ollama.log}"

if [[ ! -x "$OLLAMA_BIN" ]]; then
  echo "No existe $OLLAMA_BIN — ejecute deploy/infra/ollama-usuario-instalar.sh primero." >&2
  exit 1
fi

if curl -fsS "http://${OLLAMA_HOST}/api/tags" >/dev/null 2>&1; then
  echo "Ollama ya responde en http://${OLLAMA_HOST}"
else
  echo "Iniciando Ollama serve..."
  nohup env OLLAMA_HOST="$OLLAMA_HOST" "$OLLAMA_BIN" serve >>"$LOG" 2>&1 &
  sleep 2
fi

if ! curl -fsS "http://${OLLAMA_HOST}/api/tags" | grep -q "$OLLAMA_MODEL"; then
  echo "Descargando modelo ${OLLAMA_MODEL} (puede tardar varios minutos)..."
  env OLLAMA_HOST="$OLLAMA_HOST" "$OLLAMA_BIN" pull "$OLLAMA_MODEL"
fi

echo "Ollama listo: http://${OLLAMA_HOST} — modelo ${OLLAMA_MODEL}"
