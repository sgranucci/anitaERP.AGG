<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\RequisicionReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\RequisicionReporteService;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Compras\RequisicionReporteFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class RequisicionReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'requisicion_compras_reporte';

    public function __construct(
        private RequisicionReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-requisicion-compras');

        ini_set('memory_limit', '512M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RequisicionReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && RequisicionReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
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

        $filtrosQuery = RequisicionReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.requisicion_reporte.index', [
            'empresa_query' => $empresaQuery,
            'opciones_estado' => RequisicionReporteFiltros::OPCIONES_ESTADO,
            'opciones_agrupacion' => RequisicionReporteFiltros::OPCIONES_AGRUPACION,
            'opciones_modo_listado' => RequisicionReporteFiltros::OPCIONES_MODO_LISTADO,
            'opciones_urgente' => RequisicionReporteFiltros::OPCIONES_URGENTE,
            'opciones_contratacion' => RequisicionReporteFiltros::OPCIONES_CONTRATACION,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => RequisicionReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo_estado' => RequisicionReporteFiltros::subtituloEstado(
                (string) ($filtros['estado_requisicion'] ?? RequisicionReporteFiltros::ESTADO_EN_COMPRAS),
            ),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_usuarios' => RequisicionReporteCriteriosSupport::metaTextoUsuarios(
                (string) ($filtros['usuarios'] ?? ''),
            ),
            'meta_centrocostos' => RequisicionReporteCriteriosSupport::metaTextoCentrocostosCodigo(
                (string) ($filtros['centrocostos_codigo'] ?? ''),
            ),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_requisicion' => can('editar-requisicion', false) || can('listar-requisicion', false),
            'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-requisicion-compras');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RequisicionReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! RequisicionReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_requisicion_compras');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Requisiciones de compra';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado, $empresaQuery);
                }

                $view = \View::make('compras.requisicion_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'requisiciones_compras_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RequisicionReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('requisiciones_compras.xlsx');

            case 'CSV':
                return (new RequisicionReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('requisiciones_compras.csv', Excel::CSV);
        }

        return redirect()->route('reporte_requisicion_compras', RequisicionReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoCompleto
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    private function descargarPdfPorEmpresa(array $filtros, array $resultadoCompleto, $empresaQuery)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];
        $titulo = 'Requisiciones de compra';

        try {
            foreach ($resultadoCompleto['secciones'] ?? [] as $seccion) {
                $empresaId = (int) ($seccion['empresa_id'] ?? 0);
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'consolidar_empresas' => true,
                ]);
                $filas = $seccion['filas'] ?? [];
                $totales = $seccion['totales'] ?? [];
                $subtitulo = ((string) ($seccion['empresa_nombre'] ?? ''))
                    .' · '.$this->service->subtituloFiltros($filtrosEmpresa, $empresaQuery);

                $view = \View::make('compras.requisicion_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();

                $temp = $dir.'/req_compras_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $nombreBase = 'requisiciones_compras_'.date('Ymd_His');
            $destino = $dir.'/'.$nombreBase.'.pdf';

            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
                $temporales = [];
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino, $nombreBase.'.pdf')->deleteFileAfterSend(true);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = RequisicionReporteFiltros::defaults();
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

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        return $filtros;
    }
}
