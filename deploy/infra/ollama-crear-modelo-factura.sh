#!/usr/bin/env bash
# Crea el modelo Ollama factura-proveedor-anita (instrucciones ERP + qwen2.5:3b).
# Uso: ./deploy/infra/ollama-crear-modelo-factura.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MODEL="${OLLAMA_FACTURA_MODEL:-factura-proveedor-anita}"
BASE="${OLLAMA_FACTURA_BASE:-qwen2.5:3b-instruct}"
MODEFILE="$ROOT/deploy/infra/ollama/Modelfile.factura-proveedor-anita"

if ! command -v ollama >/dev/null 2>&1; then
  echo "ollama no está en PATH" >&2
  exit 1
fi

if ! curl -fsS http://127.0.0.1:11434/api/version >/dev/null 2>&1; then
  echo "Ollama no responde en 127.0.0.1:11434 — inicie el servicio primero." >&2
  exit 1
fi

echo "Base: $BASE"
ollama pull "$BASE" 2>/dev/null || true

echo "Creando modelo $MODEL ..."
ollama create "$MODEL" -f "$MODEFILE"

echo "Prueba:"
ollama run "$MODEL" 'Respondé solo JSON: {"ok":true}' 2>&1 | head -3

echo ""
echo "Listo. En .env del ERP:"
echo "COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_MODEL=$MODEL"
