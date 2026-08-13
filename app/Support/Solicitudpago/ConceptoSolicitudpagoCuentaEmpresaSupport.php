<?php

namespace App\Support\Solicitudpago;

use Illuminate\Support\Facades\DB;

/**
 * Empresas que pueden tener cuentas en el concepto de SP.
 *
 * Budget / Temporal / BYSON no tienen usuarios en usuario_empresa: el ABM las
 * muestra como «sin empresa» (allFiltrado) y el sync Anita empresa 0 las
 * replicaba a todo el plan de cuentas.
 */
final class ConceptoSolicitudpagoCuentaEmpresaSupport
{
    /** @var list<int>|null */
    private static ?array $idsCache = null;

    /**
     * @return list<int>
     */
    public static function idsConUsuariosAsignados(): array
    {
        if (self::$idsCache !== null) {
            return self::$idsCache;
        }

        self::$idsCache = DB::table('usuario_empresa')
            ->distinct()
            ->pluck('empresa_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();

        return self::$idsCache;
    }

    public static function esOperativa(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        return in_array($empresaId, self::idsConUsuariosAsignados(), true);
    }
}
