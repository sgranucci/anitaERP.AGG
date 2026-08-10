<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ArticuloCuentaOcReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ArticuloCuentaOcReporteService;
use App\Support\Compras\ArticuloCuentaOcReporteFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ArticuloCuentaOcReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'articulo_cuenta_oc_reporte';

    public function __construct(
        private ArticuloCuentaOcReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-articulo-cuenta-oc');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ArticuloCuentaOcReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && ArticuloCuentaOcReporteFiltros::tieneCriteriosAplicados($filtros);

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

        $filtrosQuery = ArticuloCuentaOcReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.articulo_cuenta_oc_reporte.index', [
            'empresa_query' => $empresaQuery,
            'opciones_modo' => ArticuloCuentaOcReporteFiltros::OPCIONES_MODO,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => ArticuloCuentaOcReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores(
                (string) ($filtros['proveedores'] ?? ''),
            ),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_cuentacontable' => can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-articulo-cuenta-oc');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ArticuloCuentaOcReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! ArticuloCuentaOcReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_articulo_cuenta_oc');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Cuentas contables de artículos vs OC (Anita)';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $modo = (string) ($filtros['modo'] ?? ArticuloCuentaOcReporteFiltros::MODO_RESUMEN);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.articulo_cuenta_oc_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                    'modo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'articulo_cuenta_oc_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ArticuloCuentaOcReporteExport($filas, $titulo, $subtitulo, $totales, $modo))
                    ->download('articulo_cuenta_oc.xlsx');

            case 'CSV':
                return (new ArticuloCuentaOcReporteExport($filas, $titulo, $subtitulo, $totales, $modo))
                    ->download('articulo_cuenta_oc.csv', Excel::CSV);
        }

        return redirect()->route('reporte_articulo_cuenta_oc', ArticuloCuentaOcReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = ArticuloCuentaOcReporteFiltros::defaults();
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
