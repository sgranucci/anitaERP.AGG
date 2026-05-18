<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use Illuminate\Support\Collection;

/**
 * Relaciona líneas de cuenta gastronómica con ítems de venta_emision (misma venta).
 */
final class GastronomiaVentaEmisionMapSupport
{
    /**
     * @param  Collection<int, CuentaGastronomiaLinea>  $lineasCuenta
     * @return array<int, int> cuenta_gastronomia_linea.id => venta_emision.id
     */
    public static function mapLineasCuentaAVentaEmision(Venta $venta, Collection $lineasCuenta): array
    {
        $emisiones = $venta->venta_emisiones
            ->sortBy('numeroitem')
            ->values();

        $usadas = [];
        $map = [];

        foreach ($lineasCuenta->sortBy('id') as $linea) {
            $articuloId = (int) ($linea->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $emision = self::primeraEmisionDisponible($emisiones, $usadas, $articuloId);
            if ($emision instanceof Venta_Emision) {
                $map[(int) $linea->id] = (int) $emision->id;
                $usadas[] = (int) $emision->id;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, Venta_Emision>  $emisiones
     * @param  list<int>  $usadas
     */
    private static function primeraEmisionDisponible(
        Collection $emisiones,
        array $usadas,
        int $articuloId,
    ): ?Venta_Emision {
        foreach ($emisiones as $em) {
            if (in_array((int) $em->id, $usadas, true)) {
                continue;
            }
            if ((int) ($em->articulo_id ?? 0) === $articuloId) {
                return $em;
            }
        }

        foreach ($emisiones as $em) {
            if (! in_array((int) $em->id, $usadas, true)) {
                return $em;
            }
        }

        return null;
    }
}
