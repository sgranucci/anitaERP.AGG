<?php

namespace App\Support\Caja\Estacionamiento;

use Illuminate\Support\Collection;

class ListaPrecioEstacionamientoItemVigenteSupport
{
    /**
     * @param  Collection<int, \App\Models\Caja\Estacionamiento\ListaPrecioEstacionamientoItem>  $lineas
     */
    public static function resolverVigente(Collection $lineas, ?string $fechaReferencia = null): ?object
    {
        if ($lineas->isEmpty()) {
            return null;
        }

        $fecha = $fechaReferencia ?? now()->toDateString();

        $hastaHoy = $lineas->filter(function ($linea) use ($fecha) {
            $fv = $linea->fecha_vigencia;
            if ($fv instanceof \DateTimeInterface) {
                return $fv->format('Y-m-d') <= $fecha;
            }

            return substr((string) $fv, 0, 10) <= $fecha;
        })->sortByDesc(function ($linea) {
            $fv = $linea->fecha_vigencia;

            return $fv instanceof \DateTimeInterface ? $fv->format('Y-m-d') : substr((string) $fv, 0, 10);
        });

        if ($hastaHoy->isNotEmpty()) {
            return $hastaHoy->first();
        }

        return $lineas->sortBy(function ($linea) {
            $fv = $linea->fecha_vigencia;

            return $fv instanceof \DateTimeInterface ? $fv->format('Y-m-d') : substr((string) $fv, 0, 10);
        })->first();
    }

    /**
     * @param  Collection<int, \App\Models\Caja\Estacionamiento\ListaPrecioEstacionamientoItem>  $lineas
     * @return Collection<int, \App\Models\Caja\Estacionamiento\ListaPrecioEstacionamientoItem>
     */
    public static function historialOrdenado(Collection $lineas, ?string $fechaReferencia = null): Collection
    {
        $vigente = self::resolverVigente($lineas, $fechaReferencia);
        $vigenteId = $vigente?->id;

        return $lineas
            ->sortByDesc(function ($linea) {
                $fv = $linea->fecha_vigencia;

                return $fv instanceof \DateTimeInterface ? $fv->format('Y-m-d') : substr((string) $fv, 0, 10);
            })
            ->values()
            ->map(function ($linea) use ($vigenteId, $fechaReferencia) {
                $linea->es_vigente_actual = $vigenteId !== null && (int) $linea->id === (int) $vigenteId;

                return $linea;
            });
    }
}
