<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use App\Services\Compras\FacturaProveedorCorpusAprendizajeService;
use Illuminate\Support\Facades\Log;

/**
 * Estructuración vía Ollama (opcional). Refuerza heurísticas cuando el servicio está disponible.
 * La llamada al modelo pasa por AiGateway (punto único); el feature flag propio del módulo
 * (comprobante_proveedor_pdf_ia.ollama.*) decide si se intenta o no.
 */
final class FacturaProveedorOllamaStructurerSupport
{
    public function __construct(
        private FacturaProveedorCorpusAprendizajeService $corpusService,
        private AiGateway $aiGateway,
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

        $model = (string) config('comprobante_proveedor_pdf_ia.ollama.model', 'factura-proveedor-anita');
        $prompt = new AiPrompt(
            prompt: $this->armarPrompt($textoOcr, $heuristica),
            esperaJson: true,
            driver: 'ollama',
            model: $model,
            temperature: (float) config('comprobante_proveedor_pdf_ia.ollama.temperature', 0.05),
            maxTokens: (int) config('comprobante_proveedor_pdf_ia.ollama.max_tokens', 1024),
            timeout: (int) config('comprobante_proveedor_pdf_ia.ollama.timeout', 240),
            meta: [
                'origen' => 'factura_proveedor_pdf_ia',
                'cuit_proveedor' => $heuristica['cuit_proveedor'] ?? null,
            ],
        );

        $resultado = $this->aiGateway->generar($prompt);

        if (! $resultado->ok) {
            Log::channel($this->logChannel())->info('pdf_ia.ollama_no_disponible', [
                'message' => $resultado->error,
                'driver' => $resultado->driver,
                'model' => $resultado->model,
                'latencia_ms' => $resultado->latenciaMs,
            ]);

            return null;
        }

        if (! is_array($resultado->json)) {
            Log::channel($this->logChannel())->warning('pdf_ia.ollama_json_invalido', [
                'model' => $resultado->model,
                'latencia_ms' => $resultado->latenciaMs,
                'muestra' => mb_substr($resultado->texto, 0, 300),
            ]);

            return null;
        }

        return $resultado->json;
    }

    /** @param  array<string, mixed>  $heuristica */
    private function armarPrompt(string $textoOcr, array $heuristica): string
    {
        $hints = json_encode($heuristica, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $textoRecortado = FacturaProveedorOcrRecorteSupport::cabeceraYPie(
            $textoOcr,
            (int) config('comprobante_proveedor_pdf_ia.ollama.max_chars_ocr', 12000),
            (float) config('comprobante_proveedor_pdf_ia.ollama.cabecera_ratio', 0.4),
        );

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

IMPORTANTE: El tipo contable fino (FGA, FIA, CGA, DIB, etc.) NO lo deduzcas del nombre de archivo:
el ERP lo arma con la OC + centro de costo (listaConcepto).
En JSON usá solo el tipo GENÉRICO AFIP:
- "FC" = Factura
- "ND" = Nota de débito (texto "Nota de Débito", N/D, código 002/007/012…)
- "NC" = Nota de crédito (texto "Nota de Crédito", N/C, código 003/008/013…)
- "REC" / "REM" solo si es claramente recibo/remito.
Si dudás entre FC/ND/NC, preferí el que diga el título/código del comprobante.

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
