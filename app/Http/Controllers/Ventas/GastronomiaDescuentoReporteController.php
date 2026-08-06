<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaDescuentoReporteColumnasExport;
use App\Exports\Ventas\GastronomiaDescuentoReporteExport;
use App\Exports\Ventas\GastronomiaDescuentoReporteMultiExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ClienteVipGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Queries\Ventas\GastronomiaDescuentoReporteQuery;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\ClienteVipGastronomiaRepositoryInterface;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Services\Ventas\GastronomiaDescuentoReporteService;
use App\Support\Ventas\GastronomiaDescuentoReporteClienteSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCacheSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCodigoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCostoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use App\Support\Ventas\GastronomiaDescuentoReporteMozoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteVipSupport;
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
        private readonly MozoGastronomiaRepositoryInterface $mozoRepository,
        private readonly ClienteVipGastronomiaRepositoryInterface $clienteVipRepository,
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
        $clientesRangoCodigosFaltantes = [];
        $mozosRangoCodigosFaltantes = [];
        $vipsRangoCodigosFaltantes = [];
        $bloquesPag = null;
        $filasColumnasPag = null;
        $vistaColumnasPag = null;
        $filasBloquePag = null;
        $bloquesVista = [];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            $clientesRangoCodigosFaltantes = $this->reporteService->codigosClienteRangoSinRegistro($filtros);
            if (GastronomiaDescuentoReporteFiltros::esModoMozo($filtros)) {
                $mozosRangoCodigosFaltantes = $this->reporteService->codigosMozoRangoSinRegistro($filtros);
            }
            if (GastronomiaDescuentoReporteFiltros::esModoVip($filtros)) {
                $vipsRangoCodigosFaltantes = $this->reporteService->codigosVipRangoSinRegistro($filtros);
            }

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
                $agrupadoPagina = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas(
                    array_values($filasColumnasPag->items()),
                );
                $vistaColumnasPag['filas'] = $agrupadoPagina['filas'];
                $vistaColumnasPag['grupos'] = $agrupadoPagina['grupos'];
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
                    $agrupadoPagina = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas(
                        array_values($filasBloquePag->items()),
                    );
                    $bloquePaginado['filas'] = $agrupadoPagina['filas'];
                    $bloquePaginado['grupos'] = $agrupadoPagina['grupos'];
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
            'clientes_rango_codigos_faltantes' => $clientesRangoCodigosFaltantes,
            'mozos_rango_codigos_faltantes' => $mozosRangoCodigosFaltantes,
            'vips_rango_codigos_faltantes' => $vipsRangoCodigosFaltantes,
            'periodo_texto' => GastronomiaDescuentoReporteFiltros::formatearPeriodoTextoLargo($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'descuentos_iniciales' => $this->descuentosInicialesDesdeFiltros($filtros),
            'clientes_iniciales' => $this->clientesInicialesDesdeFiltros($filtros),
            'mozos_iniciales' => $this->mozosInicialesDesdeFiltros($filtros),
            'vips_iniciales' => $this->vipsInicialesDesdeFiltros($filtros),
        ]);
    }

    public function consultaMozo(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        return $this->mozoRepository->consultaMozo(
            (string) ($request->get('consulta') ?? ''),
            $empresaId,
            false,
        );
    }

    public function leerMozoPorCodigo(Request $request, string $codigo)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        if ($empresaId <= 0) {
            return response()->json(['error' => 'Seleccione empresa antes de consultar mozos.'], 422);
        }

        $mozo = $this->mozoRepository->findPorCodigo($codigo, $empresaId, false);
        if (! $mozo) {
            return response()->json(['error' => 'Mozo no encontrado'], 404);
        }

        return response()->json([
            'id' => $mozo->id,
            'codigo' => $mozo->codigo,
            'nombre' => $mozo->nombre,
        ]);
    }

    public function consultaClienteVip(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        if ($empresaId <= 0) {
            return response('{"data":"<tr><td colspan=\"8\" class=\"text-center text-muted\">Seleccione empresa antes de consultar clientes VIP.</td></tr>"}')
                ->header('Content-Type', 'application/json');
        }

        return $this->clienteVipRepository->consultaClienteVipReporte(
            (string) ($request->get('consulta') ?? ''),
            $empresaId,
        );
    }

    public function leerClienteVipPorCodigo(Request $request, string $codigo)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaDescuentoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        if ($empresaId <= 0) {
            return response()->json(['error' => 'Seleccione empresa antes de consultar clientes VIP.'], 422);
        }

        $codigo = trim($codigo);
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return response()->json(['error' => 'Cliente VIP no encontrado'], 404);
        }

        $vip = $this->clienteVipRepository->findPorNumeroid($empresaId, (int) $codigo);
        if (! $vip) {
            return response()->json(['error' => 'Cliente VIP no encontrado'], 404);
        }

        return response()->json([
            'id' => (int) $vip->id,
            'codigo' => (string) $vip->numeroid,
            'nombre' => $vip->nombreCompleto(),
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
                ->with('errores', ['Consulte el reporte y seleccione códigos, clientes internos, mozos, clientes VIP o marque Listar todos antes de exportar.']);
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
                        ->parametros($resultado, $titulo, $subtitulo, $empresaTexto, true)
                        ->download('descuento_reporte_gastronomia.csv', Excel::CSV);
                }

                return (new GastronomiaDescuentoReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, false, $resultado, true)
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
            $explicitos = GastronomiaDescuentoReporteFiltros::parsearIdsCsv(
                (string) $filtros['clientes_descuento_ids_raw'],
            );
            $filtros['clientes_descuento_ids_explicitos'] = $explicitos;
            $filtros['clientes_descuento_ids'] = GastronomiaDescuentoReporteClienteSupport::fusionarSeleccion(
                $explicitos,
                (string) ($filtros['cliente_codigo_desde'] ?? ''),
                (string) ($filtros['cliente_codigo_hasta'] ?? ''),
            );
        }

        if (trim((string) ($filtros['codigos_descuento_cliente'] ?? '')) !== '') {
            $filtros['codigos_descuento_cliente_resueltos'] = GastronomiaDescuentoReporteCodigoSupport::expandir(
                (string) $filtros['codigos_descuento_cliente'],
            );
        }

        if (trim((string) ($filtros['mozos_descuento_ids_raw'] ?? '')) !== ''
            || GastronomiaDescuentoReporteMozoSupport::tieneRangoCodigo(
                (string) ($filtros['mozo_codigo_desde'] ?? ''),
                (string) ($filtros['mozo_codigo_hasta'] ?? ''),
            )) {
            $explicitos = GastronomiaDescuentoReporteFiltros::parsearIdsCsv(
                (string) ($filtros['mozos_descuento_ids_raw'] ?? ''),
            );
            $filtros['mozos_descuento_ids_explicitos'] = $explicitos;
            $filtros['mozos_descuento_ids'] = GastronomiaDescuentoReporteMozoSupport::fusionarSeleccion(
                $explicitos,
                (string) ($filtros['mozo_codigo_desde'] ?? ''),
                (string) ($filtros['mozo_codigo_hasta'] ?? ''),
                (int) ($filtros['empresa_id'] ?? 0),
            );
        }

        if (trim((string) ($filtros['vips_descuento_ids_raw'] ?? '')) !== ''
            || GastronomiaDescuentoReporteVipSupport::tieneRangoCodigo(
                (string) ($filtros['vip_codigo_desde'] ?? ''),
                (string) ($filtros['vip_codigo_hasta'] ?? ''),
            )) {
            $explicitos = GastronomiaDescuentoReporteFiltros::parsearIdsCsv(
                (string) ($filtros['vips_descuento_ids_raw'] ?? ''),
            );
            $filtros['vips_descuento_ids_explicitos'] = $explicitos;
            $filtros['vips_descuento_ids'] = GastronomiaDescuentoReporteVipSupport::fusionarSeleccion(
                $explicitos,
                (string) ($filtros['vip_codigo_desde'] ?? ''),
                (string) ($filtros['vip_codigo_hasta'] ?? ''),
                (int) ($filtros['empresa_id'] ?? 0),
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

        $etiquetaRango = GastronomiaDescuentoReporteClienteSupport::etiquetaRangoCodigo(
            (string) ($filtros['cliente_codigo_desde'] ?? ''),
            (string) ($filtros['cliente_codigo_hasta'] ?? ''),
        );
        if ($etiquetaRango !== '') {
            $partes[] = 'Rango cód. cliente: '.$etiquetaRango;
        }

        $etiquetaRangoMozo = GastronomiaDescuentoReporteMozoSupport::etiquetaRangoCodigo(
            (string) ($filtros['mozo_codigo_desde'] ?? ''),
            (string) ($filtros['mozo_codigo_hasta'] ?? ''),
        );
        if ($etiquetaRangoMozo !== '') {
            $partes[] = 'Rango cód. mozo: '.$etiquetaRangoMozo;
        }

        if (trim((string) ($filtros['mozos_descuento_ids_raw'] ?? '')) !== '') {
            $partes[] = 'Mozos ID: '.($filtros['mozos_descuento_ids_raw'] ?? '');
        }

        $etiquetaRangoVip = GastronomiaDescuentoReporteVipSupport::etiquetaRangoCodigo(
            (string) ($filtros['vip_codigo_desde'] ?? ''),
            (string) ($filtros['vip_codigo_hasta'] ?? ''),
        );
        if ($etiquetaRangoVip !== '') {
            $partes[] = 'Rango cód. VIP: '.$etiquetaRangoVip;
        }

        if (trim((string) ($filtros['vips_descuento_ids_raw'] ?? '')) !== '') {
            $partes[] = 'Clientes VIP ID: '.($filtros['vips_descuento_ids_raw'] ?? '');
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
        $ids = $filtros['clientes_descuento_ids_explicitos'] ?? $filtros['clientes_descuento_ids'] ?? [];
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

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function mozosInicialesDesdeFiltros(array $filtros): array
    {
        $ids = $filtros['mozos_descuento_ids_explicitos'] ?? $filtros['mozos_descuento_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $query = MozoGastronomia::query()->whereIn('id', $ids);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $encontrados = $query->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

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

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function vipsInicialesDesdeFiltros(array $filtros): array
    {
        $ids = $filtros['vips_descuento_ids_explicitos'] ?? $filtros['vips_descuento_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $query = ClienteVipGastronomia::query()->whereIn('id', $ids);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $encontrados = $query->orderBy('numeroid')->get(['id', 'numeroid', 'apellido', 'nombre']);

        $porId = [];
        foreach ($encontrados as $row) {
            $porId[(int) $row->id] = [
                'id' => (int) $row->id,
                'codigo' => (string) $row->numeroid,
                'nombre' => trim(trim((string) $row->apellido).' '.trim((string) $row->nombre)),
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
