<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownSlot;

final class TesoreriaCrownEscritorioNav
{
    /**
     * @return array{puedeMesas: bool, puedeBoveda: bool, puedeSlots: bool, puedePoder: bool, puedeAnexo: bool, asignaciones: int, mesas: int, borradoresMesa: int, movimientosBoveda: int, slots: int}
     */
    public static function armar(TesoreriaCrownJornada $jornada): array
    {
        $puedeMesas = can('listar-tesoreria-crown-mesa', false);
        $puedeBoveda = can('listar-tesoreria-crown-boveda', false);
        $puedeSlots = can('listar-tesoreria-crown-slot', false);
        $puedePoder = can('listar-tesoreria-crown-poder-publico', false);
        $puedeAnexo = can('listar-tesoreria-crown-anexo', false);

        return [
            'puedeMesas' => $puedeMesas,
            'puedeBoveda' => $puedeBoveda,
            'puedeSlots' => $puedeSlots,
            'puedePoder' => $puedePoder,
            'puedeAnexo' => $puedeAnexo,
            'asignaciones' => TesoreriaCrownAsignacion::query()->where('jornada_id', $jornada->id)->count(),
            'mesas' => $puedeMesas ? TesoreriaCrownMesaSupport::mesas((int) $jornada->sala_id)->count() : 0,
            'borradoresMesa' => $puedeMesas
                ? TesoreriaCrownMesa::query()->where('jornada_id', $jornada->id)->where('estado', TesoreriaCrownMesa::BORRADOR)->count()
                : 0,
            'movimientosBoveda' => $puedeBoveda
                ? TesoreriaCrownBoveda::query()->where('jornada_id', $jornada->id)->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)->count()
                : 0,
            'slots' => $puedeSlots
                ? TesoreriaCrownSlot::query()->where('jornada_id', $jornada->id)->count()
                : 0,
        ];
    }
}
