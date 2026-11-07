<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use Illuminate\Support\Facades\Log;

/**
 * Comprobantes aplicados a una OC en Anita (aplicped), para legajos cuya factura
 * o recepción vive solo en Anita (sin comprobante_proveedor / recepción en ERP).
 */
final class OrdencompraAplicpedAnitaSupport
{
    /**
     * Factura anticipada en Anita: aplicped con orden -1 o artículo «OC ANTICIPADA».
     */
    public static function tieneFacturaAnticipada(int $numeroOc): bool
    {
        $orden = RecepcionProveedorAnitaEscrituraSupport::APLICPED_ORDEN_ANTICIPADA;
        $articulo = str_replace("'", "''", RecepcionProveedorAnitaEscrituraSupport::APLICPED_ARTICULO_ANTICIPADA);
        $comTipo = (string) config('recepcion_proveedor.anita.recepcion_tipo', 'COM');

        return self::existe(
            $numeroOc,
            " and aplp_tipo<>'{$comTipo}' and (aplp_orden={$orden} or aplp_articulo='{$articulo}')"
        );
    }

    /**
     * Cualquier comprobante (factura o COM) aplicado a la OC en Anita.
     */
    public static function tieneComprobantes(int $numeroOc): bool
    {
        return self::existe($numeroOc, '');
    }

    private static function existe(int $numeroOc, string $filtroExtra): bool
    {
        if ($numeroOc <= 0) {
            return false;
        }

        $cfg = config('recepcion_proveedor.anita');

        try {
            $rows = json_decode((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['aplicacion_oc'],
                'campos' => 'aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro',
                'whereArmado' => " WHERE
                    aplp_ref_tipo='{$cfg['oc_tipo']}' and
                    aplp_ref_letra='{$cfg['oc_letra']}' and
                    aplp_ref_sucursal={$cfg['oc_sucursal']} and
                    aplp_ref_nro={$numeroOc}
                    {$filtroExtra}",
            ]));
        } catch (\Throwable $e) {
            Log::warning('ordencompra_aplicped_anita.lectura_fallo', [
                'numero_oc' => $numeroOc,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return is_array($rows) && $rows !== [];
    }
}
