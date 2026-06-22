<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Services\Compras\FacturaProveedorCorpusAprendizajeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Estructuración vía Ollama (opcional). Refuerza heurísticas cuando el servicio está disponible.
 */
final class FacturaProveedorOllamaStructurerSupport
{
    public function __construct(
        private FacturaProveedorCorpusAprendizajeService $corpusService,
    ) {}
    /**
     * @param  array<string, mixed>  $heuristica
     * @return ?array<string, mixed>
     */
    public function estructurar(string $textoOcr, array $heuristica): ?array
    {
        if (! config('comprobante_proveedor_pdf_ia.ollama.habilitado', true)) {
            return null;
        }

        $url = rtrim((string) config('comprobante_proveedor_pdf_ia.ollama.url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('comprobante_proveedor_pdf_ia.ollama.model', 'qwen2.5:14b-instruct');
        $timeout = (int) config('comprobante_proveedor_pdf_ia.ollama.timeout', 180);

        $prompt = $this->armarPrompt($textoOcr, $heuristica);

        try {
            $response = Http::timeout($timeout)
                ->post($url.'/api/generate', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'temperature' => (float) config('comprobante_proveedor_pdf_ia.ollama.temperature', 0.05),
                        'num_predict' => (int) config('comprobante_proveedor_pdf_ia.ollama.max_tokens', 4096),
                    ],
                ]);

            if (! $response->successful()) {
                Log::channel($this->logChannel())->warning('pdf_ia.ollama_error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $body = $response->json();
            $raw = (string) ($body['response'] ?? '');
            $parsed = json_decode($raw, true);

            if (! is_array($parsed)) {
                if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
                    $parsed = json_decode($m[0], true);
                }
            }

            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable $e) {
            Log::channel($this->logChannel())->info('pdf_ia.ollama_no_disponible', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @param  array<string, mixed>  $heuristica */
    private function armarPrompt(string $textoOcr, array $heuristica): string
    {
        $hints = json_encode($heuristica, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $textoRecortado = mb_substr($textoOcr, 0, (int) config('comprobante_proveedor_pdf_ia.ollama.max_chars_ocr', 12000));

        $cuitProv = (string) ($heuristica['cuit_proveedor'] ?? '');
        $ejemplos = $this->corpusService->ejemplosParaCuit(
            $cuitProv !== '' ? $cuitProv : null,
            (int) config('comprobante_proveedor_pdf_ia.corpus.max_ejemplos_prompt', 2)
        );
        $bloqueCorpus = $ejemplos === []
            ? '(sin ejemplos; ejecute: php artisan compras:factura-pdf-ia-aprender --fuente=precargas)'
            : json_encode($ejemplos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $modelo = (string) config('comprobante_proveedor_pdf_ia.ollama.model', 'qwen2.5:3b-instruct');
        $usaModelfileAnita = str_contains($modelo, 'factura-proveedor-anita');

        if ($usaModelfileAnita) {
            return <<<PROMPT
Ejemplos reales del mismo proveedor (referencia de conceptos e importes; tipo FGA/FIB lo resuelve el ERP):
{$bloqueCorpus}

Heurísticas previas (pueden tener errores — corregí con el OCR):
{$hints}

Texto OCR de la factura:
---
{$textoRecortado}
---
PROMPT;
        }

        return <<<PROMPT
Sos un extractor experto de facturas de compra argentinas (AFIP). NO extraigas líneas de artículos/SKU/cantidades.
Solo conceptos impositivos y totales de pie de factura: netos gravados por alícuota, IVA, exentos, no gravado, percepciones IVA/IIBB/ganancias, impuestos internos, otros tributos.

IMPORTANTE: El tipo exacto de comprobante contable (FGA, FIA, FIB, FNB, etc.) NO lo deduzcas del nombre de archivo.
Ese tipo lo resuelve el ERP con la OC y el centro de costo vía API listaConcepto. En JSON usá tipo_comprobante "FC" genérico.

numero_oc: SIEMPRE 6 dígitos (penmp_nro Anita). Si no hay OC en el texto, null.

Ejemplos reales del mismo proveedor (agente externo ya procesado — usá como referencia de conceptos e importes):
{$bloqueCorpus}

Respondé ÚNICAMENTE JSON válido con esta forma:
{
  "cuit_destinatario": "30-12345678-9",
  "cuit_proveedor": "30-98765432-1",
  "numero_oc": "214482",
  "tipo_comprobante": "FC",
  "letra": "A",
  "sucursal": 3,
  "numero_factura": 946427,
  "fecha_factura": "2026-01-15",
  "numerocae": "12345678901234",
  "fecha_vto_cai_cae": "2026-02-15",
  "subtotal": 1000.00,
  "total": 1210.00,
  "moneda": "PESOS",
  "cotizacion": 1.0,
  "lineas": [
    {"descripcion": "Neto gravado 21%", "importe": 1000.00, "alicuota_iva": 21, "tipo": "neto"},
    {"descripcion": "IVA 21%", "importe": 210.00, "alicuota_iva": 21, "tipo": "iva"}
  ]
}

tipos de linea permitidos: neto, iva, exento, no_gravado, percepcion_iva, percepcion_iibb, percepcion_ganancias, interno, otro_tributo, retencion_iva, retencion_iibb.
moneda: PESOS, DOLARES o EUROS.

Heurísticas previas (pueden tener errores, corregí con el OCR):
{$hints}

Texto OCR de la factura:
---
{$textoRecortado}
---
PROMPT;
    }

    private function logChannel(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.log_channel', 'precarga_proveedor_api');
    }
}
