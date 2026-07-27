<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Exports\Solicitudpago\SolicitudpagoMaeReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoMaeListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoMaeReporteConsulta;
use Illuminate\Http\Request;

class SolicitudpagoMaeReporteController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-informe-solicitudpago');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoMaeListadoFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $filtrosQuery = SolicitudpagoMaeListadoFiltros::paraQueryString($filtros);
        $consultado = ! empty($filtros['consultar']);

        $datas = null;
        $totales = [
            'registros' => 0,
            'monto' => 0.0,
            'conciliadas_ok' => 0,
            'conciliadas_dif' => 0,
        ];

        if ($consultado) {
            $page = max(1, (int) $request->input('page', 1));
            if (SolicitudpagoMaeListadoFiltros::expandirFamilia($filtros)) {
                $todas = SolicitudpagoMaeReporteConsulta::listarTodas($filtros, $this->empresaRepository);
                $datas = (new \Illuminate\Pagination\LengthAwarePaginator(
                    $todas->forPage($page, 25)->values(),
                    $todas->count(),
                    25,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                ))->appends($filtrosQuery);
                $totales = [
                    'registros' => $todas->count(),
                    'monto' => (float) $todas->sum(fn ($f) => (float) ($f->monto ?? 0)),
                    'conciliadas_ok' => $todas->where('concil_estado', 'OK')->count(),
                    'conciliadas_dif' => $todas->where('concil_estado', 'DIF')->count(),
                ];
            } else {
                $datas = SolicitudpagoMaeReporteConsulta::paginarInforme(
                    $filtros,
                    $this->empresaRepository,
                    25,
                    $page
                )->appends($filtrosQuery);
                $query = SolicitudpagoMaeReporteConsulta::query($filtros, $this->empresaRepository);
                $totalesBase = SolicitudpagoMaeReporteConsulta::totales($query, $filtros, null);
                $totales = SolicitudpagoMaeReporteConsulta::totales($query, $filtros, collect($datas->items()));
                $totales['registros'] = $totalesBase['registros'];
                $totales['monto'] = $totalesBase['monto'];
            }
        }

        return view('solicitudpago.informe_solicitudpago.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'totales' => $totales,
            'consultado' => $consultado,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'sectores' => Sector_Solicitudpago::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'estados' => SolicitudpagoEstados::opciones(),
            'tratamientos_filtro' => SolicitudpagoMaeListadoFiltros::opcionesTratamiento(),
            'muestra_cuota' => SolicitudpagoMaeListadoFiltros::muestraColumnasCuota($filtros),
            'subtitulo' => SolicitudpagoMaeListadoFiltros::subtitulo($filtros),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-informe-solicitudpago');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoMaeListadoFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $filtros['consultar'] = true;

        $filas = SolicitudpagoMaeReporteConsulta::listarTodas($filtros, $this->empresaRepository);
        $query = SolicitudpagoMaeReporteConsulta::query($filtros, $this->empresaRepository);
        $totales = SolicitudpagoMaeReporteConsulta::totales($query, $filtros, $filas);
        $subtitulo = SolicitudpagoMaeListadoFiltros::subtitulo($filtros);
        $muestraCuota = SolicitudpagoMaeListadoFiltros::muestraColumnasCuota($filtros);
        $incluirConcil = ! empty($filtros['incluir_conciliacion_mayor']);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('solicitudpago.informe_solicitudpago.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                    'subtitulo' => $subtitulo,
                    'muestra_cuota' => $muestraCuota,
                    'incluir_conciliacion' => $incluirConcil,
                    'puede_ver_sp' => false,
                    'puede_ver_proveedor' => false,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombrePdf = 'listado_informe_solicitudpago';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new SolicitudpagoMaeReporteExport(
                    $filas->all(),
                    $totales,
                    $subtitulo,
                    $muestraCuota,
                    $incluirConcil,
                ))->download('informe_solicitudpago.xlsx');

            case 'CSV':
                return (new SolicitudpagoMaeReporteExport(
                    $filas->all(),
                    $totales,
                    $subtitulo,
                    $muestraCuota,
                    $incluirConcil,
                ))->download('informe_solicitudpago.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'informe_solicitudpago',
            SolicitudpagoMaeListadoFiltros::paraQueryString($filtros)
        );
    }
}
