<?php

namespace App\Support\Compras\AnitaSync\Surmar;

use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaSyncContext;

/**
 * pendmovp Surmar → ordencompra_articulo (+ lote_transferencia / peso_unitario).
 */
final class SurmarArticuloLineaFieldMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function mapAll(
        object $linea,
        object $cabecera,
        OrdencompraAnitaSyncContext $ctx,
        int $ordencompraId,
    ): array {
        $ordenRaw = $linea->penvp_orden ?? null;
        $orden = ($ordenRaw === null || $ordenRaw === '') ? null : (int) $ordenRaw;
        $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
        $lote = (int) ($linea->penvp_lote_transf ?? 0);
        $peso = isset($linea->penvp_peso_unit) ? (float) $linea->penvp_peso_unit : null;
        $cantidad = (float) ($linea->penvp_cantidad ?? 0);
        $pesoUnit = ($peso !== null && abs($peso) > 0.0000001) ? $peso : null;

        $codMon = $linea->penvp_cod_mon ?? $cabecera->penmp_cod_mon ?? null;
        $cot = (float) ($cabecera->penmp_cotizacion ?? 1);

        return [
            'ordencompra_id' => $ordencompraId,
            'penvp_orden' => $orden,
            'penvp_nro_interno' => $nroInterno > 0 ? $nroInterno : null,
            'lote_transferencia' => $lote > 0 ? $lote : null,
            'peso_unitario' => $pesoUnit,
            'peso_total' => ($pesoUnit !== null && $cantidad > 0) ? round($pesoUnit * $cantidad, 6) : null,
            'fechaentrega' => $ctx->fechaYmd($linea->penvp_fecha_ent ?? null),
            'articulo_id' => $ctx->fkArticuloSku($linea->penvp_articulo ?? null),
            'cantidad' => $cantidad,
            'precio' => (float) ($linea->penvp_precio ?? 0),
            'moneda_id' => $ctx->fkMoneda($codMon),
            'cotizacion' => $cot > 0 ? $cot : 1.0,
            'descuento' => isset($linea->penvp_dto_art) ? (float) $linea->penvp_dto_art : null,
            'cantidadalternativa' => 0.0,
            'detalle' => trim((string) ($linea->penvp_desc ?? '')),
            'centrocostodestino_id' => $ctx->fkCentrocosto($linea->penvp_ccosto ?? null),
            'partidagasto_id' => null,
            'capex_id' => null,
        ];
    }
}
