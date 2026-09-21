<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use App\Support\Stock\CombinacionEstadoCanalSupport;
use Illuminate\Support\Facades\DB;

/**
 * Decide color suelto vs combinación Ferli; talle siempre.
 * Combinaciones vendibles en POS Local: estado_local = A (canal Local).
 */
final class FacturacionLocalVarianteArticuloSupport
{
    public const MODO_COLOR_TALLE = 'color_talle';

    public const MODO_COMBINACION = 'combinacion';

    public const COMBINACION_ACTIVA = 'A';

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function scopeCombinacionesActivas($query)
    {
        return CombinacionEstadoCanalSupport::scopeActivasEnAmbito(
            $query,
            CombinacionEstadoCanalSupport::AMBITO_LOCAL
        );
    }

    public static function queryCombinacionesActivas(int $articuloId)
    {
        $q = Combinacion::query()->where('articulo_id', $articuloId);

        return CombinacionEstadoCanalSupport::scopeActivasEnAmbito(
            $q,
            CombinacionEstadoCanalSupport::AMBITO_LOCAL
        )->orderBy('codigo');
    }

    public static function tieneCombinacionesActivas(int $articuloId): bool
    {
        if ($articuloId <= 0) {
            return false;
        }

        $q = Combinacion::query()->where('articulo_id', $articuloId);

        return CombinacionEstadoCanalSupport::scopeActivasEnAmbito(
            $q,
            CombinacionEstadoCanalSupport::AMBITO_LOCAL
        )->exists();
    }

    public static function combinacionActiva(int $combinacionId, ?int $articuloId = null): bool
    {
        if ($combinacionId <= 0) {
            return false;
        }

        $q = Combinacion::query()->where('id', $combinacionId);
        CombinacionEstadoCanalSupport::scopeActivasEnAmbito(
            $q,
            CombinacionEstadoCanalSupport::AMBITO_LOCAL
        );
        if ($articuloId !== null && $articuloId > 0) {
            $q->where('articulo_id', $articuloId);
        }

        return $q->exists();
    }

    public static function modo(Articulo|int $articulo): string
    {
        $model = $articulo instanceof Articulo
            ? $articulo
            : Articulo::query()->find((int) $articulo);

        if (! $model) {
            return self::MODO_COMBINACION;
        }

        $tieneCombinaciones = self::tieneCombinacionesActivas((int) $model->id);

        if (! $tieneCombinaciones && ArticuloStockColorTalleSupport::articuloManejaColorTalle($model)) {
            return self::MODO_COLOR_TALLE;
        }

        return self::MODO_COMBINACION;
    }

    /**
     * Artículos con variante usable en POS: combinación activa, o color/talle sin combos activas.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function scopeArticulosConVarianteVendible($query)
    {
        $colLocal = CombinacionEstadoCanalSupport::columnaPorAmbito(
            CombinacionEstadoCanalSupport::AMBITO_LOCAL
        );

        return $query->where(function ($w) use ($colLocal) {
            $w->whereExists(function ($q) use ($colLocal) {
                $q->select(DB::raw(1))
                    ->from('combinacion')
                    ->whereColumn('combinacion.articulo_id', 'articulo.id')
                    ->where($colLocal, self::COMBINACION_ACTIVA);
            })->orWhere(function ($q) use ($colLocal) {
                $q->where('articulo.maneja_stock_color_talle', true)
                    ->whereNotExists(function ($sub) use ($colLocal) {
                        $sub->select(DB::raw(1))
                            ->from('combinacion')
                            ->whereColumn('combinacion.articulo_id', 'articulo.id')
                            ->where($colLocal, self::COMBINACION_ACTIVA);
                    });
            });
        });
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
        $model = $articulo instanceof Articulo
            ? $articulo
            : Articulo::query()->find((int) $articulo);
        $articuloId = (int) ($model?->id ?? 0);
        $modo = self::modo($model ?? $articulo);
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
                'error' => 'Seleccione una combinación activa.',
                'modo' => $modo,
                'color_id' => null,
                'talle_id' => $talleId,
                'combinacion_id' => null,
            ];
        }

        if (! self::combinacionActiva((int) $combinacionId, $articuloId > 0 ? $articuloId : null)) {
            return [
                'ok' => false,
                'error' => 'La combinación no está activa o no pertenece al artículo.',
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
