<?php

namespace App\Support\Caja;

final class MaquinavendingRendicionNroOperPisoSupport
{
    public static function pisoParaEmpresa(int $empresaId): int
    {
        $map = config('rendicion_maquinavending_anita.nro_oper_piso_por_empresa', []);

        return (int) ($map[$empresaId] ?? 0);
    }

    public static function techoParaEmpresa(int $empresaId): int
    {
        $map = config('rendicion_maquinavending_anita.nro_oper_techo_por_empresa', []);

        return (int) ($map[$empresaId] ?? 0);
    }

    public static function enRangoEmpresa(int $empresaId, int $nroOper): bool
    {
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

    public static function filtroSqlAnita(int $empresaId): string
    {
        $piso = self::pisoParaEmpresa($empresaId);
        $techo = self::techoParaEmpresa($empresaId);
        $sql = '';

        if ($piso > 0) {
            $sql .= " AND rendg_nro_oper >= '".$piso."' ";
        }
        if ($techo > 0) {
            $sql .= " AND rendg_nro_oper < '".$techo."' ";
        }

        return $sql;
    }
}
