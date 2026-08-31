<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;

/**
 * Evita facturar dos veces la misma orden Waitry (tótem ya cobrado o reimportación).
 */
final class WaitryFacturacionDuplicadosSupport
{
    public static function waitryOrderIdYaFacturado(int $waitryOrderId, ?int $excluirCuentaId = null): bool
    {
        if ($waitryOrderId <= 0) {
            return false;
        }

        if (VentaGastronomiaEmision::query()->where('waitry_order_id', $waitryOrderId)->exists()) {
            return true;
        }

        // Factura CF del proceso: waitry_order_id queda null; los IDs viven en waitry_comandas_json.
        if (VentaGastronomiaEmision::query()
            ->whereNotNull('waitry_comandas_json')
            ->whereJsonContains('waitry_comandas_json', ['waitry_order_id' => $waitryOrderId])
            ->exists()) {
            return true;
        }

        $q = CuentaGastronomia::query()
            ->where('waitry_order_id', $waitryOrderId)
            ->where('estado', CuentaGastronomia::ESTADO_FACTURADA)
            ->whereNotNull('venta_id');

        if ($excluirCuentaId !== null && $excluirCuentaId > 0) {
            $q->where('id', '!=', $excluirCuentaId);
        }

        return $q->exists();
    }

    public static function mensajeOrdenYaFacturada(int $waitryOrderId): string
    {
        return 'La orden Waitry #'.$waitryOrderId.' ya fue facturada en el sistema.';
    }
}
