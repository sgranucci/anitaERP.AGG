<?php

namespace App\Support\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;

/**
 * Resuelve cuentacaja → cuentacontable de la empresa del asiento.
 *
 * Muchas cuentas de caja apuntan al plan de otra empresa (legado Anita);
 * si el vínculo no coincide, homologa por el mismo código contable.
 */
final class CuentacajaCuentacontableResolverSupport
{
    public static function resolverIdParaEmpresa(Cuentacaja $caja, int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $cc = $caja->relationLoaded('cuentacontables')
            ? $caja->cuentacontables
            : $caja->cuentacontables()->first(['id', 'codigo', 'nombre', 'empresa_id']);

        if ($cc === null) {
            $fallbackId = (int) ($caja->cuentacontable_id ?? 0);
            if ($fallbackId <= 0) {
                return null;
            }
            $cc = Cuentacontable::query()->find($fallbackId, ['id', 'codigo', 'nombre', 'empresa_id']);
            if ($cc === null) {
                return null;
            }
        }

        if ((int) ($cc->empresa_id ?? 0) === $empresaId) {
            return (int) $cc->id;
        }

        $codigo = trim((string) ($cc->codigo ?? ''));
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
