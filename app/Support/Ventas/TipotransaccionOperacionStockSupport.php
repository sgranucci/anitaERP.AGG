<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Traits\Ventas\TipotransaccionTrait;

/**
 * Signo de cantidad en articulo_movimiento según operacionstock del tipo de venta.
 *
 * Entrada → cantidad positiva; salida → negativa. Nulo / sin operación → no movimiento.
 */
final class TipotransaccionOperacionStockSupport
{
    public const SALIDA = 'S';

    public const ENTRADA = 'E';

    public const NULO = 'N';

    public const SIN_OPERACION = 'O';

    public static function afectaStock(?string $operacionstock): bool
    {
        return in_array($operacionstock, [self::SALIDA, self::ENTRADA], true);
    }

    public static function cantidadFirmada(float $cantidad, ?string $operacionstock): float
    {
        $abs = abs($cantidad);

        return $operacionstock === self::ENTRADA ? $abs : -$abs;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function firmarPayloadMovimiento(array $data, ?string $operacionstock): array
    {
        $data['cantidad'] = self::cantidadFirmada((float) ($data['cantidad'] ?? 0), $operacionstock);
        $data['cantidad_ya_firmada'] = true;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null  null si el tipo no genera movimiento de stock
     */
    public static function firmarPayloadDesdeTipotransaccion(array $data, Tipotransaccion $tipotransaccion): ?array
    {
        $operacionstock = $tipotransaccion->operacionstock ?? self::SIN_OPERACION;

        if (! self::afectaStock($operacionstock)) {
            return null;
        }

        return self::firmarPayloadMovimiento($data, $operacionstock);
    }

    public static function operacionstockPorDefecto(): string
    {
        return self::SIN_OPERACION;
    }

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return TipotransaccionTrait::$enumOperacionStock;
    }
}
