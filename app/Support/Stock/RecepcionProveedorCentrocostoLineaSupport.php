<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Support\Compras\OrdencompraLineaEstados;

class RecepcionProveedorCentrocostoLineaSupport
{
    /**
     * Centro de costo destino de una línea de recepción (contabilidad/stock).
     * Prioridad: valor del ítem → CC destino línea OC → CC cabecera OC.
     */
    public static function resolverDesdeOcYItem(Ordencompra $oc, array $item): ?int
    {
        $ccItem = (int) ($item['centrocosto_id'] ?? 0);
        if ($ccItem > 0) {
            return $ccItem;
        }

        $ocArtId = (int) ($item['ordencompra_articulo_id'] ?? 0);
        $sustituidoId = (int) ($item['ordencompra_articulo_sustituido_id'] ?? 0);
        $claveOc = $ocArtId > 0 ? $ocArtId : $sustituidoId;

        if ($claveOc > 0) {
            $oc->loadMissing('ordencompra_articulos');
            $ocArt = $oc->ordencompra_articulos->firstWhere('id', $claveOc);
            $ccLinea = (int) ($ocArt->centrocostodestino_id ?? 0);
            if ($ccLinea > 0) {
                return $ccLinea;
            }
        }

        $ccOc = (int) ($oc->centrocosto_id ?? 0);

        return $ccOc > 0 ? $ccOc : null;
    }

    /**
     * @throws \RuntimeException si la OC no permite resolver CC en ninguna línea activa
     */
    public static function assertOcRecepcionable(Ordencompra $oc): void
    {
        $oc->loadMissing('ordencompra_articulos');
        $ccOc = (int) ($oc->centrocosto_id ?? 0);
        if ($ccOc > 0) {
            return;
        }

        $lineasActivasSinCc = $oc->ordencompra_articulos->filter(
            static function ($ocArt) {
                if ((string) ($ocArt->estado_linea_oc ?? OrdencompraLineaEstados::ACTIVA)
                    === OrdencompraLineaEstados::CERRADA) {
                    return false;
                }

                return (int) ($ocArt->centrocostodestino_id ?? 0) <= 0;
            }
        );

        if ($lineasActivasSinCc->isNotEmpty()) {
            throw new \RuntimeException(
                'Orden de compra '.(int) $oc->numeroordencompra
                .' sin centro de costo en cabecera ni en sus líneas activas. Corrija la OC antes de recepcionar.'
            );
        }
    }
}
