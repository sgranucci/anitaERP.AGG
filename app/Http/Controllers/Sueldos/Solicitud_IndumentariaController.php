<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SolicitudIndumentariaExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\SolicitudPrendaService;
use App\Support\Sueldos\SolicitudIndumentariaConsulta;
use Illuminate\Http\Request;

class Solicitud_IndumentariaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private SolicitudPrendaService $solicitudService,
    ) {}

    /** Bandeja de aprobación: redirige a Mis aprobaciones (entrada única). */
    public function bandeja(Request $request)
    {
        can('aprobar-solicitud-indumentaria');

        return redirect()->to(url('mis-aprobaciones').'?fuente=indumentaria');
    }

    public function aprobarBandeja(Request $request, $solicitudId)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);

        try {
            $this->solicitudService->aprobar($solicitud, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to(url('mis-aprobaciones').'?fuente=indumentaria')->with('error', $e->getMessage());
        }

        return redirect()->to(url('mis-aprobaciones').'?fuente=indumentaria')
            ->with('mensaje', 'Solicitud #'.$solicitud->id.' aprobada.');
    }

    public function rechazarBandeja(Request $request, $solicitudId)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);

        try {
            $this->solicitudService->rechazar($solicitud, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to(url('mis-aprobaciones').'?fuente=indumentaria')->with('error', $e->getMessage());
        }

        return redirect()->to(url('mis-aprobaciones').'?fuente=indumentaria')
            ->with('mensaje', 'Solicitud #'.$solicitud->id.' rechazada.');
    }

    /** Reporte de solicitudes (todos los estados) con filtros + export. */
    public function index(Request $request)
    {
        can('listar-solicitud-indumentaria');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudIndumentariaConsulta::resolverFiltros($request, $empresaDefault ? (int) $empresaDefault : null);

        $solicitudes = SolicitudIndumentariaConsulta::query($filtros)->paginate(20);
        $filtrosQuery = SolicitudIndumentariaConsulta::paraQueryString($filtros);

        return view('sueldos.solicitud_indumentaria.index', [
            'solicitudes' => $solicitudes,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'estados' => Solicitud_Prenda_Sueldos::ESTADOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-solicitud-indumentaria');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudIndumentariaConsulta::resolverFiltros($request, $empresaDefault ? (int) $empresaDefault : null);

        switch ($formato) {
            case 'PDF':
                $solicitudes = SolicitudIndumentariaConsulta::coleccion($filtros);
                $view = \View::make('sueldos.solicitud_indumentaria.listado', [
                    'solicitudes' => $solicitudes,
                    'filtros' => $filtros,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/solicitudes_indumentaria.pdf');

                return response()->download($path.'/solicitudes_indumentaria.pdf');

            case 'EXCEL':
                return app(SolicitudIndumentariaExport::class)
                    ->parametros($filtros)
                    ->download('solicitudes_indumentaria.xlsx');

            case 'CSV':
                return app(SolicitudIndumentariaExport::class)
                    ->parametros($filtros)
                    ->download('solicitudes_indumentaria.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_solicitud_indumentaria', SolicitudIndumentariaConsulta::paraQueryString($filtros));
    }
}
