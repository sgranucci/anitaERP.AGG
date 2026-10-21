<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Support\Contable\CuentaCentrocostoAsignadosSupport;

/**
 * Centro de costo de la factura / asiento de compras: destino de la OC.
 *
 * Prioridad: centrocostodestino_id de las líneas → cabecera origen.
 * Al grabar Anita, penmp_ccosto_dest usa esta misma prioridad (no copiar el origen).
 * La tolerancia factura vs COM también toma este CC (no el origen de cabecera).
 * El default del proveedor no se usa si hay OC.
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

        // Solo sin OC (comprobante suelto). Sin CC del proveedor → 0 (no inventar id 1).
        $ccProveedor = (int) ($comprobante->proveedores?->centrocostocompra_id ?? 0);

        return $ccProveedor > 0 ? $ccProveedor : 0;
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

        return (int) ($oc->centrocosto_id ?? 0);
    }

    /**
     * Impuestos, percepciones, IVA y la cuenta del proveedor.
     *
     * a-compprov.c graba la contrapartida del proveedor con subd_ccosto_contrap = 0.
     * La cuenta del impuesto toma centro solo si CCOSV_asigna_ccosto le encuentra uno
     * propio (ccosvalid), aunque el flag manejaccosto esté en N. Si no tiene ninguno,
     * queda en 0. No hereda centrocostocompra del proveedor (en CABIFY ese default es
     * 85 Gastronomía). IVA crédito fiscal indirecto no tiene centro en Anita.
     */
    public static function paraCuentaSinGasto(int $cuentaId): int
    {
        return CuentaCentrocostoAsignadosSupport::unicoAsignado($cuentaId);
    }
}
