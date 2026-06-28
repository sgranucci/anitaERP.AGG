<?php

namespace App\Support\Caja;

use App\Models\Caja\InterbankingSaldoDiario;
use Carbon\Carbon;

/**
 * Resuelve saldo bancario persistido (Interbanking balances).
 *
 * En filas históricas suele venir solo day_balance; countable/current solo en el snapshot del día.
 */
final class InterbankingSaldoResolverSupport
{
    public static function saldoEnFecha(int $empresaId, string $accountNumber, Carbon $fechaHasta): float
    {
        $row = InterbankingSaldoDiario::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $accountNumber)
            ->whereDate('fecha', '<=', $fechaHasta->toDateString())
            ->orderByDesc('fecha')
            ->first([
                'countable_balance',
                'current_operating_balance',
                'day_balance',
            ]);

        if ($row === null) {
            return 0.0;
        }

        foreach (['countable_balance', 'current_operating_balance', 'day_balance'] as $campo) {
            $valor = $row->{$campo};
            if ($valor !== null && $valor !== '') {
                return round((float) $valor, 2);
            }
        }

        return 0.0;
    }
}
