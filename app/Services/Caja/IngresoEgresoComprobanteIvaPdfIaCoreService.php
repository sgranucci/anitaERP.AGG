<?php

namespace App\Services\Caja;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor;
use App\Services\Compras\ComprobanteProveedorPdfIaPipelineService;
use App\Support\Compras\PrecargaProveedor\ComprobanteProveedorPdfIaConceptoMatcherSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorIvaTasaInversaSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorWscdcConstatacionSupport;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Implementación de negocio de lectura PDF-IVA para Caja.
 *
 * No conoce la plataforma de gobernanza: la Skill y el wrapper público agregan
 * policy, score y ai_decision sin generar dependencias circulares.
 */
final class IngresoEgresoComprobanteIvaPdfIaCoreService
{
    public function __construct(
        private ComprobanteProveedorPdfIaPipelineService $pipeline,
        private ComprobanteProveedorPdfIaConceptoMatcherSupport $conceptoMatcher,
        private FacturaProveedorIvaTasaInversaSupport $ivaTasaInversa,
        private PrecargaProveedorWscdcConstatacionSupport $wscdcConstatacionSupport,
        private IngresoEgresoComprobanteIvaArchivoService $archivoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extraer(UploadedFile $pdf, int $empresaId): array
    {
        $pdfTempId = $this->archivoService->guardarTempDesdeUpload($pdf);
        $extraido = $this->pipeline->extraer($pdf);
        $cuit = preg_replace('/\D+/', '', (string) ($extraido['cuit_proveedor'] ?? '')) ?? '';

        $proveedor = null;
        if ($cuit !== '') {
            $proveedor = Proveedor::query()
                ->whereRaw("REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '') = ?", [$cuit])
                ->first();
        }

        $lineasIa = is_array($extraido['lineas'] ?? null) ? $extraido['lineas'] : [];
        $lineasIa = $this->ivaTasaInversa->enriquecerLineas($lineasIa);
        $extraido['lineas'] = $lineasIa;

        $candidatos = $this->conceptosCandidatos($empresaId);
        $conceptosAsignados = [];
        $advertencias = [];

        try {
            $matcheados = $this->conceptoMatcher->matchear($candidatos, $lineasIa);
            foreach ($matcheados as $linea) {
                $conceptosAsignados[] = [
                    'concepto_ivacompra_id' => (int) ($linea['id_concepto'] ?? 0),
                    'concepto_nombre' => $linea['concepto_nombre'] ?? '',
                    'descripcion_ia' => $linea['descripcion_ia'] ?? '',
                    'monto' => (float) ($linea['importe'] ?? 0),
                    'cuentacontabledebe_id' => null,
                ];
            }
        } catch (RuntimeException $e) {
            $advertencias[] = $e->getMessage();
            foreach ($lineasIa as $linea) {
                $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
                if ($importe <= 0) {
                    continue;
                }
                $conceptosAsignados[] = [
                    'concepto_ivacompra_id' => 0,
                    'concepto_nombre' => '',
                    'descripcion_ia' => (string) ($linea['descripcion'] ?? ''),
                    'monto' => $importe,
                    'cuentacontabledebe_id' => null,
                ];
            }
        }

        $numero = $extraido['numero_factura'] ?? [];
        $letra = strtoupper((string) (is_array($numero) ? ($numero['letra'] ?? '') : '') ?: ($extraido['letra'] ?? 'B'));
        $sucursal = (int) (is_array($numero) ? ($numero['sucursal'] ?? 0) : ($extraido['sucursal'] ?? 0));
        $nro = (int) (is_array($numero)
            ? ($numero['numero'] ?? 0)
            : ($numero ?: ($extraido['numero'] ?? 0)));

        $total = (float) ($extraido['total'] ?? 0);
        $cabecera = [
            'proveedor_id' => $proveedor?->id,
            'proveedor_nombre' => $proveedor?->nombre ?? ($extraido['razon_social_proveedor'] ?? $extraido['proveedor_nombre'] ?? ''),
            'proveedor_documento_eventual' => $cuit,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numerocomprobante' => $nro,
            'fechacomprobante' => $extraido['fecha_factura'] ?? $extraido['fecha'] ?? date('Y-m-d'),
            'fechaiva' => $extraido['fecha_factura'] ?? $extraido['fecha'] ?? date('Y-m-d'),
            'total' => $total,
            'numerocae' => $extraido['numerocae'] ?? $extraido['cae'] ?? null,
            'tipo_autorizacion' => $extraido['tipo_autorizacion'] ?? null,
            'fechavencimientocae' => $extraido['fecha_vto_cai_cae'] ?? $extraido['vencimiento_cae'] ?? null,
        ];

        // Constatación WSCDC (misma vía que precarga / API externa): corrige total ARCA.
        $resueltoWscdc = $this->wscdcConstatacionSupport->constatarYEnriquecer($extraido, [
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedor?->id,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero_factura' => $nro,
            'fecha_factura' => $cabecera['fechacomprobante'],
            'total' => $total,
            'numerocae' => $cabecera['numerocae'],
            'advertencias' => $advertencias,
        ]);

        foreach ($resueltoWscdc['advertencias'] ?? [] as $aviso) {
            if (is_string($aviso) && $aviso !== '' && ! in_array($aviso, $advertencias, true)) {
                $advertencias[] = $aviso;
            }
        }

        if (isset($resueltoWscdc['total']) && (float) $resueltoWscdc['total'] > 0) {
            $cabecera['total'] = (float) $resueltoWscdc['total'];
        }
        if (! empty($resueltoWscdc['numerocae'])) {
            $cabecera['numerocae'] = $resueltoWscdc['numerocae'];
            $cabecera['tipo_autorizacion'] = $cabecera['tipo_autorizacion'] ?: 'CAE';
        }
        if (! empty($resueltoWscdc['fecha_factura'])) {
            $cabecera['fechacomprobante'] = $resueltoWscdc['fecha_factura'];
            $cabecera['fechaiva'] = $resueltoWscdc['fecha_factura'];
        }
        if (isset($resueltoWscdc['sucursal']) && (int) $resueltoWscdc['sucursal'] > 0) {
            $cabecera['sucursal'] = (int) $resueltoWscdc['sucursal'];
        }
        if (isset($resueltoWscdc['numero_factura']) && (int) $resueltoWscdc['numero_factura'] > 0) {
            $cabecera['numerocomprobante'] = (int) $resueltoWscdc['numero_factura'];
        }

        $corregidos = $this->corregirIvaContraTotal($conceptosAsignados, (float) $cabecera['total']);
        $conceptosAsignados = $corregidos['conceptos'];
        foreach ($corregidos['advertencias'] as $aviso) {
            $advertencias[] = $aviso;
        }

        return [
            'ok' => true,
            'pdf_temp_id' => $pdfTempId,
            'extraccion' => $extraido,
            'cabecera' => $cabecera,
            'conceptos' => $conceptosAsignados,
            'advertencias' => $advertencias,
            'constatacion_arca' => $resueltoWscdc['constatacion_arca'] ?? null,
        ];
    }

    /**
     * Si hay un solo neto y un solo IVA y la suma no cierra con el total (p. ej. ARCA),
     * recalcula IVA = total − neto.
     *
     * @param  list<array<string, mixed>>  $conceptos
     * @return array{conceptos: list<array<string, mixed>>, advertencias: list<string>}
     */
    private function corregirIvaContraTotal(array $conceptos, float $total): array
    {
        $advertencias = [];
        if ($total <= 0 || count($conceptos) !== 2) {
            return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
        }

        $idxNeto = null;
        $idxIva = null;
        foreach ($conceptos as $i => $c) {
            $nombre = mb_strtolower(
                (string) (($c['concepto_nombre'] ?? '').' '.($c['descripcion_ia'] ?? ''))
            );
            $monto = abs((float) ($c['monto'] ?? 0));
            if ($monto <= 0) {
                continue;
            }
            if (str_contains($nombre, 'iva') && ! str_contains($nombre, 'compra')) {
                $idxIva = $i;
            } elseif (str_contains($nombre, 'compra') || str_contains($nombre, 'neto')
                || str_contains($nombre, 'gravado') || str_contains($nombre, 'subtotal')) {
                $idxNeto = $i;
            }
        }

        if ($idxNeto === null || $idxIva === null) {
            return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
        }

        $neto = round(abs((float) $conceptos[$idxNeto]['monto']), 2);
        $iva = round(abs((float) $conceptos[$idxIva]['monto']), 2);
        $suma = round($neto + $iva, 2);
        if (abs($suma - $total) <= 0.05) {
            return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
        }

        $ivaEsperado = round($total - $neto, 2);
        if ($ivaEsperado <= 0) {
            return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
        }

        // Solo corregir si el IVA esperado cierra con alguna tasa típica del neto.
        $ratio = $neto > 0 ? $ivaEsperado / $neto : 0.0;
        $tasaOk = false;
        foreach ([0.105, 0.21, 0.27] as $tasa) {
            if (abs($ratio - $tasa) <= 0.005) {
                $tasaOk = true;
                break;
            }
        }
        if (! $tasaOk) {
            return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
        }

        $conceptos[$idxIva]['monto'] = $ivaEsperado;
        $advertencias[] = sprintf(
            'IVA corregido a %s para cerrar con el total (%s − neto %s).',
            number_format($ivaEsperado, 2, ',', '.'),
            number_format($total, 2, ',', '.'),
            number_format($neto, 2, ',', '.')
        );

        return ['conceptos' => $conceptos, 'advertencias' => $advertencias];
    }

    /** @return list<array<string, mixed>> */
    private function conceptosCandidatos(int $empresaId): array
    {
        return Concepto_Ivacompra::query()
            ->with('impuestos:id,valor')
            ->where(function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            })
            ->orderBy('codigo')
            ->get()
            ->map(function (Concepto_Ivacompra $c): array {
                $alicuota = null;
                if ($c->impuestos && isset($c->impuestos->valor)) {
                    $alicuota = (float) $c->impuestos->valor;
                } else {
                    $texto = mb_strtolower((string) ($c->nombre_ia ?? '').' '.($c->nombre ?? ''));
                    if (preg_match('/\b(21|10[,.]5|27|5)\s*%/', $texto, $m)) {
                        $alicuota = (float) str_replace(',', '.', $m[1]);
                    }
                }

                return [
                    'id_concepto' => (int) $c->id,
                    'nombre' => (string) $c->nombre,
                    'descripcion_ai' => (string) ($c->nombre_ia ?? $c->nombre),
                    'tipoconcepto' => (string) $c->tipoconcepto,
                    'alicuota_iva' => $alicuota,
                ];
            })
            ->all();
    }
}
