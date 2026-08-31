<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Contrato_Venta;

/**
 * Arma la línea de factura desde un abono/contrato (prefill).
 */
final class ContratoVentaPrefillSupport
{
    /**
     * @param  array<string, string>  $valoresExtra
     * @return array{
     *   contrato_venta_id: int,
     *   concepto_venta_id: int|null,
     *   codigo: string,
     *   descripcion: string,
     *   plantilla: string,
     *   tags: list<array<string, mixed>>,
     *   valores: array<string, string>,
     *   precio: float|null,
     *   impuesto_id: int|null,
     *   periodo_desde: string,
     *   periodo_hasta: string,
     *   periodo_etiqueta: string,
     *   texto_preview: string
     * }
     */
    public static function armarLinea(
        Contrato_Venta $contrato,
        ?string $fechaFacturaYmd = null,
        array $valoresExtra = []
    ): array {
        if (! $contrato->relationLoaded('conceptoVenta')) {
            $contrato->load(['conceptoVenta.tags', 'conceptoVenta.impuesto', 'cliente', 'empresa', 'datos']);
        } else {
            if (! $contrato->relationLoaded('datos')) {
                $contrato->load('datos');
            }
            if ($contrato->conceptoVenta && ! $contrato->conceptoVenta->relationLoaded('tags')) {
                $contrato->conceptoVenta->load(['tags' => fn ($q) => $q->orderBy('orden')->orderBy('id')]);
            }
            if (! $contrato->relationLoaded('cliente')) {
                $contrato->load('cliente');
            }
            if (! $contrato->relationLoaded('empresa')) {
                $contrato->load('empresa');
            }
        }

        $concepto = $contrato->conceptoVenta;
        $fecha = substr(trim((string) ($fechaFacturaYmd ?: date('Y-m-d'))), 0, 10);
        if ($fecha === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            $fecha = date('Y-m-d');
        }

        $periodo = ContratoVentaSupport::periodoParaFecha(
            $fecha,
            (string) ($contrato->periodicidad ?? ContratoVentaSupport::PERIODICIDAD_MENSUAL)
        );

        $plantilla = trim((string) ($concepto->descripcion ?? ''));
        $tagsPedibles = ConceptoVentaTagSupport::tagsPediblesDesdeConcepto($concepto);
        $metas = ConceptoVentaTagSupport::metasDesdeTagsApi(
            ConceptoVentaTagSupport::tagsDesdeConcepto($concepto)
        );

        $valores = ContratoVentaSupport::datosFijosComoValores($contrato);
        $valores['periodo'] = ContratoVentaSupport::valorPeriodoTag($periodo);

        $sistema = ConceptoVentaPlantillaMotor::valoresSistema([
            'cliente_nombre' => $contrato->cliente->nombre ?? '',
            'cliente_documento' => $contrato->cliente->numerodocumento ?? '',
            'fecha_factura' => $fecha,
            'empresa_nombre' => $contrato->empresa->nombre ?? '',
            'codigo_concepto' => $concepto->codigo ?? '',
            'nombre_concepto' => $concepto->nombre ?? '',
        ]);
        // Stub: tags de sistema pueden quedar incompletos hasta facturar (cliente/cuit ya cargados).
        foreach ($sistema as $clave => $valor) {
            if ($valor !== '') {
                $valores[$clave] = $valor;
            }
        }

        foreach ($valoresExtra as $clave => $valor) {
            $claveN = ConceptoVentaPlantillaMotor::normalizarClave((string) $clave);
            if ($claveN === '') {
                continue;
            }
            $valores[$claveN] = trim((string) $valor);
        }

        $resuelto = ConceptoVentaPlantillaMotor::resolver($plantilla, $valores, $metas);

        return [
            'contrato_venta_id' => (int) $contrato->id,
            'concepto_venta_id' => $concepto ? (int) $concepto->id : null,
            'codigo' => (string) ($concepto->codigo ?? ''),
            'descripcion' => $plantilla,
            'plantilla' => $plantilla,
            'tags' => $tagsPedibles,
            'valores' => $resuelto['valores'],
            'precio' => $contrato->precio !== null ? (float) $contrato->precio : null,
            'impuesto_id' => $concepto && $concepto->impuesto_id ? (int) $concepto->impuesto_id : null,
            'periodo_desde' => $periodo['desde'],
            'periodo_hasta' => $periodo['hasta'],
            'periodo_etiqueta' => $periodo['etiqueta'],
            'texto_preview' => $resuelto['texto'],
        ];
    }
}
