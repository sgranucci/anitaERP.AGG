<?php

namespace App\Services\Compras;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorCabeceraHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorConceptosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorExtraccionFusionSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorNombreArchivoParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorOllamaStructurerSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTextoExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pipeline interno Anita: OCR (pdftotext/tesseract) + heurísticas AR + Ollama opcional.
 */
final class ComprobanteProveedorPdfIaPipelineService
{
    public function __construct(
        private RecepcionProveedorOcrTextoExtractor $textoExtractor,
        private FacturaProveedorCabeceraHeuristicaSupport $cabeceraHeuristica,
        private FacturaProveedorConceptosHeuristicaSupport $conceptosHeuristica,
        private FacturaProveedorOllamaStructurerSupport $ollamaStructurer,
        private FacturaProveedorExtraccionFusionSupport $fusion,
        private FacturaProveedorNombreArchivoParserSupport $nombreArchivoParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extraer(UploadedFile $pdf): array
    {
        $rutaRelativa = $pdf->store('compras/factura_pdf_ia/'.date('Y/m/d'), 'local');
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        try {
            $textoOcr = $this->extraerTexto($rutaAbsoluta, (string) $pdf->getMimeType());
            $chars = mb_strlen(trim($textoOcr));

            if ($chars < (int) config('comprobante_proveedor_pdf_ia.ocr.min_chars', 30)) {
                throw new RuntimeException(
                    'No se pudo leer texto suficiente del PDF ('.$chars.' caracteres). '
                    .'Verifique que pdftotext/tesseract estén instalados o que el PDF no esté corrupto.'
                );
            }

            $cabecera = $this->cabeceraHeuristica->extraer($textoOcr);
            $cabecera = $this->enriquecerDesdeNombreArchivo($cabecera, $pdf->getClientOriginalName());
            $conceptos = $this->conceptosHeuristica->extraer($textoOcr, $cabecera['total'] ?? null);

            $heuristica = array_merge($cabecera, ['lineas' => $conceptos]);

            $ollama = null;
            if (config('comprobante_proveedor_pdf_ia.ollama.habilitado', true)) {
                $ollama = $this->ollamaStructurer->estructurar($textoOcr, $heuristica);
            }

            $resultado = $this->fusion->fusionar($heuristica, $ollama);
            $resultado['_meta']['ocr_chars'] = $chars;
            $resultado['_meta']['ocr_muestra'] = mb_substr(preg_replace('/\s+/', ' ', $textoOcr) ?? '', 0, 400);

            Log::channel($this->logChannel())->info('pdf_ia.pipeline_ok', [
                'ocr_chars' => $chars,
                'lineas' => count($resultado['lineas'] ?? []),
                'fuentes' => $resultado['_meta']['fuentes'] ?? [],
                'numero_oc' => $resultado['numero_oc'] ?? null,
            ]);

            return $this->sinMetaInterna($resultado);
        } finally {
            Storage::disk('local')->delete($rutaRelativa);
        }
    }

    /** @param  array<string, mixed>  $cabecera */
    private function enriquecerDesdeNombreArchivo(array $cabecera, string $nombreArchivo): array
    {
        $meta = $this->nombreArchivoParser->parsear($nombreArchivo);

        if (empty($cabecera['cuit_proveedor']) && ! empty($meta['cuit_proveedor'])) {
            $cabecera['cuit_proveedor'] = $meta['cuit_proveedor'];
        }
        if (empty($cabecera['letra']) && ! empty($meta['letra'])) {
            $cabecera['letra'] = $meta['letra'];
        }
        if (empty($cabecera['sucursal']) && ! empty($meta['sucursal'])) {
            $cabecera['sucursal'] = $meta['sucursal'];
        }
        if (empty($cabecera['numero_factura']) && ! empty($meta['numero_factura'])) {
            $cabecera['numero_factura'] = $meta['numero_factura'];
        }

        $cabecera['_archivo'] = $meta;

        return $cabecera;
    }

    private function extraerTexto(string $rutaAbsoluta, string $mime): string
    {
        return $this->textoExtractor->extraer($rutaAbsoluta, $mime);
    }

    /** Quita campos solo de debug antes de validar negocio (se conserva _meta resumida). */
    private function sinMetaInterna(array $resultado): array
    {
        if (isset($resultado['_meta']['ocr_muestra'])) {
            unset($resultado['_meta']['ocr_muestra']);
        }

        return $resultado;
    }

    private function logChannel(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.log_channel', 'precarga_proveedor_api');
    }
}
