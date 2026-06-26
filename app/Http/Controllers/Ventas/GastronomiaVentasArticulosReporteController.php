<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaVentasArticulosReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaVentasArticulosReporteService;
use App\Support\Ventas\GastronomiaVentasArticulosReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

class GastronomiaVentasArticulosReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/ventas-articulos-reporte';

    private const PER_PAGE_FILAS = 15;

    public function __construct(
        private readonly GastronomiaVentasArticulosReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaVentasArticulosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $consultado = $request->boolean('consultar')
            && GastronomiaVentasArticulosReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filasPag = null;
        $filasVista = [];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(100, (int) $request->input('per_page', self::PER_PAGE_FILAS)));
            $filasPag = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filasPag->items();
        }

        $filtrosQuery = GastronomiaVentasArticulosReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filasPag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasPag->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.ventas_articulos_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas_pag' => $filasPag,
            'filas_vista' => $filasVista,
            'periodo_texto' => GastronomiaVentasArticulosReporteFiltros::formatearPeriodoTexto($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertAccesoMenu();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaVentasArticulosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! $request->boolean('consultar')
            || ! GastronomiaVentasArticulosReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()
                ->route('gastronomia_ventas_articulos_reporte', GastronomiaVentasArticulosReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consulte el reporte antes de exportar.']);
        }

        $resultado = $this->reporteService->generar($filtros);

        if (($resultado['filas'] ?? []) === []) {
            return redirect()
                ->route('gastronomia_ventas_articulos_reporte', GastronomiaVentasArticulosReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['No hay ventas en el período para los filtros aplicados.']);
        }

        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $titulo = 'Ventas de artículos';
        $subtitulo = 'Empresa: '.$empresaTexto
            .' · '.$resultado['periodo_texto']
            .' · P.Vta. lista '.$resultado['listaprecio_venta_codigo']
            .' · Costo lista '.($resultado['listas_costo']['lista_actual'] ?? '');

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.ventas_articulos_reporte.listado', [
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                    'puede_ver_articulo' => false,
                ])->render();

                return $this->descargarPdf($view, 'ventas_articulos_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new GastronomiaVentasArticulosReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('ventas_articulos_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaVentasArticulosReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('ventas_articulos_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_ventas_articulos_reporte',
            GastronomiaVentasArticulosReporteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        if (($filtros['fecha_desde'] ?? '') === '' && ($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }

        [$desde, $hasta] = GastronomiaVentasArticulosReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function assertAccesoMenu(): void
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        $rolId = (int) session()->get('rol_id');
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            abort(403, 'Reporte no disponible.');
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            abort(403, 'No tiene acceso a este reporte.');
        }
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return '—';
        }

        $nombre = $empresaQuery->firstWhere('id', $empresaId)?->nombre;

        return $nombre !== null && trim((string) $nombre) !== ''
            ? trim((string) $nombre)
            : (string) $empresaId;
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
