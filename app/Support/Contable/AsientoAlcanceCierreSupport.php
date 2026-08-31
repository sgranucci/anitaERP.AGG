<?php

namespace App\Support\Contable;

use App\Models\Stock\MovimientoStock;

/**
 * Alcance de cierre contable que corresponde a un asiento.
 *
 * El alcance lo define el **documento que origina** el asiento (cobranza, movimiento de caja,
 * venta, recepción, factura de proveedor, movimiento de stock). Un asiento cargado desde el
 * ABM de asientos es siempre "Asientos contables manuales", cualquiera sea su tipo de asiento:
 * el tipo (VTA, TES, COM, STK…) es una clasificación contable, no el circuito que lo generó.
 */
class AsientoAlcanceCierreSupport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function inferir(array $data): string
    {
        if (! empty($data['cobranza_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_COBRANZA;
        }

        if (! empty($data['caja_movimiento_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_CAJA;
        }

        if (! empty($data['movimientostock_id'])) {
            $abreviatura = strtoupper((string) (
                MovimientoStock::query()
                    ->whereKey((int) $data['movimientostock_id'])
                    ->with('tipotransaccion_stock:id,abreviatura')
                    ->first()
                    ?->tipotransaccion_stock
                    ?->abreviatura
                ?? ''
            ));

            return $abreviatura === 'EIND'
                ? PeriodoContableCierreSupport::ALCANCE_INDUMENTARIA
                : PeriodoContableCierreSupport::ALCANCE_STOCK;
        }

        if (! empty($data['venta_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_FACTURACION;
        }

        if (! empty($data['recepcionproveedor_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR;
        }

        if (! empty($data['comprobante_proveedor_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR;
        }

        if (! empty($data['liquidacion_sueldos_id'])) {
            return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
        }

        return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
    }
}
