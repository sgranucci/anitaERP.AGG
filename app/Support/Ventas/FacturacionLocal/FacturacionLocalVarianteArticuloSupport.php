<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Support\Stock\ArticuloStockColorTalleSupport;

/**
 * Decide color suelto vs combinación Ferli; talle siempre.
 */
final class FacturacionLocalVarianteArticuloSupport
{
    public const MODO_COLOR_TALLE = 'color_talle';

    public const MODO_COMBINACION = 'combinacion';

    public static function modo(Articulo|int $articulo): string
    {
        $model = $articulo instanceof Articulo
            ? $articulo
            : Articulo::query()->find((int) $articulo);

        if (! $model) {
            return self::MODO_COMBINACION;
        }

        $tieneCombinaciones = Combinacion::query()
            ->where('articulo_id', (int) $model->id)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', 'I');
            })
            ->exists();

        if (! $tieneCombinaciones && ArticuloStockColorTalleSupport::articuloManejaColorTalle($model)) {
            return self::MODO_COLOR_TALLE;
        }

        return self::MODO_COMBINACION;
    }

    /**
     * @return array{ok:bool,error?:string,modo:string,color_id:?int,talle_id:?int,combinacion_id:?int}
     */
    public static function validarLinea(
        Articulo|int $articulo,
        ?int $talleId,
        ?int $colorId = null,
        ?int $combinacionId = null,
    ): array {
        $modo = self::modo($articulo);
        $talleOk = $talleId !== null && $talleId > 0;

        if (! $talleOk) {
            return [
                'ok' => false,
                'error' => 'El talle (numeración) es obligatorio.',
                'modo' => $modo,
                'color_id' => null,
                'talle_id' => null,
                'combinacion_id' => null,
            ];
        }

        if ($modo === self::MODO_COLOR_TALLE) {
            $colorOk = $colorId !== null && $colorId > 0;
            if (! $colorOk) {
                return [
                    'ok' => false,
                    'error' => 'El artículo maneja color y talle: indique color.',
                    'modo' => $modo,
                    'color_id' => null,
                    'talle_id' => $talleId,
                    'combinacion_id' => null,
                ];
            }

            return [
                'ok' => true,
                'modo' => $modo,
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'combinacion_id' => null,
            ];
        }

        $combOk = $combinacionId !== null && $combinacionId > 0;
        if (! $combOk) {
            return [
                'ok' => false,
                'error' => 'Seleccione una combinación (color y especificaciones).',
                'modo' => $modo,
                'color_id' => null,
                'talle_id' => $talleId,
                'combinacion_id' => null,
            ];
        }

        return [
            'ok' => true,
            'modo' => $modo,
            'color_id' => null,
            'talle_id' => $talleId,
            'combinacion_id' => $combinacionId,
        ];
    }
}
