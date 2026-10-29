<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaRecetasReporteHojaExport;
use App\Exports\Ventas\GastronomiaRecetasReporteWorkbookExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaRecetasReporteService;
use App\Support\Ventas\GastronomiaRecetasReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

class GastronomiaRecetasReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/recetas-reporte';

    public function __construct(
        private readonly GastronomiaRecetasReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaRecetasReporteFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));
        $opciones = $this->reporteService->opcionesFiltro();

        $consultado = $request->boolean('consultar');
        if ($consultado && ! GastronomiaRecetasReporteFiltros::tieneCriterios($filtros)) {
            return redirect()
                ->route('gastronomia_recetas_reporte', GastronomiaRecetasReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Elegí al menos un criterio: artículos activos, artículos inactivos o recetas sin asignar.']);
        }

        $resultado = null;
        $paginador = null;
        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            $resultado = $this->reporteService->generar($filtros);
            $vista = GastronomiaRecetasReporteFiltros::normalizarVista($filtros['vista'] ?? null);
            $items = match ($vista) {
                GastronomiaRecetasReporteFiltros::VISTA_LINEAS => $resultado['lineas'],
                GastronomiaRecetasReporteFiltros::VISTA_ALERTAS => $resultado['alertas'],
                GastronomiaRecetasReporteFiltros::VISTA_RESUMEN => [],
                default => $resultado['recetas'],
            };
            if ($items !== []) {
                $perPage = max(10, min(100, (int) $request->input('per_page', $vista === GastronomiaRecetasReporteFiltros::VISTA_LINEAS ? 40 : 25)));
                $paginador = $this->reporteService->paginar($items, $perPage, max(1, (int) $request->input('page', 1)));
            }
        }

        $filtrosQuery = GastronomiaRecetasReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($paginador instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $paginador->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.recetas_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'paginador' => $paginador,
            'categorias' => $opciones['categorias'],
            'subcategorias' => $opciones['subcategorias'],
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'inclusion_texto' => GastronomiaRecetasReporteFiltros::textoInclusion($filtros),
            'puede_editar_formula' => can('editar-formula-articulo', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertAccesoMenu();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaRecetasReporteFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! $request->boolean('consultar') || ! GastronomiaRecetasReporteFiltros::tieneCriterios($filtros)) {
            return redirect()
                ->route('gastronomia_recetas_reporte', GastronomiaRecetasReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consultá el reporte antes de exportar.']);
        }

        $resultado = $this->reporteService->generar($filtros);
        if (($resultado['recetas'] ?? []) === []) {
            return redirect()
                ->route('gastronomia_recetas_reporte', GastronomiaRecetasReporteFiltros::paraQueryString($filtros) + ['consultar' => 1])
                ->with('errores', ['No hay recetas para los filtros elegidos.']);
        }

        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $titulo = 'Recetas';
        $subtitulo = 'Empresa: '.$empresaTexto
            .' · '.GastronomiaRecetasReporteFiltros::textoInclusion($filtros)
            .' · Lista de precios '.$resultado['listaprecio_id'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.recetas_reporte.listado', [
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                ])->render();

                return $this->descargarPdf($view, 'recetas_gastronomia');

            case 'EXCEL':
                return (new GastronomiaRecetasReporteWorkbookExport($resultado))
                    ->download('recetas_gastronomia.xlsx');

            case 'CSV':
                return $this->csvRecetas($resultado);
        }

        return redirect()->route(
            'gastronomia_recetas_reporte',
            GastronomiaRecetasReporteFiltros::paraQueryString($filtros) + ['consultar' => 1],
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function csvRecetas(array $resultado)
    {
        $filas = [];
        foreach ($resultado['recetas'] ?? [] as $fila) {
            $filas[] = [
                $fila['codigo'],
                $fila['nombre'],
                $fila['sku'],
                $fila['estado_articulo'],
                $fila['categoria'],
                $fila['subcategoria'],
                (float) $fila['porciones'],
                (float) $fila['costo'],
                (float) $fila['precio'],
                $fila['ratio'] === null ? null : (float) $fila['ratio'],
                (int) $fila['lineas'],
                $fila['alerta'] ?? '',
                (int) $fila['cant_alertas'],
            ];
        }

        return (new GastronomiaRecetasReporteHojaExport(
            'Recetas',
            ['Código', 'Receta', 'SKU venta', 'Estado artículo', 'Categoría', 'Subcategoría', 'Porciones', 'Costo', 'Precio vta', 'Precio / costo', 'Líneas', 'Alerta', 'Cant. alertas'],
            $filas,
            ['G' => 2, 'H' => 2, 'I' => 2, 'J' => 2, 'K' => 0, 'M' => 0],
        ))->download('recetas_gastronomia.csv', Excel::CSV);
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
        if ($menuId <= 0 || ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            abort(403, 'No tiene acceso a este reporte.');
        }
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return 'Todas';
        }

        $nombre = $empresaQuery->firstWhere('id', $empresaId)?->nombre;

        return $nombre !== null && trim((string) $nombre) !== ''
            ? trim((string) $nombre)
            : (string) $empresaId;
    }

    private function descargarPdf(string $view, string $nombreBase)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
