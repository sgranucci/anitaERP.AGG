<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo;
use App\Models\Stock\Tipoarticulo;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * En la carga de una requisición no entran insumos de gastronomía ni artículos de tipo VENTA.
 */
final class RequisicionArticuloCatalogoSupport
{
    public static function idTipoVenta(): ?int
    {
        $id = Cache::remember(
            'requisicion_tipoarticulo_venta_id',
            3600,
            fn () => Tipoarticulo::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', ['VENTA'])
                ->value('id'),
        );

        return $id !== null ? (int) $id : null;
    }

    public static function esTipoVenta(?int $tipoarticuloId): bool
    {
        $ventaId = self::idTipoVenta();

        return $ventaId !== null && $ventaId > 0 && (int) $tipoarticuloId === $ventaId;
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicarExclusion(Builder $query): void
    {
        $insumoId = ArticuloUsoInsumoSupport::idUsoInsumo();
        if ($insumoId !== null && $insumoId > 0) {
            $query->where(function ($q) use ($insumoId) {
                $q->whereNull('articulo.usoarticulo_id')
                    ->orWhere('articulo.usoarticulo_id', '<>', $insumoId);
            });
        }

        $ventaId = self::idTipoVenta();
        if ($ventaId !== null && $ventaId > 0) {
            $query->where(function ($q) use ($ventaId) {
                $q->whereNull('articulo.tipoarticulo_id')
                    ->orWhere('articulo.tipoarticulo_id', '<>', $ventaId);
            });
        }
    }

    public static function mensajeExclusion(?Articulo $articulo): ?string
    {
        if ($articulo === null) {
            return null;
        }

        $sku = trim((string) ($articulo->sku ?? ''));
        $etiqueta = $sku !== '' ? $sku : ('id '.(int) $articulo->id);

        if (ArticuloUsoInsumoSupport::esUsoInsumo((int) ($articulo->usoarticulo_id ?? 0))) {
            return 'El artículo '.$etiqueta.' es un insumo y no se puede cargar en una requisición.';
        }

        if (self::esTipoVenta((int) ($articulo->tipoarticulo_id ?? 0))) {
            return 'El artículo '.$etiqueta.' es un artículo de ventas y no se puede cargar en una requisición.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload  articulo_ids[]
     *
     * @throws \InvalidArgumentException
     */
    public static function validarPayload(array $payload): void
    {
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

        $articulos = Articulo::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'sku', 'usoarticulo_id', 'tipoarticulo_id'])
            ->keyBy('id');

        $faltan = [];
        foreach ($articuloIds as $i => $id) {
            $articuloId = (int) $id;
            if ($articuloId <= 0) {
                continue;
            }
            $mensaje = self::mensajeExclusion($articulos->get($articuloId));
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
