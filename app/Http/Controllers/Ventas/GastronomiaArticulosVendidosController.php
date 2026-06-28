<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaArticulosVendidosExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Queries\Ventas\GastronomiaArticulosVendidosQuery;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Services\Stock\FormulaArticuloService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Ventas\GastronomiaArticulosVendidosCacheSupport;
use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel;

class GastronomiaArticulosVendidosController extends Controller
{
    public function __construct(
        private readonly GastronomiaArticulosVendidosQuery $query,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly FormulaArticuloService $formulaArticuloService,
    ) {}

    public function index(Request $request)
    {
        can('listar-articulos-vendidos-gastronomia');

        $contexto = $this->prepararContextoFiltros($request);
        $filtros = $contexto['filtros'];
        $empresaIdFiltro = (int) ($filtros['empresa_id'] ?? 0);
        $puedeConsultar = $this->puedeConsultarReporte($empresaIdFiltro, $contexto['requiere_seleccion_empresa']);

        $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
        $totales = $this->totalesVacios();

        if ($puedeConsultar) {
            $jornadaEstado = $empresaIdFiltro > 0
                ? $this->jornadaService->estadoParaEmpresa($empresaIdFiltro)
                : null;
            $resultado = $this->resolverResultadoListado($request, $filtros, $jornadaEstado);
            $totales = $resultado['totales'];
            $filas = $this->paginarFilas($resultado['filas'], $perPage, $request);
        } else {
            $jornadaEstado = null;
            $filas = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $filas->appends(GastronomiaArticulosVendidosListadoFiltros::paraQueryString($filtros));
        if ($request->has('per_page')) {
            $filas->appends(['per_page' => $perPage]);
        }

        $jornada = $jornadaEstado ?? ($empresaIdFiltro > 0
            ? $this->jornadaService->estadoParaEmpresa($empresaIdFiltro)
            : null);

        $fechaJornada = GastronomiaArticulosVendidosListadoFiltros::fechaJornadaDesdeFiltros($filtros);

        return view('ventas.gastronomia.articulos_vendidos.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => GastronomiaArticulosVendidosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => GastronomiaArticulosVendidosListadoFiltros::CAMPOS,
            'empresa_query' => $contexto['empresa_query'],
            'requiere_seleccion_empresa' => $contexto['requiere_seleccion_empresa'],
            'puede_consultar' => $puedeConsultar,
            'deposito_query' => $this->depositosGastronomia($empresaIdFiltro, $contexto['empresa_query']),
            'jornadas' => $empresaIdFiltro > 0
                ? $this->jornadaRepository->historialPorEmpresa($empresaIdFiltro, 30)
                : collect(),
            'jornada' => $jornada,
            'empresa_id' => $empresaIdFiltro,
            'fecha_jornada' => $fechaJornada,
            'totales' => $totales,
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'puede_ver_articulo' => can('editar-articulos', false),
            'puede_ver_formula' => can('listar-formula-articulo', false) || can('listar-articulos', false),
            'puede_ver_movimientos' => MovimientosArticuloDepositoSupport::puedeConsultar(),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-articulos-vendidos-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $contexto = $this->prepararContextoFiltros($request);
        $filtros = $contexto['filtros'];
        $empresaIdFiltro = (int) ($filtros['empresa_id'] ?? 0);

        if (! $this->puedeConsultarReporte($empresaIdFiltro, $contexto['requiere_seleccion_empresa'])) {
            return redirect()
                ->route('gastronomia_articulos_vendidos')
                ->with('errores', ['Seleccione empresa para exportar el reporte.']);
        }

        $jornadaEstado = $empresaIdFiltro > 0
            ? $this->jornadaService->estadoParaEmpresa($empresaIdFiltro)
            : null;
        $resultado = $this->resolverResultadoListado($request, $filtros, $jornadaEstado);
        $filas = $resultado['filas'];
        $totalesExport = $resultado['totales'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.articulos_vendidos.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'totales' => $totalesExport,
                ])->render();

                return $this->descargarPdf($view, 'articulos_vendidos_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new GastronomiaArticulosVendidosExport($filas, $filtros))
                    ->download('articulos_vendidos_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaArticulosVendidosExport($filas, $filtros))
                    ->download('articulos_vendidos_gastronomia.csv', Excel::CSV);
        }

        abort(404);
    }

    public function apiFacturas(Request $request, int $articuloId)
    {
        can('listar-articulos-vendidos-gastronomia');

        if ($articuloId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
        }

        $contexto = $this->prepararContextoFiltros($request);
        $filtros = $contexto['filtros'];
        $empresaIdFiltro = (int) ($filtros['empresa_id'] ?? 0);

        if (! $this->puedeConsultarReporte($empresaIdFiltro, $contexto['requiere_seleccion_empresa'])) {
            return response()->json(['ok' => false, 'error' => 'Seleccione empresa para consultar comprobantes.'], 422);
        }

        $facturas = $this->query->facturasPorArticulo($articuloId, $filtros);

        $fechaFacturasDia = GastronomiaArticulosVendidosListadoFiltros::fechaJornadaDesdeFiltros($filtros);
        if ($fechaFacturasDia === '') {
            $fechaFacturasDia = Carbon::today()->format('Y-m-d');
        }

        $sku = trim((string) $request->input('sku', ''));

        $formulaResuelta = null;
        if (can('listar-formula-articulo', false) || can('listar-articulos', false)) {
            $formulaResuelta = $this->formulaArticuloService->resolverIdParaArticulo($articuloId);
        }

        $formulaId = is_array($formulaResuelta) ? (int) ($formulaResuelta['formula_id'] ?? 0) : 0;
        $urlFormula = $formulaId > 0
            ? route('editar_formula_articulo', ['id' => $formulaId, 'origen' => 'modal_consulta'])
            : null;

        return response()->json([
            'ok' => true,
            'articulo_id' => $articuloId,
            'facturas' => $facturas,
            'url_facturas_dia' => route('gastronomia_facturas_dia', array_filter([
                'fecha' => $fechaFacturasDia,
                'empresa_id' => $empresaIdFiltro > 0 ? $empresaIdFiltro : null,
                'articulo_id' => $articuloId,
                'articulo_sku' => $sku !== '' ? $sku : null,
                'todas_pc' => '1',
            ])),
            'url_factura_ver_base' => can('ver-factura-gastronomia', false)
                ? url('ventas/gastronomia/facturas-dia')
                : null,
            'formula_id' => $formulaId > 0 ? $formulaId : null,
            'formula_mensaje' => is_array($formulaResuelta) ? ($formulaResuelta['mensaje'] ?? null) : null,
            'url_formula' => $urlFormula,
        ]);
    }

    public function apiMovimientos(Request $request, int $articuloId)
    {
        can('listar-articulos-vendidos-gastronomia');

        if (! MovimientosArticuloDepositoSupport::puedeConsultar()) {
            return response()->json(['ok' => false, 'error' => 'No tiene permisos para consultar movimientos de stock.'], 403);
        }

        if ($articuloId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Artículo inválido.'], 422);
        }

        $contexto = $this->prepararContextoFiltros($request);
        $filtros = $contexto['filtros'];
        $empresaIdFiltro = (int) ($filtros['empresa_id'] ?? 0);

        if (! $this->puedeConsultarReporte($empresaIdFiltro, $contexto['requiere_seleccion_empresa'])) {
            return response()->json(['ok' => false, 'error' => 'Seleccione empresa para consultar movimientos.'], 422);
        }

        $resultado = $this->query->movimientosPorArticulo($articuloId, $filtros);

        $depositoId = (int) ($filtros['deposito_id'] ?? 0);
        $urlKardex = route('recuento_movimientos_articulo', array_filter([
            'articulo_id' => $articuloId,
            'deposito_id' => $depositoId > 0 ? $depositoId : null,
            'volver' => $request->fullUrl(),
            'vista' => 'consulta',
        ]));

        return response()->json([
            'ok' => true,
            'articulo_id' => $articuloId,
            'movimientos' => $resultado['movimientos'],
            'totales' => $resultado['totales'],
            'url_kardex' => $urlKardex,
        ]);
    }

    /**
     * @return array{
     *   empresa_query: Collection,
     *   requiere_seleccion_empresa: bool,
     *   filtros: array<string, mixed>
     * }
     */
    private function prepararContextoFiltros(Request $request): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $requiereSeleccionEmpresa = $empresaQuery->count() > 1;

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $this->assertAccesoEmpresa($empresaId);

        $filtros = GastronomiaArticulosVendidosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        return [
            'empresa_query' => $empresaQuery,
            'requiere_seleccion_empresa' => $requiereSeleccionEmpresa,
            'filtros' => $filtros,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, Collection $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $filtros['puntoventa_id'] = 0;

        $fechaJornada = GastronomiaArticulosVendidosListadoFiltros::fechaJornadaDesdeFiltros($filtros);

        if ($fechaJornada === '' && (int) ($filtros['jornada_id'] ?? 0) <= 0) {
            if ($empresaId > 0) {
                $jornada = $this->jornadaService->estadoParaEmpresa($empresaId);
                if (! empty($jornada['jornada_abierta']) && ! empty($jornada['fecha_jornada'])) {
                    $fechaJornada = (string) $jornada['fecha_jornada'];
                }
            }
            if ($fechaJornada === '') {
                $fechaJornada = Carbon::today()->format('Y-m-d');
            }
        }

        if ($fechaJornada !== '') {
            $filtros['fecha_jornada'] = Carbon::parse($fechaJornada)->format('Y-m-d');
            $filtros['fecha_desde'] = $filtros['fecha_jornada'];
            $filtros['fecha_hasta'] = $filtros['fecha_jornada'];
            $filtros['jornada_id'] = 0;
        }

        return $filtros;
    }

    private function puedeConsultarReporte(int $empresaIdFiltro, bool $requiereSeleccionEmpresa): bool
    {
        if ($requiereSeleccionEmpresa) {
            return $empresaIdFiltro > 0;
        }

        return true;
    }

    /**
     * @return array{cantidad_articulos:int,cantidad_total:float,importe_total:float,cantidad_comprobantes:int}
     */
    private function totalesVacios(): array
    {
        return [
            'cantidad_articulos' => 0,
            'cantidad_total' => 0.,
            'importe_total' => 0.,
            'cantidad_comprobantes' => 0,
        ];
    }

    /**
     * Depósitos de venta e insumos definidos en la configuración gastronomía.
     */
    private function depositosGastronomia(int $empresaId, Collection $empresaQuery): Collection
    {
        $empresaIds = $empresaId > 0
            ? collect([$empresaId])
            : $empresaQuery->pluck('id');

        if ($empresaIds->isEmpty()) {
            return collect();
        }

        $depIds = ConfiguracionPuntoventaGastronomia::query()
            ->whereIn('empresa_id', $empresaIds)
            ->get(['deposito_venta_id', 'deposito_insumos_id'])
            ->flatMap(fn ($cfg) => [(int) $cfg->deposito_venta_id, (int) $cfg->deposito_insumos_id])
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($depIds->isEmpty()) {
            return collect();
        }

        return Depmae::query()
            ->paraUsuarioAutorizado()
            ->whereIn('id', $depIds)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombreBase.'.pdf');

        return response()->download($path.'/'.$nombreBase.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $jornadaEstado
     * @return array{filas: Collection<int, object>, totales: array{cantidad_articulos:int,cantidad_total:float,importe_total:float,cantidad_comprobantes:int}}
     */
    private function resolverResultadoListado(Request $request, array $filtros, ?array $jornadaEstado): array
    {
        $usaCache = GastronomiaArticulosVendidosCacheSupport::permiteUsarCache($filtros, $jornadaEstado);
        $forzarConsulta = $request->boolean('consultar') || $request->boolean('refrescar_cache');

        if ($forzarConsulta) {
            GastronomiaArticulosVendidosCacheSupport::limpiar();
        }

        if ($usaCache && ! $forzarConsulta) {
            $desdeCache = GastronomiaArticulosVendidosCacheSupport::recuperar($filtros);
            if ($desdeCache !== null) {
                return $desdeCache;
            }
        }

        $resultado = $this->query->listadoConTotales($filtros);

        if ($usaCache) {
            GastronomiaArticulosVendidosCacheSupport::guardar($filtros, $resultado);
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, object>  $filas
     */
    private function paginarFilas(Collection $filas, int $perPage, Request $request): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $items = $filas->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $filas->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }
}
