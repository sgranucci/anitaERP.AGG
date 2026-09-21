<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\FacturacionLocalVentasArticulosReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Puntoventa;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalVentasArticulosReporteService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVentasArticulosReporteFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FacturacionLocalReporteController extends Controller
{
    private const PER_PAGE_FILAS = 50;

    public function __construct(
        private readonly FacturacionLocalVentasArticulosReporteService $reporteService,
    ) {}

    public function index(Request $request)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');

        $filtros = FacturacionLocalVentasArticulosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros);
        $filtros = $this->enriquecerPuntoventa($filtros);

        $consultado = $request->boolean('consultar')
            && FacturacionLocalVentasArticulosReporteFiltros::tieneCriteriosAplicados($filtros);

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

        $filtrosQuery = FacturacionLocalVentasArticulosReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filasPag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasPag->appends($filtrosQuery);
        }

        return view('ventas.facturacion_local.reportes.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'modos' => FacturacionLocalVentasArticulosReporteFiltros::MODOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas_pag' => $filasPag,
            'filas_vista' => $filasVista,
            'periodo_texto' => FacturacionLocalVentasArticulosReporteFiltros::formatearPeriodoTexto($filtros),
            'modo_texto' => FacturacionLocalVentasArticulosReporteFiltros::etiquetaModo($filtros),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FacturacionLocalVentasArticulosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros);
        $filtros = $this->enriquecerPuntoventa($filtros);

        if (! $request->boolean('consultar')
            || ! FacturacionLocalVentasArticulosReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()
                ->route('facturacion_local_reportes', FacturacionLocalVentasArticulosReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consulte el reporte antes de exportar.']);
        }

        $resultado = $this->reporteService->generar($filtros);
        if (($resultado['filas'] ?? []) === []) {
            return redirect()
                ->route('facturacion_local_reportes', array_merge(
                    FacturacionLocalVentasArticulosReporteFiltros::paraQueryString($filtros),
                    ['consultar' => 1],
                ))
                ->with('errores', ['No hay ventas en el período para los filtros aplicados.']);
        }

        $titulo = 'Reportes Local — Ventas por artículo';
        $subtitulo = 'Empresa: '.($resultado['nombreempresa'] ?? '')
            .' · Punto de venta: '.($resultado['puntoventa_texto'] ?? $resultado['local_texto'] ?? '')
            .' · '.($resultado['periodo_texto'] ?? '')
            .' · '.($resultado['modo_texto'] ?? '');
        if (! empty($resultado['incluir_costo']) && ($resultado['costo_formula'] ?? '') !== '') {
            $subtitulo .= ' · '.$resultado['costo_formula'];
        }

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.facturacion_local.reportes.listado', [
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_articulo' => false,
                ])->render();

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($view)->setPaper('legal', 'landscape');
                $dir = storage_path('pdf/listados');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $path = $dir.'/listado_facturacion_local_ventas_articulos.pdf';
                $pdf->save($path);

                return response()->download($path)->deleteFileAfterSend(true);

            case 'EXCEL':
                return (new FacturacionLocalVentasArticulosReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, (string) ($resultado['nombreempresa'] ?? ''))
                    ->download('facturacion_local_ventas_articulos.xlsx');

            case 'CSV':
                return (new FacturacionLocalVentasArticulosReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, (string) ($resultado['nombreempresa'] ?? ''), true)
                    ->download('facturacion_local_ventas_articulos.csv', Excel::CSV);
        }

        return redirect()->route(
            'facturacion_local_reportes',
            FacturacionLocalVentasArticulosReporteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros): array
    {
        $hoy = now()->format('Y-m-d');
        if (($filtros['fecha_desde'] ?? '') === '') {
            $filtros['fecha_desde'] = $hoy;
        }
        if (($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_hasta'] = $filtros['fecha_desde'];
        }

        return $filtros;
    }

    /**
     * Completa código/nombre del PV para el input de consulta (GET sin blur).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function enriquecerPuntoventa(array $filtros): array
    {
        $id = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($id <= 0) {
            return $filtros;
        }
        $pv = Puntoventa::query()->whereKey($id)->first(['id', 'codigo', 'nombre']);
        if (! $pv) {
            $filtros['puntoventa_id'] = 0;
            $filtros['puntoventa_codigo'] = '';
            $filtros['puntoventa_nombre'] = '';

            return $filtros;
        }
        if (($filtros['puntoventa_codigo'] ?? '') === '') {
            $filtros['puntoventa_codigo'] = (string) $pv->codigo;
        }
        if (($filtros['puntoventa_nombre'] ?? '') === '') {
            $filtros['puntoventa_nombre'] = (string) $pv->nombre;
        }

        return $filtros;
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
