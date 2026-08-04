<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Stock\Recepcion_Proveedor;

/**
 * Regla: no cambiar tratamiento NO ANTICIPADA ↔ ANTICIPADA si la OC ya tiene
 * recepción (COM) o factura (comprobante proveedor) asociada.
 */
final class OrdencompraTratamientoMovimientosSupport
{
    public static function normalizar(?string $tratamiento): string
    {
        return strtoupper(trim((string) $tratamiento));
    }

    public static function hayMovimientosQueBloqueanCambio(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        if (Recepcion_Proveedor::query()->where('ordencompra_id', $ordencompraId)->exists()) {
            return true;
        }

        return Comprobante_Proveedor::query()->where('ordencompra_id', $ordencompraId)->exists();
    }

    public static function mensajeBloqueoCambio(): string
    {
        return 'No se puede cambiar el tratamiento entre NO ANTICIPADA y ANTICIPADA '
            .'porque la orden de compra ya tiene recepción o factura asociada.';
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertPuedeCambiarTratamiento(
        int $ordencompraId,
        ?string $tratamientoActual,
        ?string $tratamientoNuevo,
    ): void {
        if (self::normalizar($tratamientoActual) === self::normalizar($tratamientoNuevo)) {
            return;
        }

        if (self::hayMovimientosQueBloqueanCambio($ordencompraId)) {
            throw new \InvalidArgumentException(self::mensajeBloqueoCambio());
        }
    }
}
