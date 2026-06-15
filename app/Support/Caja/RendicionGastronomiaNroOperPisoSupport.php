<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Rangos dedicados de rendg_nro_oper por empresa (gastronomía ERP).
 */
final class RendicionGastronomiaNroOperPisoSupport
{
    public static function pisoParaEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $mapa = config('rendicion_gastronomia_anita.nro_oper_piso_por_empresa', []);

        return max(0, (int) ($mapa[$empresaId] ?? 0));
    }

    public static function techoParaEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $mapa = config('rendicion_gastronomia_anita.nro_oper_techo_por_empresa', []);

        return max(0, (int) ($mapa[$empresaId] ?? 0));
    }

    public static function enRangoEmpresa(int $empresaId, int $nroOper): bool
    {
        if ($nroOper <= 0) {
            return false;
        }

        $piso = self::pisoParaEmpresa($empresaId);
        $techo = self::techoParaEmpresa($empresaId);

        if ($piso > 0 && $nroOper < $piso) {
            return false;
        }

        if ($techo > 0 && $nroOper >= $techo) {
            return false;
        }

        return true;
    }

    public static function filtroSqlAnita(int $empresaId, string $columna = 'rendg_nro_oper'): string
    {
        $piso = self::pisoParaEmpresa($empresaId);
        $techo = self::techoParaEmpresa($empresaId);
        $partes = '';

        if ($piso > 0) {
            $partes .= " AND {$columna} >= '".$piso."' ";
        }

        if ($techo > 0) {
            $partes .= " AND {$columna} < '".$techo."' ";
        }

        return $partes;
    }
}
