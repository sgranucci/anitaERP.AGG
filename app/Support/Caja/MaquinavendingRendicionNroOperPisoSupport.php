<?php

namespace App\Support\Caja;

/**
 * Numeración global rendg_nro_oper vending (Informix Biyemas central).
 * Una sola secuencia para todas las empresas ERP mientras el cierre siga en Anita.
 */
final class MaquinavendingRendicionNroOperPisoSupport
{
    /**
     * @return list<int>
     */
    public static function empresaIdsVending(): array
    {
        $ids = config('rendicion_maquinavending_anita.empresa_ids', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $id) => $id > 0));
    }

    public static function pisoGlobal(): int
    {
        return max(0, (int) config('rendicion_maquinavending_anita.nro_oper_piso_global', 600001));
    }

    public static function techoGlobal(): int
    {
        return max(0, (int) config('rendicion_maquinavending_anita.nro_oper_techo_global', 0));
    }

    /** @deprecated Use pisoGlobal() — secuencia única cross-empresa. */
    public static function pisoParaEmpresa(int $empresaId): int
    {
        return self::pisoGlobal();
    }

    /** @deprecated Use techoGlobal() — secuencia única cross-empresa. */
    public static function techoParaEmpresa(int $empresaId): int
    {
        return self::techoGlobal();
    }

    public static function enRangoGlobal(int $nroOper): bool
    {
        $piso = self::pisoGlobal();
        $techo = self::techoGlobal();

        if ($piso > 0 && $nroOper < $piso) {
            return false;
        }
        if ($techo > 0 && $nroOper >= $techo) {
            return false;
        }

        return true;
    }

    /** @deprecated Use enRangoGlobal() */
    public static function enRangoEmpresa(int $empresaId, int $nroOper): bool
    {
        return self::enRangoGlobal($nroOper);
    }

    public static function filtroSqlGlobal(): string
    {
        $piso = self::pisoGlobal();
        $techo = self::techoGlobal();
        $sql = '';

        if ($piso > 0) {
            $sql .= " AND rendg_nro_oper >= '".$piso."' ";
        }
        if ($techo > 0) {
            $sql .= " AND rendg_nro_oper < '".$techo."' ";
        }

        return $sql;
    }

    /** @deprecated Use filtroSqlGlobal() */
    public static function filtroSqlAnita(int $empresaId): string
    {
        return self::filtroSqlGlobal();
    }

    /**
     * Restringe rendgastro vending ERP (host VENDING NRO.*) en consultas Anita.
     */
    public static function filtroSqlHostVending(): string
    {
        return " AND (rendg_host LIKE 'VENDING NRO%' OR rendg_host LIKE 'VEND NRO%') ";
    }
}
