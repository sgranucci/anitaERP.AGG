<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaDescuentoReporteColumnasExport;
use App\Exports\Ventas\GastronomiaDescuentoReporteExport;
use App\Exports\Ventas\GastronomiaDescuentoReporteMultiExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\DescuentoGastronomia;
use App\Queries\Ventas\GastronomiaDescuentoReporteQuery;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaDescuentoReporteService;
use App\Support\Ventas\GastronomiaDescuentoReporteCacheSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCodigoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCostoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

class GastronomiaDescuentoReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/descuento-reporte';

    private const PER_PAGE_BLOQUES = 1;

    private const PER_PAGE_COLUMNAS = 15;
    private const PER_PAGE_FILAS_BLOQUE = 15;

    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
        private readonly GastronomiaDescuentoReporteQuery $reporteQuery,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $consultado = $request->boolean('consultar')
            && GastronomiaDescuentoReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $advertencias = [];
        $bloquesPag = null;
        $filasColumnasPag = null;
        $vistaColumnasPag = null;
        $filasBloquePag = null;
        $bloquesVista = [];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            if ($request->boolean('refrescar_cache')) {
                GastronomiaDescuentoReporteCacheSupport::limpiar();
            }

            $desdeCache = GastronomiaDescuentoReporteCacheSupport::recuperar($filtros);
            if ($desdeCache !== null) {
                $resultado = $desdeCache;
            } else {
                $advertencias = $this->reporteService->advertencias($filtros);
                $resultado = $this->reporteService->generar($filtros);
                $resultado = $this->reporteService->enriquecerVistaColumnas($filtros, $resultado);
                GastronomiaDescuentoReporteCacheSupport::guardar($filtros, $resultado);
                $advertencias = array_merge(
                    $advertencias,
                    $this->advertenciasCosto($filtros, $resultado),
                );
            }

            if (GastronomiaDescuentoReporteFiltros::debeUsarVistaColumnas($filtros, $resultado)) {
                $filasAll = $resultado['vista_columnas']['filas'] ?? [];
                $perPage = max(10, min(100, (int) $request->input('per_page', self::PER_PAGE_COLUMNAS)));
                $maxPage = max(1, (int) ceil(max(1, count($filasAll)) / $perPage));
                $page = max(1, min($maxPage, (int) $request->input('page', 1)));
                $filasColumnasPag = $this->reporteService->paginarItems($filasAll, $perPage, $page);
                $vistaColumnasPag = $resultado['vista_columnas'];
                $vistaColumnasPag['filas'] = $filasColumnasPag->items();
            } else {
                $bloquesAll = $resultado['bloques'] ?? [];
                $totalBloques = count($bloquesAll);

                if ($totalBloques === 1) {
                    $bloque = $bloquesAll[0];
                    $filasAll = $bloque['filas'] ?? [];
                    $perPageFilas = max(10, min(100, (int) $request->input('per_page', self::PER_PAGE_FILAS_BLOQUE)));
                    $maxPageFilas = max(1, (int) ceil(max(1, count($filasAll)) / $perPageFilas));
                    $pageFilas = max(1, min($maxPageFilas, (int) $request->input('page_filas', 1)));
                    $filasBloquePag = $this->reporteService->paginarItems($filasAll, $perPageFilas, $pageFilas, 'page_filas');
                    $bloquePaginado = $bloque;
                    $bloquePaginado['filas'] = $filasBloquePag->items();
                    $bloquesVista = [$bloquePaginado];
                } elseif ($totalBloques > 1) {
                    $perPage = max(1, min(20, (int) $request->input('per_page', self::PER_PAGE_BLOQUES)));
                    $maxPage = max(1, (int) ceil($totalBloques / $perPage));
                    $page = max(1, min($maxPage, (int) $request->input('page', 1)));
                    $bloquesPag = $this->reporteService->paginarItems($bloquesAll, $perPage, $page);
                    $bloquesVista = $bloquesPag->items();
                }
            }
        }

        $filtrosQuery = GastronomiaDescuentoReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($request->has('page_filas')) {
            $filtrosQuery['page_filas'] = (int) $request->input('page_filas');
        }
        if ($bloquesPag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $bloquesPag->appends($filtrosQuery);
        }
        if ($filasColumnasPag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasColumnasPag->appends($filtrosQuery);
        }
        if ($filasBloquePag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasBloquePag->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.descuento_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'bloques_pag' => $bloquesPag,
            'bloques_vista' => $bloquesVista,
            'filas_bloque_pag' => $filasBloquePag,
            'filas_columnas_pag' => $filasColumnasPag,
            'vista_columnas_pag' => $vistaColumnasPag,
            'advertencias' => $advertencias,
            'periodo_texto' => GastronomiaDescuentoReporteFiltros::formatearPeriodoTextoLargo($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'descuentos_iniciales' => $this->descuentosInicialesDesdeFiltros($filtros),
            'clientes_iniciales' => $this->clientesInicialesDesdeFiltros($filtros),
        ]);
    }

    public function consultaFacturasBloque(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! $request->boolean('consultar')
            || ! GastronomiaDescuentoReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return response()->json(['error' => 'Consulte el reporte antes de ver las facturas.'], 422);
        }

        $clave = trim((string) $request->input('clave_bloque', ''));
        if ($clave === '') {
            return response()->json(['error' => 'Falta identificar el bloque de totales.'], 422);
        }

        $ventas = $this->reporteQuery->ventasPorClaveBloque($filtros, $clave);

        return response()->json([
            'clave' => $clave,
            'titulo' => trim((string) $request->input('titulo_bloque', '')),
            'total' => count($ventas),
            'ventas' => array_map(static function ($row) {
                return [
                    'venta_id' => (int) $row->venta_id,
                    'fechajornada' => $row->fechajornada,
                    'codigo' => $row->codigo,
                    'numerocomprobante' => (int) $row->numerocomprobante,
                    'tipo_comprobante' => $row->tipo_comprobante,
                    'total_venta' => (float) $row->total_venta,
                ];
            }, $ventas),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertAccesoMenu();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! $request->boolean('consultar')
            || ! GastronomiaDescuentoReporteFiltros::tieneCriteriosAplicados($filtros)) {
            $params = GastronomiaDescuentoReporteFiltros::paraQueryString($filtros);
            unset($params['consultar']);

            return redirect()
                ->route('gastronomia_descuento_reporte', $params)
                ->with('errores', ['Consulte el reporte y seleccione códigos, clientes internos o marque Listar todos antes de exportar.']);
        }

        $resultado = GastronomiaDescuentoReporteCacheSupport::recuperar($filtros);
        if ($resultado === null) {
            $resultado = $this->reporteService->generar($filtros);
            $resultado = $this->reporteService->enriquecerVistaColumnas($filtros, $resultado);
            GastronomiaDescuentoReporteCacheSupport::guardar($filtros, $resultado);
        }

        $tieneDatos = ($resultado['bloques'] ?? []) !== []
            || ! empty($resultado['vista_columnas']['filas'] ?? []);

        if (! $tieneDatos) {
            return redirect()
                ->route('gastronomia_descuento_reporte', GastronomiaDescuentoReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['No hay ventas con descuento en el período para los filtros aplicados. No se generó la exportación.']);
        }

        $this->solicitarActualizacionCostoSiCorresponde($filtros, $resultado);

        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $titulo = 'Reporte descuentos gastronomía';
        $subtitulo = $this->armarSubtituloExport($filtros, $resultado, $empresaTexto);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.descuento_reporte.listado', [
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                    'puede_ver_articulo' => false,
                ])->render();

                return $this->descargarPdf($view, 'descuento_reporte_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                if (GastronomiaDescuentoReporteFiltros::debeUsarVistaColumnas($filtros, $resultado)) {
                    return (new GastronomiaDescuentoReporteColumnasExport())
                        ->parametros($resultado, $titulo, $subtitulo, $empresaTexto)
                        ->download('descuento_reporte_gastronomia.xlsx');
                }

                if (! empty($filtros['excel_solapas'])
                    && empty($filtros['listar_todos'])
                    && count($resultado['bloques'] ?? []) > 1) {
                    return (new GastronomiaDescuentoReporteMultiExport($this->reporteService))
                        ->parametros($filtros, $resultado, $titulo, $subtitulo, $empresaTexto)
                        ->download('descuento_reporte_gastronomia.xlsx');
                }

                return (new GastronomiaDescuentoReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, false, $resultado)
                    ->download('descuento_reporte_gastronomia.xlsx');

            case 'CSV':
                if (GastronomiaDescuentoReporteFiltros::debeUsarVistaColumnas($filtros, $resultado)) {
                    return (new GastronomiaDescuentoReporteColumnasExport())
                        ->parametros($resultado, $titulo, $subtitulo, $empresaTexto)
                        ->download('descuento_reporte_gastronomia.csv', Excel::CSV);
                }

                return (new GastronomiaDescuentoReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, false, $resultado)
                    ->download('descuento_reporte_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_descuento_reporte',
            GastronomiaDescuentoReporteFiltros::paraQueryString($filtros),
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

        [$desde, $hasta] = GastronomiaDescuentoReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        if (trim((string) ($filtros['codigos_descuento'] ?? '')) !== '') {
            $filtros['codigos_descuento_resueltos'] = GastronomiaDescuentoReporteCodigoSupport::expandir(
                (string) $filtros['codigos_descuento'],
            );
        }

        if (trim((string) ($filtros['clientes_descuento_ids_raw'] ?? '')) !== '') {
            $filtros['clientes_descuento_ids'] = GastronomiaDescuentoReporteFiltros::parsearIdsCsv(
                (string) $filtros['clientes_descuento_ids_raw'],
            );
        }

        if (trim((string) ($filtros['codigos_descuento_cliente'] ?? '')) !== '') {
            $filtros['codigos_descuento_cliente_resueltos'] = GastronomiaDescuentoReporteCodigoSupport::expandir(
                (string) $filtros['codigos_descuento_cliente'],
            );
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    private function armarSubtituloExport(array $filtros, array $resultado, string $empresaTexto): string
    {
        $partes = [
            'Empresa: '.$empresaTexto,
            $resultado['periodo_texto'] ?? '',
            'Agrupación: '.GastronomiaDescuentoReporteFiltros::etiquetaAgrupacion($filtros),
        ];

        if (! empty($filtros['listar_todos'])) {
            $partes[] = 'Listado: todos con ventas en el período';
        } else {
            if (trim((string) ($filtros['codigos_descuento'] ?? '')) !== '') {
                $partes[] = 'Códigos: '.($filtros['codigos_descuento'] ?? '');
            }
            if (trim((string) ($filtros['clientes_descuento_ids_raw'] ?? '')) !== '') {
                $partes[] = 'Clientes internos ID: '.($filtros['clientes_descuento_ids_raw'] ?? '');
            }
        }

        if (trim((string) ($filtros['codigos_descuento_cliente'] ?? '')) !== '') {
            $partes[] = 'Filtro descuentos: '.($filtros['codigos_descuento_cliente'] ?? '');
        }

        if (! empty($filtros['presentacion_columnas'])) {
            $partes[] = 'Vista: columnas consolidadas';
        }

        return implode(' · ', array_filter($partes));
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

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function descuentosInicialesDesdeFiltros(array $filtros): array
    {
        $codigos = $filtros['codigos_descuento_resueltos'] ?? [];
        if (! is_array($codigos) || $codigos === []) {
            return [];
        }

        $encontrados = DescuentoGastronomia::query()
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $porCodigo = [];
        foreach ($encontrados as $row) {
            $porCodigo[trim((string) $row->codigo)] = [
                'id' => (int) $row->id,
                'codigo' => trim((string) $row->codigo),
                'nombre' => trim((string) $row->nombre),
            ];
        }

        $out = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '') {
                continue;
            }
            $out[] = $porCodigo[$codigo] ?? [
                'id' => 0,
                'codigo' => $codigo,
                'nombre' => '(no registrado en maestro)',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function clientesInicialesDesdeFiltros(array $filtros): array
    {
        $ids = $filtros['clientes_descuento_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $encontrados = Cliente::query()
            ->whereIn('id', $ids)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $porId = [];
        foreach ($encontrados as $row) {
            $porId[(int) $row->id] = [
                'id' => (int) $row->id,
                'codigo' => trim((string) $row->codigo),
                'nombre' => trim((string) $row->nombre),
            ];
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $out[] = $porId[$id] ?? [
                'id' => $id,
                'codigo' => (string) $id,
                'nombre' => '(no registrado en maestro)',
            ];
        }

        return $out;
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

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     * @return list<string>
     */
    private function advertenciasCosto(array $filtros, array $resultado): array
    {
        $sinCosto = GastronomiaDescuentoReporteCostoSupport::contarFilasSinCosto($resultado);
        if ($sinCosto <= 0) {
            return [];
        }

        $lista = $resultado['listas_costo']['lista_actual'] ?? '';
        $avisos = [
            "Hay {$sinCosto} filas con costo unitario en cero (lista {$lista}). "
            .'Revise precios en lista 5000+mes o ejecute el cierre mensual de costos catálogo.',
        ];

        if ($this->solicitarActualizacionCostoSiCorresponde($filtros, $resultado)) {
            $avisos[] = 'Se inició en segundo plano la actualización de costos catálogo (gastronomia:actualizar-costo-mensual-catalogo). '
                .'Vuelva a consultar en unos minutos.';
        }

        return $avisos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    private function solicitarActualizacionCostoSiCorresponde(array $filtros, array $resultado): bool
    {
        if (GastronomiaDescuentoReporteCostoSupport::contarFilasSinCosto($resultado) <= 0) {
            return false;
        }

        return GastronomiaDescuentoReporteCostoSupport::dispararActualizacionCostoEnBackground($filtros);
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
