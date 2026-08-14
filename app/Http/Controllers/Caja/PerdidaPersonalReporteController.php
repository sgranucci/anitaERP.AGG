<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\PerdidaPersonalReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\ConceptoPerdida;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\PerdidaPersonalReporteService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PerdidaPersonalReporteController extends Controller
{
    public function __construct(
        private readonly PerdidaPersonalReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-perdida-personal-reporte');

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

        return view('caja.perdida_personal_reporte.index', [
            'empresa_query' => $empresaQuery,
            'conceptos' => ConceptoPerdida::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'filtros' => $filtros,
            'filtrosQuery' => array_merge($filtros, $consultado ? ['consultar' => 1] : []),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'puede_ver_perdida' => can('editar-perdida-personal', false) || can('listar-perdida-personal', false),
            'puede_ver_empleado' => can('editar-empleado-sueldos', false) || can('listar-empleado-sueldos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-perdida-personal-reporte');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosDesdeRequest($request, $this->empresaRepository->allFiltrado());
        $resultado = $this->service->generar($filtros);
        $filas = $resultado['filas'];
        $titulo = 'Pérdidas de empleados';
        $subtitulo = $resultado['subtitulo'];
        $totalImporte = $resultado['total_importe'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('caja.perdida_personal_reporte.listado', compact(
                    'filas',
                    'titulo',
                    'subtitulo',
                    'totalImporte',
                    'resultado'
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'reporte_perdida_personal_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new PerdidaPersonalReporteExport($filas, $titulo, $subtitulo, $totalImporte))
                    ->download('reporte_perdida_personal.xlsx');

            case 'CSV':
                return (new PerdidaPersonalReporteExport($filas, $titulo, $subtitulo, $totalImporte))
                    ->download('reporte_perdida_personal.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('perdida_personal_reporte', array_merge($filtros, ['consultar' => 1]));
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
            'concepto_perdida_id' => (int) $request->input('concepto_perdida_id', 0),
            'orden' => in_array($request->input('orden'), [
                PerdidaPersonalReporteService::ORDEN_LEGAJO,
                PerdidaPersonalReporteService::ORDEN_ALFABETICO,
            ], true) ? (string) $request->input('orden') : PerdidaPersonalReporteService::ORDEN_LEGAJO,
            'filtro_empleado' => in_array($request->input('filtro_empleado'), [
                PerdidaPersonalReporteService::FILTRO_ACTIVOS,
                PerdidaPersonalReporteService::FILTRO_BAJAS,
                PerdidaPersonalReporteService::FILTRO_TODOS,
            ], true) ? (string) $request->input('filtro_empleado') : PerdidaPersonalReporteService::FILTRO_ACTIVOS,
            'modo' => in_array($request->input('modo'), [
                PerdidaPersonalReporteService::MODO_MOVIMIENTOS,
                PerdidaPersonalReporteService::MODO_TOTALES,
            ], true) ? (string) $request->input('modo') : PerdidaPersonalReporteService::MODO_MOVIMIENTOS,
            'legajo_desde' => (int) $request->input('legajo_desde', 1),
            'legajo_hasta' => (int) $request->input('legajo_hasta', 99999999),
        ];
    }
}
