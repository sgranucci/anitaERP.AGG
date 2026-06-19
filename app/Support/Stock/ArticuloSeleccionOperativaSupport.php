<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Artículos elegibles en operaciones de stock/compras (requisiciones, OC, recepciones,
 * movimientos, transferencias, préstamos, recuentos). Excluye INACTIVO/BAJA en búsquedas;
 * no usar al cargar documentos existentes por ID.
 */
final class ArticuloSeleccionOperativaSupport
{
    public const ESTADO_ACTIVO = 'ACTIVO';

    /**
     * @param  Builder<Articulo>|Builder  $query
     * @return Builder<Articulo>|Builder
     */
    public static function aplicarSoloActivos(Builder $query, string $columnaEstado = 'articulo.estado'): Builder
    {
        return $query->where($columnaEstado, self::ESTADO_ACTIVO);
    }

    /**
     * @param  Builder<Articulo>  $query
     * @return Builder<Articulo>
     */
    public static function aplicarSoloActivosTablaArticulo(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public static function esSeleccionable(?Articulo $articulo): bool
    {
        return $articulo !== null && (string) $articulo->estado === self::ESTADO_ACTIVO;
    }

    public static function esSeleccionablePorId(?int $articuloId): bool
    {
        if ($articuloId === null || $articuloId <= 0) {
            return false;
        }

        return Articulo::query()
            ->whereKey($articuloId)
            ->where('estado', self::ESTADO_ACTIVO)
            ->exists();
    }
}
