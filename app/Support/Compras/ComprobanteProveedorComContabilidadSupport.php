<?php

namespace App\Support\Compras;

use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Stock\Recepcion_Proveedor;

/**
 * Contabilidad de la COM (provisión facturas a recibir / GR valuado).
 *
 * Fuente de verdad operativa: configuracion_recepcion_proveedor.activa_contabilidad
 * (misma bandera que RecepcionProveedorAsientoService::debeGenerarAsiento).
 *
 * Argentina / SAP MM:
 * - ON  = recepción valuada: stock/gasto + Haber FAR; la factura revierte FAR + IVA + proveedor.
 * - OFF = recepción no valuada: COM solo logística/stock; la factura imputa neto + IVA + proveedor.
 */
final class ComprobanteProveedorComContabilidadSupport
{
    public static function generaAsientoCom(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        if (! config('recepcion_proveedor.contabilidad_activa')) {
            return false;
        }

        $cfg = Configuracion_RecepcionProveedor::query()
            ->where('empresa_id', $empresaId)
            ->value('activa_contabilidad');

        if ($cfg === null) {
            return (bool) config('recepcion_proveedor.contabilidad_activa');
        }

        return (bool) $cfg;
    }

    /**
     * Hay provisión contable a revertir solo si la COM generó (o debió generar) asiento.
     */
    public static function recepcionTieneProvisionContable(?Recepcion_Proveedor $recepcion): bool
    {
        if (! $recepcion) {
            return false;
        }

        if ((int) ($recepcion->asiento_id ?? 0) > 0) {
            return true;
        }

        return self::generaAsientoCom((int) ($recepcion->empresa_id ?? 0));
    }

    public static function persistir(int $empresaId, bool $activa): void
    {
        if ($empresaId <= 0) {
            return;
        }

        Configuracion_RecepcionProveedor::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            ['activa_contabilidad' => $activa],
        );
    }
}
