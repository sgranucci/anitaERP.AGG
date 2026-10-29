<?php

declare(strict_types=1);

namespace App\Support\Caja\Frances;

use App\Models\Caja\Cuentacaja;

/**
 * Cuenta de caja que propone el archivo Francés: la cuenta corriente del banco,
 * no la de cheques ni la de otra moneda.
 */
final class FrancesArchivoPagoCuentaSupport
{
    public static function porDefecto(int $empresaId): ?Cuentacaja
    {
        $query = Cuentacaja::query()
            ->operativa()
            ->where('nombre', 'like', '%FRANCES%')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%CTA.CTE%')
                    ->orWhere('nombre', 'like', '%CTA CTE%');
            })
            ->paraEmpresa($empresaId)
            ->orderByRaw('CASE WHEN empresa_id = ? THEN 0 ELSE 1 END', [max(0, $empresaId)])
            ->orderByDesc('cbu')
            ->orderBy('codigo');

        return $query->first();
    }
}
