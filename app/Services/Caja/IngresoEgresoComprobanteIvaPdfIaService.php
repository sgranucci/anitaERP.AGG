<?php

namespace App\Services\Caja;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor;
use App\Services\Compras\ComprobanteProveedorPdfIaPipelineService;
use App\Support\Compras\PrecargaProveedor\ComprobanteProveedorPdfIaConceptoMatcherSupport;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * OCR/IA de PDF para comprobantes IVA en ingreso/egreso (sin OC obligatoria).
 */
class IngresoEgresoComprobanteIvaPdfIaService
{
    public function __construct(
        private ComprobanteProveedorPdfIaPipelineService $pipeline,
        private ComprobanteProveedorPdfIaConceptoMatcherSupport $conceptoMatcher,
        private IngresoEgresoComprobanteIvaArchivoService $archivoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $pdf, int $empresaId): array
    {
        $this->assertHabilitado();

        $pdfTempId = $this->archivoService->guardarTempDesdeUpload($pdf);

        $extraido = $this->pipeline->extraer($pdf);
        $cuit = preg_replace('/\D+/', '', (string) ($extraido['cuit_proveedor'] ?? '')) ?? '';

        $proveedor = null;
        if ($cuit !== '') {
            $proveedor = Proveedor::query()
                ->whereRaw("REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '') = ?", [$cuit])
                ->first();
        }

        $candidatos = $this->conceptosCandidatos($empresaId);
        $lineasIa = $extraido['lineas'] ?? [];
        $conceptosAsignados = [];
        $advertencias = [];

        try {
            $matcheados = $this->conceptoMatcher->matchear($candidatos, is_array($lineasIa) ? $lineasIa : []);
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
            foreach (is_array($lineasIa) ? $lineasIa : [] as $linea) {
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
        $nro = (int) (is_array($numero) ? ($numero['numero'] ?? 0) : ($extraido['numero'] ?? 0));

        return [
            'ok' => true,
            'pdf_temp_id' => $pdfTempId,
            'extraccion' => $extraido,
            'cabecera' => [
                'proveedor_id' => $proveedor?->id,
                'proveedor_nombre' => $proveedor?->nombre ?? ($extraido['razon_social_proveedor'] ?? $extraido['proveedor_nombre'] ?? ''),
                'proveedor_documento_eventual' => $cuit,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numerocomprobante' => $nro,
                'fechacomprobante' => $extraido['fecha_factura'] ?? $extraido['fecha'] ?? date('Y-m-d'),
                'fechaiva' => $extraido['fecha_factura'] ?? $extraido['fecha'] ?? date('Y-m-d'),
                'total' => (float) ($extraido['total'] ?? 0),
                'numerocae' => $extraido['cae'] ?? null,
                'fechavencimientocae' => $extraido['vencimiento_cae'] ?? null,
            ],
            'conceptos' => $conceptosAsignados,
            'advertencias' => $advertencias,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function conceptosCandidatos(int $empresaId): array
    {
        return Concepto_Ivacompra::query()
            ->where(function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            })
            ->orderBy('codigo')
            ->get()
            ->map(static fn (Concepto_Ivacompra $c): array => [
                'id_concepto' => (int) $c->id,
                'nombre' => (string) $c->nombre,
                'descripcion_ai' => (string) ($c->nombre_ia ?? $c->nombre),
                'tipoconcepto' => (string) $c->tipoconcepto,
            ])
            ->all();
    }

    private function assertHabilitado(): void
    {
        if (! config('comprobante_proveedor_pdf_ia.habilitado', true)) {
            throw new RuntimeException('La lectura PDF por IA no está habilitada.');
        }
    }
}
