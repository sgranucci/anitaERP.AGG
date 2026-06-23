<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Precios de venta: solo artículos facturables (nofactura = 0).
 */
final class PrecioSoloFacturableSupport
{
  public const NOFACTURA_FACTURABLE = '0';

  public const NOFACTURA_NO_FACTURABLE = '1';

  public static function aplicarFiltroQuery(Builder $query, string $articuloColumn = 'articulo.nofactura'): Builder
  {
    return $query->where($articuloColumn, self::NOFACTURA_FACTURABLE);
  }

  public static function articuloEsFacturable(?Articulo $articulo): bool
  {
    if (! $articulo) {
      return false;
    }

    return (string) $articulo->nofactura === self::NOFACTURA_FACTURABLE;
  }

  public static function articuloIdEsFacturable(int $articuloId): bool
  {
    if ($articuloId <= 0) {
      return false;
    }

    return Articulo::query()
      ->whereKey($articuloId)
      ->where('nofactura', self::NOFACTURA_FACTURABLE)
      ->exists();
  }
}
