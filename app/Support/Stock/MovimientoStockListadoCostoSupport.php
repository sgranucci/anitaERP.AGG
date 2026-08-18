<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Services\Stock\PrecioService;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Costo unitario/total para filas del listado unificado de movimientos de stock.
 *
 * - Producto de venta (SKU catálogo gastronomía V…): lista 5000+mes de la fecha del documento.
 * - Resto (compras, insumos, descartables, etc.): precio de última compra.
 */
final class MovimientoStockListadoCostoSupport
{
    public const ORIGEN_LISTA_MES = 'lista_5000_mes';

    public const ORIGEN_ULTIMA_COMPRA = 'ultima_compra';

    /**
     * @param  Collection<int, MovimientoStockListadoFila>  $filas
     * @return Collection<int, MovimientoStockListadoFila>
     */
    public static function enriquecer(Collection $filas): Collection
    {
        if ($filas->isEmpty()) {
            return $filas;
        }

        $costos = self::resolverCostosPorFila($filas);

        return $filas->map(static function (MovimientoStockListadoFila $fila) use ($costos): MovimientoStockListadoFila {
            $clave = self::claveFila($fila);
            $dato = $costos[$clave] ?? null;

            return $fila->conCostoProducto(
                $dato['costo_unitario'] ?? null,
                $dato['costo_total'] ?? null,
                $dato['origen'] ?? null,
            );
        });
    }

    /**
     * @param  Collection<int, MovimientoStockListadoFila>  $filas
     * @return array<string, array{costo_unitario: float|null, costo_total: float|null, origen: string|null}>
     */
    private static function resolverCostosPorFila(Collection $filas): array
    {
        $lineasPorClave = self::cargarLineas($filas);
        if ($lineasPorClave === []) {
            return [];
        }

        $articuloIds = [];
        foreach ($lineasPorClave as $lineas) {
            foreach ($lineas as $linea) {
                $articuloIds[(int) $linea['articulo_id']] = true;
            }
        }

        $articulos = Articulo::query()
            ->whereIn('id', array_keys($articuloIds))
            ->get(['id', 'sku'])
            ->keyBy('id');

        $esVentaPorArticulo = [];
        foreach ($articulos as $id => $articulo) {
            $sku = trim((string) ($articulo->sku ?? ''));
            $esVentaPorArticulo[(int) $id] = $sku !== '' && GastronomiaSkuCatalogoSupport::skuPermitido($sku);
        }

        // Última compra para no-catálogo y fallback si falta precio en lista 5000+mes.
        $preciosCompra = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos(array_keys($articuloIds));

        /** @var array<string, int|null> $listaIdPorMes */
        $listaIdPorMes = [];
        /** @var array<string, float> $precioListaCache */
        $precioListaCache = [];

        $out = [];
        foreach ($filas as $fila) {
            $clave = self::claveFila($fila);
            $lineas = $lineasPorClave[$clave] ?? [];
            if ($lineas === []) {
                $out[$clave] = ['costo_unitario' => null, 'costo_total' => null, 'origen' => null];

                continue;
            }

            $fecha = $fila->fecha ? substr((string) $fila->fecha, 0, 10) : date('Y-m-d');
            $mesKey = substr($fecha, 0, 7);
            if (! array_key_exists($mesKey, $listaIdPorMes)) {
                $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fecha);
                $codigo = (string) ($listas['lista_actual'] ?? '');
                $idLista = $codigo !== ''
                    ? Listaprecio::query()->where('codigo', $codigo)->value('id')
                    : null;
                $listaIdPorMes[$mesKey] = $idLista !== null ? (int) $idLista : null;
            }
            $listaId = $listaIdPorMes[$mesKey];

            $totalCosto = 0.0;
            $totalCant = 0.0;
            $origenUsado = null;
            $tienePrecio = false;

            foreach ($lineas as $linea) {
                $articuloId = (int) $linea['articulo_id'];
                $cantidad = abs((float) $linea['cantidad']);
                if ($articuloId <= 0 || $cantidad <= 0.0000001) {
                    continue;
                }

                $unit = 0.0;
                $origenLinea = null;
                if ($esVentaPorArticulo[$articuloId] ?? false) {
                    $unit = self::precioListaMes($articuloId, $listaId, $fecha, $precioListaCache);
                    if ($unit > 0) {
                        $origenLinea = self::ORIGEN_LISTA_MES;
                    }
                }
                if ($unit <= 0) {
                    $unit = (float) ($preciosCompra[$articuloId]['precio'] ?? 0);
                    if ($unit > 0) {
                        $origenLinea = self::ORIGEN_ULTIMA_COMPRA;
                    }
                }

                if ($unit > 0) {
                    $tienePrecio = true;
                    $totalCosto += $cantidad * $unit;
                    $totalCant += $cantidad;
                    $origenUsado ??= $origenLinea;
                    if ($origenUsado !== $origenLinea) {
                        $origenUsado = 'mixto';
                    }
                } else {
                    $totalCant += $cantidad;
                }
            }

            if (! $tienePrecio || $totalCant <= 0.0000001) {
                $out[$clave] = ['costo_unitario' => null, 'costo_total' => null, 'origen' => null];

                continue;
            }

            $out[$clave] = [
                'costo_unitario' => round($totalCosto / $totalCant, 6),
                'costo_total' => round($totalCosto, 2),
                'origen' => $origenUsado,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $cache
     */
    private static function precioListaMes(
        int $articuloId,
        ?int $listaId,
        string $fecha,
        array &$cache,
    ): float {
        if ($listaId === null || $listaId <= 0) {
            return 0.0;
        }

        $key = $articuloId.'|'.$listaId.'|'.$fecha;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaId, $fecha);
        $cache[$key] = $precios !== []
            ? (float) (end($precios)['precio'] ?? 0)
            : 0.0;

        return $cache[$key];
    }

    /**
     * @param  Collection<int, MovimientoStockListadoFila>  $filas
     * @return array<string, list<array{articulo_id:int, cantidad:float}>>
     */
    private static function cargarLineas(Collection $filas): array
    {
        $movIds = [];
        $tmIds = [];
        foreach ($filas as $fila) {
            if ($fila->esTransferencia()) {
                $tmIds[] = $fila->pkId;
            } else {
                $movIds[] = $fila->pkId;
            }
        }
        $movIds = array_values(array_unique(array_filter($movIds)));
        $tmIds = array_values(array_unique(array_filter($tmIds)));

        $out = [];

        if ($movIds !== []) {
            $rows = DB::table('articulo_movimiento')
                ->select(['movimientostock_id', 'articulo_id', 'cantidad'])
                ->whereIn('movimientostock_id', $movIds)
                ->get();
            foreach ($rows as $row) {
                $clave = 'movimiento:'.(int) $row->movimientostock_id;
                $out[$clave][] = [
                    'articulo_id' => (int) $row->articulo_id,
                    'cantidad' => (float) $row->cantidad,
                ];
            }
        }

        if ($tmIds !== []) {
            $rows = DB::table('transferencia_mercaderia_articulo')
                ->select(['transferencia_mercaderia_id', 'articulo_destino_id', 'cantidad_destino'])
                ->whereIn('transferencia_mercaderia_id', $tmIds)
                ->get();
            foreach ($rows as $row) {
                $clave = 'transferencia:'.(int) $row->transferencia_mercaderia_id;
                $out[$clave][] = [
                    'articulo_id' => (int) $row->articulo_destino_id,
                    'cantidad' => (float) $row->cantidad_destino,
                ];
            }
        }

        return $out;
    }

    private static function claveFila(MovimientoStockListadoFila $fila): string
    {
        return $fila->filaTipo.':'.$fila->pkId;
    }
}
