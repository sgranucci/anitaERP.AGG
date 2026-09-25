<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Mventa;
use App\Models\Ventas\LocalVenta;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalErpMovimientosSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalInformeListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Informe de stock del local (puerto de l-stocklocal.c).
 * Origen default: ERP (articulo_movimiento). Opcional: Anita Local (stkdep/stkvmed).
 */
final class StockLocalInformeService
{
    private const LONGITUD_SKU_ANITA = 13;

    private const SESSION_CACHE_KEY = 'stock_local_informe_cache';

    private const IN_CHUNK = 300;

    /** @var array<string, int>|null */
    private ?array $mapaSignoTcomp = null;

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   local?:LocalVenta,
     *   deposito_anita?:int,
     *   deposito_erp_id?:int,
     *   medidas:list<int|string>,
     *   filas:\Illuminate\Support\Collection|LengthAwarePaginator,
     *   totales:array{total_filas:int,total_grupos:int,total_stock:float,origen:string},
     *   subtitulo:string
     * }
     */
    public function consultar(array $filtros, bool $paginar = true, int $porPagina = 40): array
    {
        $local = $this->resolverLocal($filtros);
        $origen = (string) ($filtros['origen'] ?? StockLocalInformeListadoFiltros::ORIGEN_ERP);
        $depositoAnita = 0;
        $depositoErpId = 0;

        if ($origen === StockLocalInformeListadoFiltros::ORIGEN_ANITA) {
            if ($local === null) {
                return $this->resultadoError(
                    'Para Anita Local seleccione un local concreto (no «Todos»).',
                    $paginar,
                    $porPagina
                );
            }
            $depositoAnita = (int) ($filtros['deposito_anita'] ?? 0);
            if ($depositoAnita <= 0) {
                $depositoAnita = (int) ($local->anita_deposito ?: 0);
            }
            if ($depositoAnita <= 0) {
                return $this->resultadoError(
                    'El local no tiene depósito Anita configurado (anita_deposito).',
                    $paginar,
                    $porPagina,
                    $local
                );
            }
        } else {
            $depositoErpId = (int) ($filtros['deposito_erp_id'] ?? 0);
            if ($depositoErpId <= 0 && $local !== null) {
                $depositoErpId = (int) ($local->deposito_id ?: 0);
            }
            if ($depositoErpId <= 0) {
                return $this->resultadoError(
                    'Indique un depósito ERP, o elija un local que tenga depósito configurado.',
                    $paginar,
                    $porPagina,
                    $local
                );
            }
            // «Todos» + depósito: anclar a un local del mismo depósito solo para metadatos.
            // Depósitos de fábrica pueden no tener local (no facturan).
            if ($local === null) {
                $local = LocalVenta::query()
                    ->where('activo', true)
                    ->where('deposito_id', $depositoErpId)
                    ->orderBy('codigo')
                    ->first();
            }
            if ($local === null) {
                // Placeholder mínimo para construir() (ERP no usa datos del local).
                $local = new LocalVenta([
                    'codigo' => '',
                    'nombre' => 'Sin local',
                    'deposito_id' => $depositoErpId,
                    'anita_deposito' => 0,
                    'activo' => true,
                ]);
            }
        }

        $firma = StockLocalInformeListadoFiltros::firma($filtros);
        $cached = $this->leerCache($firma);
        if ($cached === null) {
            $built = $this->construir($local, $depositoAnita, $depositoErpId, $filtros);
            if (! ($built['ok'] ?? false)) {
                return array_merge($this->resultadoError(
                    (string) ($built['error'] ?? 'No se pudo armar el informe.'),
                    $paginar,
                    $porPagina,
                    $local
                ), [
                    'deposito_anita' => $depositoAnita ?: null,
                    'deposito_erp_id' => $depositoErpId ?: null,
                ]);
            }
            $this->guardarCache($firma, $built);
            $cached = $built;
        }

        $todas = collect($cached['filas'] ?? []);
        $medidas = $cached['medidas'] ?? [];
        $totales = $cached['totales'] ?? [
            'total_filas' => $todas->count(),
            'total_grupos' => 0,
            'total_stock' => 0.0,
            'origen' => '',
        ];

        if ($paginar) {
            $page = LengthAwarePaginator::resolveCurrentPage();
            $slice = $todas->forPage($page, $porPagina)->values();
            $filas = new LengthAwarePaginator($slice, $todas->count(), $porPagina, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]);
        } else {
            $filas = $todas;
        }

        return [
            'ok' => true,
            'local' => $local,
            'deposito_anita' => $depositoAnita ?: null,
            'deposito_erp_id' => $depositoErpId ?: null,
            'medidas' => $medidas,
            'filas' => $filas,
            'totales' => $totales,
            'subtitulo' => $this->subtituloFiltros($filtros, $local, $depositoAnita, $depositoErpId),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function subtituloFiltros(
        array $filtros,
        ?LocalVenta $local = null,
        ?int $depositoAnita = null,
        ?int $depositoErpId = null
    ): string {
        $local ??= $this->resolverLocal($filtros);
        $parts = [];
        if ($local) {
            if (empty($filtros['local_venta_id'])) {
                $parts[] = 'Sin local / todos (dep. ERP '.$depositoErpId.')';
            } else {
                $parts[] = 'Local '.$local->codigo.' '.$local->nombre;
            }
        } elseif (! empty($depositoErpId)) {
            $parts[] = 'Dep. ERP '.$depositoErpId.' (sin local asignado)';
        }
        $origen = (string) ($filtros['origen'] ?? StockLocalInformeListadoFiltros::ORIGEN_ERP);
        $parts[] = StockLocalInformeListadoFiltros::etiquetaOrigen($origen);
        if ($origen === StockLocalInformeListadoFiltros::ORIGEN_ANITA) {
            $dep = $depositoAnita ?? (int) ($filtros['deposito_anita'] ?? ($local->anita_deposito ?? 0));
            if ($dep > 0) {
                $parts[] = 'Depósito Anita '.$dep;
            }
        } else {
            $depErp = (int) ($depositoErpId
                ?: ($filtros['deposito_erp_id'] ?? 0)
                ?: ($local->deposito_id ?? 0));
            if ($depErp > 0) {
                $parts[] = 'Depósito ERP id '.$depErp;
            }
        }
        $parts[] = StockLocalInformeListadoFiltros::etiquetaModo((string) ($filtros['modo'] ?? 'saldo'));
        $parts[] = StockLocalInformeListadoFiltros::etiquetaOrden((string) ($filtros['orden'] ?? 'articulo'));
        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            $parts[] = 'Fechas '.($filtros['fecha_desde'] ?? '…').' / '.($filtros['fecha_hasta'] ?? '…');
        }
        $desdeSku = trim((string) ($filtros['desde_sku'] ?? ''));
        $hastaSku = trim((string) ($filtros['hasta_sku'] ?? ''));
        if ($desdeSku !== '' || $hastaSku !== '') {
            $parts[] = 'SKU '.$desdeSku.' / '.$hastaSku;
        }
        $mventaId = (int) ($filtros['mventa_id'] ?? 0);
        if ($mventaId > 0) {
            $nombreMarca = trim((string) ($filtros['mventa_nombre'] ?? ''));
            if ($nombreMarca === '') {
                $nombreMarca = (string) (Mventa::query()->whereKey($mventaId)->value('nombre') ?? '');
            }
            $parts[] = 'Marca '.($nombreMarca !== '' ? $nombreMarca : '#'.$mventaId);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ok:bool,error?:string,medidas?:list<int|string>,filas?:list<array<string,mixed>>,totales?:array<string,mixed>}
     */
    private function construir(LocalVenta $local, int $depositoAnita, int $depositoErpId, array $filtros): array
    {
        $articulos = $this->articulosErp($filtros);
        if ($articulos->isEmpty()) {
            return [
                'ok' => true,
                'medidas' => [],
                'filas' => [],
                'totales' => [
                    'total_filas' => 0,
                    'total_grupos' => 0,
                    'total_stock' => 0.0,
                    'origen' => '',
                ],
            ];
        }

        /** @var array<string, array{id:int,sku:string,descripcion:string,categoria:string,categoria_codigo:string}> $porSkuAnita */
        $porSkuAnita = [];
        /** @var array<int, array{id:int,sku:string,descripcion:string,categoria:string,categoria_codigo:string,sku_anita:string}> $porArticuloId */
        $porArticuloId = [];
        foreach ($articulos as $art) {
            $skuAnita = $this->codigoAnitaDesdeSku((string) $art->sku);
            $meta = [
                'id' => (int) $art->id,
                'sku' => (string) $art->sku,
                'descripcion' => (string) $art->descripcion,
                'categoria' => (string) ($art->categorias->nombre ?? ''),
                'categoria_codigo' => (string) ($art->categorias->codigo ?? ''),
                'sku_anita' => $skuAnita,
            ];
            $porSkuAnita[$skuAnita] = $meta;
            $porArticuloId[(int) $art->id] = $meta;
        }

        $origen = (string) ($filtros['origen'] ?? StockLocalInformeListadoFiltros::ORIGEN_ERP);
        $modo = (string) ($filtros['modo'] ?? StockLocalInformeListadoFiltros::MODO_SALDO);

        if ($origen === StockLocalInformeListadoFiltros::ORIGEN_ANITA) {
            if ($modo === StockLocalInformeListadoFiltros::MODO_DETALLE) {
                return [
                    'ok' => false,
                    'error' => 'El detalle con tipo y número de comprobante solo está disponible con origen ERP (sin tilde Anita).',
                ];
            }
            if ($modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $agg = $this->agregarDesdeStkvmed($local, $depositoAnita, $filtros, array_keys($porSkuAnita));
                $etiquetaOrigen = 'anita_stkvmed';
            } else {
                $agg = $this->agregarDesdeStkdep($local, $depositoAnita, array_keys($porSkuAnita));
                if (($agg['error'] ?? null) !== null && ($agg['grupos'] ?? []) === []) {
                    $agg = $this->agregarDesdeStkvmed($local, $depositoAnita, $filtros, array_keys($porSkuAnita));
                    $etiquetaOrigen = 'anita_stkvmed';
                } else {
                    $etiquetaOrigen = 'anita_stkdep';
                }
            }
            if (($agg['error'] ?? null) !== null && ($agg['grupos'] ?? []) === []) {
                return ['ok' => false, 'error' => $agg['error'] ?? 'Error al leer Anita Local.'];
            }
            $coloresDesc = $this->mapaColoresErp(array_column($porSkuAnita, 'id'));
            $filas = $this->armarFilas(
                $agg['grupos'] ?? [],
                $porSkuAnita,
                $coloresDesc,
                $modo,
                (string) ($filtros['orden'] ?? StockLocalInformeListadoFiltros::ORDEN_ARTICULO),
                (bool) ($filtros['solo_con_saldo'] ?? true),
                $filtros['desde_color'] ?? null,
                $filtros['hasta_color'] ?? null
            );
            $medidas = $agg['medidas'] ?? [];
        } elseif ($modo === StockLocalInformeListadoFiltros::MODO_DETALLE) {
            $agg = $this->agregarDetalleDesdeErp($depositoErpId, $filtros, array_keys($porArticuloId));
            if (($agg['error'] ?? null) !== null) {
                return ['ok' => false, 'error' => $agg['error']];
            }
            $etiquetaOrigen = 'erp_articulo_movimiento_detalle';
            $filas = $this->armarFilasDetalleErp(
                $agg['movimientos'] ?? [],
                $porArticuloId,
                (string) ($filtros['orden'] ?? StockLocalInformeListadoFiltros::ORDEN_ARTICULO),
                $filtros['desde_color'] ?? null,
                $filtros['hasta_color'] ?? null
            );
            $medidas = $agg['medidas'] ?? [];
        } else {
            $agg = $this->agregarDesdeErp($depositoErpId, $filtros, array_keys($porArticuloId));
            if (($agg['error'] ?? null) !== null) {
                return ['ok' => false, 'error' => $agg['error']];
            }
            $etiquetaOrigen = 'erp_articulo_movimiento';
            $filas = $this->armarFilasErp(
                $agg['grupos'] ?? [],
                $porArticuloId,
                $modo,
                (string) ($filtros['orden'] ?? StockLocalInformeListadoFiltros::ORDEN_ARTICULO),
                (bool) ($filtros['solo_con_saldo'] ?? true),
                $filtros['desde_color'] ?? null,
                $filtros['hasta_color'] ?? null
            );
            $medidas = $agg['medidas'] ?? [];
        }

        $totalStock = 0.0;
        $grupos = 0;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') === 'detalle') {
                $totalStock += (float) ($fila['total'] ?? 0);
                $grupos++;
            } elseif (($fila['concepto'] ?? '') === 'Stock' || ($fila['tipo_fila'] ?? '') === 'saldo') {
                $totalStock += (float) ($fila['total'] ?? 0);
                $grupos++;
            }
        }

        return [
            'ok' => true,
            'medidas' => $medidas,
            'filas' => $filas,
            'totales' => [
                'total_filas' => count($filas),
                'total_grupos' => $grupos,
                'total_stock' => $totalStock,
                'origen' => $etiquetaOrigen,
            ],
        ];
    }

    /**
     * Stock ERP: articulo_movimiento firmado, agrupado por artículo × combinación/color × medida.
     *
     * @param  list<int>  $articuloIds
     * @param  array<string, mixed>  $filtros
     * @return array{grupos:array<string, array{articulo_id:int,color_codigo:string,color_desc:string,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}>,medidas:list<int>,error:?string}
     */
    private function agregarDesdeErp(int $depositoId, array $filtros, array $articuloIds): array
    {
        if ($depositoId <= 0 || $articuloIds === []) {
            return ['grupos' => [], 'medidas' => [], 'error' => null];
        }

        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '1900-01-01');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        $modo = (string) ($filtros['modo'] ?? StockLocalInformeListadoFiltros::MODO_SALDO);

        /** @var array<string, array{articulo_id:int,color_codigo:string,color_desc:string,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}> $grupos */
        $grupos = [];
        $medidasVistas = [];

        $rows = StockLocalErpMovimientosSupport::filasPorDepositoYArticulos(
            $depositoId,
            $articuloIds,
            $fechaHasta
        );

        foreach ($rows as $row) {
            $articuloId = (int) $row->articulo_id;
            $cantidad = (float) $row->cantidad;
            if (abs($cantidad) < 0.000001) {
                continue;
            }
            $medidaNorm = StockLocalErpMovimientosSupport::normalizarMedida(
                $row->medida ?? null,
                $row->medida_nombre ?? null
            );
            $medida = is_numeric($medidaNorm) ? (int) $medidaNorm : 0;
            if (! is_numeric($medidaNorm) && $medidaNorm !== 0 && $medidaNorm !== '0') {
                // Medida no numérica: usar hash estable en string key, columna como 0 en orden
                $medidaKey = (string) $medidaNorm;
            } else {
                $medidaKey = (string) ($medida > 0 ? $medida : 0);
                $medida = (int) $medidaKey;
            }
            $medidasVistas[$medida] = true;

            [$colorCodigo, $colorDesc] = StockLocalErpMovimientosSupport::colorDesdeFila($row);

            $clave = $articuloId.'|'.$colorCodigo;
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'articulo_id' => $articuloId,
                    'color_codigo' => $colorCodigo,
                    'color_desc' => $colorDesc,
                    'ingresos' => [],
                    'egresos' => [],
                    'stock' => [],
                ];
            }
            $kMed = $medidaKey;
            $grupos[$clave]['stock'][$kMed] = ($grupos[$clave]['stock'][$kMed] ?? 0.0) + $cantidad;

            if ($modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $fechaMov = substr((string) $row->fecha, 0, 10);
                if ($fechaMov >= $fechaDesde) {
                    if ($cantidad > 0) {
                        $grupos[$clave]['ingresos'][$kMed] = ($grupos[$clave]['ingresos'][$kMed] ?? 0.0) + $cantidad;
                    } else {
                        $grupos[$clave]['egresos'][$kMed] = ($grupos[$clave]['egresos'][$kMed] ?? 0.0) + abs($cantidad);
                    }
                }
            }
        }

        $medidas = array_keys($medidasVistas);
        sort($medidas, SORT_NUMERIC);
        // Medida 0 al final como "sin talle"
        if (in_array(0, $medidas, true)) {
            $medidas = array_values(array_filter($medidas, static fn ($m) => (int) $m !== 0));
            $medidas[] = 0;
        }

        return ['grupos' => $grupos, 'medidas' => $medidas, 'error' => null];
    }

    /**
     * @param  array<string, array{articulo_id:int,color_codigo:string,color_desc:string,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}>  $grupos
     * @param  array<int, array{id:int,sku:string,descripcion:string,categoria:string,categoria_codigo:string,sku_anita:string}>  $porArticuloId
     * @return list<array<string, mixed>>
     */
    private function armarFilasErp(
        array $grupos,
        array $porArticuloId,
        string $modo,
        string $orden,
        bool $soloConSaldo,
        ?int $desdeColor = null,
        ?int $hastaColor = null
    ): array {
        $items = [];
        foreach ($grupos as $grupo) {
            $meta = $porArticuloId[(int) $grupo['articulo_id']] ?? null;
            if ($meta === null) {
                continue;
            }
            $colorCodigo = (string) $grupo['color_codigo'];
            $colorInt = ctype_digit($colorCodigo) ? (int) $colorCodigo : 0;
            if ($desdeColor !== null && $colorInt < $desdeColor) {
                continue;
            }
            if ($hastaColor !== null && $colorInt > $hastaColor) {
                continue;
            }
            $totalStock = array_sum($grupo['stock']);
            if ($soloConSaldo && abs($totalStock) < 0.000001 && $modo === StockLocalInformeListadoFiltros::MODO_SALDO) {
                continue;
            }
            if ($soloConSaldo && $modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $tIng = array_sum($grupo['ingresos']);
                $tEgr = array_sum($grupo['egresos']);
                if (abs($tIng) < 0.000001 && abs($tEgr) < 0.000001 && abs($totalStock) < 0.000001) {
                    continue;
                }
            }

            $base = [
                'articulo_id' => $meta['id'],
                'sku' => $meta['sku'],
                'sku_anita' => $meta['sku_anita'],
                'descripcion' => $meta['descripcion'],
                'categoria' => $meta['categoria'],
                'categoria_codigo' => $meta['categoria_codigo'],
                'color' => $colorCodigo,
                'color_desc' => $grupo['color_desc'],
                'orden_cat' => $meta['categoria_codigo'].'|'.$meta['sku'].'|'.$colorCodigo,
                'orden_art' => $meta['sku'].'|'.$colorCodigo,
            ];

            if ($modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Entradas',
                    'cantidades' => $grupo['ingresos'],
                    'total' => array_sum($grupo['ingresos']),
                ]);
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Ventas',
                    'cantidades' => $grupo['egresos'],
                    'total' => array_sum($grupo['egresos']),
                ]);
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Stock',
                    'cantidades' => $grupo['stock'],
                    'total' => $totalStock,
                ]);
            } else {
                $items[] = array_merge($base, [
                    'tipo_fila' => 'saldo',
                    'concepto' => 'Stock',
                    'cantidades' => $grupo['stock'],
                    'total' => $totalStock,
                ]);
            }
        }

        usort($items, static function (array $a, array $b) use ($orden): int {
            $ka = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($a['orden_cat'] ?? '')
                : ($a['orden_art'] ?? '');
            $kb = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($b['orden_cat'] ?? '')
                : ($b['orden_art'] ?? '');
            $cmp = strcmp($ka, $kb);
            if ($cmp !== 0) {
                return $cmp;
            }
            $ordenConcepto = ['Entradas' => 1, 'Ventas' => 2, 'Stock' => 3];

            return ($ordenConcepto[$a['concepto'] ?? ''] ?? 9) <=> ($ordenConcepto[$b['concepto'] ?? ''] ?? 9);
        });

        return $items;
    }

    /**
     * Detalle ERP: un renglón por articulo_movimiento con tipo y número de comprobante.
     *
     * @param  list<int>  $articuloIds
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   movimientos: array<int, array{
     *     am_id:int,
     *     articulo_id:int,
     *     color_codigo:string,
     *     color_desc:string,
     *     fecha:string,
     *     tipo:string,
     *     numero:string,
     *     venta_id:?int,
     *     movimientostock_id:?int,
     *     cantidades:array<string,float>
     *   }>,
     *   medidas:list<int>,
     *   error:?string
     * }
     */
    private function agregarDetalleDesdeErp(int $depositoId, array $filtros, array $articuloIds): array
    {
        if ($depositoId <= 0 || $articuloIds === []) {
            return ['movimientos' => [], 'medidas' => [], 'error' => null];
        }

        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '1900-01-01');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));

        /** @var array<int, array{am_id:int,articulo_id:int,color_codigo:string,color_desc:string,fecha:string,tipo:string,numero:string,venta_id:?int,movimientostock_id:?int,cantidades:array<string,float>}> $movimientos */
        $movimientos = [];
        $medidasVistas = [];

        $rows = StockLocalErpMovimientosSupport::filasPorDepositoYArticulos(
            $depositoId,
            $articuloIds,
            $fechaHasta,
            $fechaDesde
        );

        foreach ($rows as $row) {
            $amId = (int) ($row->am_id ?? 0);
            if ($amId <= 0) {
                continue;
            }
            $cantidad = (float) $row->cantidad;
            if (abs($cantidad) < 0.000001) {
                continue;
            }
            $medidaNorm = StockLocalErpMovimientosSupport::normalizarMedida(
                $row->medida ?? null,
                $row->medida_nombre ?? null
            );
            $medida = is_numeric($medidaNorm) ? (int) $medidaNorm : 0;
            if (! is_numeric($medidaNorm) && $medidaNorm !== 0 && $medidaNorm !== '0') {
                $medidaKey = (string) $medidaNorm;
            } else {
                $medidaKey = (string) ($medida > 0 ? $medida : 0);
                $medida = (int) $medidaKey;
            }
            $medidasVistas[$medida] = true;

            [$colorCodigo, $colorDesc] = StockLocalErpMovimientosSupport::colorDesdeFila($row);

            if (! isset($movimientos[$amId])) {
                $movimientos[$amId] = [
                    'am_id' => $amId,
                    'articulo_id' => (int) $row->articulo_id,
                    'color_codigo' => $colorCodigo,
                    'color_desc' => $colorDesc,
                    'fecha' => substr((string) $row->fecha, 0, 10),
                    'tipo' => StockLocalErpMovimientosSupport::tipoComprobanteDesdeFila($row),
                    'numero' => StockLocalErpMovimientosSupport::numeroComprobanteDesdeFila($row),
                    'venta_id' => $row->venta_id !== null ? (int) $row->venta_id : null,
                    'movimientostock_id' => $row->movimientostock_id !== null ? (int) $row->movimientostock_id : null,
                    'cantidades' => [],
                ];
            }
            $movimientos[$amId]['cantidades'][$medidaKey] =
                ($movimientos[$amId]['cantidades'][$medidaKey] ?? 0.0) + $cantidad;
        }

        $medidas = array_keys($medidasVistas);
        sort($medidas, SORT_NUMERIC);
        if (in_array(0, $medidas, true)) {
            $medidas = array_values(array_filter($medidas, static fn ($m) => (int) $m !== 0));
            $medidas[] = 0;
        }

        return ['movimientos' => $movimientos, 'medidas' => $medidas, 'error' => null];
    }

    /**
     * @param  array<int, array{am_id:int,articulo_id:int,color_codigo:string,color_desc:string,fecha:string,tipo:string,numero:string,venta_id:?int,movimientostock_id:?int,cantidades:array<string,float>}>  $movimientos
     * @param  array<int, array{id:int,sku:string,descripcion:string,categoria:string,categoria_codigo:string,sku_anita:string}>  $porArticuloId
     * @return list<array<string, mixed>>
     */
    private function armarFilasDetalleErp(
        array $movimientos,
        array $porArticuloId,
        string $orden,
        ?int $desdeColor = null,
        ?int $hastaColor = null
    ): array {
        $items = [];
        foreach ($movimientos as $mov) {
            $meta = $porArticuloId[(int) $mov['articulo_id']] ?? null;
            if ($meta === null) {
                continue;
            }
            $colorCodigo = (string) $mov['color_codigo'];
            $colorInt = ctype_digit($colorCodigo) ? (int) $colorCodigo : 0;
            if ($desdeColor !== null && $colorInt < $desdeColor) {
                continue;
            }
            if ($hastaColor !== null && $colorInt > $hastaColor) {
                continue;
            }
            $total = array_sum($mov['cantidades']);
            if (abs($total) < 0.000001) {
                continue;
            }

            $items[] = [
                'tipo_fila' => 'detalle',
                'articulo_id' => $meta['id'],
                'sku' => $meta['sku'],
                'sku_anita' => $meta['sku_anita'],
                'descripcion' => $meta['descripcion'],
                'categoria' => $meta['categoria'],
                'categoria_codigo' => $meta['categoria_codigo'],
                'color' => $colorCodigo,
                'color_desc' => $mov['color_desc'],
                'fecha' => $mov['fecha'],
                'tipo_comprobante' => $mov['tipo'],
                'numero_comprobante' => $mov['numero'],
                'venta_id' => $mov['venta_id'],
                'movimientostock_id' => $mov['movimientostock_id'],
                'concepto' => trim($mov['tipo'].' '.$mov['numero']),
                'cantidades' => $mov['cantidades'],
                'total' => $total,
                'orden_cat' => $meta['categoria_codigo'].'|'.$mov['fecha'].'|'.$meta['sku'].'|'.$colorCodigo.'|'.$mov['am_id'],
                'orden_art' => $mov['fecha'].'|'.$meta['sku'].'|'.$colorCodigo.'|'.$mov['am_id'],
            ];
        }

        usort($items, static function (array $a, array $b) use ($orden): int {
            $ka = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($a['orden_cat'] ?? '')
                : ($a['orden_art'] ?? '');
            $kb = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($b['orden_cat'] ?? '')
                : ($b['orden_art'] ?? '');

            return strcmp($ka, $kb);
        });

        return $items;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, Articulo>
     */
    private function articulosErp(array $filtros)
    {
        $query = Articulo::query()
            ->select(['articulo.id', 'articulo.sku', 'articulo.descripcion', 'articulo.categoria_id'])
            ->with(['categorias:id,codigo,nombre']);
        ArticuloCanalSupport::scopeArticulosCanalLocal($query);

        $desdeSku = trim((string) ($filtros['desde_sku'] ?? ''));
        $hastaSku = trim((string) ($filtros['hasta_sku'] ?? ''));
        if ($desdeSku !== '') {
            $query->where('articulo.sku', '>=', $desdeSku);
        }
        if ($hastaSku !== '') {
            $query->where('articulo.sku', '<=', $hastaSku);
        }

        $mventaId = (int) ($filtros['mventa_id'] ?? 0);
        if ($mventaId > 0) {
            $query->where('articulo.mventa_id', $mventaId);
        }

        $orden = (string) ($filtros['orden'] ?? StockLocalInformeListadoFiltros::ORDEN_ARTICULO);
        if ($orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA) {
            $query->leftJoin('categoria', 'categoria.id', '=', 'articulo.categoria_id')
                ->orderBy('categoria.codigo')
                ->orderBy('articulo.sku');
        } else {
            $query->orderBy('articulo.sku');
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $skusAnita
     * @return array{grupos:array<string, array{sku_anita:string,color:int,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}>,medidas:list<int>,error:?string}
     */
    private function agregarDesdeStkdep(LocalVenta $local, int $deposito, array $skusAnita): array
    {
        /** @var array<string, array{sku_anita:string,color:int,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}> $grupos */
        $grupos = [];
        $medidasVistas = [];

        foreach (array_chunk($skusAnita, self::IN_CHUNK) as $chunk) {
            $inList = implode(',', array_map(static fn ($s) => "'".str_replace("'", "''", $s)."'", $chunk));
            $payload = [
                'acc' => 'list',
                'tabla' => 'stkdep',
                'campos' => 'stkd_articulo,stkd_deposito,stkd_color,stkd_medida,stkd_cantidad',
                'whereArmado' => " WHERE stkd_deposito = {$deposito} AND stkd_cantidad <> 0 AND stkd_articulo IN ({$inList}) ",
                'orderBy' => 'stkd_articulo,stkd_color,stkd_medida',
                'servidor' => $local->anitaServidor(),
                'ifx_server' => $local->anitaIfxServer(),
                'curl_timeout' => 180,
            ];
            $filasRaw = $this->listarAnita($payload);
            if ($filasRaw === null) {
                return ['grupos' => [], 'medidas' => [], 'error' => 'Error al leer stkdep en Anita Local.'];
            }
            foreach ($filasRaw as $fila) {
                $sku = $this->codigoAnitaDesdeSku((string) ($fila['stkd_articulo'] ?? ''));
                $color = (int) ($fila['stkd_color'] ?? 0);
                $medida = (int) ($fila['stkd_medida'] ?? 0);
                $cant = (float) ($fila['stkd_cantidad'] ?? 0);
                if (abs($cant) < 0.000001) {
                    continue;
                }
                $medidasVistas[$medida] = true;
                $clave = $sku.'|'.$color;
                if (! isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'sku_anita' => $sku,
                        'color' => $color,
                        'ingresos' => [],
                        'egresos' => [],
                        'stock' => [],
                    ];
                }
                $k = (string) $medida;
                $grupos[$clave]['stock'][$k] = ($grupos[$clave]['stock'][$k] ?? 0.0) + $cant;
            }
        }

        $medidas = array_keys($medidasVistas);
        sort($medidas, SORT_NUMERIC);

        return ['grupos' => $grupos, 'medidas' => $medidas, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $skusAnita
     * @return array{grupos:array<string, array{sku_anita:string,color:int,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}>,medidas:list<int>,error:?string}
     */
    private function agregarDesdeStkvmed(LocalVenta $local, int $deposito, array $filtros, array $skusAnita): array
    {
        $fechaDesde = $this->fechaAnita((string) ($filtros['fecha_desde'] ?? '1900-01-01'));
        $fechaHasta = $this->fechaAnita((string) ($filtros['fecha_hasta'] ?? date('Y-m-d')));
        $desdeColor = $filtros['desde_color'] ?? null;
        $hastaColor = $filtros['hasta_color'] ?? null;
        $signos = $this->mapaSignoTcomp($local);

        /** @var array<string, array{sku_anita:string,color:int,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}> $grupos */
        $grupos = [];
        $medidasVistas = [];

        foreach (array_chunk($skusAnita, self::IN_CHUNK) as $chunk) {
            $inList = implode(',', array_map(static fn ($s) => "'".str_replace("'", "''", $s)."'", $chunk));
            $where = " WHERE stkvm_deposito = {$deposito}"
                ." AND stkvm_fecha <= {$fechaHasta}"
                ." AND stkvm_articulo IN ({$inList}) ";
            if ($desdeColor !== null) {
                $where .= ' AND stkvm_color >= '.(int) $desdeColor;
            }
            if ($hastaColor !== null) {
                $where .= ' AND stkvm_color <= '.(int) $hastaColor;
            }

            $payload = [
                'acc' => 'list',
                'tabla' => 'stkvmed',
                'campos' => 'stkvm_articulo,stkvm_tipo,stkvm_deposito,stkvm_medida,stkvm_cantidad,stkvm_color,stkvm_fecha',
                'whereArmado' => $where,
                'orderBy' => 'stkvm_articulo,stkvm_color,stkvm_medida',
                'servidor' => $local->anitaServidor(),
                'ifx_server' => $local->anitaIfxServer(),
                'curl_timeout' => 300,
            ];
            $filasRaw = $this->listarAnita($payload);
            if ($filasRaw === null) {
                return ['grupos' => [], 'medidas' => [], 'error' => 'Error al leer stkvmed en Anita Local.'];
            }

            foreach ($filasRaw as $fila) {
                $cantidad = (float) ($fila['stkvm_cantidad'] ?? 0);
                if (abs($cantidad) < 0.000001) {
                    continue;
                }
                $tipo = strtoupper(trim((string) ($fila['stkvm_tipo'] ?? '')));
                $signo = $signos[$tipo] ?? $this->signoHeuristico($tipo);
                if ($signo === 0) {
                    continue;
                }
                $sku = $this->codigoAnitaDesdeSku((string) ($fila['stkvm_articulo'] ?? ''));
                $color = (int) ($fila['stkvm_color'] ?? 0);
                $medida = (int) ($fila['stkvm_medida'] ?? 0);
                $fechaMov = (int) ($fila['stkvm_fecha'] ?? 0);
                $medidasVistas[$medida] = true;
                $clave = $sku.'|'.$color;
                if (! isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'sku_anita' => $sku,
                        'color' => $color,
                        'ingresos' => [],
                        'egresos' => [],
                        'stock' => [],
                    ];
                }
                $k = (string) $medida;
                // Stock siempre acumula (movimientos <= hasta)
                $grupos[$clave]['stock'][$k] = ($grupos[$clave]['stock'][$k] ?? 0.0) + ($cantidad * $signo);
                // Entradas/ventas del período (fecha >= desde), como l-stocklocal.c
                if ($fechaMov >= $fechaDesde) {
                    if ($signo > 0) {
                        $grupos[$clave]['ingresos'][$k] = ($grupos[$clave]['ingresos'][$k] ?? 0.0) + $cantidad;
                    } else {
                        $grupos[$clave]['egresos'][$k] = ($grupos[$clave]['egresos'][$k] ?? 0.0) + $cantidad;
                    }
                }
            }
        }

        $medidas = array_keys($medidasVistas);
        sort($medidas, SORT_NUMERIC);

        return ['grupos' => $grupos, 'medidas' => $medidas, 'error' => null];
    }

    /**
     * @param  array<string, array{sku_anita:string,color:int,ingresos:array<string,float>,egresos:array<string,float>,stock:array<string,float>}>  $grupos
     * @param  array<string, array{id:int,sku:string,descripcion:string,categoria:string,categoria_codigo:string}>  $porSkuAnita
     * @param  array<int, array<string, string>>  $coloresDesc articulo_id => colorCodigo => desc
     * @return list<array<string, mixed>>
     */
    private function armarFilas(
        array $grupos,
        array $porSkuAnita,
        array $coloresDesc,
        string $modo,
        string $orden,
        bool $soloConSaldo,
        ?int $desdeColor = null,
        ?int $hastaColor = null
    ): array {
        $items = [];
        foreach ($grupos as $grupo) {
            $skuAnita = $grupo['sku_anita'];
            $meta = $porSkuAnita[$skuAnita] ?? null;
            if ($meta === null) {
                continue;
            }
            $color = (int) $grupo['color'];
            if ($desdeColor !== null && $color < $desdeColor) {
                continue;
            }
            if ($hastaColor !== null && $color > $hastaColor) {
                continue;
            }
            $totalStock = array_sum($grupo['stock']);
            if ($soloConSaldo && abs($totalStock) < 0.000001 && $modo === StockLocalInformeListadoFiltros::MODO_SALDO) {
                continue;
            }
            if ($soloConSaldo && $modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $tIng = array_sum($grupo['ingresos']);
                $tEgr = array_sum($grupo['egresos']);
                if (abs($tIng) < 0.000001 && abs($tEgr) < 0.000001 && abs($totalStock) < 0.000001) {
                    continue;
                }
            }

            $colorKey = (string) $color;
            $colorDesc = $coloresDesc[$meta['id']][$colorKey]
                ?? $coloresDesc[$meta['id']][ltrim($colorKey, '0')]
                ?? '';

            $base = [
                'articulo_id' => $meta['id'],
                'sku' => $meta['sku'],
                'sku_anita' => $skuAnita,
                'descripcion' => $meta['descripcion'],
                'categoria' => $meta['categoria'],
                'categoria_codigo' => $meta['categoria_codigo'],
                'color' => $color,
                'color_desc' => $colorDesc,
                'orden_cat' => $meta['categoria_codigo'].'|'.$meta['sku'].'|'.sprintf('%06d', $color),
                'orden_art' => $meta['sku'].'|'.sprintf('%06d', $color),
            ];

            if ($modo === StockLocalInformeListadoFiltros::MODO_APERTURA) {
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Entradas',
                    'cantidades' => $grupo['ingresos'],
                    'total' => array_sum($grupo['ingresos']),
                ]);
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Ventas',
                    'cantidades' => $grupo['egresos'],
                    'total' => array_sum($grupo['egresos']),
                ]);
                $items[] = array_merge($base, [
                    'tipo_fila' => 'apertura',
                    'concepto' => 'Stock',
                    'cantidades' => $grupo['stock'],
                    'total' => $totalStock,
                ]);
            } else {
                $items[] = array_merge($base, [
                    'tipo_fila' => 'saldo',
                    'concepto' => 'Stock',
                    'cantidades' => $grupo['stock'],
                    'total' => $totalStock,
                ]);
            }
        }

        usort($items, static function (array $a, array $b) use ($orden): int {
            $ka = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($a['orden_cat'] ?? '')
                : ($a['orden_art'] ?? '');
            $kb = $orden === StockLocalInformeListadoFiltros::ORDEN_CATEGORIA
                ? ($b['orden_cat'] ?? '')
                : ($b['orden_art'] ?? '');
            $cmp = strcmp($ka, $kb);
            if ($cmp !== 0) {
                return $cmp;
            }
            $ordenConcepto = ['Entradas' => 1, 'Ventas' => 2, 'Stock' => 3];

            return ($ordenConcepto[$a['concepto'] ?? ''] ?? 9) <=> ($ordenConcepto[$b['concepto'] ?? ''] ?? 9);
        });

        return $items;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array<string, string>>
     */
    private function mapaColoresErp(array $articuloIds): array
    {
        $mapa = [];
        foreach (array_chunk($articuloIds, 500) as $chunk) {
            $rows = Combinacion::query()
                ->whereIn('articulo_id', $chunk)
                ->get(['articulo_id', 'codigo', 'nombre']);
            foreach ($rows as $row) {
                $aid = (int) $row->articulo_id;
                $cod = trim((string) $row->codigo);
                $mapa[$aid][$cod] = trim((string) $row->nombre);
                $mapa[$aid][ltrim($cod, '0')] = trim((string) $row->nombre);
            }
        }

        return $mapa;
    }

    /** @param  array<string, mixed>  $filtros */
    private function resolverLocal(array $filtros): ?LocalVenta
    {
        $id = (int) ($filtros['local_venta_id'] ?? 0);
        if ($id > 0) {
            return LocalVenta::query()->where('activo', true)->find($id);
        }

        return LocalVenta::query()->where('activo', true)->orderBy('codigo')->first();
    }

    private function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_SKU_ANITA, '0', STR_PAD_LEFT);
    }

    private function fechaAnita(string $ymd): int
    {
        $ymd = str_replace('-', '', $ymd);
        if (! ctype_digit($ymd) || strlen($ymd) !== 8) {
            return (int) date('Ymd');
        }

        return (int) $ymd;
    }

    /** @return array<string, int> */
    private function mapaSignoTcomp(LocalVenta $local): array
    {
        if ($this->mapaSignoTcomp !== null) {
            return $this->mapaSignoTcomp;
        }
        $payload = [
            'acc' => 'list',
            'tabla' => 't_comp',
            'campos' => 'tcomp_clave,tcomp_oper_stk',
            'servidor' => $local->anitaServidor(),
            'ifx_server' => $local->anitaIfxServer(),
        ];
        $filas = $this->listarAnita($payload);
        $mapa = [];
        if ($filas !== null) {
            foreach ($filas as $fila) {
                $tipo = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
                if ($tipo === '') {
                    continue;
                }
                $oper = trim((string) ($fila['tcomp_oper_stk'] ?? ''));
                if ($oper === '1') {
                    $mapa[$tipo] = 0;
                } elseif (in_array($oper, ['2', '5', '7'], true)) {
                    $mapa[$tipo] = 1;
                } else {
                    $mapa[$tipo] = -1;
                }
            }
        }
        $this->mapaSignoTcomp = $mapa;

        return $mapa;
    }

    private function signoHeuristico(string $tipo): int
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '') {
            return 0;
        }
        if (in_array($tipo, ['REM', 'ING', 'AJ+', 'TRA', 'REC', 'COM'], true)) {
            return 1;
        }
        if (in_array($tipo, ['FAC', 'NCD', 'TKT', 'EGR', 'AJ-', 'NCR'], true)) {
            return -1;
        }
        if (str_starts_with($tipo, 'FAC') || str_starts_with($tipo, 'TKT') || str_starts_with($tipo, 'NCD')) {
            return -1;
        }

        return -1;
    }

    /**
     * @param  array{acc:string,tabla:string,campos:string,whereArmado?:string,orderBy?:string,servidor:string,ifx_server:string,curl_timeout?:int}  $payload
     * @return list<array<string, mixed>>|null
     */
    private function listarAnita(array $payload): ?array
    {
        try {
            $raw = $this->apiAnita->apiCall($payload);
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $error = ApiAnita::extraerMensajeError($rawStr);
            if ($error !== null) {
                Log::warning('facturacion_local.stock_informe.anita', [
                    'tabla' => $payload['tabla'] ?? '',
                    'error' => $error,
                ]);

                return null;
            }
            $filas = ApiAnita::decodificarListaFilas($rawStr);
            $out = [];
            foreach ($filas as $fila) {
                $out[] = is_object($fila) ? get_object_vars($fila) : (array) $fila;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('facturacion_local.stock_informe.ex', [
                'tabla' => $payload['tabla'] ?? '',
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function leerCache(string $firma): ?array
    {
        $data = Session::get(self::SESSION_CACHE_KEY);
        if (! is_array($data) || ($data['firma'] ?? '') !== $firma) {
            return null;
        }

        return $data['payload'] ?? null;
    }

    /** @param  array<string, mixed>  $payload */
    private function guardarCache(string $firma, array $payload): void
    {
        Session::put(self::SESSION_CACHE_KEY, [
            'firma' => $firma,
            'payload' => $payload,
            'at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @return array{ok:bool,error:string,medidas:list,filas:LengthAwarePaginator|\Illuminate\Support\Collection,totales:array,subtitulo:string,local?:LocalVenta}
     */
    private function resultadoError(string $error, bool $paginar, int $porPagina, ?LocalVenta $local = null): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'local' => $local,
            'medidas' => [],
            'filas' => $paginar ? new LengthAwarePaginator([], 0, $porPagina) : collect(),
            'totales' => [
                'total_filas' => 0,
                'total_grupos' => 0,
                'total_stock' => 0.0,
                'origen' => '',
            ],
            'subtitulo' => '',
        ];
    }
}
