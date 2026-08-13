<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * En El Bierzo el alta del pedido respeta el orden de carga.
 * Al editar, imprimir o listar, los ítems se muestran por SKU numérico (9 antes que 10).
 */
final class PedidoArticuloOrdenSupport
{
    public static function aplicaSkuNumerico(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    public static function aplicarAQuery($query, string $tablaPedidoArticulo = 'pedido_articulo'): void
    {
        if (! self::aplicaSkuNumerico()) {
            return;
        }

        $skuCast = SqlDialectSupport::castEntero('articulo.sku');
        $query->orderByRaw(
            '(SELECT '.$skuCast.' FROM articulo WHERE articulo.id = '.$tablaPedidoArticulo.'.articulo_id)'
        )->orderByRaw(
            '(SELECT articulo.sku FROM articulo WHERE articulo.id = '.$tablaPedidoArticulo.'.articulo_id)'
        )->orderBy($tablaPedidoArticulo.'.id');
    }

    public static function ordenarColeccion($items): Collection
    {
        $coleccion = $items instanceof Collection ? $items : collect($items ?? []);
        if (! self::aplicaSkuNumerico() || $coleccion->isEmpty()) {
            return $coleccion;
        }

        return $coleccion->sort(function ($a, $b) {
            $cmp = self::compararSku(
                (string) (data_get($a, 'articulos.sku') ?? ''),
                (string) (data_get($b, 'articulos.sku') ?? '')
            );
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a->id ?? 0)) <=> ((int) ($b->id ?? 0));
        })->values();
    }

    public static function compararSku(string $a, string $b): int
    {
        $a = trim($a);
        $b = trim($b);
        $na = self::valorNumericoSku($a);
        $nb = self::valorNumericoSku($b);
        if ($na !== $nb) {
            return $na <=> $nb;
        }

        return strnatcasecmp($a, $b);
    }

    private static function valorNumericoSku(string $sku): int
    {
        if ($sku !== '' && preg_match('/^\d+$/', $sku)) {
            return (int) $sku;
        }
        if (preg_match('/^(\d+)/', $sku, $m)) {
            return (int) $m[1];
        }

        return PHP_INT_MAX;
    }
}
