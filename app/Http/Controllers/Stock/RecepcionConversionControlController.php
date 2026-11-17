<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecepcionConversionControlExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\RecepcionConversionControlService;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Stock\RecepcionConversionControlFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class RecepcionConversionControlController extends Controller
{
    private const PREFERENCIAS_CLAVE = RecepcionConversionControlFiltros::PREFERENCIAS_CLAVE;

    private const PER_PAGE = 40;

    private const MAX_FILAS_PDF = 3000;

    public function __construct(
        private RecepcionConversionControlService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-control-conversion-recepcion');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RecepcionConversionControlFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && RecepcionConversionControlFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '300');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => RecepcionConversionControlFiltros::empresaIds($filtros),
                'consolidar_empresas' => ! empty($filtros['consolidar_empresas']),
            ]);

            $resultado = $this->service->generar($filtros, $empresaQuery);
            $perPage = max(10, min(200, (int) $request->input('per_page', self::PER_PAGE)));
            $page = max(1, (int) $request->input('page', 1));
            $filas = $this->service->paginarFilas($resultado['filas'] ?? [], $perPage, $page);
        }

        $filtrosQuery = RecepcionConversionControlFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('stock.recepcion_conversion_control.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'periodo_texto' => RecepcionConversionControlFiltros::formatearPeriodoTexto($filtros),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'puede_ver_recepcion' => can('editar-recepcion-proveedor', false) || can('listar-recepcion-proveedor', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-control-conversion-recepcion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RecepcionConversionControlFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! RecepcionConversionControlFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_control_conversion_recepcion');
        }

        $resultado = $this->service->generar($filtros, $empresaQuery);
        $titulo = 'Control conversión recepción';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'] ?? [];
        $totales = $resultado['totales'] ?? [];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $cantidadFilas = count(array_filter(
                    $filas,
                    static fn ($f) => ($f['tipo_fila'] ?? 'dato') === 'dato'
                ));
                if ($cantidadFilas > self::MAX_FILAS_PDF) {
                    return redirect()
                        ->route('reporte_control_conversion_recepcion', array_merge(
                            RecepcionConversionControlFiltros::paraQueryString($filtros),
                            ['consultar' => 1]
                        ))
                        ->with(
                            'mensaje-aviso',
                            'El PDF no admite '.$cantidadFilas.' filas (máximo '.self::MAX_FILAS_PDF
                            .'). Bajá el período en Excel o CSV.'
                        );
                }

                $html = view('stock.recepcion_conversion_control.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_recepcion' => false,
                    'puede_ver_articulo' => false,
                    'puede_ver_proveedor' => false,
                ])->render();

                $ruta = storage_path('pdf/listados/control_conversion_recepcion.pdf');
                if (! is_dir(dirname($ruta))) {
                    mkdir(dirname($ruta), 0775, true);
                }
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Control conversión recepción',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new RecepcionConversionControlExport($filas, $titulo, $subtitulo, $totales))
                    ->download('control_conversion_recepcion.xlsx');

            case 'CSV':
                return (new RecepcionConversionControlExport($filas, $titulo, $subtitulo, $totales))
                    ->download('control_conversion_recepcion.csv', Excel::CSV);
        }

        return redirect()->route(
            'reporte_control_conversion_recepcion',
            array_merge(RecepcionConversionControlFiltros::paraQueryString($filtros), ['consultar' => 1])
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = RecepcionConversionControlFiltros::defaults();
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

        if (! $request->has('solo_con_insumo') && ! $request->boolean('consultar')) {
            $filtros['solo_con_insumo'] = true;
        }

        if (RecepcionConversionControlFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (RecepcionConversionControlFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $filtros;
    }
}
