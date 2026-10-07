<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\FacturacionLocalCostosLocalReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Mventa;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalCostosLocalReporteService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalCostosLocalReporteFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FacturacionLocalCostosLocalReporteController extends Controller
{
    private const PER_PAGE_FILAS = 50;

    public function __construct(
        private readonly FacturacionLocalCostosLocalReporteService $reporteService,
    ) {}

    public function index(Request $request)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');

        $filtros = FacturacionLocalCostosLocalReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->enriquecerMarca($filtros);

        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPag = null;
        $filasVista = [];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(200, (int) $request->input('per_page', self::PER_PAGE_FILAS)));
            $filasPag = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filasPag->items();
        }

        $filtrosQuery = FacturacionLocalCostosLocalReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filasPag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasPag->appends($filtrosQuery);
        }

        return view('ventas.facturacion_local.reporte_costos.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas_pag' => $filasPag,
            'filas_vista' => $filasVista,
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FacturacionLocalCostosLocalReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->enriquecerMarca($filtros);

        if (! $request->boolean('consultar')) {
            return redirect()
                ->route('facturacion_local_reporte_costos', FacturacionLocalCostosLocalReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consulte el reporte antes de exportar.']);
        }

        $resultado = $this->reporteService->generar($filtros);
        if (($resultado['filas'] ?? []) === []) {
            return redirect()
                ->route('facturacion_local_reporte_costos', array_merge(
                    FacturacionLocalCostosLocalReporteFiltros::paraQueryString($filtros),
                    ['consultar' => 1],
                ))
                ->with('errores', ['No hay artículos para los filtros aplicados.']);
        }

        $titulo = 'Reporte de costos del local';
        $subtitulo = ($resultado['texto_filtros'] ?? '')
            .' · Precios vigentes al '.date('d/m/Y', strtotime((string) ($resultado['fecha_vigencia'] ?? 'now')))
            .' · '.($resultado['costo_formula'] ?? '');
        if ((int) ($resultado['sin_precio'] ?? 0) > 0) {
            $subtitulo .= ' · '.(int) $resultado['sin_precio'].' SKU con precio de fábrica en 0';
        }

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.facturacion_local.reporte_costos.listado', [
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_articulo' => false,
                ])->render();

                $dir = storage_path('pdf/listados');
                $path = $dir.'/listado_facturacion_local_costos.pdf';
                DompdfListadoSupport::guardarLegalLandscape($view, $path, [
                    'titulo_corto' => 'Costos del local',
                ]);

                return response()->download($path)->deleteFileAfterSend(true);

            case 'EXCEL':
                return (new FacturacionLocalCostosLocalReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('facturacion_local_costos.xlsx');

            case 'CSV':
                return (new FacturacionLocalCostosLocalReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, true)
                    ->download('facturacion_local_costos.csv', Excel::CSV);
        }

        return redirect()->route(
            'facturacion_local_reporte_costos',
            FacturacionLocalCostosLocalReporteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function enriquecerMarca(array $filtros): array
    {
        $id = (int) ($filtros['mventa_id'] ?? 0);
        if ($id <= 0) {
            $filtros['mventa_id'] = 0;
            $filtros['mventa_codigo'] = '';
            $filtros['mventa_nombre'] = '';

            return $filtros;
        }

        $marca = Mventa::query()->whereKey($id)->first(['id', 'codigo', 'nombre']);
        if (! $marca) {
            $filtros['mventa_id'] = 0;
            $filtros['mventa_codigo'] = '';
            $filtros['mventa_nombre'] = '';

            return $filtros;
        }
        $filtros['mventa_codigo'] = (string) $marca->codigo;
        $filtros['mventa_nombre'] = (string) $marca->nombre;

        return $filtros;
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
