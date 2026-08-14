<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;

/**
 * Homologa una cuenta legacy al plan contable de la empresa por código.
 */
final class CuentacontableEmpresaResolverSupport
{
    public static function resolverIdDesdeId(int $cuentacontableId, int $empresaId): ?int
    {
        if ($cuentacontableId <= 0 || $empresaId <= 0) {
            return null;
        }

        $cuenta = Cuentacontable::query()->find(
            $cuentacontableId,
            ['id', 'codigo', 'empresa_id']
        );
        if ($cuenta === null) {
            return null;
        }

        if ((int) ($cuenta->empresa_id ?? 0) === $empresaId) {
            return (int) $cuenta->id;
        }

        $codigo = trim((string) ($cuenta->codigo ?? ''));
        if ($codigo === '') {
            return null;
        }

        $homologada = (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        return $homologada > 0 ? $homologada : null;
    }
}
