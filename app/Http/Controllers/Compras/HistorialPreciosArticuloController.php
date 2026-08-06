<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\HistorialPreciosArticuloExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\HistorialPreciosArticuloService;
use App\Support\Compras\HistorialPreciosArticuloFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class HistorialPreciosArticuloController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'historial_precios_articulo_reporte';

    public function __construct(
        private HistorialPreciosArticuloService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-historial-precios-compra');

        ini_set('memory_limit', '512M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = HistorialPreciosArticuloFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && HistorialPreciosArticuloFiltros::tieneCriteriosAplicados($filtros);

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

        $filtrosQuery = HistorialPreciosArticuloFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.historial_precios_articulo.index', [
            'empresa_query' => $empresaQuery,
            'opciones_modo' => HistorialPreciosArticuloFiltros::OPCIONES_MODO,
            'opciones_agrupacion' => HistorialPreciosArticuloFiltros::OPCIONES_AGRUPACION,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => HistorialPreciosArticuloFiltros::formatearPeriodoTexto($filtros),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_articulo' => $this->metaArticulo($filtros),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores(
                (string) ($filtros['proveedores'] ?? ''),
            ),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_recepcion' => can('editar-recepcion-proveedor', false) || can('listar-recepcion-proveedor', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-historial-precios-compra');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = HistorialPreciosArticuloFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! HistorialPreciosArticuloFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_historial_precios_articulo');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Historial de precios por artículo/proveedor';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $modo = (string) ($filtros['modo'] ?? HistorialPreciosArticuloFiltros::MODO_RESUMEN);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.historial_precios_articulo.listado', compact(
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
                $nombrePdf = 'historial_precios_articulo_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new HistorialPreciosArticuloExport($filas, $titulo, $subtitulo, $totales, $modo))
                    ->download('historial_precios_articulo.xlsx');

            case 'CSV':
                return (new HistorialPreciosArticuloExport($filas, $titulo, $subtitulo, $totales, $modo))
                    ->download('historial_precios_articulo.csv', Excel::CSV);
        }

        return redirect()->route(
            'reporte_historial_precios_articulo',
            HistorialPreciosArticuloFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = HistorialPreciosArticuloFiltros::defaults();
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

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function metaArticulo(array $filtros): string
    {
        $articuloId = (int) ($filtros['articulo_id'] ?? 0);
        if ($articuloId <= 0) {
            return ($filtros['sku'] ?? '') !== ''
                ? 'Filtro SKU: '.$filtros['sku']
                : 'Todos los artículos';
        }

        $articulo = Articulo::query()->select('id', 'sku', 'descripcion')->find($articuloId);
        if (! $articulo) {
            return 'Artículo ID '.$articuloId;
        }

        return trim(($articulo->sku ?? '').' — '.($articulo->descripcion ?? ''));
    }
}
