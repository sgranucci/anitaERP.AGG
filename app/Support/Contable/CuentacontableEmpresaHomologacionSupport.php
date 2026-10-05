<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;

/**
 * Lleva un id de cuenta al plan de la empresa del asiento, por el mismo código.
 *
 * El maestro de caja y varias imputaciones siguen apuntando al plan de otra
 * empresa (legado). El código contable es el mismo; el id no.
 */
final class CuentacontableEmpresaHomologacionSupport
{
    /** @var array<string, int> */
    private static array $cache = [];

    public static function idParaEmpresa(int $cuentacontableId, int $empresaId): int
    {
        if ($cuentacontableId <= 0 || $empresaId <= 0) {
            return $cuentacontableId;
        }

        $cacheKey = $empresaId.'|'.$cuentacontableId;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $cuenta = Cuentacontable::query()->find($cuentacontableId, ['id', 'empresa_id', 'codigo']);
        if ($cuenta === null || (int) $cuenta->empresa_id === $empresaId) {
            return self::$cache[$cacheKey] = $cuentacontableId;
        }

        $codigo = trim((string) $cuenta->codigo);
        if ($codigo === '') {
            return self::$cache[$cacheKey] = $cuentacontableId;
        }

        $destino = (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->where('tipocuenta', 1)
            ->orderBy('id')
            ->value('id') ?? 0);

        return self::$cache[$cacheKey] = $destino > 0 ? $destino : $cuentacontableId;
    }
}
