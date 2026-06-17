<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Salida;
use App\Models\Configuracion\UsoSalidaImpresora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Impresoras alternativas cuando falla la salida configurada (mismo uso / uso del programa).
 */
class SalidaImpresionFallbackSupport
{
    /**
     * @return Collection<int, Salida>
     */
    public static function alternativasPorMismoUso(Salida $fallida, ?string $programa = null): Collection
    {
        $fallida->loadMissing('usoSalidaImpresoras');
        $programa = SeteoSalidaProgramaSupport::resolver($programa);

        if ($fallida->esUsoGenerico()) {
            return Salida::query()
                ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
                ->whereKeyNot($fallida->id)
                ->whereDoesntHave('usoSalidaImpresoras')
                ->orderBy('nombre')
                ->get();
        }

        $usosFallida = $fallida->usoSalidaImpresoras;
        $usosPrograma = $usosFallida->filter(
            fn (UsoSalidaImpresora $uso) => self::usoAplicaAPrograma($uso, $programa)
        );

        $usosReferencia = $usosPrograma->isNotEmpty() ? $usosPrograma : $usosFallida;
        $usoIds = $usosReferencia->pluck('id')->all();

        if ($usoIds === []) {
            return collect();
        }

        $usoIdsPrograma = $usosPrograma->pluck('id')->all();

        return Salida::query()
            ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
            ->whereKeyNot($fallida->id)
            ->whereHas('usoSalidaImpresoras', function (Builder $q) use ($usoIds) {
                $q->whereIn('uso_salida_impresora.id', $usoIds);
            })
            ->orderBy('nombre')
            ->get()
            ->sortBy(function (Salida $candidata) use ($usoIdsPrograma) {
                $prioridad = $usoIdsPrograma !== []
                    && $candidata->usoSalidaImpresoras->pluck('id')->intersect($usoIdsPrograma)->isNotEmpty()
                    ? '0'
                    : '1';

                return $prioridad.mb_strtolower((string) $candidata->nombre);
            })
            ->values();
    }

    public static function comandoImpresionValido(?Salida $salida): bool
    {
        if (! $salida instanceof Salida) {
            return false;
        }

        $comando = trim((string) $salida->comando);

        return $comando !== '' && str_contains($comando, '%s');
    }

    private static function usoAplicaAPrograma(UsoSalidaImpresora $uso, string $programa): bool
    {
        $destinos = array_values(array_filter((array) ($uso->programas_destino ?? [])));

        if ($destinos === []) {
            return false;
        }

        foreach (SalidaParaProgramaSupport::codigosCoincidentesPrograma($programa) as $codigo) {
            if (in_array($codigo, $destinos, true)) {
                return true;
            }
        }

        return false;
    }
}
