<?php

namespace App\Http\Controllers\Sala;

use App\Exports\Sala\RequisicionSalaReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sala\RequisicionSalaReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Sala\RequisicionSalaReporteCriteriosSupport;
use App\Support\Sala\RequisicionSalaReporteFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class RequisicionSalaReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'requisicion_sala_reporte';

    public function __construct(
        private RequisicionSalaReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-requisicion-sala');

        ini_set('memory_limit', '512M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RequisicionSalaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && RequisicionSalaReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);
            $resultado = $this->service->generar($filtros);
            $perPage = max(25, min(500, (int) $request->input('per_page', 100)));
            $filas = $this->service->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
        }

        $filtrosQuery = RequisicionSalaReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('sala.requisicion_sala_reporte.index', [
            'empresa_query' => $empresaQuery,
            'opciones_estado_linea' => RequisicionSalaReporteFiltros::OPCIONES_ESTADO_LINEA,
            'opciones_agrupacion' => RequisicionSalaReporteFiltros::OPCIONES_AGRUPACION,
            'opciones_modo_listado' => RequisicionSalaReporteFiltros::OPCIONES_MODO_LISTADO,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => RequisicionSalaReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo_estado' => RequisicionSalaReporteFiltros::subtituloEstadoLinea(
                (string) ($filtros['estado_linea'] ?? RequisicionSalaReporteFiltros::ESTADO_TODOS),
            ),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_usuarios' => RequisicionSalaReporteCriteriosSupport::metaTextoUsuarios(
                (string) ($filtros['usuarios'] ?? ''),
            ),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_requisicion' => can('editar-requisicion-sala', false) || can('listar-requisicion-sala', false),
            'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-requisicion-sala');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RequisicionSalaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! RequisicionSalaReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_requisicion_sala');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Requisiciones de SALA';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('sala.requisicion_sala_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'requisiciones_sala_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RequisicionSalaReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('requisiciones_sala.xlsx');

            case 'CSV':
                return (new RequisicionSalaReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('requisiciones_sala.csv', Excel::CSV);
        }

        return redirect()->route('reporte_requisicion_sala', RequisicionSalaReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = RequisicionSalaReporteFiltros::defaults();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = $defaults['fecha_desde'];
        }

        if (empty($filtros['fecha_hasta']) && ! $request->has('fecha_hasta')) {
            $filtros['fecha_hasta'] = $defaults['fecha_hasta'];
        }

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $filtros;
    }
}
