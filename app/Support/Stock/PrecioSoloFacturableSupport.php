<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use Illuminate\Database\Eloquent\Builder;

/**
 * Precios de venta en listados: por defecto solo artículos facturables (nofactura = 0).
 *
 * Excepción de negocio: listas con incluyeimpuesto = 2 (impuesto interno / precios sugeridos)
 * admiten también artículos no facturables (ej. insumos con II en listas 162/262/362).
 */
final class PrecioSoloFacturableSupport
{
    public const NOFACTURA_FACTURABLE = '0';

    public const NOFACTURA_NO_FACTURABLE = '1';

    /** Listas de impuesto interno / sugeridos / costo (Anita prem_incl_impuesto). */
    public const INCLUYEIMPUESTO_LISTA_ESPECIAL = '2';

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

    public static function listaEsEspecialImpuestoInterno(int $listaprecioId): bool
    {
        if ($listaprecioId <= 0) {
            return false;
        }

        return Listaprecio::query()
            ->whereKey($listaprecioId)
            ->where('incluyeimpuesto', self::INCLUYEIMPUESTO_LISTA_ESPECIAL)
            ->exists();
    }

    /**
     * ¿Se puede grabar/editar un precio para este artículo y lista?
     */
    public static function permiteRegistrar(int $articuloId, int $listaprecioId): bool
    {
        if ($articuloId <= 0 || $listaprecioId <= 0) {
            return false;
        }

        if (self::articuloIdEsFacturable($articuloId)) {
            return true;
        }

        return self::listaEsEspecialImpuestoInterno($listaprecioId);
    }
}
