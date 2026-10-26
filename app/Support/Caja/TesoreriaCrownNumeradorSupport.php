<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownNumerador;
use Illuminate\Database\QueryException;

final class TesoreriaCrownNumeradorSupport
{
    public static function siguiente(int $salaId, string $tipo): int
    {
        $fila = TesoreriaCrownNumerador::query()
            ->where('sala_id', $salaId)
            ->where('tipo', $tipo)
            ->lockForUpdate()
            ->first();
        if ($fila === null) {
            try {
                $fila = TesoreriaCrownNumerador::query()->create([
                    'sala_id' => $salaId,
                    'tipo' => $tipo,
                    'ultimo' => 0,
                ]);
            } catch (QueryException) {
                $fila = TesoreriaCrownNumerador::query()
                    ->where('sala_id', $salaId)
                    ->where('tipo', $tipo)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $fila = TesoreriaCrownNumerador::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
        }
        $fila->ultimo = (int) $fila->ultimo + 1;
        $fila->save();

        return (int) $fila->ultimo;
    }

    public static function visible(?int $numero): string
    {
        if ($numero === null || $numero <= 0) {
            return '';
        }

        return str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }
}
