<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;

/**
 * Asigna penvp_orden y penvp_nro_interno en líneas ERP antes de escribir Anita.
 */
final class OrdencompraAnitaLineaSupport
{
    public static function asignarClavesLineas(Ordencompra $oc): void
    {
        $oc->loadMissing('ordencompra_articulos');

        $lineas = $oc->ordencompra_articulos
            ->sortBy([['penvp_orden', 'asc'], ['id', 'asc']])
            ->values();

        if ($lineas->isEmpty()) {
            throw new \RuntimeException('La orden de compra no tiene ítems para grabar en Anita.');
        }

        $siguienteInterno = OrdencompraAnitaNumeracionSupport::reservarSiguienteNroInterno();
        $orden = 0;

        foreach ($lineas as $linea) {
            $cambios = [];

            if ($linea->penvp_orden === null) {
                $cambios['penvp_orden'] = $orden;
            }

            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                $cambios['penvp_nro_interno'] = $siguienteInterno;
                $siguienteInterno++;
            }

            if ($cambios !== []) {
                Ordencompra_Articulo::query()
                    ->whereKey($linea->id)
                    ->where('ordencompra_id', $oc->id)
                    ->update($cambios);
                foreach ($cambios as $k => $v) {
                    $linea->{$k} = $v;
                }
            }

            $orden++;
        }

        $oc->unsetRelation('ordencompra_articulos');
        $oc->load('ordencompra_articulos');
    }

    /**
     * Anula penvp_nro_interno de líneas cuyo interno no está en el set válido de esta OC
     * (p. ej. copiados por error de otras OC) para que asignarClavesLineas reserve nuevos.
     *
     * @param  array<int, true>  $internosValidosEnEstaOc  penvp_nro_interno => true
     * @return array<int, array{antes: int, despues: int}> ordencompra_articulo.id => cambio
     */
    public static function liberarNroInternosInvalidos(Ordencompra $oc, array $internosValidosEnEstaOc): array
    {
        $oc->loadMissing('ordencompra_articulos');
        $cambios = [];

        foreach ($oc->ordencompra_articulos as $linea) {
            $nro = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nro <= 0) {
                continue;
            }
            if (isset($internosValidosEnEstaOc[$nro])) {
                continue;
            }

            Ordencompra_Articulo::query()
                ->whereKey($linea->id)
                ->where('ordencompra_id', $oc->id)
                ->update(['penvp_nro_interno' => null]);
            $linea->penvp_nro_interno = null;
            $cambios[(int) $linea->id] = ['antes' => $nro, 'despues' => 0];
        }

        if ($cambios !== []) {
            $oc->unsetRelation('ordencompra_articulos');
            $oc->load('ordencompra_articulos');
        }

        return $cambios;
    }
}
