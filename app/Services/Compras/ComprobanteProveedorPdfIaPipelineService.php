<?php

namespace App\Services\Compras;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorArticulosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorCabeceraHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorConceptosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorExtraccionFusionSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorMonedaOcrSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorNombreArchivoParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorNumeroComprobanteSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorOllamaStructurerSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorPieTotalesSupport;
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
        private FacturaProveedorArticulosHeuristicaSupport $articulosHeuristica,
        private FacturaProveedorOllamaStructurerSupport $ollamaStructurer,
        private FacturaProveedorExtraccionFusionSupport $fusion,
        private FacturaProveedorNombreArchivoParserSupport $nombreArchivoParser,
        private FacturaProveedorPieTotalesSupport $pieTotales,
        private FacturaProveedorMonedaOcrSupport $monedaOcr,
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

            // Pie AFIP en tabla (rótulos / importes): fuente de verdad de subtotal/total/conceptos.
            $pie = $this->pieTotales->extraer($textoOcr);
            $cabecera = $this->aplicarPieACabecera($cabecera, $pie);
            $conceptos = $this->aplicarPieAConceptos($conceptos, $pie);

            $heuristica = array_merge($cabecera, [
                'lineas' => $conceptos,
                'articulos' => $this->articulosHeuristica->extraer($textoOcr),
            ]);

            $ollama = null;
            if (config('comprobante_proveedor_pdf_ia.ollama.habilitado', true)) {
                $ollama = $this->ollamaStructurer->estructurar($textoOcr, $heuristica);
            }

            $resultado = $this->fusion->fusionar($heuristica, $ollama);
            // El nombre del agente (FGA-A-00003-01377643.pdf) manda sobre OCR/Ollama:
            // un PV mal leído dispara obs ARCA 104 (CAE no corresponde al punto de venta).
            $resultado = $this->aplicarCamposAutoridadArchivo($resultado, $pdf->getClientOriginalName());
            // Compacto OCR (0070A00369548) y fecha dd.mm.yyyy de heurística mandan si el archivo no trae PV/nro.
            $resultado = $this->aplicarCamposAutoridadCompactoYFechaOcr($resultado, $textoOcr, $heuristica);
            $resultado = $this->sanearMonedaArgentina($resultado, $textoOcr, $heuristica);
            $resultado = $this->aplicarPieAResultado($resultado, $pie);
            $resultado = $this->sanearCuitsYOc($resultado);
            $resultado = $this->sanearSoloTotalComoExento($resultado, $textoOcr);
            $resultado['_meta']['ocr_chars'] = $chars;
            $resultado['_meta']['ocr_muestra'] = mb_substr(preg_replace('/\s+/', ' ', $textoOcr) ?? '', 0, 400);

            Log::channel($this->logChannel())->info('pdf_ia.pipeline_ok', [
                'ocr_chars' => $chars,
                'lineas' => count($resultado['lineas'] ?? []),
                'articulos' => count($resultado['articulos'] ?? []),
                'fuentes' => $resultado['_meta']['fuentes'] ?? [],
                'moneda' => $resultado['moneda'] ?? null,
                'cotizacion' => $resultado['cotizacion'] ?? null,
                'numero_oc' => $resultado['numero_oc'] ?? null,
                'cuit_proveedor' => $resultado['cuit_proveedor'] ?? null,
                'cuit_destinatario' => $resultado['cuit_destinatario'] ?? null,
                'tipo_comprobante' => $resultado['tipo_comprobante'] ?? null,
                'sucursal' => $resultado['sucursal'] ?? null,
                'numero_factura' => $resultado['numero_factura'] ?? null,
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
        $cabecera['_archivo'] = $meta;

        return $this->aplicarCamposAutoridadArchivo($cabecera, $nombreArchivo);
    }

    /**
     * Cuando el PDF sigue el naming del agente externo (TIPO-LETRA-PV-NUMERO.pdf),
     * esos campos son más confiables que OCR/Ollama.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Número compacto del PDF (Factura N° 0070A00369548) y fecha con puntos
     * ganan sobre Ollama cuando el nombre de archivo no trae PV/número.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $heuristica
     * @return array<string, mixed>
     */
    private function aplicarCamposAutoridadCompactoYFechaOcr(
        array $data,
        string $textoOcr,
        array $heuristica,
    ): array {
        $archivoTieneNro = ! empty($data['_archivo']['numero_factura'] ?? null)
            && ! empty($data['_archivo']['sucursal'] ?? null);

        if (! $archivoTieneNro) {
            $compacto = FacturaProveedorNumeroComprobanteSupport::extraerCompactoDesdeTexto($textoOcr)
                ?? FacturaProveedorNumeroComprobanteSupport::parsearValorCompacto($data['numero_factura'] ?? null)
                ?? FacturaProveedorNumeroComprobanteSupport::parsearValorCompacto(
                    ($data['sucursal'] ?? '').($data['letra'] ?? '').($data['numero_factura'] ?? '')
                );

            if ($compacto !== null && ($compacto['numero'] ?? 0) > 0) {
                $data['letra'] = $compacto['letra'];
                $data['sucursal'] = $compacto['sucursal'];
                $data['numero_factura'] = $compacto['numero'];
            }
        }

        // Fecha etiquetada del OCR (incluye 05.08.2026) es más confiable que el LLM.
        if (! empty($heuristica['fecha_factura'])) {
            $data['fecha_factura'] = $heuristica['fecha_factura'];
        }

        return $data;
    }

    /**
     * En facturas AR el "$" es peso. Solo DOLARES/EUROS con mención explícita (USD, U$S, etc.).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $heuristica
     * @return array<string, mixed>
     */
    private function sanearMonedaArgentina(array $data, string $textoOcr, array $heuristica): array
    {
        $monedaH = strtoupper((string) ($heuristica['moneda'] ?? 'PESOS'));
        $moneda = strtoupper((string) ($data['moneda'] ?? $monedaH));

        $hayDolares = $this->monedaOcr->indicaDolares($textoOcr);
        $hayEuros = $this->monedaOcr->indicaEuros($textoOcr);
        $pesosFuerte = $this->monedaOcr->indicaPesosFuerte($textoOcr);

        // Pie "tipo de cambio … dolar" / "Son Pesos:" ⇒ pesos aunque Ollama diga DOLARES.
        if ($pesosFuerte && ! $hayDolares && ! $hayEuros) {
            $moneda = 'PESOS';
        }
        if (in_array($moneda, ['DOLARES', 'USD', 'DOLAR', 'DOL'], true) && ! $hayDolares) {
            $moneda = 'PESOS';
        }
        if (in_array($moneda, ['EUROS', 'EUR', 'EURO'], true) && ! $hayEuros) {
            $moneda = 'PESOS';
        }
        if ($monedaH === 'PESOS' && ! $hayDolares && ! $hayEuros) {
            $moneda = 'PESOS';
        }

        $data['moneda'] = $moneda;
        if ($moneda === 'PESOS') {
            $data['cotizacion'] = 1.0;
        } elseif (empty($data['cotizacion']) || (float) $data['cotizacion'] <= 1.0001) {
            $cotH = (float) ($heuristica['cotizacion'] ?? 0);
            if ($cotH > 1.0001) {
                $data['cotizacion'] = $cotH;
            }
        }

        return $data;
    }

    private function aplicarCamposAutoridadArchivo(array $data, string $nombreArchivo): array
    {
        $meta = is_array($data['_archivo'] ?? null)
            ? $data['_archivo']
            : $this->nombreArchivoParser->parsear($nombreArchivo);

        $data['_archivo'] = $meta;

        if (! empty($meta['cuit_proveedor'])) {
            $data['cuit_proveedor'] = $meta['cuit_proveedor'];
        }
        if (! empty($meta['letra'])) {
            $data['letra'] = $meta['letra'];
        }
        if (! empty($meta['sucursal'])) {
            $data['sucursal'] = $meta['sucursal'];
        }
        if (! empty($meta['numero_factura'])) {
            $data['numero_factura'] = $meta['numero_factura'];
        }
        // Nombre tipo "oc 220146.pdf": OC del archivo si el OCR no la vio.
        if (! empty($meta['numero_oc']) && empty($data['numero_oc'])) {
            $data['numero_oc'] = $meta['numero_oc'];
            $data['numero_oc_origen'] = 'nombre_archivo';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  array<string, mixed>  $pie
     * @return array<string, mixed>
     */
    private function aplicarPieACabecera(array $cabecera, array $pie): array
    {
        if (! empty($pie['subtotal'])) {
            $cabecera['subtotal'] = $pie['subtotal'];
        }
        if (! empty($pie['total'])) {
            $cabecera['total'] = $pie['total'];
            $cabecera['total_origen'] = $pie['origen'] ?? 'pie';
        }

        return $cabecera;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @param  array<string, mixed>  $pie
     * @return list<array<string, mixed>>
     */
    private function aplicarPieAConceptos(array $conceptos, array $pie): array
    {
        $lineasPie = is_array($pie['lineas'] ?? null) ? $pie['lineas'] : [];
        if ($lineasPie === []) {
            return $conceptos;
        }

        $sumaPie = round(array_sum(array_column($lineasPie, 'importe')), 2);
        $sumaOld = round(array_sum(array_column($conceptos, 'importe')), 2);
        $total = (float) ($pie['total'] ?? 0);

        if ($total > 0 && abs($sumaPie - $total) <= 0.05) {
            return $lineasPie;
        }
        if ($sumaPie > $sumaOld + 0.05) {
            return $lineasPie;
        }

        return $conceptos;
    }

    /**
     * Tras fusionar con Ollama, el pie sigue mandando en total/subtotal/líneas de conceptos.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $pie
     * @return array<string, mixed>
     */
    private function aplicarPieAResultado(array $resultado, array $pie): array
    {
        if (! empty($pie['subtotal'])) {
            $resultado['subtotal'] = $pie['subtotal'];
        }

        $totalPie = isset($pie['total']) ? (float) $pie['total'] : 0.0;
        $totalActual = isset($resultado['total']) ? (float) $resultado['total'] : 0.0;
        $subtotal = isset($resultado['subtotal']) ? (float) $resultado['subtotal'] : (float) ($pie['subtotal'] ?? 0);

        if ($totalPie > 0) {
            $resultado['total'] = $totalPie;
            $resultado['_meta']['total_origen'] = $pie['origen'] ?? 'pie';
        } elseif ($subtotal > 0 && $totalActual > 0 && abs($totalActual - $subtotal) <= 0.05) {
            // Total == subtotal: Ollama/heurística tomaron el neto. Reconstruir si hay líneas.
            $lineas = is_array($resultado['lineas'] ?? null) ? $resultado['lineas'] : [];
            $suma = round(array_sum(array_map(
                static fn ($l): float => abs((float) ($l['importe'] ?? 0)),
                $lineas
            )), 2);
            if ($suma > $totalActual + 0.05) {
                $resultado['total'] = $suma;
                $resultado['_meta']['total_origen'] = 'suma_conceptos';
            }
        }

        $lineasPie = is_array($pie['lineas'] ?? null) ? $pie['lineas'] : [];
        if ($lineasPie !== [] && $totalPie > 0) {
            $sumaPie = round(array_sum(array_column($lineasPie, 'importe')), 2);
            if (abs($sumaPie - $totalPie) <= 0.05) {
                $resultado['lineas'] = $lineasPie;
                $resultado['_meta']['lineas_origen'] = $pie['origen'] ?? 'pie';
            }
        }

        return $resultado;
    }

    /**
     * Comprobante con solo TOTAL (sin IVA/percepciones): una línea exento = total.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanearSoloTotalComoExento(array $data, string $textoOcr): array
    {
        $total = isset($data['total']) ? (float) $data['total'] : 0.0;
        $lineas = is_array($data['lineas'] ?? null) ? $data['lineas'] : [];
        if ($total <= 0 || $lineas === []) {
            return $data;
        }

        $sumaIva = 0.0;
        $sumaOtros = 0.0;
        $sumaNeto = 0.0;
        $sumaExento = 0.0;
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $imp = abs((float) ($linea['importe'] ?? 0));
            $tipo = strtolower((string) ($linea['tipo'] ?? ''));
            if (str_contains($tipo, 'iva') && ! str_contains($tipo, 'percepcion') && ! str_contains($tipo, 'retencion')) {
                $sumaIva += $imp;
            } elseif (str_contains($tipo, 'exento')) {
                $sumaExento += $imp;
            } elseif (str_contains($tipo, 'neto') || str_contains($tipo, 'subtotal') || str_contains($tipo, 'gravado')) {
                $sumaNeto += $imp;
            } elseif (str_contains($tipo, 'percepcion') || str_contains($tipo, 'interno') || str_contains($tipo, 'retencion') || str_contains($tipo, 'tributo')) {
                $sumaOtros += $imp;
            }
        }

        if ($sumaIva > 0.01 || $sumaOtros > 0.01) {
            return $data;
        }

        if ($sumaExento > 0 && abs($sumaExento - $total) <= 0.05 && $sumaNeto <= 0.01) {
            $data['subtotal'] = $total;
            $data['lineas'] = [[
                'descripcion' => 'Operaciones exentas (total sin desglose IVA)',
                'importe' => $total,
                'alicuota_iva' => 0.0,
                'tipo' => 'exento',
            ]];
            $data['_meta']['lineas_origen'] = 'solo_total_exento';

            return $data;
        }

        $senalExento = (bool) preg_match('/imp\.?\s*exento/iu', $textoOcr);
        if ($sumaNeto > 0 && abs($sumaNeto - $total) <= 0.05 && ($senalExento || $sumaExento <= 0.01)) {
            $data['subtotal'] = $total;
            $data['lineas'] = [[
                'descripcion' => 'Operaciones exentas (total sin desglose IVA)',
                'importe' => $total,
                'alicuota_iva' => 0.0,
                'tipo' => 'exento',
            ]];
            $data['_meta']['lineas_origen'] = 'solo_total_exento';
        }

        return $data;
    }

    /**
     * Evita errores típicos de OCR: CUIT receptor = emisor, u OC = número de factura.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanearCuitsYOc(array $data): array
    {
        $prov = preg_replace('/\D/', '', (string) ($data['cuit_proveedor'] ?? '')) ?? '';
        $dest = preg_replace('/\D/', '', (string) ($data['cuit_destinatario'] ?? '')) ?? '';
        if ($prov !== '' && $dest !== '' && $prov === $dest) {
            $data['cuit_destinatario'] = null;
            $data['cuit_destinatario_origen'] = 'descartado_igual_proveedor';
            $fuentes = $data['_meta']['fuentes'] ?? [];
            if (is_array($fuentes)) {
                $data['_meta']['fuentes'] = $fuentes;
            }
            $data['_meta']['cuit_destinatario_descartado'] = true;
        }

        $ocDigitos = preg_replace('/\D/', '', (string) ($data['numero_oc'] ?? '')) ?? '';
        $nroFactura = (string) (int) ($data['numero_factura'] ?? 0);
        if ($ocDigitos !== '' && $nroFactura !== '0') {
            $ocSinCeros = (string) (int) $ocDigitos;
            if ($ocSinCeros === $nroFactura || $ocDigitos === str_pad($nroFactura, strlen($ocDigitos), '0', STR_PAD_LEFT)) {
                $data['numero_oc'] = null;
                $data['numero_oc_origen'] = 'descartado_igual_factura';
                $data['_meta']['numero_oc_descartado'] = $ocDigitos;
            }
        }

        // Descarta PV/número tomados del CUIT (ej. 33-69509841 de 33-69509841-9).
        $sucursal = (int) ($data['sucursal'] ?? 0);
        $nroFac = (int) ($data['numero_factura'] ?? 0);
        if ($sucursal > 0 && $nroFac > 0) {
            foreach ([$prov, $dest] as $cuitDigits) {
                if (strlen($cuitDigits) !== 11) {
                    continue;
                }
                $prefijo = (int) substr($cuitDigits, 0, 2);
                $medio = (int) substr($cuitDigits, 2, 8);
                if ($sucursal === $prefijo && $nroFac === $medio) {
                    $data['sucursal'] = null;
                    $data['numero_factura'] = null;
                    $data['_meta']['pv_numero_descartado_cuit'] = $cuitDigits;
                    break;
                }
            }
        }

        return $data;
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
