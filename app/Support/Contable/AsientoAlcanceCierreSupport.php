<?php

namespace App\Support\Contable;

use App\Models\Stock\MovimientoStock;

/**
 * Alcance de cierre contable que corresponde a un asiento.
 *
 * El alta lo define el documento que origina el asiento (cobranza, caja, venta, recepción,
 * factura de proveedor, stock). Un asiento cargado desde el ABM es "Asientos contables
 * manuales": el tipo (VTA, TES, COM, STK…) clasifica, no es el circuito que lo generó.
 *
 * Corregir o borrar un asiento ya existente depende solo del cierre de Contable.
 * Si Contable sigue abierto, se puede modificar el asiento aunque Compras, Ventas
 * u otro subsistema ya esté cerrado.
 */
class AsientoAlcanceCierreSupport
{
    /**
     * Alcance que debe validar la grabación del asiento.
     *
     * @param  array<string, mixed>  $data
     */
    public static function alcanceParaValidar(array $data, bool $esModificacion): string
    {
        if ($esModificacion) {
            return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
        }

        $explicito = trim((string) ($data['alcance_cierre_contable'] ?? ''));
        if ($explicito !== '' && PeriodoContableCierreSupport::alcanceEsValido($explicito)) {
            return $explicito;
        }

        return self::inferir($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function tieneAlcanceExplicito(array $data): bool
    {
        $explicito = trim((string) ($data['alcance_cierre_contable'] ?? ''));

        return $explicito !== '' && PeriodoContableCierreSupport::alcanceEsValido($explicito);
    }

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
