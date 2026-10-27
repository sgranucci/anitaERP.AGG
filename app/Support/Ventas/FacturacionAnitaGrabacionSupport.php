<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Réplica a Anita de la facturación administrativa de El Bierzo
 * (pedido, remito, mostrador y la pata Villafranca).
 *
 * Otras instalaciones no leen el flag. En El Bierzo, false deja la factura
 * en el ERP y en ARCA sin escribir el bridge (venta, vencae, ctamov, pendmae,
 * numerador Villafranca).
 */
final class FacturacionAnitaGrabacionSupport
{
    public static function habilitada(): bool
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return true;
        }

        return (bool) config('facturacion.GRABA_ANITA_BRIDGE', false);
    }

    public static function logSiApagada(): void
    {
        static $logueado = false;
        if ($logueado || self::habilitada()) {
            return;
        }

        $logueado = true;
        Log::info('facturacion.anita_bridge.apagado', [
            'motivo' => 'BIERZO_FACTURACION_GRABA_ANITA=false',
        ]);
    }
}
