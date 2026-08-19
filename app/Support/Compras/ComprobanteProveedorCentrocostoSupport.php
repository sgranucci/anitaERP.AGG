<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;

/**
 * Centro de costo de la factura / asiento de compras: destino de la OC.
 *
 * Prioridad: centrocostodestino_id de las líneas → cabecera (mismo valor que
 * Anita penmp_ccosto_dest al grabar). El default del proveedor no se usa si hay OC.
 */
final class ComprobanteProveedorCentrocostoSupport
{
    /**
     * @param  Comprobante_Proveedor|object|null  $comprobante
     */
    public static function resolverDesdeComprobante(?object $comprobante): int
    {
        $oc = $comprobante->ordencompras ?? null;
        $ccOc = self::resolverDesdeOc($oc instanceof Ordencompra || is_object($oc) ? $oc : null);
        if ($ccOc > 0) {
            return $ccOc;
        }

        // Solo sin OC (comprobante suelto).
        $ccProveedor = (int) ($comprobante->proveedores->centrocostocompra_id ?? 0);

        return $ccProveedor > 0 ? $ccProveedor : 1;
    }

    public static function resolverDesdeOc(?object $oc): int
    {
        if (! $oc) {
            return 0;
        }

        $lineas = $oc->ordencompra_articulos ?? null;
        if ($lineas !== null) {
            foreach ($lineas as $linea) {
                $ccLinea = (int) ($linea->centrocostodestino_id ?? 0);
                if ($ccLinea > 0) {
                    return $ccLinea;
                }
            }
        }

        // Cabecera local: al sync Anita se escribe en penmp_ccosto_dest.
        return (int) ($oc->centrocosto_id ?? 0);
    }
}
