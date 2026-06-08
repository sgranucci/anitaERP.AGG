<?php

namespace App\Support\Stock;

use App\Models\Stock\Usoarticulo;
use Illuminate\Support\Facades\Cache;

/**
 * Uso maestro «INSUMO GASTRONOMIA» en articulo.usoarticulo_id.
 */
final class ArticuloUsoInsumoSupport
{
    public const NOMBRE_USO_INSUMO = 'INSUMO GASTRONOMIA';

    public static function idUsoInsumo(): ?int
    {
        $id = Cache::remember(
            'articulo_uso_insumo_gastronomia_id',
            3600,
            fn () => Usoarticulo::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [self::NOMBRE_USO_INSUMO])
                ->value('id'),
        );

        return $id !== null ? (int) $id : null;
    }

    public static function esUsoInsumo(?int $usoarticuloId): bool
    {
        $insumoId = self::idUsoInsumo();

        return $insumoId !== null && $insumoId > 0 && (int) $usoarticuloId === $insumoId;
    }

    public static function olvidarCache(): void
    {
        Cache::forget('articulo_uso_insumo_gastronomia_id');
    }
}
