<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Filtros SQL compartidos para idempotencia rendgastro/rendvalor.
 *
 * Gastronomía y estacionamiento son módulos ERP separados; solo comparten la tabla
 * Informix rendgastro. La clave de idempotencia por turno debe incluir rendg_host
 * (PC del turno), no solo rendg_nro_rend_vta, porque los IDs de turno_operativo
 * son autoincrementales independientes por módulo y pueden coincidir numéricamente.
 */
final class RendicionAnitaIdempotenciaWhereSupport
{
    public static function filtroHost(?string $rendgHost): string
    {
        $host = trim((string) $rendgHost);
        if ($host === '') {
            return '';
        }

        return " AND rendg_host = '".RendicionGastronomiaCabeceraAnitaMapper::texto($host, 15)."' ";
    }

    public static function whereTurnoOperativo(
        int $turnoOperativoId,
        int $empresaId,
        string $tipoOper,
        ?string $rendgHost = null,
    ): string {
        if ($turnoOperativoId <= 0 || $empresaId <= 0) {
            return '';
        }

        return " WHERE rendg_nro_rend_vta = '".$turnoOperativoId."'"
            ." AND rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."' "
            .self::filtroHost($rendgHost);
    }
}
