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
}
