<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra_Articulo_Precio_Historia;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio unitario de última compra para valorización de stock.
 *
 * Orden de resolución (ver también config/stock.php → precio_ultima_compra):
 * 1. Anita: stkmae.stkm_pre_compra3 (StkmaeUltimaCompraAnitaService)
 * 2. ERP: última COM confirmada (historia de precio OC o línea de recepción)
 * 3. Fallback: articulo.ppp (y costo/precio si existen en la instalación)
 */
final class ArticuloPrecioUltimaCompraSupport
{
    public const ORIGEN_ANITA = 'anita';

    public const ORIGEN_ERP_COM = 'erp_com';

    public const ORIGEN_ARTICULO = 'articulo';

    /**
     * @return array{precio: float|null, moneda_id: int|null, origen: string|null}
     */
    public static function resolverPorArticulo(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $porId = self::resolverPorArticulos([$articulo], $anitaService);

        return $porId[(int) $articulo->id] ?? ['precio' => null, 'moneda_id' => null, 'origen' => null];
    }

    /**
     * @param  iterable<Articulo|int>  $articulosOrIds
     * @return array<int, array{precio: float|null, moneda_id: int|null, origen: string|null}>
     */
    public static function resolverPorArticulos(
        iterable $articulosOrIds,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $articulos = [];
        foreach ($articulosOrIds as $item) {
            if ($item instanceof Articulo) {
                $articulos[(int) $item->id] = $item;
            } elseif (is_numeric($item) && (int) $item > 0) {
                $articulos[(int) $item] = null;
            }
        }

        if ($articulos === []) {
            return [];
        }

        $faltantes = array_keys(array_filter($articulos, static fn ($a) => $a === null));
        if ($faltantes !== []) {
            foreach (Articulo::query()->whereIn('id', $faltantes)->get(self::columnasSelectArticulo()) as $art) {
                $articulos[(int) $art->id] = $art;
            }
        }

        $skuPorArticuloId = [];
        $skus = [];
        foreach ($articulos as $id => $articulo) {
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $skuPorArticuloId[(int) $id] = $sku;
            $skus[] = $sku;
        }

        $anitaService ??= app(StkmaeUltimaCompraAnitaService::class);
        $datosAnita = $skus !== []
            ? $anitaService->obtenerDatosUltimaCompraPorSkus(array_values(array_unique($skus)))
            : [];

        $articuloIdsSinAnita = [];
        $out = [];
        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                $out[$id] = ['precio' => null, 'moneda_id' => null, 'origen' => null];

                continue;
            }

            $sku = $skuPorArticuloId[$id] ?? '';
            if ($sku !== '') {
                $datoAnita = $datosAnita[$sku] ?? null;
                $precioAnita = $datoAnita['precio'] ?? null;
                if ($precioAnita !== null && (float) $precioAnita > 0) {
                    $out[$id] = [
                        'precio' => round((float) $precioAnita, 6),
                        'moneda_id' => isset($datoAnita['moneda_id']) ? (int) $datoAnita['moneda_id'] : null,
                        'origen' => self::ORIGEN_ANITA,
                    ];

                    continue;
                }
            }

            $articuloIdsSinAnita[] = $id;
        }

        $erpPorId = self::preciosErpPorArticuloIds($articuloIdsSinAnita);
        foreach ($articuloIdsSinAnita as $id) {
            $articulo = $articulos[$id];
            if (isset($erpPorId[$id])) {
                $out[$id] = $erpPorId[$id];

                continue;
            }

            $fallback = self::fallbackDesdeArticulo($articulo);
            $out[$id] = $fallback !== null
                ? ['precio' => $fallback, 'moneda_id' => null, 'origen' => self::ORIGEN_ARTICULO]
                : ['precio' => null, 'moneda_id' => null, 'origen' => null];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float|null> clave = SKU del ERP
     */
    public static function resolverPreciosPorSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        if ($skus === []) {
            return [];
        }

        $articulos = Articulo::query()
            ->whereIn('sku', $skus)
            ->get(self::columnasSelectArticulo());

        $porSku = [];
        $porId = self::resolverPorArticulos($articulos);
        foreach ($articulos as $articulo) {
            $dato = $porId[(int) $articulo->id] ?? null;
            $porSku[(string) $articulo->sku] = $dato['precio'] ?? null;
        }

        foreach ($skus as $sku) {
            if (! array_key_exists($sku, $porSku)) {
                $porSku[$sku] = null;
            }
        }

        return $porSku;
    }

    /**
     * Asigna atributos virtuales {@see $atributoPrecio} y {@see $atributoOrigen} en líneas con artículo.
     *
     * @param  iterable<object>  $lineas
     */
    public static function enriquecerLineas(
        iterable $lineas,
        string $atributoPrecio = 'precio_ultima_compra',
        string $atributoOrigen = 'origen_precio_ultima_compra',
    ): void {
        $articuloIds = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea->articulo_id ?? optional($linea->articulos)->id ?? 0);
            if ($articuloId > 0) {
                $articuloIds[] = $articuloId;
            }
        }

        if ($articuloIds === []) {
            return;
        }

        $precios = self::resolverPorArticulos($articuloIds);
        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea->articulo_id ?? optional($linea->articulos)->id ?? 0);
            $dato = $precios[$articuloId] ?? ['precio' => null, 'origen' => null];
            $linea->{$atributoPrecio} = $dato['precio'];
            $linea->{$atributoOrigen} = $dato['origen'];
        }
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, moneda_id: int|null, origen: string}>
     */
    private static function preciosErpPorArticuloIds(array $articuloIds): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn ($id) => $id > 0)));
        if ($articuloIds === []) {
            return [];
        }

        $candidatos = [];

        $historia = Ordencompra_Articulo_Precio_Historia::query()
            ->whereIn('articulo_id', $articuloIds)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['articulo_id', 'precio_nuevo', 'fecha']);

        foreach ($historia as $fila) {
            $articuloId = (int) $fila->articulo_id;
            if ($articuloId <= 0 || isset($candidatos[$articuloId])) {
                continue;
            }
            $precio = (float) $fila->precio_nuevo;
            if ($precio <= 0) {
                continue;
            }
            $candidatos[$articuloId] = [
                'precio' => round($precio, 6),
                'moneda_id' => null,
                'origen' => self::ORIGEN_ERP_COM,
                'ref_ts' => $fila->fecha?->timestamp ?? 0,
            ];
        }

        $recepcionesQuery = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->whereIn('rpa.articulo_id', $articuloIds)
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION);

        if (Schema::hasColumn('recepcion_proveedor', 'deleted_at')) {
            $recepcionesQuery->whereNull('rp.deleted_at');
        }

        $recepciones = $recepcionesQuery
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rp.id')
            ->orderByDesc('rpa.id')
            ->get([
                'rpa.articulo_id',
                'rpa.precio',
                'rp.fecha',
            ]);

        foreach ($recepciones as $fila) {
            $articuloId = (int) $fila->articulo_id;
            $precio = (float) ($fila->precio ?? 0);
            if ($articuloId <= 0 || $precio <= 0) {
                continue;
            }

            $refTs = $fila->fecha ? strtotime((string) $fila->fecha) : 0;
            if (isset($candidatos[$articuloId]) && ($candidatos[$articuloId]['ref_ts'] ?? 0) >= $refTs) {
                continue;
            }

            $candidatos[$articuloId] = [
                'precio' => round($precio, 6),
                'moneda_id' => null,
                'origen' => self::ORIGEN_ERP_COM,
                'ref_ts' => $refTs,
            ];
        }

        $out = [];
        foreach ($candidatos as $articuloId => $dato) {
            unset($dato['ref_ts']);
            $out[$articuloId] = $dato;
        }

        return $out;
    }

    public static function fallbackPrecioDesdeArticulo(Articulo $articulo): ?float
    {
        return self::fallbackDesdeArticulo($articulo);
    }

    /**
     * @return list<string>
     */
    private static function camposFallbackOrdenados(): array
    {
        static $campos = null;
        if ($campos !== null) {
            return $campos;
        }

        $campos = [];
        foreach (['costo', 'ppp', 'precio'] as $campo) {
            if (Schema::hasColumn('articulo', $campo)) {
                $campos[] = $campo;
            }
        }

        return $campos;
    }

    /**
     * @return list<string>
     */
    private static function columnasSelectArticulo(): array
    {
        return array_values(array_unique(array_merge(['id', 'sku'], self::camposFallbackOrdenados())));
    }

    private static function fallbackDesdeArticulo(Articulo $articulo): ?float
    {
        foreach (self::camposFallbackOrdenados() as $campo) {
            $valor = (float) ($articulo->{$campo} ?? 0);
            if ($valor > 0) {
                return round($valor, 6);
            }
        }

        return null;
    }
}
