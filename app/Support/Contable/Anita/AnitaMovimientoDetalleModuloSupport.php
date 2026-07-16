<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

/**
 * Clasifica movimientos del mayor Anita (subdiario/ctamov) por módulo de negocio
 * según el detalle grabado, para que los controles de gastronomía y estacionamiento
 * no se contaminen entre sí (comparten cuentas de IVA 214/114).
 *
 * Anita trunca detalles (ej. "Cierre rendicin estacionamient"), por eso se
 * comparan prefijos parciales en minúsculas.
 */
final class AnitaMovimientoDetalleModuloSupport
{
    /** Cuenta de ventas estacionamiento (415010003). */
    public const CUENTA_VENTAS_ESTACIONAMIENTO = 415010003;

    /**
     * @param  array<string, mixed>|string  $movODetalle
     */
    public static function esEstacionamiento(array|string $movODetalle): bool
    {
        $detalle = self::detalleNormalizado($movODetalle);
        if ($detalle === '') {
            return false;
        }

        return str_contains($detalle, 'estacionamient')
            || str_contains($detalle, 'estac.');
    }

    /**
     * @param  array<string, mixed>|string  $movODetalle
     */
    public static function esGastronomia(array|string $movODetalle): bool
    {
        $detalle = self::detalleNormalizado($movODetalle);
        if ($detalle === '') {
            return false;
        }

        // "Venta gastronomia", "gastronoma" (sin tilde / truncado Anita).
        return str_contains($detalle, 'gastronom');
    }

    /**
     * @param  array<string, mixed>|string  $movODetalle
     */
    private static function detalleNormalizado(array|string $movODetalle): string
    {
        $detalle = is_array($movODetalle)
            ? (string) ($movODetalle['detalle'] ?? '')
            : $movODetalle;

        return mb_strtolower(trim($detalle));
    }
}
