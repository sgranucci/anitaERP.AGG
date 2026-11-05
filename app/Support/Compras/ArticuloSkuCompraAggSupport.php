<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * En AGG, la carga de requisiciones y órdenes de compra no acepta artículos
 * de catálogo de ventas (V + dígitos) ni de insumos (I + dígitos).
 * VAJ0001, IMP0001 o II1000 siguen pudiendo cargarse: no son ese formato.
 */
final class ArticuloSkuCompraAggSupport
{
    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esAgg();
    }

    public static function esSkuBloqueado(?string $sku): bool
    {
        if (! self::aplica()) {
            return false;
        }

        $sku = strtoupper(trim((string) $sku));

        return $sku !== '' && preg_match('/^[VI][0-9]+$/', $sku) === 1;
    }

    public static function mensaje(?string $sku): ?string
    {
        if (! self::esSkuBloqueado($sku)) {
            return null;
        }

        $sku = strtoupper(trim((string) $sku));
        $que = $sku[0] === 'V' ? 'de ventas' : 'de insumos';

        return 'El artículo '.$sku.' es un artículo '.$que
            .' y no se puede cargar en una requisición ni en una orden de compra.';
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicarExclusion(Builder $query, string $columnaSku = 'articulo.sku'): void
    {
        if (! self::aplica()) {
            return;
        }

        $expr = SqlDialectSupport::lower('TRIM('.$columnaSku.')');
        $query->where(function (Builder $q) use ($columnaSku, $expr) {
            $q->whereRaw($columnaSku.' IS NULL')
                ->orWhereRaw('NOT ('.SqlDialectSupport::coincideRegex($expr).')', ['^[vi][0-9]+$']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload  articulo_ids[]
     *
     * @throws \InvalidArgumentException
     */
    public static function validarPayload(array $payload): void
    {
        if (! self::aplica()) {
            return;
        }

        $articuloIds = $payload['articulo_ids'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === []) {
            return;
        }

        $ids = [];
        foreach ($articuloIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        if ($ids === []) {
            return;
        }

        $skus = Articulo::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('sku', 'id');

        $faltan = [];
        foreach ($articuloIds as $i => $id) {
            $articuloId = (int) $id;
            if ($articuloId <= 0) {
                continue;
            }
            $mensaje = self::mensaje($skus[$articuloId] ?? null);
            if ($mensaje === null) {
                continue;
            }
            $faltan[] = 'Línea '.($i + 1).': '.$mensaje;
        }

        if ($faltan === []) {
            return;
        }

        throw new \InvalidArgumentException(implode(' ', $faltan));
    }
}
