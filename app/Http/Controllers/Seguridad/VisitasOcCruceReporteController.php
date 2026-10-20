<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\VisitasOcCruceReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Seguridad\VisitasOcCruceReporteService;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\VisitasOcCruceReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class VisitasOcCruceReporteController extends Controller
{
    public function __construct(
        private readonly VisitasOcCruceReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-reporte-visitas-oc-cruce');
        $filtros = VisitasOcCruceReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = $this->reporteService->generar($filtros);
            $filasPaginadas = $this->paginar(
                $resultado['filas'],
                $request,
                VisitasOcCruceReporteFiltros::paraQueryString($filtros)
            );
        }

        return view('seguridad.visitas_oc_cruce_reporte.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estados' => IngresoProveedorEstados::META,
            'filtros' => $filtros,
            'filtrosQuery' => VisitasOcCruceReporteFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
        ] + VisitasOcCruceReporteService::permisosEnlacesPantalla());
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-visitas-oc-cruce');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = VisitasOcCruceReporteFiltros::resolverDesdeRequest($request);
        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'];
        $titulo = 'Cruce visitas por orden de compra';
        $subtitulo = VisitasOcCruceReporteFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = view('seguridad.visitas_oc_cruce_reporte.listado', [
                    'filas' => $filas,
                    'kpis' => $resultado['kpis'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre = 'visitas_oc_cruce_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new VisitasOcCruceReporteExport($filas, $titulo, $subtitulo))
                    ->download('visitas_oc_cruce.xlsx');

            case 'CSV':
                return (new VisitasOcCruceReporteExport($filas, $titulo, $subtitulo))
                    ->download('visitas_oc_cruce.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_visitas_oc_cruce', VisitasOcCruceReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $query
     */
    private function paginar(array $filas, Request $request, array $query): LengthAwarePaginator
    {
        $porPagina = 50;
        $page = max(1, (int) $request->input('page', 1));
        $slice = array_slice($filas, ($page - 1) * $porPagina, $porPagina);

        return new LengthAwarePaginator($slice, count($filas), $porPagina, $page, [
            'path' => $request->url(),
            'query' => $query,
        ]);
    }
}
