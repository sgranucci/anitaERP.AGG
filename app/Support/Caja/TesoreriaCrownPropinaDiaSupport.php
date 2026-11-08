<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownPropinaDia;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot diario de propina acumulada (equivalente a propina.prop_propina).
 * Nativo: Σ propina de estados finales F confirmados de la sala hasta la fecha.
 */
final class TesoreriaCrownPropinaDiaSupport
{
    public static function sincronizarDesdeMesas(TesoreriaCrownJornada $jornada): void
    {
        if (! Schema::hasTable('tesoreria_crown_propina_dia')) {
            return;
        }
        $fechaYmd = $jornada->fecha->format('Y-m-d');
        $salaId = (int) $jornada->sala_id;
        $monto = round((float) TesoreriaCrownMesa::query()
            ->where('tipo', 'F')
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereDoesntHave('reversa')
            ->whereHas('jornada', fn ($q) => $q
                ->where('sala_id', $salaId)
                ->whereDate('fecha', '<=', $fechaYmd))
            ->sum('propina'), 2);

        TesoreriaCrownPropinaDia::query()->updateOrCreate(
            ['sala_id' => $salaId, 'fecha' => $fechaYmd],
            [
                'monto' => $monto,
                'fuente' => TesoreriaCrownPropinaDia::FUENTE_ERP,
            ]
        );
        TesoreriaCrownAnexo8Calculo::invalidarCache($jornada);
    }
}
