<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\OrdencompraReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraReporteService;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\OrdencompraReporteFiltros;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class OrdencompraReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'ordencompra_compras_reporte';

    public function __construct(
        private OrdencompraReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-ordencompra');

        ini_set('memory_limit', '512M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = OrdencompraReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && OrdencompraReporteFiltros::tieneCriteriosAplicados($filtros);

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

        $filtrosQuery = OrdencompraReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.ordencompra_reporte.index', [
            'empresa_query' => $empresaQuery,
            'opciones_estado' => OrdencompraReporteFiltros::OPCIONES_ESTADO,
            'opciones_pendiente' => OrdencompraReporteFiltros::OPCIONES_PENDIENTE,
            'opciones_anticipada' => OrdencompraReporteFiltros::OPCIONES_ANTICIPADA,
            'opciones_agrupacion' => OrdencompraReporteFiltros::OPCIONES_AGRUPACION,
            'opciones_modo_listado' => OrdencompraReporteFiltros::OPCIONES_MODO_LISTADO,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => OrdencompraReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo_estado' => OrdencompraReporteFiltros::subtituloEstado(
                (string) ($filtros['estado_oc'] ?? OrdencompraReporteFiltros::ESTADO_ACTIVOS),
            ),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_usuarios' => RequisicionReporteCriteriosSupport::metaTextoUsuarios(
                (string) ($filtros['usuarios'] ?? ''),
            ),
            'meta_centrocostos' => RequisicionReporteCriteriosSupport::metaTextoCentrocostosCodigo(
                (string) ($filtros['centrocostos_codigo'] ?? ''),
            ),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores(
                (string) ($filtros['proveedores'] ?? ''),
            ),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_requisicion' => can('editar-requisicion', false) || can('listar-requisicion', false),
            'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_capex' => can('editar-capex', false) || can('listar-capex', false),
            'puede_ver_recepcion' => can('editar-recepcion-proveedor', false) || can('listar-recepcion-proveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-ordencompra');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = OrdencompraReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! OrdencompraReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_ordencompra');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Pedidos a proveedores (órdenes de compra)';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.ordencompra_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'ordenes_compra_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new OrdencompraReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('ordenes_compra.xlsx');

            case 'CSV':
                return (new OrdencompraReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('ordenes_compra.csv', Excel::CSV);
        }

        return redirect()->route('reporte_ordencompra', OrdencompraReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = OrdencompraReporteFiltros::defaults();
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
