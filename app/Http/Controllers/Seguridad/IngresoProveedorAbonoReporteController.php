<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Seguridad\IngresoProveedorAbonoReporteService;
use App\Support\Seguridad\IngresoProveedorAbonoReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class IngresoProveedorAbonoReporteController extends Controller
{
    public function __construct(
        private readonly IngresoProveedorAbonoReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-reporte-abono-sin-ingresos');
        $filtros = IngresoProveedorAbonoReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = $this->reporteService->generar($filtros);
            $filasPaginadas = $this->paginar($resultado['filas'], $request, IngresoProveedorAbonoReporteFiltros::paraQueryString($filtros));
        }

        return view('seguridad.ingreso_proveedor_reporte.abono.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorAbonoReporteFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'puede_ver_oc' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-abono-sin-ingresos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IngresoProveedorAbonoReporteFiltros::resolverDesdeRequest($request);
        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'];
        $kpis = $resultado['kpis'];
        $titulo = 'Abono mensual sin ingresos';
        $subtitulo = IngresoProveedorAbonoReporteFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = view('seguridad.ingreso_proveedor_reporte.abono.listado', [
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
                $nombre = 'abono_sin_ingresos_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'abono'))
                    ->download('abono_sin_ingresos.xlsx');

            case 'CSV':
                return (new IngresoProveedorReporteExport($filas, $titulo, $subtitulo, 'abono'))
                    ->download('abono_sin_ingresos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_abono_sin_ingresos', IngresoProveedorAbonoReporteFiltros::paraQueryString($filtros));
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
