<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\WaitryComandaEnvio;

/**
 * Resuelve el orderId Waitry asociado a una emisión gastronomía (emisión, cuenta o envío KDS).
 */
final class VentaGastronomiaEmisionWaitrySupport
{
    public static function resolverOrderId(?VentaGastronomiaEmision $emision): int
    {
        if ($emision === null) {
            return 0;
        }

        $desdeEmision = (int) ($emision->waitry_order_id ?? 0);
        if ($desdeEmision > 0) {
            return $desdeEmision;
        }

        $cuenta = $emision->relationLoaded('cuenta')
            ? $emision->cuenta
            : $emision->cuenta()->first();
        $desdeCuenta = (int) ($cuenta?->waitry_order_id ?? 0);
        if ($desdeCuenta > 0) {
            return $desdeCuenta;
        }

        $envio = $emision->relationLoaded('waitryComandaEnvio')
            ? $emision->waitryComandaEnvio
            : WaitryComandaEnvio::query()->where('venta_id', (int) $emision->venta_id)->first();

        return (int) ($envio?->waitry_order_id ?? 0);
    }

    /**
     * @return array{estado:?string,waitry_order_id:int,ultimo_error:?string}
     */
    public static function metaEnvioComanda(?VentaGastronomiaEmision $emision): array
    {
        if ($emision === null) {
            return ['estado' => null, 'waitry_order_id' => 0, 'ultimo_error' => null];
        }

        $envio = $emision->relationLoaded('waitryComandaEnvio')
            ? $emision->waitryComandaEnvio
            : WaitryComandaEnvio::query()->where('venta_id', (int) $emision->venta_id)->first();

        return [
            'estado' => $envio?->estado,
            'waitry_order_id' => (int) ($envio?->waitry_order_id ?? 0),
            'ultimo_error' => $envio?->ultimo_error ? mb_substr((string) $envio->ultimo_error, 0, 500) : null,
        ];
    }

    /**
     * Persiste waitry_order_id si no está asignado a otra venta (índice uq_vge_waitry_order_id).
     */
    public static function persistirOrderIdEnEmision(int $ventaId, int|string|null $orderId): bool
    {
        if ($ventaId <= 0 || ! is_numeric($orderId) || (int) $orderId <= 0) {
            return false;
        }

        $orderId = (int) $orderId;

        if (self::ventaIdConWaitryOrderId($orderId, $ventaId) !== null) {
            return false;
        }

        $actual = (int) (VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaId)
            ->value('waitry_order_id') ?? 0);
        if ($actual === $orderId) {
            return true;
        }

        return VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaId)
            ->update(['waitry_order_id' => $orderId]) > 0;
    }

    public static function ventaIdConWaitryOrderId(int $orderId, ?int $excluirVentaId = null): ?int
    {
        if ($orderId <= 0) {
            return null;
        }

        $q = VentaGastronomiaEmision::query()->where('waitry_order_id', $orderId);
        if ($excluirVentaId !== null && $excluirVentaId > 0) {
            $q->where('venta_id', '!=', $excluirVentaId);
        }

        $ventaId = (int) ($q->value('venta_id') ?? 0);

        return $ventaId > 0 ? $ventaId : null;
    }
}
