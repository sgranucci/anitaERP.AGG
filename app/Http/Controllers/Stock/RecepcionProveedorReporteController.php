<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecepcionProveedorReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\RecepcionProveedorReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Stock\RecepcionProveedorReporteCacheSupport;
use App\Support\Stock\RecepcionProveedorReporteFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class RecepcionProveedorReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'recepcion_proveedor_reporte';

    private const PER_PAGE = 25;

    public function __construct(
        private RecepcionProveedorReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-recepcion-proveedor');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RecepcionProveedorReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && RecepcionProveedorReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;

        if ($consultado) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '300');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => RecepcionProveedorReporteFiltros::empresaIds($filtros),
                'consolidar_empresas' => ! empty($filtros['consolidar_empresas']),
            ]);

            if ($request->boolean('refrescar_cache')) {
                RecepcionProveedorReporteCacheSupport::limpiar($filtros);
            }

            $resultado = $this->obtenerResultado($filtros);
            $perPage = max(10, min(200, (int) $request->input('per_page', self::PER_PAGE)));
            $page = max(1, (int) $request->input('page', 1));
            $filas = $this->service->paginarFilas($resultado['filas'] ?? [], $perPage, $page);
        }

        $filtrosQuery = RecepcionProveedorReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('stock.recepcion_proveedor_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'periodo_texto' => RecepcionProveedorReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'opciones_modo' => RecepcionProveedorReporteFiltros::OPCIONES_MODO,
            'opciones_orden' => RecepcionProveedorReporteFiltros::OPCIONES_ORDEN,
            'opciones_facturacion' => RecepcionProveedorReporteFiltros::OPCIONES_FACTURACION,
            'opciones_tipo' => RecepcionProveedorReporteFiltros::OPCIONES_TIPO,
            'opciones_estado' => RecepcionProveedorReporteFiltros::OPCIONES_ESTADO,
            'puede_ver_recepcion' => can('editar-recepcion-proveedor', false) || can('listar-recepcion-proveedor', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_requisicion' => can('editar-requisicion', false) || can('listar-requisicion', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_cuentacontable' => can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-recepcion-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RecepcionProveedorReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! RecepcionProveedorReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_recepcion_proveedor');
        }

        $resultado = $this->obtenerResultado($filtros);
        $titulo = 'Recepción de proveedores';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'] ?? collect();
        $totales = $resultado['totales'] ?? [];
        $kpis = $resultado['kpis'] ?? [];
        $modo = (string) ($filtros['modo'] ?? RecepcionProveedorReporteFiltros::MODO_DETALLE);
        $advertencia = trim(implode(' ', array_filter([
            $resultado['advertencia_cotizacion'] ?? null,
            $resultado['advertencia_anita'] ?? null,
        ]))) ?: null;

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('stock.recepcion_proveedor_reporte.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'kpis' => $kpis,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'modo' => $modo,
                    'advertencia_cotizacion' => $advertencia,
                    'puede_ver_recepcion' => false,
                    'puede_ver_articulo' => false,
                    'puede_ver_ordencompra' => false,
                    'puede_ver_requisicion' => false,
                    'puede_ver_proveedor' => false,
                    'puede_ver_cuentacontable' => false,
                    'puede_ver_comprobante' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'recepcion_proveedor_reporte_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RecepcionProveedorReporteExport(
                    $filas,
                    $titulo,
                    $subtitulo,
                    $totales,
                    $kpis,
                    $modo,
                    $advertencia,
                ))->download('recepcion_proveedor_reporte.xlsx');

            case 'CSV':
                return (new RecepcionProveedorReporteExport(
                    $filas,
                    $titulo,
                    $subtitulo,
                    $totales,
                    $kpis,
                    $modo,
                    $advertencia,
                ))->download('recepcion_proveedor_reporte.csv', Excel::CSV);
        }

        return redirect()->route(
            'reporte_recepcion_proveedor',
            RecepcionProveedorReporteFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function obtenerResultado(array $filtros): array
    {
        $cached = RecepcionProveedorReporteCacheSupport::recuperar($filtros);
        if ($cached !== null) {
            return $cached;
        }

        $resultado = $this->service->generar($filtros);
        RecepcionProveedorReporteCacheSupport::guardar($filtros, $resultado);

        return RecepcionProveedorReporteCacheSupport::recuperar($filtros) ?? $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = RecepcionProveedorReporteFiltros::defaults();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = $defaults['fecha_desde'];
        }
        if (empty($filtros['fecha_hasta']) && ! $request->has('fecha_hasta')) {
            $filtros['fecha_hasta'] = $defaults['fecha_hasta'];
        }

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        if (RecepcionProveedorReporteFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (RecepcionProveedorReporteFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $filtros;
    }
}
