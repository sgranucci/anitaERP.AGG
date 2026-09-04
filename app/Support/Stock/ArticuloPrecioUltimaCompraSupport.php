<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra_Articulo_Precio_Historia;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio unitario de última compra para valorización de stock.
 *
 * Orden de resolución (ver también config/stock.php → precio_ultima_compra):
 * Entre candidatos con precio &gt; 0 gana la fecha más reciente; empate → COM &gt; entrada ERP &gt; Anita.
 * 1. ERP COM: historia OC / recepción confirmada (cigarrillos: precio línea + II/u, como a-stock.c)
 * 2. ERP entrada: TRA confirmada (precio destino) o movimiento con cantidad &gt; 0
 * 3. Anita: stkmae.stkm_pre_compra3 unificado entre empresas (fecha máx.)
 * 4. Fallback: articulo.costo / articulo.ppp / articulo.precio
 */
final class ArticuloPrecioUltimaCompraSupport
{
    public const ORIGEN_ANITA = 'anita';

    public const ORIGEN_ERP_COM = 'erp_com';

    public const ORIGEN_ERP_ENTRADA = 'erp_entrada';

    public const ORIGEN_ARTICULO = 'articulo';

    /** Ajustes de recuento: no son compra; no deben pisar última compra. */
    private const ABREV_ENTRADA_NO_COMPRA = ['RCAJP', 'RCAJN', 'RCAJR'];

    /**
     * Índice (articulo_id, fecha, id, cantidad, precio) sobre articulo_movimiento. Si existe, la
     * "última entrada" se resuelve con una consulta LIMIT 1 por artículo (backward index scan +
     * ICP) en vez de barrer y ordenar todos los movimientos de los artículos pedidos.
     * DDL: ALTER TABLE articulo_movimiento ADD INDEX idx_am_ultima_entrada (articulo_id, fecha, id, cantidad, precio), ALGORITHM=INPLACE, LOCK=NONE;
     */
    public const IDX_ULTIMA_ENTRADA = 'idx_am_ultima_entrada';

    private const CACHE_KEY_IDX_ULTIMA_ENTRADA = 'stock.articulo_movimiento.idx_ultima_entrada';

    /** Prioridad en empate de fecha (mayor gana). */
    private const PRIORIDAD_ORIGEN = [
        self::ORIGEN_ERP_COM => 30,
        self::ORIGEN_ERP_ENTRADA => 20,
        self::ORIGEN_ANITA => 10,
        self::ORIGEN_ARTICULO => 0,
    ];

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

        $articuloIds = [];
        $skuPorArticuloId = [];
        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $articuloIds[] = $id;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku !== '') {
                $skuPorArticuloId[$id] = $sku;
            }
        }

        /** @var array<int, array{precio: float, moneda_id: int|null, origen: string, ref_ts: int}> $candidatos */
        $candidatos = [];

        foreach (self::preciosErpComConFecha($articuloIds) as $id => $dato) {
            self::considerarCandidato($candidatos, $id, $dato);
        }
        foreach (self::preciosErpEntradaConFecha($articuloIds) as $id => $dato) {
            self::considerarCandidato($candidatos, $id, $dato);
        }

        $skusAnita = array_values(array_unique(array_filter($skuPorArticuloId)));
        $anitaService ??= app(StkmaeUltimaCompraAnitaService::class);
        $datosAnita = $skusAnita !== []
            ? $anitaService->obtenerDatosUltimaCompraUnificadaPorSkus($skusAnita)
            : [];

        foreach ($skuPorArticuloId as $id => $sku) {
            $datoAnita = $datosAnita[$sku] ?? null;
            $precioAnita = $datoAnita['precio'] ?? null;
            if ($precioAnita === null || (float) $precioAnita <= 0) {
                continue;
            }
            $fechaYmd = $datoAnita['fecha_ymd'] ?? null;
            $refTs = is_string($fechaYmd) && $fechaYmd !== ''
                ? (int) strtotime($fechaYmd)
                : 0;
            self::considerarCandidato($candidatos, $id, [
                'precio' => round((float) $precioAnita, 6),
                'moneda_id' => isset($datoAnita['moneda_id']) ? (int) $datoAnita['moneda_id'] : null,
                'origen' => self::ORIGEN_ANITA,
                'ref_ts' => $refTs,
            ]);
        }

        $out = [];
        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                $out[$id] = ['precio' => null, 'moneda_id' => null, 'origen' => null];

                continue;
            }

            if (isset($candidatos[$id])) {
                $c = $candidatos[$id];
                $out[$id] = [
                    'precio' => $c['precio'],
                    'moneda_id' => $c['moneda_id'],
                    'origen' => $c['origen'],
                ];

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
        $datos = self::resolverDatosPorSkus($skus);
        $out = [];
        foreach ($datos as $sku => $dato) {
            $out[$sku] = $dato['precio'] ?? null;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{precio: float|null, moneda_id: int|null, origen: string|null}>
     */
    public static function resolverDatosPorSkus(array $skus): array
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
            $porSku[(string) $articulo->sku] = [
                'precio' => $dato['precio'] ?? null,
                'moneda_id' => $dato['moneda_id'] ?? null,
                'origen' => $dato['origen'] ?? null,
            ];
        }

        foreach ($skus as $sku) {
            if (! array_key_exists($sku, $porSku)) {
                $porSku[$sku] = ['precio' => null, 'moneda_id' => null, 'origen' => null];
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
     * Asigna {@see $hijo->costo_ultima_compra} (no persistido) a líneas de fórmula con artículo.
     *
     * @param  iterable<object>  $hijos
     */
    public static function enriquecerLineasFormulaConCosto(iterable $hijos): void
    {
        $skus = [];
        foreach ($hijos as $hijo) {
            $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        if ($skus === []) {
            return;
        }

        $precios = self::resolverPreciosPorSkus($skus);
        foreach ($hijos as $hijo) {
            $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
            $hijo->costo_ultima_compra = $sku !== '' ? ($precios[$sku] ?? null) : null;
        }
    }

    /**
     * @param  iterable<\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Support\Collection|array>  $formulas
     */
    public static function enriquecerFormulasPaginadasConCosto(iterable $formulas): void
    {
        $skus = [];
        foreach ($formulas as $formula) {
            foreach ($formula->formula_articulo_hijos ?? [] as $hijo) {
                $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        if ($skus === []) {
            return;
        }

        $precios = self::resolverPreciosPorSkus($skus);
        foreach ($formulas as $formula) {
            foreach ($formula->formula_articulo_hijos ?? [] as $hijo) {
                $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
                $hijo->costo_ultima_compra = $sku !== '' ? ($precios[$sku] ?? null) : null;
            }
        }
    }

    /**
     * Precio de línea COM en moneda local (pesos).
     *
     * Solo convierte cuando la moneda de la línea es divisa (≠ moneda local).
     * No multiplicar cotización si moneda = pesos: en COM Anita/manual la cotización
     * del día suele quedar cargada aunque el precio ya esté en pesos (incidente TRA
     * Otros Activos ago/2026: LIB0036 4941×1405 → 6.9M, LIB0184 3200×1405 → 4.5M).
     */
    public static function precioUnitarioMonedaLocal(float $precio, ?int $monedaId, ?float $cotizacion): float
    {
        if ($precio <= 0) {
            return 0.0;
        }

        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $monedaId = (int) ($monedaId ?? 0);
        $cotizacion = (float) ($cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        if ($monedaId > 0 && $monedaId !== $monedaLocalId) {
            return round($precio * $cotizacion, 6);
        }

        return round($precio, 6);
    }

    /**
     * Últimas N recepciones COM confirmadas con precio &gt; 0 (más recientes primero).
     * Los precios se devuelven en moneda local (pesos) según moneda ERP de la línea.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, list<float>> articulo_id => precios (máx. $limite)
     */
    public static function ultimasRecepcionesConfirmadasPorArticuloIds(array $articuloIds, int $limite = 3): array
    {
        $detalle = self::ultimasRecepcionesConfirmadasDetallePorArticuloIds($articuloIds, $limite);
        $out = [];
        foreach ($detalle as $articuloId => $filas) {
            $out[$articuloId] = [];
            foreach ($filas as $fila) {
                $precioLocal = self::precioUnitarioMonedaLocal(
                    (float) ($fila['precio'] ?? 0),
                    isset($fila['moneda_id']) ? (int) $fila['moneda_id'] : null,
                    isset($fila['cotizacion']) ? (float) $fila['cotizacion'] : null,
                );
                if ($precioLocal <= 0) {
                    continue;
                }
                $out[$articuloId][] = $precioLocal;
            }
        }

        return $out;
    }

    /**
     * Últimas N COM confirmadas (sin convertir). Incluye clave Anita para leer recepmov.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, list<array{
     *     precio: float,
     *     moneda_id: int|null,
     *     cotizacion: float|null,
     *     fecha: string|null,
     *     numerorecepcion: int|null,
     *     sku: string,
     *     anita_tipo: string,
     *     anita_letra: string,
     *     anita_sucursal: int,
     *     anita_nro: int
     * }>>
     */
    public static function ultimasRecepcionesConfirmadasDetallePorArticuloIds(array $articuloIds, int $limite = 3): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn ($id) => $id > 0)));
        $limite = max(1, $limite);
        if ($articuloIds === []) {
            return [];
        }

        $columnas = [
            'rpa.articulo_id',
            'rpa.precio',
            'rpa.moneda_id',
            'rpa.cotizacion',
            'rp.fecha',
            'rp.numerorecepcion',
            'a.sku',
        ];
        if (Schema::hasColumn('recepcion_proveedor', 'anita_tipo')) {
            $columnas[] = 'rp.anita_tipo';
            $columnas[] = 'rp.anita_letra';
            $columnas[] = 'rp.anita_sucursal';
            $columnas[] = 'rp.anita_nro';
        }

        $recepcionesQuery = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->whereIn('rpa.articulo_id', $articuloIds)
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('rpa.precio', '>', 0);

        if (Schema::hasColumn('recepcion_proveedor', 'deleted_at')) {
            $recepcionesQuery->whereNull('rp.deleted_at');
        }

        $recepciones = $recepcionesQuery
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rp.id')
            ->orderByDesc('rpa.id')
            ->get($columnas);

        $out = [];
        foreach ($recepciones as $fila) {
            $articuloId = (int) $fila->articulo_id;
            if ($articuloId <= 0) {
                continue;
            }
            if (! isset($out[$articuloId])) {
                $out[$articuloId] = [];
            }
            if (count($out[$articuloId]) >= $limite) {
                continue;
            }
            $precio = (float) ($fila->precio ?? 0);
            if ($precio <= 0) {
                continue;
            }
            $out[$articuloId][] = [
                'precio' => $precio,
                'moneda_id' => isset($fila->moneda_id) ? (int) $fila->moneda_id : null,
                'cotizacion' => isset($fila->cotizacion) ? (float) $fila->cotizacion : null,
                'fecha' => $fila->fecha ? (string) $fila->fecha : null,
                'numerorecepcion' => isset($fila->numerorecepcion) ? (int) $fila->numerorecepcion : null,
                'sku' => trim((string) ($fila->sku ?? '')),
                'anita_tipo' => trim((string) ($fila->anita_tipo ?? 'COM')) ?: 'COM',
                'anita_letra' => trim((string) ($fila->anita_letra ?? 'X')) ?: 'X',
                'anita_sucursal' => (int) ($fila->anita_sucursal ?? 0),
                'anita_nro' => (int) ($fila->anita_nro ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, moneda_id: int|null, origen: string}>
     */
    private static function preciosErpPorArticuloIds(array $articuloIds): array
    {
        $candidatos = [];
        foreach (self::preciosErpComConFecha($articuloIds) as $id => $dato) {
            self::considerarCandidato($candidatos, $id, $dato);
        }
        foreach (self::preciosErpEntradaConFecha($articuloIds) as $id => $dato) {
            self::considerarCandidato($candidatos, $id, $dato);
        }

        $out = [];
        foreach ($candidatos as $articuloId => $dato) {
            unset($dato['ref_ts']);
            $out[$articuloId] = $dato;
        }

        return $out;
    }

    /**
     * @param  array<int, array{precio: float, moneda_id: int|null, origen: string, ref_ts: int}>  $candidatos
     * @param  array{precio: float, moneda_id: int|null, origen: string, ref_ts: int}  $nuevo
     */
    private static function considerarCandidato(array &$candidatos, int $articuloId, array $nuevo): void
    {
        if ($articuloId <= 0 || (float) $nuevo['precio'] <= 0) {
            return;
        }

        $prev = $candidatos[$articuloId] ?? null;
        if ($prev === null) {
            $candidatos[$articuloId] = $nuevo;

            return;
        }

        $tsNuevo = (int) ($nuevo['ref_ts'] ?? 0);
        $tsPrev = (int) ($prev['ref_ts'] ?? 0);
        if ($tsNuevo > $tsPrev) {
            $candidatos[$articuloId] = $nuevo;

            return;
        }
        if ($tsNuevo < $tsPrev) {
            return;
        }

        $prioNuevo = self::PRIORIDAD_ORIGEN[$nuevo['origen']] ?? 0;
        $prioPrev = self::PRIORIDAD_ORIGEN[$prev['origen']] ?? 0;
        if ($prioNuevo > $prioPrev) {
            $candidatos[$articuloId] = $nuevo;
        }
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, moneda_id: int|null, origen: string, ref_ts: int}>
     */
    private static function preciosErpComConFecha(array $articuloIds): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn ($id) => $id > 0)));
        if ($articuloIds === []) {
            return [];
        }

        $candidatos = [];

        $columnasHistoria = ['articulo_id', 'precio_nuevo', 'fecha'];
        if (Schema::hasColumn('ordencompra_articulo_precio_historia', 'recepcion_proveedor_id')) {
            $columnasHistoria[] = 'recepcion_proveedor_id';
        }

        $historia = Ordencompra_Articulo_Precio_Historia::query()
            ->whereIn('articulo_id', $articuloIds)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get($columnasHistoria);

        foreach ($historia as $fila) {
            $articuloId = (int) $fila->articulo_id;
            if ($articuloId <= 0 || isset($candidatos[$articuloId])) {
                continue;
            }
            // Historia nacida de una COM: el neto de línea no incluye II.
            // La recepción (abajo) aplica precio + II/u como a-stock.c.
            if ((int) ($fila->recepcion_proveedor_id ?? 0) > 0) {
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

        $recepcionesQuery = DB::query()
            ->from(DB::raw(
                '`recepcion_proveedor_articulo` as `rpa` STRAIGHT_JOIN `recepcion_proveedor` as `rp` ON `rp`.`id` = `rpa`.`recepcion_proveedor_id`'
            ))
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
                'rpa.moneda_id',
                'rpa.cotizacion',
                'rp.id as recepcion_id',
                'rp.fecha',
            ]);

        /** @var array<int, array{precio_local: float, moneda_id: int|null, ref_ts: int, recepcion_id: int}> $pendientes */
        $pendientes = [];
        foreach ($recepciones as $fila) {
            $articuloId = (int) $fila->articulo_id;
            $precioRaw = (float) ($fila->precio ?? 0);
            if ($articuloId <= 0 || $precioRaw <= 0) {
                continue;
            }

            $precio = self::precioUnitarioMonedaLocal(
                $precioRaw,
                isset($fila->moneda_id) ? (int) $fila->moneda_id : null,
                isset($fila->cotizacion) ? (float) $fila->cotizacion : null,
            );
            if ($precio <= 0) {
                continue;
            }

            $refTs = $fila->fecha ? (int) strtotime((string) $fila->fecha) : 0;
            if (isset($candidatos[$articuloId]) && ($candidatos[$articuloId]['ref_ts'] ?? 0) >= $refTs) {
                continue;
            }
            if (isset($pendientes[$articuloId])) {
                continue;
            }

            $pendientes[$articuloId] = [
                'precio_local' => $precio,
                'moneda_id' => (int) config('cotizacion.ID_MONEDA_DEFAULT', 1),
                'ref_ts' => $refTs,
                'recepcion_id' => (int) ($fila->recepcion_id ?? 0),
            ];
        }

        if ($pendientes === []) {
            return $candidatos;
        }

        $tipoCigarrilloId = RecepcionProveedorImpuestoInternoSupport::tipoArticuloCigarrilloId();
        $tipoPorArticulo = DB::table('articulo')
            ->whereIn('id', array_keys($pendientes))
            ->pluck('tipoarticulo_id', 'id');

        $recepcionIdsCig = [];
        if ($tipoCigarrilloId !== null) {
            foreach ($pendientes as $articuloId => $dato) {
                if ((int) ($tipoPorArticulo[$articuloId] ?? 0) === $tipoCigarrilloId
                    && ($dato['recepcion_id'] ?? 0) > 0) {
                    $recepcionIdsCig[] = (int) $dato['recepcion_id'];
                }
            }
        }
        $iiPorRecepcion = RecepcionProveedorImpuestoInternoSupport::impuestoInternoPorUnidadPorRecepcionIds(
            $recepcionIdsCig
        );

        foreach ($pendientes as $articuloId => $dato) {
            $esCigarrillo = $tipoCigarrilloId !== null
                && (int) ($tipoPorArticulo[$articuloId] ?? 0) === $tipoCigarrilloId;
            $iiUnidad = $esCigarrillo
                ? (float) ($iiPorRecepcion[(int) $dato['recepcion_id']] ?? 0)
                : 0.0;
            $precio = RecepcionProveedorImpuestoInternoSupport::precioUltimaCompraConImpuestoInterno(
                (float) $dato['precio_local'],
                $iiUnidad,
                $esCigarrillo,
            );
            if ($precio <= 0) {
                continue;
            }

            $candidatos[$articuloId] = [
                'precio' => $precio,
                'moneda_id' => $dato['moneda_id'],
                'origen' => self::ORIGEN_ERP_COM,
                'ref_ts' => $dato['ref_ts'],
            ];
        }

        return $candidatos;
    }

    /**
     * TRA confirmada (precio destino) y última entrada de stock (cantidad &gt; 0).
     *
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, moneda_id: int|null, origen: string, ref_ts: int}>
     */
    private static function preciosErpEntradaConFecha(array $articuloIds): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn ($id) => $id > 0)));
        if ($articuloIds === []) {
            return [];
        }

        $candidatos = [];

        $tras = DB::table('transferencia_mercaderia_articulo as tma')
            ->join('transferencia_mercaderia as tm', 'tm.id', '=', 'tma.transferencia_mercaderia_id')
            ->whereIn('tma.articulo_destino_id', $articuloIds)
            ->where('tm.estado', TransferenciaMercaderiaEstados::CONFIRMADA)
            ->where('tma.precio_costo_destino', '>', 0)
            ->orderByDesc('tm.fecha')
            ->orderByDesc('tm.id')
            ->orderByDesc('tma.id')
            ->get([
                'tma.articulo_destino_id',
                'tma.precio_costo_destino',
                'tm.fecha',
            ]);

        foreach ($tras as $fila) {
            $articuloId = (int) $fila->articulo_destino_id;
            if ($articuloId <= 0 || isset($candidatos[$articuloId])) {
                continue;
            }
            $precio = round((float) $fila->precio_costo_destino, 6);
            if ($precio <= 0) {
                continue;
            }
            $fecha = (string) ($fila->fecha ?? '');
            $refTs = $fecha !== '' ? (int) strtotime(substr($fecha, 0, 10)) : 0;
            $candidatos[$articuloId] = [
                'precio' => $precio,
                'moneda_id' => (int) config('cotizacion.ID_MONEDA_DEFAULT', 1),
                'origen' => self::ORIGEN_ERP_ENTRADA,
                'ref_ts' => $refTs,
            ];
        }

        $movs = self::tieneIndiceUltimaEntrada()
            ? self::ultimaEntradaMovimientoPorArticulo($articuloIds)
            : self::queryEntradasMovimiento()
                ->whereIn('am.articulo_id', $articuloIds)
                ->orderByDesc('am.fecha')
                ->orderByDesc('am.id')
                ->get(['am.articulo_id', 'am.precio', 'am.fecha']);

        foreach ($movs as $fila) {
            $articuloId = (int) $fila->articulo_id;
            if ($articuloId <= 0) {
                continue;
            }
            $precio = round((float) $fila->precio, 6);
            if ($precio <= 0) {
                continue;
            }
            $fecha = (string) ($fila->fecha ?? '');
            $refTs = $fecha !== '' ? (int) strtotime(substr($fecha, 0, 10)) : 0;
            if (isset($candidatos[$articuloId]) && ($candidatos[$articuloId]['ref_ts'] ?? 0) >= $refTs) {
                continue;
            }
            $candidatos[$articuloId] = [
                'precio' => $precio,
                'moneda_id' => (int) config('cotizacion.ID_MONEDA_DEFAULT', 1),
                'origen' => self::ORIGEN_ERP_ENTRADA,
                'ref_ts' => $refTs,
            ];
        }

        return $candidatos;
    }

    /**
     * Movimientos que cuentan como entrada de compra (cantidad y precio > 0, sin recuentos).
     */
    private static function queryEntradasMovimiento(): \Illuminate\Database\Query\Builder
    {
        return DB::table('articulo_movimiento as am')
            ->leftJoin('tipotransaccion_stock as tts', 'tts.id', '=', 'am.tipotransaccion_stock_id')
            ->where('am.cantidad', '>', 0)
            ->where('am.precio', '>', 0)
            ->where(function ($q) {
                $q->whereNull('tts.abreviatura')
                    ->orWhereNotIn('tts.abreviatura', self::ABREV_ENTRADA_NO_COMPRA);
            })
            ->where(function ($q) {
                $q->whereNull('am.concepto')
                    ->orWhere('am.concepto', 'not like', 'Recuento%');
            });
    }

    /**
     * Última entrada por artículo con IDX_ULTIMA_ENTRADA: una consulta LIMIT 1 por artículo,
     * que recorre el índice hacia atrás y corta en la primera entrada válida.
     *
     * @param  list<int>  $articuloIds
     * @return list<object{articulo_id: int, precio: string, fecha: string}>
     */
    private static function ultimaEntradaMovimientoPorArticulo(array $articuloIds): array
    {
        $out = [];
        foreach ($articuloIds as $articuloId) {
            $fila = self::queryEntradasMovimiento()
                ->useIndex(self::IDX_ULTIMA_ENTRADA)
                ->where('am.articulo_id', $articuloId)
                ->orderByDesc('am.fecha')
                ->orderByDesc('am.id')
                ->first(['am.articulo_id', 'am.precio', 'am.fecha']);
            if ($fila !== null) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * Existencia del índice, cacheada 10 min: al crearlo (o borrarlo) el cambio de estrategia
     * entra solo, sin flag ni deploy. Con `php artisan cache:forget` de la clave se fuerza.
     */
    public static function tieneIndiceUltimaEntrada(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY_IDX_ULTIMA_ENTRADA, 600, static function (): int {
            try {
                $filas = DB::select('SHOW INDEX FROM articulo_movimiento WHERE Key_name = ?', [self::IDX_ULTIMA_ENTRADA]);

                return $filas !== [] ? 1 : 0;
            } catch (\Throwable) {
                return 0;
            }
        });
    }

    public static function olvidarCacheIndiceUltimaEntrada(): void
    {
        Cache::forget(self::CACHE_KEY_IDX_ULTIMA_ENTRADA);
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
