<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

/**
 * Cabecera reqmae (a-reqmae.c).
 */
final class ReqmaeCabeceraAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            reqm_nro,
            reqm_fecha,
            reqm_fecha_ent,
            reqm_deposito,
            reqm_emp_sueldos,
            reqm_legajo,
            reqm_ccosto,
            reqm_fecha_ing,
            reqm_hora_ing,
            reqm_usuario,
            reqm_estado,
            reqm_leyenda,
            reqm_deposito_alfa,
            reqm_empresa,
            reqm_proveedor,
            reqm_cod_mon,
            reqm_ccosto_dest,
            reqm_fecha_alfa,
            reqm_cond_pago,
            reqm_es_urgente,
            reqm_mot_urgencia,
            reqm_cont_directa
        ';
    }

    public static function valoresInsert(RequisicionAnitaSyncContext $ctx): string
    {
        $ccosto = $ctx->centrocostoCodigo();
        $ccostoDest = $ccosto;

        return '
            '.AnitaSqlLiteral::int($ctx->numeroRequisicion()).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaYmd()).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaYmd($ctx->requisicion->fechaentrega)).',
            0,
            0,
            0,
            '.AnitaSqlLiteral::int($ccosto).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaIngYmd()).',
            '.AnitaSqlLiteral::string($ctx->horaIngCabecera(), 8).',
            '.AnitaSqlLiteral::int($ctx->usuarioAnitaCodigo()).',
            '.AnitaSqlLiteral::char($ctx->estadoAnitaChar()).',
            '.AnitaSqlLiteral::string($ctx->leyendaCabecera(), 80).',
            '.AnitaSqlLiteral::string('', 30).',
            '.AnitaSqlLiteral::int($ctx->empresaCodigo()).',
            '.AnitaSqlLiteral::string($ctx->proveedorCodigo(), 6).',
            '.AnitaSqlLiteral::char($ctx->monedaCodigoAnitaChar()).',
            '.AnitaSqlLiteral::int($ccostoDest).',
            '.AnitaSqlLiteral::string($ctx->fechaAlfa(), 8).',
            '.AnitaSqlLiteral::int($ctx->condicionPagoCodigo()).',
            '.AnitaSqlLiteral::char($ctx->esUrgenteChar()).',
            '.AnitaSqlLiteral::string((string) $ctx->requisicion->motivotratamiento, 50).',
            '.AnitaSqlLiteral::char($ctx->contratacionDirectaChar()).'
        ';
    }

    public static function valoresUpdate(RequisicionAnitaSyncContext $ctx): string
    {
        $ccosto = $ctx->centrocostoCodigo();
        $ccostoDest = $ccosto;

        return '
            reqm_fecha = '.AnitaSqlLiteral::int((int) $ctx->fechaYmd()).',
            reqm_fecha_ent = '.AnitaSqlLiteral::int((int) $ctx->fechaYmd($ctx->requisicion->fechaentrega)).',
            reqm_ccosto = '.AnitaSqlLiteral::int($ccosto).',
            reqm_estado = '.AnitaSqlLiteral::char($ctx->estadoAnitaChar()).',
            reqm_leyenda = '.AnitaSqlLiteral::string($ctx->leyendaCabecera(), 80).',
            reqm_empresa = '.AnitaSqlLiteral::int($ctx->empresaCodigo()).',
            reqm_proveedor = '.AnitaSqlLiteral::string($ctx->proveedorCodigo(), 6).',
            reqm_cod_mon = '.AnitaSqlLiteral::char($ctx->monedaCodigoAnitaChar()).',
            reqm_ccosto_dest = '.AnitaSqlLiteral::int($ccostoDest).',
            reqm_fecha_alfa = '.AnitaSqlLiteral::string($ctx->fechaAlfa(), 8).',
            reqm_cond_pago = '.AnitaSqlLiteral::int($ctx->condicionPagoCodigo()).',
            reqm_es_urgente = '.AnitaSqlLiteral::char($ctx->esUrgenteChar()).',
            reqm_mot_urgencia = '.AnitaSqlLiteral::string((string) $ctx->requisicion->motivotratamiento, 50).',
            reqm_cont_directa = '.AnitaSqlLiteral::char($ctx->contratacionDirectaChar()).'
        ';
    }
}
