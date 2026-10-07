<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Configuracion\Feriado;
use App\Models\Ventas\TiendanubeConfiguracion;
use App\Models\Ventas\TiendanubeStockSubida;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalErpMovimientosSupport;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

        $query = TiendanubeConfiguracion::query()->where('sube_stock', true);
        $storeId = trim((string) $storeId);
        if ($storeId !== '') {
            $query->where('store_id', $storeId);
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
                'hora_subida' => self::normalizarHora((string) ($cfg->hora_subida ?: '14:00')) ?? '14:00',
                'deposito_ids' => TiendanubeConfiguracionSupport::depositoIdsStock($id),
                'listaprecio_precio_id' => (int) ($cfg->listaprecio_precio_id ?? 0),
                'listaprecio_oferta_id' => (int) ($cfg->listaprecio_oferta_id ?? 0),
            ];
        }

        if ($soloHoraActual) {
            $out = array_values(array_filter(
                $out,
                static fn (array $tienda): bool => self::horaYaAlcanzada($tienda['hora_subida'])
            ));
        }

        return $out;
    }

    /**
     * Lunes a viernes que no estén cargados en la tabla feriado.
     * La subida manual no usa este corte.
     */
    public static function esDiaHabilSubidaAutomatica(?CarbonInterface $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::now();
        if ($fecha->isWeekend()) {
            return false;
        }
        if (! Schema::hasTable('feriado')) {
            return true;
        }

        return ! Feriado::query()
            ->where('fecha', $fecha->toDateString())
            ->exists();
    }

    /** La hora configurada ya pasó (o es este minuto). Sirve para no perder el día si el cron no pega el minuto exacto. */
    public static function horaYaAlcanzada(string $horaProgramada, ?CarbonInterface $ahora = null): bool
    {
        $hora = self::normalizarHora($horaProgramada);
        if ($hora === null) {
            return false;
        }
        $ahora = $ahora ?? Carbon::now();

        return $ahora->format('H:i') >= $hora;
    }

    public static function cronYaCorrioHoy(string $storeId, string $horaProgramada, ?CarbonInterface $fecha = null): bool
    {
        if (! Schema::hasTable('tiendanube_stock_subida')) {
            return false;
        }
        $dia = ($fecha ?? Carbon::now())->toDateString();

        return TiendanubeStockSubida::query()
            ->where('store_id', $storeId)
            ->where('origen', TiendanubeStockSubida::ORIGEN_CRON)
            ->where('hora_programada', self::normalizarHora($horaProgramada) ?? $horaProgramada)
            ->where('inicio_at', '>=', $dia.' 00:00:00')
            ->where('inicio_at', '<=', $dia.' 23:59:59')
            ->exists();
    }

    public static function debeDispararCron(): bool
    {
        if (! EntornoEmpresaSupport::esFerli() || ! self::esDiaHabilSubidaAutomatica()) {
            return false;
        }

        foreach (self::tiendasParaSubir(null, true) as $tienda) {
            if (! self::cronYaCorrioHoy($tienda['store_id'], $tienda['hora_subida'])) {
                return true;
            }
        }

        return false;
    }

    private static function normalizarHora(string $hora): ?string
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m) !== 1) {
            return null;
        }
        $hh = (int) $m[1];
        $mm = (int) $m[2];
        if ($hh > 23 || $mm > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hh, $mm);
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
     * Solo artículos operativos del canal Local (solapa Marketplaces): canal LOCAL,
     * estado local activo y estado del maestro activo. Los viejos de fábrica o
     * inactivos en local quedan afuera aunque Anita les haya dejado una asignación.
     *
     * @return array<int, array{sku:string, combinaciones:list<string>}>
     */
    public static function articulosMarketplace(int $marketplaceCodigo): array
    {
        if ($marketplaceCodigo <= 0 || ! Schema::hasTable('articulo_marketplace')) {
            return [];
        }

        $query = DB::table('articulo_marketplace as am')
            ->join('marketplace as m', 'm.id', '=', 'am.marketplace_id')
            ->join('articulo', 'articulo.id', '=', 'am.articulo_id')
            ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
            ->where('m.codigo', $marketplaceCodigo)
            ->where('m.activo', true);
        ArticuloCanalSupport::scopeArticulosCanalLocal($query);

        $rows = $query
            ->orderBy('articulo.sku')
            ->orderBy('am.orden')
            ->get([
                'am.articulo_id',
                'articulo.sku',
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
     * Stock del informe de stock del local, sumado en cada depósito elegido.
     * Clave articulo|COLOR|TALLE. El talle "0" (sin talle) no arma variante.
     *
     * @param  list<int>  $depositoIds
     * @param  list<int>  $articuloIds
     * @return array<string, int>
     */
    public static function stockPorVariante(array $depositoIds, array $articuloIds): array
    {
        $depositoIds = array_values(array_unique(array_filter(array_map('intval', $depositoIds))));
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        if ($depositoIds === [] || $articuloIds === []) {
            return [];
        }

        $hasta = Carbon::today()->toDateString();
        $mapa = [];
        foreach ($depositoIds as $depositoId) {
            foreach (array_chunk($articuloIds, 400) as $lote) {
                $rows = StockLocalErpMovimientosSupport::filasPorDepositoYArticulos($depositoId, $lote, $hasta);
                foreach ($rows as $row) {
                    $cantidad = (float) $row->cantidad;
                    if (abs($cantidad) < 0.000001) {
                        continue;
                    }
                    [$color] = StockLocalErpMovimientosSupport::colorDesdeFila($row);
                    $talle = StockLocalErpMovimientosSupport::medidaKeyDesdeFila($row);
                    if ($color === '' || $talle === '' || $talle === '0') {
                        continue;
                    }
                    $clave = self::claveStock((int) $row->articulo_id, $color, $talle);
                    $mapa[$clave] = ($mapa[$clave] ?? 0.0) + $cantidad;
                }
            }
        }

        $out = [];
        foreach ($mapa as $clave => $cantidad) {
            $out[$clave] = (int) round($cantidad);
        }

        return $out;
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

}
