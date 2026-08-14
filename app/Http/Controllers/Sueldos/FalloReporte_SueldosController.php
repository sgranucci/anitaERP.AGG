<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\FalloCuentaCorrienteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\FalloCuentaCorrienteReporteService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FalloReporte_SueldosController extends Controller
{
    public function __construct(
        private readonly FalloCuentaCorrienteReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-fallo-reporte-sueldos');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);
        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = $this->service->generar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $slice = array_slice($resultado['filas'], ($page - 1) * $perPage, $perPage);
            $filasPaginadas = new LengthAwarePaginator(
                $slice,
                count($resultado['filas']),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('sueldos.fallo_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => array_merge($filtros, $consultado ? ['consultar' => 1] : []),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-fallo-reporte-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosDesdeRequest($request, $this->empresaRepository->allFiltrado());
        $resultado = $this->service->generar($filtros);
        $titulo = 'Cta. cte. fallos de empleados';
        $subtitulo = $resultado['subtitulo'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('sueldos.fallo_reporte.listado', [
                    'filas' => $resultado['filas'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'resultado' => $resultado,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'reporte_fallo_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new FalloCuentaCorrienteExport($resultado['filas'], $titulo, $subtitulo, $resultado))
                    ->download('reporte_fallo.xlsx');

            case 'CSV':
                return (new FalloCuentaCorrienteExport($resultado['filas'], $titulo, $subtitulo, $resultado))
                    ->download('reporte_fallo.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('fallo_reporte_sueldos', array_merge($filtros, ['consultar' => 1]));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request, $empresaQuery = null): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery !== null && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : 0,
            'fecha_desde' => (string) $request->input('fecha_desde', date('Y-m-01')),
            'fecha_hasta' => (string) $request->input('fecha_hasta', date('Y-m-d')),
            'orden' => $request->input('orden') === 'alfabetico' ? 'alfabetico' : 'legajo',
            'modo' => $request->input('modo') === 'totales' ? 'totales' : 'movimientos',
            'legajo_desde' => (int) $request->input('legajo_desde', 1),
            'legajo_hasta' => (int) $request->input('legajo_hasta', 99999999),
        ];
    }
}
