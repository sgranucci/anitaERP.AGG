<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Caja\TesoreriaCrownValor;
use Illuminate\Support\Collection;

final class TesoreriaCrownSlotSupport
{
    /**
     * Recuento de slots: se puede cargar con la jornada abierta, o con la
     * jornada cerrada si la inmediata siguiente (+1 día calendario) de la
     * misma sala ya está abierta (operación CashBox / stacker del día anterior).
     */
    public static function permiteCarga(?TesoreriaCrownJornada $jornada): bool
    {
        if ($jornada === null) {
            return false;
        }
        if ($jornada->estaAbierta()) {
            return true;
        }
        $fecha = $jornada->fecha?->format('Y-m-d');
        if ($fecha === null) {
            return false;
        }
        $siguiente = $jornada->fecha->copy()->addDay()->format('Y-m-d');

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereDate('fecha', $siguiente)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->exists();
    }

    public static function mensajeSinCarga(): string
    {
        return 'La jornada está cerrada. El recuento se puede cargar si la jornada siguiente (+1 día) ya está abierta.';
    }

    /**
     * Destino de «Nuevo recuento» desde el menú: prioriza el día anterior
     * cuando la jornada abierta es la siguiente (caso operativo CashBox).
     */
    public static function jornadaPreferidaParaAlta(int $salaId): ?TesoreriaCrownJornada
    {
        $abierta = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->orderByDesc('fecha')
            ->first();
        if ($abierta === null) {
            return null;
        }
        $fechaPrev = $abierta->fecha->copy()->subDay()->format('Y-m-d');
        $anterior = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaPrev)
            ->first();
        if ($anterior !== null && self::permiteCarga($anterior)) {
            return $anterior;
        }

        return $abierta;
    }

    /**
     * Billetes de pesos para el billetaje del stacker (a-recslot).
     *
     * @return Collection<int, TesoreriaCrownValor>
     */
    public static function billetesPesos(int $salaId): Collection
    {
        return TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->whereHas('tipoValor', fn ($q) => $q->where('codigo', 'ARS'))
            ->orderByDesc('denominacion')
            ->get();
    }

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
