<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Support\Stock\ReporteStockOtSituacionSupport;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Saca la tarea Terminada stock cuando la OT sigue en planta.
 * Una OT de stock que nació cerrada (sin avance de fabricación) no se toca.
 */
final class QuitarTerminadaStockOtSupport
{
    public function __construct(
        private Ordentrabajo_TareaRepositoryInterface $ordentrabajoTareaRepository,
    ) {
    }

    public function sePuedeQuitar(Ordentrabajo $ordentrabajo): bool
    {
        $tareas = $this->tareas($ordentrabajo);

        return $this->motivoBloqueo($ordentrabajo, $tareas) === null
            && $this->tareasTerminadaStock($tareas)->isNotEmpty();
    }

    public function quitar(int $ordentrabajoId): string
    {
        $ordentrabajo = Ordentrabajo::query()->find($ordentrabajoId);
        if (! $ordentrabajo) {
            throw new RuntimeException('La orden de trabajo no existe.');
        }

        $tareas = $this->tareas($ordentrabajo);
        $motivo = $this->motivoBloqueo($ordentrabajo, $tareas);
        if ($motivo !== null) {
            throw new RuntimeException($motivo);
        }

        $stock = $this->tareasTerminadaStock($tareas);
        if ($stock->isEmpty()) {
            throw new RuntimeException('Esta OT no tiene Terminada stock.');
        }

        foreach ($stock as $tarea) {
            $this->ordentrabajoTareaRepository->delete($tarea->id);
        }

        return 'Se quitó Terminada stock de la OT '.$ordentrabajo->codigo.'. Sigue en fabricación.';
    }

    /**
     * @param  Collection<int, Ordentrabajo_Tarea>  $tareas
     */
    private function motivoBloqueo(Ordentrabajo $ordentrabajo, Collection $tareas): ?string
    {
        $facturadaId = (int) config('consprod.TAREA_FACTURADA');
        foreach ($tareas as $tarea) {
            if ((int) $tarea->tarea_id === $facturadaId) {
                return 'La OT ya está facturada. No se puede quitar Terminada stock.';
            }
        }

        if ($this->esOtStockNacidaCerrada($ordentrabajo, $tareas)) {
            return 'Es una OT de stock cerrada al generarla. No se quita Terminada stock.';
        }

        return null;
    }

    /**
     * @param  Collection<int, Ordentrabajo_Tarea>  $tareas
     */
    private function esOtStockNacidaCerrada(Ordentrabajo $ordentrabajo, Collection $tareas): bool
    {
        if (strtoupper(trim((string) $ordentrabajo->tipoot)) !== 'S') {
            return false;
        }

        $sinAvance = ReporteStockOtSituacionSupport::idsTareasSinAvanceFabricacion();
        foreach ($tareas as $tarea) {
            if (! in_array((int) $tarea->tarea_id, $sinAvance, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, Ordentrabajo_Tarea>
     */
    private function tareas(Ordentrabajo $ordentrabajo): Collection
    {
        if ($ordentrabajo->relationLoaded('ordentrabajo_tareas')) {
            return $ordentrabajo->ordentrabajo_tareas;
        }

        return $ordentrabajo->ordentrabajo_tareas()->get();
    }

    /**
     * @param  Collection<int, Ordentrabajo_Tarea>  $tareas
     * @return Collection<int, Ordentrabajo_Tarea>
     */
    private function tareasTerminadaStock(Collection $tareas): Collection
    {
        $tareaId = (int) config('consprod.TAREA_TERMINADA_STOCK');

        return $tareas->filter(function ($tarea) use ($tareaId) {
            return (int) $tarea->tarea_id === $tareaId;
        })->values();
    }
}
