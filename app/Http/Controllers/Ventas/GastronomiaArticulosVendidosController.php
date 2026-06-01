<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaArticulosVendidosExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Queries\Ventas\GastronomiaArticulosVendidosQuery;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Services\Stock\FormulaArticuloService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $filtros = GastronomiaArticulosVendidosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaId, $request);

        $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
        $filas = $this->query->listado($filtros, true, $perPage);
        $filas->appends(GastronomiaArticulosVendidosListadoFiltros::paraQueryString($filtros));

        $totales = $this->query->totales($filtros);

        $jornada = $empresaId > 0 ? $this->jornadaService->estadoParaEmpresa($empresaId) : null;
        $jornadas = $empresaId > 0 ? $this->jornadaRepository->historialPorEmpresa($empresaId, 30) : collect();

        $empresaIdSelectores = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? (int) $filtros['empresa_id']
            : $empresaId;

        return view('ventas.gastronomia.articulos_vendidos.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => GastronomiaArticulosVendidosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => GastronomiaArticulosVendidosListadoFiltros::CAMPOS,
            'empresa_query' => $empresaQuery,
            'puntoventa_query' => $this->puntoventasGastronomia($empresaIdSelectores, $empresaQuery),
            'deposito_query' => $this->depositosGastronomia($empresaIdSelectores, $empresaQuery),
            'jornadas' => $empresaIdSelectores > 0
                ? $this->jornadaRepository->historialPorEmpresa($empresaIdSelectores, 30)
                : $jornadas,
            'jornada' => $jornada,
            'empresa_id' => $empresaId,
            'totales' => $totales,
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'puede_ver_articulo' => can('editar-articulos', false),
            'puede_ver_formula' => can('listar-formula-articulo', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-articulos-vendidos-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $filtros = GastronomiaArticulosVendidosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaId, $request);

        $filas = $this->query->listado($filtros, false);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.articulos_vendidos.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'totales' => $this->query->totales($filtros),
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

        $empresaId = (int) $request->input('empresa_id', 0);
        $filtros = GastronomiaArticulosVendidosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaId, $request);

        $facturas = $this->query->facturasPorArticulo($articuloId, $filtros);

        $fechaFacturasDia = $filtros['fecha_desde'] !== ''
            ? $filtros['fecha_desde']
            : Carbon::today()->format('Y-m-d');

        if ((int) ($filtros['jornada_id'] ?? 0) > 0) {
            $jornada = JornadaGastronomia::query()->find((int) $filtros['jornada_id']);
            if ($jornada !== null) {
                $fechaFacturasDia = $jornada->fecha_jornada->format('Y-m-d');
            }
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

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, int $empresaId, Request $request): array
    {
        if ($empresaId > 0 && (int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $filtros['empresa_id'] = $empresaId;
        }

        if ((int) ($filtros['jornada_id'] ?? 0) <= 0
            && $filtros['fecha_desde'] === ''
            && $filtros['fecha_hasta'] === '') {
            $jornada = $empresaId > 0 ? $this->jornadaService->estadoParaEmpresa($empresaId) : null;
            if (! empty($jornada['jornada_abierta']) && ! empty($jornada['fecha_jornada'])) {
                $filtros['fecha_desde'] = (string) $jornada['fecha_jornada'];
                $filtros['fecha_hasta'] = (string) $jornada['fecha_jornada'];
            } else {
                $filtros['fecha_desde'] = Carbon::today()->subDays(7)->format('Y-m-d');
                $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
            }
        }

        if ($filtros['fecha_desde'] !== '' || $filtros['fecha_hasta'] !== '') {
            [$desde, $hasta] = GastronomiaArticulosVendidosListadoFiltros::normalizarRangoFechas(
                $filtros['fecha_desde'],
                $filtros['fecha_hasta'],
            );
            $filtros['fecha_desde'] = $desde;
            $filtros['fecha_hasta'] = $hasta;
        }

        return $filtros;
    }

    /**
     * PV configurados en gastronomía (CAE/CAEA) de la empresa o de todas las asignadas al usuario.
     */
    private function puntoventasGastronomia(int $empresaId, \Illuminate\Support\Collection $empresaQuery): \Illuminate\Support\Collection
    {
        $empresaIds = $empresaId > 0
            ? collect([$empresaId])
            : $empresaQuery->pluck('id');

        if ($empresaIds->isEmpty()) {
            return collect();
        }

        $pvIds = ConfiguracionPuntoventaGastronomia::query()
            ->whereIn('empresa_id', $empresaIds)
            ->get(['puntoventa_cae_id', 'puntoventa_caea_id'])
            ->flatMap(fn ($cfg) => [(int) $cfg->puntoventa_cae_id, (int) $cfg->puntoventa_caea_id])
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($pvIds->isEmpty()) {
            return collect();
        }

        return Puntoventa::query()
            ->whereIn('id', $pvIds)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    /**
     * Depósitos de venta e insumos definidos en la configuración gastronomía.
     */
    private function depositosGastronomia(int $empresaId, \Illuminate\Support\Collection $empresaQuery): \Illuminate\Support\Collection
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
}
