<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SancionReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Empleado_Sancion_SueldosRepositoryInterface;
use App\Support\Sueldos\EmpleadoSancionSupport;
use App\Support\Sueldos\SancionReporteListadoFiltros;
use Illuminate\Http\Request;

class SancionReporte_SueldosController extends Controller
{
    public function __construct(
        private Empleado_Sancion_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-sancion-reporte-sueldos');
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = SancionReporteListadoFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $datas = null;

        if ($consultado) {
            $datas = $this->repository->leeSancionesReporte($filtros, true);
            $datas->getCollection()->each(function ($row) {
                $row->nombreempresa = optional(optional($row->empleado)->empresa)->nombre ?? '';
            });
        }

        return view('sueldos.sancion_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => SancionReporteListadoFiltros::paraQueryString($filtros, $consultado),
            'consultado' => $consultado,
            'datas' => $datas,
            'estados' => EmpleadoSancionSupport::ESTADOS,
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-sancion-reporte-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $filtros = SancionReporteListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeSancionesReporte($filtros, false);
        $subtitulo = SancionReporteListadoFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('sueldos.sancion_reporte.listado', [
                    'datas' => $datas,
                    'subtitulo' => $subtitulo,
                    'incluirComentario' => ! empty($filtros['incluir_comentario']),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'listado_sancion_sueldos';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');
            case 'EXCEL':
                return app(SancionReporteExport::class)
                    ->parametros($filtros)
                    ->download('sanciones_empleados.xlsx');
            case 'CSV':
                return app(SancionReporteExport::class)
                    ->parametros($filtros)
                    ->download('sanciones_empleados.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('sancion_reporte_sueldos', SancionReporteListadoFiltros::paraQueryString($filtros, true));
    }
}
