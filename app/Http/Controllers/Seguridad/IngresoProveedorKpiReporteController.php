<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\IngresoProveedorArea;
use App\Models\Seguridad\IngresoProveedorMotivo;
use App\Models\Seguridad\IngresoProveedorPunto;
use App\Models\Seguridad\IngresoProveedorSector;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Seguridad\IngresoProveedorReporteService;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class IngresoProveedorKpiReporteController extends Controller
{
    public function __construct(
        private readonly IngresoProveedorReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-reporte-tickets-ingreso');
        $filtros = IngresoProveedorReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = $this->reporteService->generar($filtros, IngresoProveedorReporteService::MODO_KPI);
            $filasPaginadas = $this->paginar($resultado['filas'], $request, IngresoProveedorReporteFiltros::paraQueryString($filtros));
        }

        return view('seguridad.ingreso_proveedor_reporte.kpi.index', array_merge(
            $this->catalogos(),
            [
                'filtros' => $filtros,
                'filtrosQuery' => IngresoProveedorReporteFiltros::paraQueryString($filtros),
                'consultado' => $consultado,
                'resultado' => $resultado,
                'filasPaginadas' => $filasPaginadas,
                'puede_ver_ticket' => can('editar-ingreso-proveedor', false),
            ]
        ));
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-tickets-ingreso');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IngresoProveedorReporteFiltros::resolverDesdeRequest($request);
        $resultado = $this->reporteService->generar($filtros, IngresoProveedorReporteService::MODO_KPI);
        $filas = $resultado['filas'];
        $kpis = $resultado['kpis'];
        $titulo = 'Reporte tickets e ingresos';
        $subtitulo = IngresoProveedorReporteFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = view('seguridad.ingreso_proveedor_reporte.kpi.listado', [
                    'filas' => $filas,
                    'kpis' => $kpis,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'totalFilas' => count($filas),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre = 'reporte_tickets_ingreso_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'kpi'))
                    ->download('reporte_tickets_ingreso.xlsx');

            case 'CSV':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'kpi'))
                    ->download('reporte_tickets_ingreso.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_tickets_ingreso', IngresoProveedorReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $query
     */
    private function paginar(array $filas, Request $request, array $query): LengthAwarePaginator
    {
        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return (new LengthAwarePaginator($slice, count($filas), $perPage, $page))
            ->appends($query)
            ->withPath($request->url());
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'motivos' => IngresoProveedorMotivo::query()->where('activo', true)->orderBy('nombre')->get(),
            'puntos' => IngresoProveedorPunto::query()->where('activo', true)->orderBy('nombre')->get(),
            'sectores' => IngresoProveedorSector::query()->where('activo', true)->orderBy('nombre')->get(),
            'areas' => IngresoProveedorArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => IngresoProveedorEstados::META,
        ];
    }
}
