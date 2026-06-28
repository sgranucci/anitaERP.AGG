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

    public static function whereVendingRendicion(
        int $rendicionId,
        int $empresaId,
        int $sucursal,
        string $tipoOper,
        ?string $rendgHost = null,
    ): string {
        if ($rendicionId <= 0 || $empresaId <= 0) {
            return '';
        }

        $sql = " WHERE rendg_nro_rend_vta = '".$rendicionId."'"
            ." AND rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."' ";

        $host = trim((string) $rendgHost);
        if ($host !== '') {
            $hostSql = RendicionGastronomiaCabeceraAnitaMapper::texto($host, 15);
            $sql .= " AND (rendg_host = '".$hostSql."' OR rendg_host = '' OR rendg_host IS NULL) ";
        } else {
            $sql .= " AND (rendg_host = '' OR rendg_host IS NULL) ";
        }

        if ($sucursal > 0) {
            $sql .= " AND rendg_sucursal = '".$sucursal."' ";
        }

        return $sql;
    }
}
