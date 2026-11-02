<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownSlot;
use Illuminate\Support\Collection;

final class TesoreriaCrownSlotSupport
{
    /**
     * @return Collection<int, TesoreriaCrownSlot>
     */
    public static function delDia(TesoreriaCrownJornada $jornada, string $texto = ''): Collection
    {
        $query = TesoreriaCrownSlot::query()
            ->with(['lineas.valor.tipoValor'])
            ->where('jornada_id', $jornada->id)
            ->orderBy('maquina')
            ->orderBy('numero');
        $texto = trim($texto);
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('maquina', 'like', '%'.$texto.'%')
                    ->orWhere('maquina_nombre', 'like', '%'.$texto.'%');
                if (ctype_digit($texto)) {
                    $q->orWhere('numero', (int) $texto);
                }
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, TesoreriaCrownSlot>  $filas
     * @return array{drops: int, drop_total: float, recuentos: int, recuento_total: float}
     */
    public static function resumen(Collection $filas): array
    {
        $drops = $filas->where('tipo', TesoreriaCrownSlot::DROP);
        $recuentos = $filas->where('tipo', '!=', TesoreriaCrownSlot::DROP);

        return [
            'drops' => $drops->count(),
            'drop_total' => round((float) $drops->sum(fn (TesoreriaCrownSlot $fila) => (float) $fila->total), 2),
            'recuentos' => $recuentos->count(),
            'recuento_total' => round((float) $recuentos->sum(fn (TesoreriaCrownSlot $fila) => (float) $fila->total), 2),
        ];
    }
}
