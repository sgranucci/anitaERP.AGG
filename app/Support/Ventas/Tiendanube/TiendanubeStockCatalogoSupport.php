<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubeConfiguracion;
use App\Models\Ventas\TiendanubeStockSubida;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\PrecioListaVigenteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Artículos de marketplace, stock por combinación/talle y precios de lista
 * que alimentan la subida a Tiendanube.
 */
final class TiendanubeStockCatalogoSupport
{
    /**
     * @return list<array{
     *   store_id:string,
     *   nombre:string,
     *   marketplace_codigo:int,
     *   hora_subida:string,
     *   deposito_ids:list<int>,
     *   listaprecio_precio_id:int,
     *   listaprecio_oferta_id:int
     * }>
     */
    public static function tiendasParaSubir(?string $storeId = null, bool $soloHoraActual = false): array
    {
        if (! Schema::hasTable('tiendanube_configuracion')
            || ! Schema::hasColumn('tiendanube_configuracion', 'sube_stock')) {
            return [];
        }

        $hora = Carbon::now()->format('H:i');
        $query = TiendanubeConfiguracion::query()->where('sube_stock', true);
        $storeId = trim((string) $storeId);
        if ($storeId !== '') {
            $query->where('store_id', $storeId);
        }
        if ($soloHoraActual) {
            $query->where('hora_subida', $hora);
        }

        $out = [];
        foreach ($query->orderBy('store_id')->get() as $cfg) {
            $id = trim((string) $cfg->store_id);
            if ($id === '' || TiendanubeTiendasSupport::porStoreId($id) === null) {
                continue;
            }
            $out[] = [
                'store_id' => $id,
                'nombre' => TiendanubeTiendasSupport::nombre($id),
                'marketplace_codigo' => (int) ($cfg->marketplace_codigo ?: 2),
                'hora_subida' => trim((string) ($cfg->hora_subida ?: '14:00')),
                'deposito_ids' => TiendanubeConfiguracionSupport::depositoIdsStock($id),
                'listaprecio_precio_id' => (int) ($cfg->listaprecio_precio_id ?? 0),
                'listaprecio_oferta_id' => (int) ($cfg->listaprecio_oferta_id ?? 0),
            ];
        }

        return $out;
    }

    public static function debeDispararCron(): bool
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return false;
        }

        return self::tiendasParaSubir(null, true) !== [];
    }

    public static function haySubidaEnCurso(): bool
    {
        if (! Schema::hasTable('tiendanube_stock_subida')) {
            return false;
        }

        return TiendanubeStockSubida::query()
            ->where('estado', TiendanubeStockSubida::ESTADO_PROCESO)
            ->where('inicio_at', '>=', Carbon::now()->subHours(3))
            ->exists();
    }

    /**
     * Asignaciones del marketplace, agrupadas por artículo.
     *
     * @return array<int, array{sku:string, combinaciones:list<string>}>
     */
    public static function articulosMarketplace(int $marketplaceCodigo): array
    {
        if ($marketplaceCodigo <= 0 || ! Schema::hasTable('articulo_marketplace')) {
            return [];
        }

        $rows = DB::table('articulo_marketplace as am')
            ->join('marketplace as m', 'm.id', '=', 'am.marketplace_id')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
            ->where('m.codigo', $marketplaceCodigo)
            ->where('m.activo', true)
            ->orderBy('a.sku')
            ->orderBy('am.orden')
            ->get([
                'am.articulo_id',
                'a.sku',
                'c.codigo as combinacion_codigo',
                'am.codigo_combinacion',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $articuloId = (int) $row->articulo_id;
            $codigo = trim((string) ($row->combinacion_codigo ?: $row->codigo_combinacion));
            if (! isset($out[$articuloId])) {
                $out[$articuloId] = [
                    'sku' => trim((string) $row->sku),
                    'combinaciones' => [],
                ];
            }
            if ($codigo === '') {
                continue;
            }
            $clave = self::clave($codigo);
            if (! in_array($clave, $out[$articuloId]['combinaciones'], true)) {
                $out[$articuloId]['combinaciones'][] = $codigo;
            }
        }

        return $out;
    }

    /**
     * Stock sumado de los depósitos, clave articulo|COMB|TALLE => unidades (mínimo 0).
     *
     * @param  list<int>  $depositoIds
     * @param  list<int>  $articuloIds
     * @return array<string, int>
     */
    public static function stockPorVariante(array $depositoIds, array $articuloIds): array
    {
        $depositoIds = array_values(array_filter(array_map('intval', $depositoIds)));
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        if ($depositoIds === [] || $articuloIds === []) {
            return [];
        }

        $mapa = [];
        foreach (array_chunk($articuloIds, 400) as $lote) {
            $rows = DB::table('articulo_movimiento as am')
                ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
                ->join('talle as t', 't.id', '=', 'amt.talle_id')
                ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
                ->whereIn('am.deposito_id', $depositoIds)
                ->whereIn('am.articulo_id', $lote)
                ->groupBy('am.articulo_id', 'c.codigo', 't.nombre', 't.codigo')
                ->get([
                    'am.articulo_id',
                    'c.codigo as combinacion_codigo',
                    't.nombre as talle_nombre',
                    't.codigo as talle_codigo',
                    DB::raw('SUM(amt.cantidad) as cantidad'),
                ]);

            foreach ($rows as $row) {
                $comb = trim((string) $row->combinacion_codigo);
                if ($comb === '') {
                    continue;
                }
                $cantidad = (int) round(max(0, (float) $row->cantidad));
                foreach (self::etiquetasTalle((string) $row->talle_nombre, (string) $row->talle_codigo) as $talle) {
                    $clave = self::claveStock((int) $row->articulo_id, $comb, $talle);
                    $mapa[$clave] = max($mapa[$clave] ?? 0, $cantidad);
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, list<string>>
     */
    public static function skusConocidosTienda(array $articuloIds): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        if ($articuloIds === [] || ! Schema::hasTable('tiendanube_pedido_linea')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($articuloIds, 400) as $lote) {
            $rows = DB::table('tiendanube_pedido_linea')
                ->whereIn('articulo_id', $lote)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->distinct()
                ->get(['articulo_id', 'sku']);
            foreach ($rows as $row) {
                $sku = trim((string) $row->sku);
                if ($sku === '') {
                    continue;
                }
                $out[(int) $row->articulo_id][$sku] = $sku;
            }
        }

        foreach ($out as $id => $skus) {
            $out[$id] = array_values($skus);
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio:?float, oferta:?float}>
     */
    public static function precios(array $articuloIds, int $listaPrecioId, int $listaOfertaId): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        $fecha = Carbon::today()->toDateString();
        $web = $listaPrecioId > 0
            ? PrecioListaVigenteSupport::vigentesPorArticulos($articuloIds, $listaPrecioId, $fecha)
            : [];
        $oferta = $listaOfertaId > 0
            ? PrecioListaVigenteSupport::vigentesPorArticulos($articuloIds, $listaOfertaId, $fecha)
            : [];

        $out = [];
        foreach ($articuloIds as $id) {
            $precio = isset($web[$id]) ? round((float) $web[$id]['precio'], 2) : null;
            $promo = isset($oferta[$id]) ? round((float) $oferta[$id]['precio'], 2) : null;
            if ($precio !== null && $precio <= 0) {
                $precio = null;
            }
            if ($promo !== null && $precio !== null && abs($promo - $precio) < 0.001) {
                $promo = 0.0;
            }
            if ($promo !== null && $promo <= 0 && $promo !== 0.0) {
                $promo = null;
            }
            $out[$id] = [
                'precio' => $precio,
                'oferta' => $precio === null ? null : ($promo ?? 0.0),
            ];
        }

        return $out;
    }

    /**
     * @return array{combinacion:string, talle:string}|null
     */
    public static function partesVariante(string $skuTienda, string $skuArticulo): ?array
    {
        $skuTienda = trim($skuTienda);
        $skuArticulo = trim($skuArticulo);
        if ($skuTienda === '' || $skuArticulo === '') {
            return null;
        }

        $prefix = $skuArticulo.'-';
        if (strncasecmp($skuTienda, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        $resto = substr($skuTienda, strlen($prefix));
        $pos = strrpos($resto, '-');
        if ($pos === false || $pos === 0) {
            return null;
        }

        $comb = trim(substr($resto, 0, $pos));
        $talle = trim(substr($resto, $pos + 1));
        if ($comb === '' || $talle === '') {
            return null;
        }

        return ['combinacion' => $comb, 'talle' => $talle];
    }

    public static function claveStock(int $articuloId, string $combinacion, string $talle): string
    {
        return $articuloId.'|'.self::clave($combinacion).'|'.self::clave($talle);
    }

    public static function clave(string $valor): string
    {
        return strtoupper(trim($valor));
    }

    /**
     * @return list<string>
     */
    private static function etiquetasTalle(string $nombre, string $codigo): array
    {
        $out = [];
        foreach ([$nombre, $codigo] as $valor) {
            $valor = trim($valor);
            if ($valor !== '' && ! in_array($valor, $out, true)) {
                $out[] = $valor;
            }
        }

        return $out;
    }
}
