<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use InvalidArgumentException;

/**
 * Stock dimensional color/talle (indumentaria y artículos con flag).
 *
 * - En articulo_movimiento: color_id/talle_id nullable (FK real).
 * - En articulo_saldo_deposito: color_id/talle_id NOT NULL, 0 = sin variante
 *   (unique MySQL no colisiona con NULL).
 */
class ArticuloStockColorTalleSupport
{
    public const SIN_VARIANTE = 0;

    public static function articuloManejaColorTalle(Articulo|int|null $articulo): bool
    {
        if ($articulo === null) {
            return false;
        }

        if ($articulo instanceof Articulo) {
            return (bool) ($articulo->maneja_stock_color_talle ?? false);
        }

        $flag = Articulo::query()->whereKey((int) $articulo)->value('maneja_stock_color_talle');

        return (bool) $flag;
    }

    /**
     * Clave de saldo: 0/0 si no hay color/talle.
     *
     * @return array{0: int, 1: int} [color_id, talle_id]
     */
    public static function claveSaldo(?int $colorId, ?int $talleId): array
    {
        return [
            ($colorId !== null && $colorId > 0) ? $colorId : self::SIN_VARIANTE,
            ($talleId !== null && $talleId > 0) ? $talleId : self::SIN_VARIANTE,
        ];
    }

    /**
     * Valores a persistir en articulo_movimiento (null si sin variante).
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function valoresMovimiento(?int $colorId, ?int $talleId): array
    {
        return [
            ($colorId !== null && $colorId > 0) ? $colorId : null,
            ($talleId !== null && $talleId > 0) ? $talleId : null,
        ];
    }

    /**
     * Valida una línea de stock según el flag del artículo.
     *
     * @throws InvalidArgumentException
     */
    public static function validarLinea(Articulo|int $articulo, ?int $colorId, ?int $talleId): void
    {
        $maneja = self::articuloManejaColorTalle($articulo);
        $colorOk = $colorId !== null && $colorId > 0;
        $talleOk = $talleId !== null && $talleId > 0;

        if ($maneja) {
            if (! $colorOk || ! $talleOk) {
                throw new InvalidArgumentException(
                    'El artículo maneja stock por color y talle: ambos son obligatorios.'
                );
            }

            return;
        }

        if ($colorOk || $talleOk) {
            throw new InvalidArgumentException(
                'El artículo no maneja stock por color/talle: no debe informar color ni talle.'
            );
        }
    }
}
