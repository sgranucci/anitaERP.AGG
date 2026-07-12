<?php

namespace App\Support\Ventas\Vianda;

use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use Carbon\Carbon;

/**
 * Precios viandas: costo desde lista 5000+mes (vigente a la jornada); venta desde lista configurada en la terminal.
 */
final class ViandaPrecioSupport
{
    public static function baseListaCosto(): int
    {
        return max(1, (int) config('vianda.costo_lista_base', 5000));
    }

    public static function codigoListaCostoDesdeFecha(string $fechaYmd): string
    {
        $mes = (int) Carbon::parse($fechaYmd)->format('n');

        return (string) (self::baseListaCosto() + $mes);
    }

    public static function listaprecioIdCostoDesdeFecha(string $fechaYmd): ?int
    {
        $codigo = self::codigoListaCostoDesdeFecha($fechaYmd);
        $id = Listaprecio::query()->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function precioCostoUnitario(int $articuloId, string $fechaJornada): float
    {
        return self::precioDesdeLista(
            $articuloId,
            self::listaprecioIdCostoDesdeFecha($fechaJornada),
            $fechaJornada,
        );
    }

    public static function precioVentaUnitario(int $articuloId, ?int $listaprecioVentaId, ?string $fechaJornada = null): float
    {
        return self::precioDesdeLista($articuloId, $listaprecioVentaId, $fechaJornada);
    }

    public static function precioDesdeLista(int $articuloId, ?int $listaprecioId, ?string $fechaVigenciaMax = null): float
    {
        if ($listaprecioId === null || $listaprecioId <= 0) {
            return 0.0;
        }

        $query = Precio::query()
            ->where('articulo_id', $articuloId)
            ->where('listaprecio_id', $listaprecioId)
            ->orderByDesc('fechavigencia')
            ->orderByDesc('id');

        if ($fechaVigenciaMax !== null && trim($fechaVigenciaMax) !== '') {
            $query->whereDate('fechavigencia', '<=', $fechaVigenciaMax);
        }

        $precio = $query->value('precio');

        return $precio !== null ? (float) $precio : 0.0;
    }
}
