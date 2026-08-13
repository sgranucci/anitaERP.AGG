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
            maxTokens: (int) config('comprobante_proveedor_pdf_ia.ollama.max_tokens', 2048),
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
        $textoRecortado = FacturaProveedorOcrRecorteSupport::cabeceraMedioYPie(
            $textoOcr,
            (int) config('comprobante_proveedor_pdf_ia.ollama.max_chars_ocr', 14000),
            (float) config('comprobante_proveedor_pdf_ia.ollama.cabecera_ratio', 0.28),
            (float) config('comprobante_proveedor_pdf_ia.ollama.medio_ratio', 0.44),
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
Ejemplos reales del mismo proveedor (referencia de conceptos e importes; tipo FGA/FIB/FIS lo resuelve el ERP vía listaConcepto — servicios/medidores → FIS/FNS):
{$bloqueCorpus}

Heurísticas previas (pueden tener errores — corregí con el OCR). Incluyen `lineas` (conceptos IVA) y `articulos` (ítems):
{$hints}

Devolvé JSON con cabecera + `lineas` (conceptos) + `articulos` (sku/codigo_proveedor/descripcion/cantidad/precio_unitario). Si no hay detalle de ítems, articulos: [].

Texto OCR de la factura:
---
{$textoRecortado}
---
PROMPT;
        }

        return <<<PROMPT
Sos un extractor experto de facturas de compra argentinas (AFIP).

EXTRAÉ DOS COSAS DISTINTAS:
1) Conceptos impositivos del pie (`lineas`): netos, IVA, exentos, percepciones, etc.
2) Ítems de mercadería del detalle (`articulos`): código/SKU, descripción, cantidad y precio unitario.
No mezcles artículos dentro de `lineas` ni conceptos IVA dentro de `articulos`.

IMPORTANTE: El tipo contable fino (FGA, FIA, FIS, FNS, CGA, DIB, etc.) NO lo deduzcas del nombre de archivo:
el ERP lo arma con la OC + centro de costo (listaConcepto).
Si el proveedor tiene medidores/servicios cargados en anitaERP, listaConcepto fuerza tipo Servicio (FIS/FNS/…).
En JSON usá solo el tipo GENÉRICO AFIP:
- "FC" = Factura
- "ND" = Nota de débito (texto "Nota de Débito", N/D, código 002/007/012…)
- "NC" = Nota de crédito (texto "Nota de Crédito", N/C, código 003/008/013…)
- "REC" / "REM" solo si es claramente recibo/remito.
Si dudás entre FC/ND/NC, preferí el que diga el título/código del comprobante.

numero_oc: SIEMPRE 6 dígitos (penmp_nro Anita). Si no hay OC en el texto, null.

Número de factura:
- Si viene como 0001-00001234 → sucursal=1, numero_factura=1234, letra del tipo (A/B/C).
- Si viene COMPACTO sin guiones (muy común): "Factura N° 0070A00369548"
  → sucursal=70, letra="A", numero_factura=369548 (NO juntes los dígitos en un solo número).
fecha_factura: ISO YYYY-MM-DD. Si el PDF dice 05.08.2026 o 05/08/2026 → "2026-08-05".
fecha_vencimiento: vencimiento de PAGO comercial (Fecha de vencimiento / Vencimiento / Vto. pago). NO confundir con fecha_vto_cai_cae.
fecha_vto_cai_cae: solo el vencimiento del CAE/CAI/CAEA.

Ejemplos reales del mismo proveedor (agente externo ya procesado — usá como referencia de conceptos e importes):
{$bloqueCorpus}

Respondé ÚNICAMENTE JSON válido con esta forma:
{
  "cuit_destinatario": "30-12345678-9",
  "cuit_proveedor": "30-98765432-1",
  "numero_oc": "214482",
  "tipo_comprobante": "FC",
  "letra": "A",
  "sucursal": 70,
  "numero_factura": 369548,
  "fecha_factura": "2026-08-05",
  "numerocae": "12345678901234",
  "fecha_vto_cai_cae": "2026-02-15",
  "fecha_vencimiento": "2026-08-20",
  "subtotal": 1000.00,
  "total": 1210.00,
  "moneda": "PESOS",
  "cotizacion": 1.0,
  "lineas": [
    {"descripcion": "Neto gravado 21%", "importe": 1000.00, "alicuota_iva": 21, "tipo": "neto"},
    {"descripcion": "IVA 21%", "importe": 210.00, "alicuota_iva": 21, "tipo": "iva"}
  ],
  "articulos": [
    {"sku": "123456", "codigo_proveedor": "PRV-001", "descripcion": "Producto X", "cantidad": 2.0, "precio_unitario": 500.0}
  ]
}

tipos de linea (conceptos) permitidos: neto, iva, exento, no_gravado, percepcion_iva, percepcion_iibb, percepcion_ganancias, interno, otro_tributo, retencion_iva, retencion_iibb.
Si solo hay TOTAL final sin desglose neto/IVA (o Imp. Exento = Total), una sola línea tipo "exento" con importe = total (subtotal = total). No inventes IVA.
En articulos: si no hay código interno, usá el código del proveedor en sku y codigo_proveedor. Si no hay detalle de ítems, devolvé articulos: [].
moneda: SOLO "PESOS", "DOLARES" o "EUROS".
IMPORTANTE Argentina: el símbolo "$" significa PESOS, NUNCA dólares.
"Son Pesos:", "Importe $" y el pie "tipo de cambio … por cada dolar" son PESOS (solo referencia impositiva).
Solo poné "DOLARES" si hay "Moneda: USD/U\$S", importes con U\$S/USD, o "facturado/importe en dólares".
La palabra "dólar" suelta en el pie NO alcanza. Sin señal fuerte → "PESOS" y cotizacion=1.

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
