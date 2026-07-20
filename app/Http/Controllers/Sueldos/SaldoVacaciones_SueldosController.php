<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SaldoVacacionesReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\DevengamientoVacacionesService;
use App\Support\Sueldos\SaldoVacacionesListadoFiltros;
use App\Support\Sueldos\SaldoVacacionesReporteConsulta;
use Illuminate\Http\Request;

class SaldoVacaciones_SueldosController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private DevengamientoVacacionesService $devengamiento,
    ) {}

    public function index(Request $request)
    {
        can('listar-saldo-vacaciones-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SaldoVacacionesListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault ? (int) $empresaDefault : null);

        $query = SaldoVacacionesReporteConsulta::query($filtros, $this->empresaRepository);
        $totales = SaldoVacacionesReporteConsulta::totales($query);
        $datas = $query->paginate(20)->appends(SaldoVacacionesListadoFiltros::paraQueryString($filtros));

        return view('sueldos.saldo_vacaciones.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => SaldoVacacionesListadoFiltros::paraQueryString($filtros),
            'totales' => $totales,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-saldo-vacaciones-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SaldoVacacionesListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault ? (int) $empresaDefault : null);

        switch ($formato) {
            case 'PDF':
                $query = SaldoVacacionesReporteConsulta::query($filtros, $this->empresaRepository);
                $datas = $query->get();
                $totales = SaldoVacacionesReporteConsulta::totales(
                    SaldoVacacionesReporteConsulta::query($filtros, $this->empresaRepository)
                );

                $view = \View::make('sueldos.saldo_vacaciones.listado', [
                    'datas' => $datas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_saldo_vacaciones';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(SaldoVacacionesReporteExport::class)
                    ->parametros($filtros)
                    ->download('saldo_vacaciones.xlsx');

            case 'CSV':
                return app(SaldoVacacionesReporteExport::class)
                    ->parametros($filtros)
                    ->download('saldo_vacaciones.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('saldo_vacaciones_sueldos', SaldoVacacionesListadoFiltros::paraQueryString($filtros));
    }

    public function recalcular(Request $request)
    {
        can('listar-saldo-vacaciones-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SaldoVacacionesListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault ? (int) $empresaDefault : null);

        $usuarioId = optional(auth()->user())->id;
        $procesados = 0;

        SaldoVacacionesReporteConsulta::query($filtros, $this->empresaRepository)
            ->reorder()
            ->chunkById(200, function ($empleados) use (&$procesados, $usuarioId) {
                foreach ($empleados as $empleado) {
                    $this->devengamiento->recalcularEmpleado($empleado, $usuarioId);
                    $procesados++;
                }
            }, 'empleado_sueldos.id', 'id');

        return redirect()
            ->route('saldo_vacaciones_sueldos', SaldoVacacionesListadoFiltros::paraQueryString($filtros))
            ->with('mensaje', 'Saldos recalculados: '.$procesados.' empleados devengados según antigüedad (LCT).');
    }
}
