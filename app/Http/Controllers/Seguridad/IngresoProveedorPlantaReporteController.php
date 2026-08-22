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
use App\Support\Seguridad\IngresoProveedorReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class IngresoProveedorPlantaReporteController extends Controller
{
    public function __construct(
        private readonly IngresoProveedorReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-reporte-ingresos-planta');
        $filtros = IngresoProveedorReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = $this->reporteService->generar($filtros, IngresoProveedorReporteService::MODO_PLANTA);
            $filasPaginadas = $this->paginar($resultado['filas'], $request, IngresoProveedorReporteFiltros::paraQueryString($filtros));
        }

        return view('seguridad.ingreso_proveedor_reporte.planta.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'motivos' => IngresoProveedorMotivo::query()->where('activo', true)->orderBy('nombre')->get(),
            'puntos' => IngresoProveedorPunto::query()->where('activo', true)->orderBy('nombre')->get(),
            'sectores' => IngresoProveedorSector::query()->where('activo', true)->orderBy('nombre')->get(),
            'areas' => IngresoProveedorArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorReporteFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-ingresos-planta');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IngresoProveedorReporteFiltros::resolverDesdeRequest($request);
        $resultado = $this->reporteService->generar($filtros, IngresoProveedorReporteService::MODO_PLANTA);
        $filas = $resultado['filas'];
        $kpis = $resultado['kpis'];
        $titulo = 'Ingresos de planta';
        $subtitulo = IngresoProveedorReporteFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = view('seguridad.ingreso_proveedor_reporte.planta.listado', [
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
                $nombre = 'ingresos_planta_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'planta'))
                    ->download('ingresos_planta.xlsx');

            case 'CSV':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'planta'))
                    ->download('ingresos_planta.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_ingresos_planta', IngresoProveedorReporteFiltros::paraQueryString($filtros));
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
}
