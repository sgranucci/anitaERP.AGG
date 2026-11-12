<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sesión operativa de un cajero en una jornada (reemplaza la asignación caja–cajero).
 */
final class TesoreriaCrownDiaCajeroSupport
{
    /**
     * Filas del escritorio: cajeros con movimiento o cierre en la jornada.
     *
     * @return Collection<int, object>
     */
    public static function filasDeJornada(TesoreriaCrownJornada $jornada): Collection
    {
        $idsComp = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornada->id)
            ->whereNotNull('cajero_crown_id')
            ->distinct()
            ->pluck('cajero_crown_id');
        $idsCierre = TesoreriaCrownCierre::query()
            ->where('jornada_id', $jornada->id)
            ->whereNotNull('cajero_crown_id')
            ->distinct()
            ->pluck('cajero_crown_id');
        $ids = $idsComp->merge($idsCierre)->unique()->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $cajeros = CajeroCrown::query()
            ->whereIn('id', $ids)
            ->orderBy('codigo')
            ->get()
            ->keyBy('id');

        $comprobantes = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornada->id)
            ->whereIn('cajero_crown_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('cajero_crown_id');

        $cierres = TesoreriaCrownCierre::query()
            ->where('jornada_id', $jornada->id)
            ->whereIn('cajero_crown_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('cajero_crown_id');

        return $ids->map(function ($cajeroId) use ($cajeros, $comprobantes, $cierres, $jornada) {
            $cajero = $cajeros->get($cajeroId);
            if (! $cajero) {
                return null;
            }
            $docs = $comprobantes->get($cajeroId, collect());
            $listaCierres = $cierres->get($cajeroId, collect());
            $cierre = $listaCierres->firstWhere('reabierta_at', null);

            return (object) [
                'id' => 'c'.$cajero->id,
                'jornada_id' => $jornada->id,
                'sala_id' => $jornada->sala_id,
                'cajero_crown_id' => $cajero->id,
                'cajero' => $cajero,
                'tipo_tarea' => (string) ($cajero->tipo_tarea ?? ''),
                'permite_rendir_sin_habilitacion' => (bool) $cajero->permite_rendir_sin_habilitacion,
                'comprobantes' => $docs,
                'cierres' => $listaCierres,
                'cierre' => $cierre,
                'turno' => '',
                'deposito' => null,
            ];
        })->filter()->values();
    }

    public static function cierreAbierto(int $jornadaId, int $cajeroId): ?TesoreriaCrownCierre
    {
        return TesoreriaCrownCierre::query()
            ->where('jornada_id', $jornadaId)
            ->where('cajero_crown_id', $cajeroId)
            ->whereNull('reabierta_at')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TesoreriaCrownComprobante>
     */
    public static function comprobantes(int $jornadaId, int $cajeroId)
    {
        return TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornadaId)
            ->where('cajero_crown_id', $cajeroId)
            ->orderBy('id')
            ->get();
    }

    public static function cajeroDeComprobante(TesoreriaCrownComprobante $comprobante): ?CajeroCrown
    {
        if ($comprobante->relationLoaded('cajero') && $comprobante->cajero) {
            return $comprobante->cajero;
        }
        if ($comprobante->cajero_crown_id) {
            $comprobante->loadMissing('cajero');

            return $comprobante->cajero;
        }
        $comprobante->loadMissing('asignacion.cajero');

        return $comprobante->asignacion->cajero ?? null;
    }

    public static function tipoTareaDeComprobante(TesoreriaCrownComprobante $comprobante): string
    {
        $comprobante->loadMissing('asignacion.cajero');
        if ($comprobante->asignacion) {
            return $comprobante->asignacion->tipoTareaEfectiva();
        }
        $cajero = self::cajeroDeComprobante($comprobante);

        return trim((string) ($cajero->tipo_tarea ?? ''));
    }

    public static function permiteRendirSinHab(TesoreriaCrownComprobante $comprobante): bool
    {
        $cajero = self::cajeroDeComprobante($comprobante);
        if ($cajero) {
            return (bool) $cajero->permite_rendir_sin_habilitacion;
        }
        $comprobante->loadMissing('asignacion');

        return (bool) ($comprobante->asignacion->permite_rendir_sin_habilitacion ?? false);
    }
}
