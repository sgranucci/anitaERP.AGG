<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Stock\RecuentoMovimientosArticuloSupport;
use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Artículos vendidos gastronomía: agrega venta_emision con signo del comprobante (tt.signo).
 * No usa articulo_movimiento.cantidad (signo de operacionstock / saldo).
 */
class GastronomiaArticulosVendidosQuery
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    private static function importeExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    private const DEPOSITO_EXPR = 'COALESCE(am_dep.deposito_id, am_dep_v.deposito_id, ve.deposito_id)';

    private const DEPOSITO_INSUMO_EXPR = 'COALESCE(am_ing.deposito_id, am_ing_v.deposito_id)';

    private const DEPOSITO_INSUMO_JOIN = 'COALESCE(am_ing.deposito_id, am_ing_v.deposito_id)';

    /**
     * @return LengthAwarePaginator<int, object>|Collection<int, object>
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = $this->queryBase($filtros);

        $query->orderBy('a.sku')
            ->orderBy('pv.codigo')
            ->orderBy('d.codigo');

        if ($paginar) {
            return $query->paginate(max(10, min(200, $perPage)))
                ->through(fn ($row) => $this->enriquecerFila($row));
        }

        return $query->get()->map(fn ($row) => $this->enriquecerFila($row));
    }

    /**
     * Top N artículos vendidos en una jornada (líneas facturadas en venta_emision).
     * Excluye artículos con uso «INSUMO GASTRONOMIA» (insumos de fórmula).
     *
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function topPorJornada(
        int $empresaId,
        string $fechaJornada,
        string $orden = 'cantidad',
        int $limit = 10,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        return $this->topPorRangoJornada($empresaId, $fechaJornada, $fechaJornada, $orden, $limit);
    }

    /**
     * Top N artículos vendidos en el mes calendario de la fecha de jornada.
     *
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function topPorMes(
        int $empresaId,
        string $fechaReferenciaYmd,
        string $orden = 'cantidad',
        int $limit = 10,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        $fecha = Carbon::parse($fechaReferenciaYmd);
        $desde = $fecha->copy()->startOfMonth()->format('Y-m-d');
        $hasta = $fecha->copy()->endOfMonth()->format('Y-m-d');

        return $this->topPorRangoJornada($empresaId, $desde, $hasta, $orden, $limit);
    }

    /**
     * Top N artículos vendidos en un rango de fechas de jornada.
     *
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function topPorRango(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $orden = 'cantidad',
        int $limit = 10,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        return $this->topPorRangoJornada($empresaId, $fechaDesde, $fechaHasta, $orden, $limit);
    }

    /**
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    private function topPorRangoJornada(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $orden,
        int $limit,
    ): array {
        $orderCol = $orden === 'importe' ? 'importe_total' : 'cantidad_total';

        $rows = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fechajornada', '>=', $fechaDesde)
            ->whereDate('v.fechajornada', '<=', $fechaHasta);

        $this->aplicarExclusionInsumos($rows);

        $rows = $rows
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw('SUM('.self::cantidadExpr().') as cantidad_total')
            ->selectRaw('SUM('.self::importeExpr().') as importe_total')
            ->groupBy('ve.articulo_id', 'a.sku', 'a.descripcion')
            ->orderByDesc($orderCol)
            ->orderBy('a.sku')
            ->limit(max(1, min(50, $limit)))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'articulo_id' => (int) $row->articulo_id,
                'sku' => trim((string) $row->sku),
                'descripcion' => trim((string) $row->descripcion),
                'cantidad' => round((float) $row->cantidad_total, 4),
                'importe' => round((float) $row->importe_total, 2),
            ];
        }

        return $out;
    }

    /**
     * Totales del listado filtrado.
     *
     * @return array{cantidad_articulos:int,cantidad_total:float,importe_total:float,cantidad_comprobantes:int}
     */
    public function totales(array $filtros): array
    {
        return $this->listadoConTotales($filtros)['totales'];
    }

    /**
     * Listado completo + totales en una sola pasada SQL (queryBase una vez).
     *
     * @return array{filas: Collection<int, object>, totales: array{cantidad_articulos:int,cantidad_total:float,importe_total:float,cantidad_comprobantes:int}}
     */
    public function listadoConTotales(array $filtros): array
    {
        $filas = $this->queryBase($filtros)
            ->orderBy('a.sku')
            ->orderBy('pv.codigo')
            ->orderBy('d.codigo')
            ->get()
            ->map(fn ($row) => $this->enriquecerFila($row));

        return [
            'filas' => $filas,
            'totales' => [
                'cantidad_articulos' => $filas->count(),
                'cantidad_total' => round($filas->sum(fn (object $fila): float => (float) ($fila->cantidad_total ?? 0)), 4),
                'importe_total' => round($filas->sum(fn (object $fila): float => (float) ($fila->importe_total ?? 0)), 2),
                'cantidad_comprobantes' => $this->contarComprobantesDistintos($filtros),
            ],
        ];
    }

    private function queryComprobantesDistintos(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->select('v.id')
            ->distinct();

        if ($this->filtroRequiereJoinsDeposito($filtros)) {
            $this->aplicarJoinsDeposito($query, $filtros);
        }

        $this->aplicarExclusionInsumos($query);

        $this->aplicarFiltrosEstructurales($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '' || ($filtros['operador'] ?? '') === 'vacio') {
            $this->aplicarFiltrosTexto($query, $filtros);
        }

        return $query;
    }

    private function contarComprobantesDistintos(array $filtros): int
    {
        if ($this->filtroRequiereJoinsDeposito($filtros)) {
            return (int) DB::query()
                ->fromSub($this->queryComprobantesDistintos($filtros), 'c')
                ->count();
        }

        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->select('v.id')
            ->distinct();

        $this->aplicarExclusionInsumos($query);
        $this->aplicarFiltrosEstructurales($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '' || ($filtros['operador'] ?? '') === 'vacio') {
            $this->aplicarFiltrosTexto($query, $filtros);
        }

        return (int) DB::query()->fromSub($query, 'c')->count();
    }

    /**
     * Joins de depósito solo cuando filtran o buscan por depósito (evita escanear articulo_movimiento completo).
     */
    private function filtroRequiereJoinsDeposito(array $filtros): bool
    {
        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            return true;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');

        if ($operador === 'vacio') {
            $modo = (string) ($filtros['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS);

            return $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO
                && (string) ($filtros['campo'] ?? '') === 'deposito';
        }

        if ($valor === '') {
            return false;
        }

        $modo = (string) ($filtros['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS);
        if ($modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO) {
            return (string) ($filtros['campo'] ?? '') === 'deposito';
        }

        return ($filtros['busqueda_rapida'] ?? false) || $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangoJornadaFiltros(array $filtros): array
    {
        $jornadaId = (int) ($filtros['jornada_id'] ?? 0);
        if ($jornadaId > 0) {
            $jornada = JornadaGastronomia::query()->find($jornadaId);
            if ($jornada !== null) {
                $fecha = $jornada->fecha_jornada->format('Y-m-d');

                return [$fecha, $fecha];
            }
        }

        return GastronomiaArticulosVendidosListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
    }

    private function aplicarFiltroJornadaSubqueryMovimiento(Builder $query, array $filtros, string $columna = 'fechajornada'): void
    {
        [$desde, $hasta] = $this->rangoJornadaFiltros($filtros);
        if ($desde !== '') {
            $query->whereDate($columna, '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate($columna, '<=', $hasta);
        }
    }

    /**
     * Comprobantes que incluyen un artículo (mismos filtros estructurales del listado).
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   fecha_jornada:?string,
     *   fecha_comprobante:?string,
     *   hora:?string,
     *   cantidad:float,
     *   importe:float,
     *   puntoventa_etiqueta:string,
     *   deposito_etiqueta:string,
     *   es_nota_credito:bool
     * }>
     */
    public function facturasPorArticulo(int $articuloId, array $filtros): array
    {
        if ($articuloId <= 0) {
            return [];
        }

        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('ve.articulo_id', $articuloId);

        $this->aplicarJoinsDeposito($query, $filtros);

        $this->aplicarExclusionInsumos($query);

        $query
            ->select([
                'v.id as venta_id',
                'v.codigo',
                'v.fechajornada',
                'v.fecha',
                'v.created_at',
                'v.puntoventa_id',
                've.deposito_id',
                'vge.venta_factura_origen_id',
                'tt.signo as tipotransaccion_signo',
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
                'd_ing.codigo as deposito_insumos_codigo',
                'd_ing.nombre as deposito_insumos_nombre',
            ])
            ->selectRaw(self::DEPOSITO_EXPR.' as deposito_resuelto_id')
            ->selectRaw(self::DEPOSITO_INSUMO_EXPR.' as deposito_insumos_id')
            ->selectRaw(self::cantidadExpr().' as cantidad')
            ->selectRaw(self::importeExpr().' as importe')
            ->selectRaw("TRIM(CONCAT(COALESCE(pv.codigo, ''), ' ', COALESCE(pv.nombre, ''))) as puntoventa_etiqueta");

        $this->aplicarFiltrosEstructurales($query, $filtros);

        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            $this->aplicarFiltroDeposito($query, (int) $filtros['deposito_id']);
        }

        return $query
            ->orderByDesc('v.id')
            ->get()
            ->map(function ($row) {
                $fechaJornada = $row->fechajornada
                    ? \Illuminate\Support\Carbon::parse($row->fechajornada)->format('d/m/Y')
                    : null;
                $fechaComp = $row->fecha
                    ? \Illuminate\Support\Carbon::parse($row->fecha)->format('d/m/Y')
                    : null;
                $hora = $row->created_at
                    ? \Illuminate\Support\Carbon::parse($row->created_at)->format('H:i:s')
                    : null;

                return [
                    'venta_id' => (int) $row->venta_id,
                    'codigo' => (string) ($row->codigo ?? ''),
                    'fecha_jornada' => $fechaJornada,
                    'fecha_comprobante' => $fechaComp,
                    'hora' => $hora,
                    'cantidad' => round((float) $row->cantidad, 4),
                    'importe' => round((float) $row->importe, 2),
                    'puntoventa_etiqueta' => trim((string) ($row->puntoventa_etiqueta ?? '')),
                    'deposito_etiqueta' => $this->etiquetaDepositoFila($row),
                    'es_nota_credito' => GastronomiaVentaComprobanteSignoSupport::esNotaCreditoSigno($row->tipotransaccion_signo ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Movimientos de stock del artículo vendido (salida del ítem facturado, no insumos de fórmula).
     *
     * @return array{
     *   movimientos: list<array{
     *     id:int,
     *     fecha:?string,
     *     venta_id:int,
     *     venta_codigo:string,
     *     concepto:string,
     *     deposito_etiqueta:string,
     *     puntoventa_etiqueta:string,
     *     entrada:?float,
     *     salida:?float,
     *     es_nota_credito:bool
     *   }>,
     *   totales: array{
     *     cantidad_movimientos:int,
     *     entrada_total:float,
     *     salida_total:float,
     *     cantidad_venta:float
     *   }
     * }
     */
    public function movimientosPorArticulo(int $articuloId, array $filtros): array
    {
        if ($articuloId <= 0) {
            return [
                'movimientos' => [],
                'totales' => [
                    'cantidad_movimientos' => 0,
                    'entrada_total' => 0.,
                    'salida_total' => 0.,
                    'cantidad_venta' => 0.,
                ],
            ];
        }

        $query = DB::table('articulo_movimiento as am')
            ->join('venta as v', 'v.id', '=', 'am.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->leftJoin('venta_emision as ve', function ($join) use ($articuloId) {
                $join->on('ve.id', '=', 'am.venta_emision_id')
                    ->where('ve.articulo_id', '=', $articuloId);
            })
            ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('am.articulo_id', $articuloId)
            ->tap(fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoNoEsInsumo($q, 'am.concepto'))
            ->whereNull('v.deleted_at')
            ->where(function ($w) use ($articuloId) {
                $w->whereNotNull('ve.id')
                    ->orWhere(function ($w2) use ($articuloId) {
                        $w2->whereNull('am.venta_emision_id')
                            ->whereExists(function ($sub) use ($articuloId) {
                                $sub->select(DB::raw(1))
                                    ->from('venta_emision as ve2')
                                    ->whereColumn('ve2.venta_id', 'am.venta_id')
                                    ->where('ve2.articulo_id', $articuloId);
                            });
                    });
            })
            ->select([
                'am.id',
                'am.fecha',
                'am.cantidad',
                'am.concepto',
                'am.venta_id',
                'am.deposito_id',
                'v.codigo as venta_codigo',
                'tt.signo as tipotransaccion_signo',
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
            ])
            ->selectRaw("TRIM(CONCAT(COALESCE(pv.codigo, ''), ' ', COALESCE(pv.nombre, ''))) as puntoventa_etiqueta");

        $depositoId = (int) ($filtros['deposito_id'] ?? 0);
        $filtrosEstructurales = $filtros;
        $filtrosEstructurales['deposito_id'] = 0;
        $this->aplicarFiltrosEstructurales($query, $filtrosEstructurales);

        if ($depositoId > 0) {
            $query->where('am.deposito_id', $depositoId);
        }

        $movimientos = $query
            ->orderByDesc('am.fecha')
            ->orderByDesc('am.id')
            ->get()
            ->map(function ($row) {
                $cantidad = (float) ($row->cantidad ?? 0);
                $fecha = $row->fecha
                    ? \Illuminate\Support\Carbon::parse($row->fecha)->format('d/m/Y')
                    : null;

                return [
                    'id' => (int) $row->id,
                    'fecha' => $fecha,
                    'venta_id' => (int) ($row->venta_id ?? 0),
                    'venta_codigo' => (string) ($row->venta_codigo ?? ''),
                    'concepto' => RecuentoMovimientosArticuloSupport::resolverConceptoDisplay($row),
                    'deposito_etiqueta' => RecuentoMovimientosArticuloSupport::etiquetaDeposito([
                        'id' => (int) ($row->deposito_id ?? 0),
                        'codigo' => (string) ($row->deposito_codigo ?? ''),
                        'nombre' => (string) ($row->deposito_nombre ?? ''),
                    ]),
                    'puntoventa_etiqueta' => trim((string) ($row->puntoventa_etiqueta ?? '')),
                    'entrada' => $cantidad > 0 ? round($cantidad, 4) : null,
                    'salida' => $cantidad < 0 ? round(abs($cantidad), 4) : null,
                    'es_nota_credito' => GastronomiaVentaComprobanteSignoSupport::esNotaCreditoSigno($row->tipotransaccion_signo ?? null),
                ];
            })
            ->values()
            ->all();

        $entradaTotal = 0.;
        $salidaTotal = 0.;
        foreach ($movimientos as $mov) {
            $entradaTotal += (float) ($mov['entrada'] ?? 0);
            $salidaTotal += (float) ($mov['salida'] ?? 0);
        }

        $cantidadVenta = round(array_sum(array_column(
            $this->facturasPorArticulo($articuloId, $filtros),
            'cantidad',
        )), 4);

        return [
            'movimientos' => $movimientos,
            'totales' => [
                'cantidad_movimientos' => count($movimientos),
                'entrada_total' => round($entradaTotal, 4),
                'salida_total' => round($salidaTotal, 4),
                'cantidad_venta' => $cantidadVenta,
            ],
        ];
    }

    private function queryBase(array $filtros, bool $aplicarTexto = true): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('subcategoria as sc', 'sc.id', '=', 'a.subcategoria_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at');

        $this->aplicarJoinsDeposito($query, $filtros);

        $this->aplicarExclusionInsumos($query);

        $query
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
                'sc.nombre as subcategoria_nombre',
            ])
            ->selectRaw(self::DEPOSITO_EXPR.' as deposito_id')
            ->addSelect([
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
                'd_ing.codigo as deposito_insumos_codigo',
                'd_ing.nombre as deposito_insumos_nombre',
                'v.puntoventa_id',
                'pv.codigo as pv_codigo',
                'pv.nombre as pv_nombre',
            ])
            ->selectRaw(self::DEPOSITO_INSUMO_EXPR.' as deposito_insumos_id')
            ->selectRaw('SUM('.self::cantidadExpr().') as cantidad_total')
            ->selectRaw('SUM('.self::importeExpr().') as importe_total')
            ->selectRaw('COUNT(DISTINCT v.id) as cantidad_comprobantes')
            ->groupBy(
                've.articulo_id',
                'a.sku',
                'a.descripcion',
                'sc.nombre',
                DB::raw(self::DEPOSITO_EXPR),
                'd.codigo',
                'd.nombre',
                DB::raw(self::DEPOSITO_INSUMO_EXPR),
                'd_ing.codigo',
                'd_ing.nombre',
                'v.puntoventa_id',
                'pv.codigo',
                'pv.nombre',
            );

        $this->aplicarFiltrosEstructurales($query, $filtros);

        if ($aplicarTexto) {
            $valor = trim((string) ($filtros['valor'] ?? ''));
            if ($valor !== '' || ($filtros['operador'] ?? '') === 'vacio') {
                $this->aplicarFiltrosTexto($query, $filtros);
            }
        }

        return $query;
    }

    /**
     * Excluye artículos con uso maestro «INSUMO GASTRONOMIA» (componentes de fórmula).
     */
    private function aplicarExclusionInsumos(Builder $query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('usoarticulo as ua_ins')
                ->whereColumn('ua_ins.id', 'a.usoarticulo_id')
                ->whereRaw(
                    'UPPER(TRIM(ua_ins.nombre)) = ?',
                    [ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO],
                );
        });
    }

    private function aplicarJoinsDeposito(Builder $query, array $filtros): void
    {
        $query
            ->leftJoinSub($this->subqueryDepositoMovimientoItem($filtros), 'am_dep', function ($join) {
                $join->on('am_dep.venta_emision_id', '=', 've.id')
                    ->on('am_dep.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoinSub($this->subqueryDepositoMovimientoPorVenta($filtros), 'am_dep_v', function ($join) {
                $join->on('am_dep_v.venta_id', '=', 'v.id')
                    ->on('am_dep_v.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoinSub($this->subqueryDepositoInsumosLinea($filtros), 'am_ing', function ($join) {
                $join->on('am_ing.venta_emision_id', '=', 've.id');
            })
            ->leftJoinSub($this->subqueryDepositoInsumoPorVentaArticulo($filtros), 'am_ing_v', function ($join) {
                $join->on('am_ing_v.venta_id', '=', 'v.id')
                    ->on('am_ing_v.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoin('depmae as d', 'd.id', '=', DB::raw(self::DEPOSITO_EXPR))
            ->leftJoin('depmae as d_ing', function ($join) {
                $join->whereRaw('d_ing.id = '.self::DEPOSITO_INSUMO_JOIN);
            });
    }

    private function subqueryDepositoMovimientoItem(array $filtros): Builder
    {
        $query = DB::table('articulo_movimiento')
            ->select('venta_emision_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_emision_id')
            ->tap(fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoNoEsInsumo($q));

        $this->aplicarFiltroJornadaSubqueryMovimiento($query, $filtros);

        return $query->groupBy('venta_emision_id', 'articulo_id');
    }

    /**
     * Depósito del artículo vendido buscando en toda la venta (líneas sin movimiento propio).
     */
    private function subqueryDepositoMovimientoPorVenta(array $filtros): Builder
    {
        $query = DB::table('articulo_movimiento')
            ->select('venta_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_id')
            ->tap(fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoNoEsInsumo($q));

        $this->aplicarFiltroJornadaSubqueryMovimiento($query, $filtros);

        return $query->groupBy('venta_id', 'articulo_id');
    }

    /**
     * Depósito de insumos de fórmula descontados en la línea facturada.
     */
    private function subqueryDepositoInsumosLinea(array $filtros): Builder
    {
        $query = DB::table('articulo_movimiento')
            ->select('venta_emision_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_emision_id')
            ->tap(fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoEsInsumo($q));

        $this->aplicarFiltroJornadaSubqueryMovimiento($query, $filtros);

        return $query->groupBy('venta_emision_id');
    }

    /**
     * Depósito cuando el artículo vendido es el insumo (movimiento con sufijo Insumo en la venta).
     */
    private function subqueryDepositoInsumoPorVentaArticulo(array $filtros): Builder
    {
        $query = DB::table('articulo_movimiento')
            ->select('venta_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_id')
            ->tap(fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoEsInsumo($q));

        $this->aplicarFiltroJornadaSubqueryMovimiento($query, $filtros);

        return $query->groupBy('venta_id', 'articulo_id');
    }

    private function aplicarFiltroDeposito(Builder $query, int $depositoId): void
    {
        $query->where(function ($w) use ($depositoId) {
            $w->whereRaw(self::DEPOSITO_EXPR.' = ?', [$depositoId])
                ->orWhereRaw(self::DEPOSITO_INSUMO_EXPR.' = ?', [$depositoId]);
        });
    }

    private function aplicarFiltrosEstructurales(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pv.empresa_id');
        }

        $puntoventaId = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $query->where('v.puntoventa_id', $puntoventaId);
        }

        $depositoId = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoId > 0) {
            $this->aplicarFiltroDeposito($query, $depositoId);
        }

        $jornadaId = (int) ($filtros['jornada_id'] ?? 0);
        if ($jornadaId > 0) {
            $jornada = JornadaGastronomia::query()->find($jornadaId);
            if ($jornada !== null) {
                $query->whereDate('v.fechajornada', $jornada->fecha_jornada);
                if ($empresaId <= 0) {
                    $query->where('pv.empresa_id', (int) $jornada->empresa_id);
                }
            }
        } else {
            [$desde, $hasta] = GastronomiaArticulosVendidosListadoFiltros::normalizarRangoFechas(
                (string) ($filtros['fecha_desde'] ?? ''),
                (string) ($filtros['fecha_hasta'] ?? ''),
            );
            if ($desde !== '') {
                $query->whereDate('v.fechajornada', '>=', $desde);
            }
            if ($hasta !== '') {
                $query->whereDate('v.fechajornada', '<=', $hasta);
            }
        }
    }

    private function aplicarFiltrosTexto(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $modo = (string) ($filtros['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS);

        if ($valor === '' && $operador !== 'vacio') {
            return;
        }

        if ($modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'descripcion');
            $this->aplicarEnCampo($query, $campo, $operador, $valor);

            return;
        }

        $query->where(function ($w) use ($operador, $valor) {
            foreach (array_keys(GastronomiaArticulosVendidosListadoFiltros::CAMPOS) as $campo) {
                $w->orWhere(function ($sub) use ($campo, $operador, $valor) {
                    $this->aplicarEnCampo($sub, $campo, $operador, $valor);
                });
            }
        });
    }

    private function aplicarEnCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $columna = match ($campo) {
            'sku' => 'a.sku',
            'descripcion' => 'a.descripcion',
            'deposito' => "TRIM(CONCAT(
                COALESCE(d.codigo, ''), ' ', COALESCE(d.nombre, ''),
                CASE WHEN ".self::DEPOSITO_INSUMO_JOIN." IS NOT NULL
                    AND ".self::DEPOSITO_INSUMO_JOIN." <> COALESCE(".self::DEPOSITO_EXPR.", 0)
                    THEN CONCAT(' ins. ', COALESCE(d_ing.codigo, ''), ' ', COALESCE(d_ing.nombre, ''))
                    ELSE '' END
            ))",
            'puntoventa' => "TRIM(CONCAT(COALESCE(pv.codigo, ''), ' ', COALESCE(pv.nombre, '')))",
            default => 'a.descripcion',
        };

        if ($operador === 'vacio') {
            $query->whereRaw('('.$columna.') IS NULL OR TRIM(('.$columna.')) = \'\'');

            return;
        }

        if ($valor === '') {
            return;
        }

        $expr = 'LOWER('.$columna.')';
        $bus = Str::lower($valor);

        if ($operador === 'igual') {
            $query->whereRaw($expr.' = ?', [$bus]);

            return;
        }

        if ($operador === 'distinto') {
            $query->whereRaw($expr.' <> ?', [$bus]);

            return;
        }

        $like = $this->patronLike($operador, $valor);

        $query->where(function ($w) use ($expr, $like, $operador, $valor, $columna) {
            $w->whereRaw($expr.' LIKE ?', [Str::lower($like)]);

            if ($operador === 'contiene' && in_array($columna, ['a.sku', 'a.descripcion'], true)) {
                $this->aplicarCoincidenciaFlexibleRaw($w, $columna, $valor);
            }
        });
    }

    private function aplicarCoincidenciaFlexibleRaw(Builder $query, string $columna, string $valor): void
    {
        $min = $columna === 'a.sku'
            ? CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
            : CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;

        if (mb_strlen($valor) < $min) {
            return;
        }

        $pref = mb_strtolower(mb_substr($valor, 0, 3));
        $longitudSufijo = mb_strlen($valor) >= 8 ? 5 : 4;
        $suf = mb_strtolower(mb_substr($valor, -$longitudSufijo));

        if ($pref === '' || $suf === '' || $pref === $suf) {
            return;
        }

        $expr = 'LOWER('.$columna.')';
        $query->orWhere(function ($w) use ($expr, $pref, $suf) {
            $w->whereRaw($expr.' LIKE ?', ['%'.CoincidenciaFlexibleTexto::escapeLike($pref).'%'])
                ->whereRaw($expr.' LIKE ?', ['%'.CoincidenciaFlexibleTexto::escapeLike($suf).'%']);
        });
    }

    private function patronLike(string $operador, string $valor): string
    {
        $esc = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $esc.'%',
            'termina' => '%'.$esc,
            'igual' => $esc,
            'distinto' => $esc,
            default => '%'.$esc.'%',
        };
    }

    private function enriquecerFila(object $row): object
    {
        $venta = trim((string) ($row->deposito_codigo ?? '').' '.(string) ($row->deposito_nombre ?? ''));
        $insumos = trim((string) ($row->deposito_insumos_codigo ?? '').' '.(string) ($row->deposito_insumos_nombre ?? ''));
        $depVentaId = (int) ($row->deposito_id ?? 0);
        $depInsumosId = (int) ($row->deposito_insumos_id ?? 0);

        $row->deposito_venta_etiqueta = $venta;
        $row->deposito_insumos_etiqueta = ($depInsumosId > 0 && $insumos !== '' && $depInsumosId !== $depVentaId)
            ? $insumos
            : '';
        $row->deposito_etiqueta = $this->etiquetaDepositoFila($row);
        $row->puntoventa_etiqueta = trim(
            (string) ($row->pv_codigo ?? '').' '.(string) ($row->pv_nombre ?? '')
        );
        $row->cantidad_total = round((float) ($row->cantidad_total ?? 0), 4);
        $row->importe_total = round((float) ($row->importe_total ?? 0), 2);

        return $row;
    }

    private function etiquetaDepositoFila(object $row): string
    {
        $venta = trim((string) ($row->deposito_codigo ?? '').' '.(string) ($row->deposito_nombre ?? ''));
        $insumos = trim((string) ($row->deposito_insumos_codigo ?? '').' '.(string) ($row->deposito_insumos_nombre ?? ''));
        $depVentaId = (int) ($row->deposito_id ?? $row->deposito_resuelto_id ?? 0);
        $depInsumosId = (int) ($row->deposito_insumos_id ?? 0);

        if ($depInsumosId > 0 && $insumos !== '') {
            if ($depVentaId <= 0 || $depInsumosId !== $depVentaId) {
                return $venta !== '' ? $venta.' / ins. '.$insumos : $insumos;
            }
        }

        return $venta !== '' ? $venta : $insumos;
    }
}
