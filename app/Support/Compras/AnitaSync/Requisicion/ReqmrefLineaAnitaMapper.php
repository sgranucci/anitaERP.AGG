<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Models\Compras\Requisicion_Articulo;

/**
 * Referencia presupuesto reqmref (partida / capex por línea).
 */
final class ReqmrefLineaAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            reqr_nro_requi,
            reqr_fecha,
            reqr_partida,
            reqr_presupuesto,
            reqr_escenario,
            reqr_proyecto,
            reqr_mes,
            reqr_cod_proyecto,
            reqr_empresa,
            reqr_usuario_autor,
            reqr_fecha_ing,
            reqr_hora_ing,
            reqr_usuario_carga,
            reqr_leyenda,
            reqr_concepto,
            reqr_cta_contable,
            reqr_ccosto,
            reqr_importe,
            reqr_cod_mon,
            reqr_nro_orden,
            reqr_nro_interno
        ';
    }

    public static function valoresInsert(
        RequisicionAnitaSyncContext $ctx,
        Requisicion_Articulo $linea,
        int $nroOrden,
        int $nroInterno,
    ): string {
        $partida = $linea->partidagastos;
        $capex = $linea->capexs;
        $articulo = $linea->articulos;

        $partidaCodigo = (int) ($partida?->codigo ?? 0);
        $presupuestoCodigo = (int) ($partida?->presupuestos?->codigo ?? $capex?->presupuestos?->codigo ?? 0);
        $escenarioCodigo = (int) ($partida?->presupuesto_escenarios?->codigo ?? 0);
        $proyectoCodigo = (int) ($capex?->codigo ?? 0);
        $codProyecto = trim((string) ($capex?->codigoproyecto ?? ''));
        $mes = 0;

        $importe = (float) $linea->cantidad * (float) $linea->precio;
        $concepto = $ctx->articuloSkuPadded($articulo?->sku ?? '');
        $leyenda = trim((string) ($partida?->detalle ?? $linea->detalle ?? ''));
        $ctaContable = (int) ($partida?->cuentacontables?->codigo ?? 0);

        return '
            '.AnitaSqlLiteral::int($ctx->numeroRequisicion()).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaYmd()).',
            '.AnitaSqlLiteral::int($partidaCodigo).',
            '.AnitaSqlLiteral::int($presupuestoCodigo).',
            '.AnitaSqlLiteral::int($escenarioCodigo).',
            '.AnitaSqlLiteral::int($proyectoCodigo).',
            '.AnitaSqlLiteral::int($mes).',
            '.AnitaSqlLiteral::string($codProyecto, 20).',
            '.AnitaSqlLiteral::int($ctx->empresaCodigo()).',
            0,
            '.AnitaSqlLiteral::int((int) $ctx->fechaIngYmd()).',
            '.AnitaSqlLiteral::string($ctx->horaIngRef(), 5).',
            '.AnitaSqlLiteral::int($ctx->usuarioAnitaCodigo()).',
            '.AnitaSqlLiteral::string($leyenda, 128).',
            '.AnitaSqlLiteral::string(trim($concepto), 13).',
            '.AnitaSqlLiteral::int($ctaContable).',
            '.AnitaSqlLiteral::int($ctx->centrocostoCodigoLinea($linea)).',
            '.AnitaSqlLiteral::decimal($importe).',
            '.AnitaSqlLiteral::char($ctx->monedaCodigoAnitaChar($linea->moneda_id)).',
            '.AnitaSqlLiteral::int($nroOrden).',
            '.AnitaSqlLiteral::int($nroInterno).'
        ';
    }

    public static function tieneDatosPresupuesto(Requisicion_Articulo $linea): bool
    {
        if ($linea->partidagasto_id || $linea->capex_id) {
            return true;
        }

        $partida = $linea->partidagastos;
        if ($partida && (int) ($partida->codigo ?? 0) > 0) {
            return true;
        }

        $capex = $linea->capexs;
        if ($capex && ((int) ($capex->codigo ?? 0) > 0 || trim((string) ($capex->codigoproyecto ?? '')) !== '')) {
            return true;
        }

        return false;
    }
}
