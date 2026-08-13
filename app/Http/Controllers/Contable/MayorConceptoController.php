<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MayorConceptoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use App\Support\Contable\MayorConceptoExcelFormatoNumero;
use App\Support\Contable\MayorConceptoListadoFiltros;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class MayorConceptoController extends Controller
{
    private const SESSION_CACHE_KEY = 'mayor_concepto_resultado_cache';

    private const PREFERENCIAS_CLAVE = 'mayor_concepto';

    public function __construct(
        private readonly MayorConceptoReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-asiento');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();

        $filtros = MayorConceptoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaultsEmpresas($request, $filtros, $empresaQuery);

        $this->assertAccesoEmpresas(MayorConceptoListadoFiltros::empresaIds($filtros));

        $consultado = false;
        $filas = null;
        $resultado = null;
        $resumen = [];
        $resumenPorCuenta = [];
        $auditoriaPanel = null;
        $totales = null;
        $erroresBridge = [];
        $filasBase = null;

        if (MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $pack = $this->leerCachePack($filtros);

            // GET solo lee cache. El cálculo pesado va por POST @consultar (evita timeout/404 del proxy).
            if ($pack === null && $request->boolean('consultar') && $request->boolean('recalcular')) {
                MayorConceptoRuntimeSupport::elevarLimites();
                $pack = $this->generarYCachearPack($filtros);
            }

            if ($pack !== null) {
                $consultado = true;
                $resultado = $pack['resultado'];
                $totales = $pack['totales'];
                $resumen = $pack['resumen'];
                $resumenPorCuenta = $pack['resumen_por_cuenta'];
                $auditoriaPanel = $pack['auditoria_panel'];
                $erroresBridge = $resultado['errores_bridge'] ?? [];
                $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
                $filasBase = $this->reporteService->aplanarFilasConTotales($resultado);
                if (MayorConceptoListadoFiltros::tieneFiltroDetalle($filtros)) {
                    $filasBase = MayorConceptoListadoFiltros::aplicarFiltroDetalle($filasBase, $filtros);
                }
                $filas = $this->paginarFilas($filasBase, $perPage, $request);
            }
        }

        $totalesVisibles = null;
        if ($consultado && MayorConceptoListadoFiltros::tieneFiltroDetalle($filtros) && is_array($filasBase)) {
            $totalesVisibles = MayorConceptoListadoFiltros::totalesDesdeFilasVisibles($filasBase);
        }

        $filtrosQuery = MayorConceptoListadoFiltros::paraQueryString($filtros);
        $filtrosQueryBase = MayorConceptoListadoFiltros::paraQueryStringBase($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
            $filtrosQueryBase['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $empresaIds = MayorConceptoListadoFiltros::empresaIds($filtros);
        $empresa = count($empresaIds) === 1
            ? $this->empresaRepository->find((int) $empresaIds[0])
            : null;
        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));

        return view('contable.mayor_concepto.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'filtrosQueryBase' => $filtrosQueryBase,
            'consultado' => $consultado,
            'filas' => $filas,
            'resumen' => $resumen,
            'resumen_por_cuenta' => $resumenPorCuenta,
            'auditoria_panel' => $auditoriaPanel,
            'agrupacion_resumen' => $filtros['agrupacion_resumen'] ?? 'concepto_cuenta',
            'totales' => $totales,
            'totales_visibles' => $totalesVisibles,
            'filtro_detalle_activo' => MayorConceptoListadoFiltros::tieneFiltroDetalle($filtros),
            'filtros_detalle_texto' => MayorConceptoListadoFiltros::descripcionFiltrosDetalleActivos($filtros),
            'errores_bridge' => $erroresBridge,
            'empresa' => $empresa,
            'moneda' => $moneda,
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'multiempresa' => MayorConceptoListadoFiltros::esMultiempresa($filtros),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_concepto' => can('listar-conceptos-de-gastos', false) || can('editar-conceptos-de-gastos', false),
            'puede_ver_ordencompra' => can('listar-ordencompra', false) || can('editar-ordencompra', false),
            'puede_ver_capex' => can('listar-capex', false) || can('editar-capex', false),
        ]);
    }

    /**
     * Cálculo pesado: POST. Con AJAX procesa de a una empresa (evita Timeout 600s → 404).
     */
    public function consultar(Request $request)
    {
        can('listar-asiento');
        MayorConceptoRuntimeSupport::elevarLimites();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = MayorConceptoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaultsEmpresas($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas(MayorConceptoListadoFiltros::empresaIds($filtros));

        if (! MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            if ($request->ajax() || $request->boolean('ajax') || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Seleccione empresa(s) y período para consultar el mayor por concepto.',
                ], 422);
            }

            return redirect()->route('mayor_concepto')
                ->with('mensaje_error', 'Seleccione empresa(s) y período para consultar el mayor por concepto.');
        }

        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_ids' => MayorConceptoListadoFiltros::empresaIds($filtros),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']),
            'excel_formato_numero' => $filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal(),
        ]);

        if ($request->ajax() || $request->boolean('ajax') || $request->wantsJson()) {
            return $this->consultarPasoAjax($request, $filtros);
        }

        $this->generarYCachearPack($filtros);

        return redirect()->route('mayor_concepto', array_merge(
            MayorConceptoListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * Un paso = una empresa. El front encadena hasta terminar y redirige al index.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function consultarPasoAjax(Request $request, array $filtros)
    {
        $empresaIds = MayorConceptoListadoFiltros::empresaIds($filtros);
        $total = count($empresaIds);
        $idx = max(0, (int) $request->input('empresa_idx', 0));

        if ($total === 0 || $idx >= $total) {
            return response()->json(['ok' => false, 'mensaje' => 'Índice de empresa inválido.'], 422);
        }

        $empresaId = (int) $empresaIds[$idx];
        $nombreEmpresa = $this->empresaRepository->find($empresaId)?->nombre ?? ('#'.$empresaId);
        $progressKey = $this->cacheProgressKey($filtros);

        if ($idx === 0) {
            Cache::store('file')->forget($progressKey);
            Cache::store('file')->put($progressKey, ['bloques' => []], now()->addHours(2));
        }

        $progress = Cache::store('file')->get($progressKey);
        if (! is_array($progress)) {
            $progress = ['bloques' => []];
        }

        $bloque = $this->reporteService->generarUnaEmpresaDesdeFiltros($filtros, $empresaId);
        $progress['bloques'][$empresaId] = $bloque;
        Cache::store('file')->put($progressKey, $progress, now()->addHours(2));

        $siguiente = $idx + 1;
        if ($siguiente < $total) {
            return response()->json([
                'ok' => true,
                'done' => false,
                'empresa_idx' => $siguiente,
                'procesada' => $idx + 1,
                'total' => $total,
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombreEmpresa,
                'mensaje' => 'Procesada '.$nombreEmpresa.' ('.($idx + 1).'/'.$total.')',
            ]);
        }

        $consolidar = (bool) ($filtros['consolidar_empresas'] ?? true);
        $bloques = [];
        foreach ($empresaIds as $eid) {
            if (isset($progress['bloques'][$eid])) {
                $bloques[(int) $eid] = $progress['bloques'][$eid];
            }
        }

        if (count($bloques) === 1) {
            $eid = (int) array_key_first($bloques);
            $resultado = $bloques[$eid];
            $resultado['parametros']['empresa_ids'] = $empresaIds;
            $resultado['parametros']['empresa_id'] = $eid;
            $resultado['parametros']['consolidar_empresas'] = true;
        } else {
            // Multiempresa: consolidar=true fusiona concepto/cuenta; false = bloques por empresa.
            $resultado = $this->reporteService->fusionarBloquesEmpresas($bloques, $empresaIds, $consolidar);
            $resultado['parametros']['consolidar_empresas'] = $consolidar;
        }

        $this->persistirPackDesdeResultado($resultado, $filtros);
        Cache::store('file')->forget($progressKey);

        return response()->json([
            'ok' => true,
            'done' => true,
            'procesada' => $total,
            'total' => $total,
            'empresa_nombre' => $nombreEmpresa,
            'mensaje' => 'Listo. Abriendo reporte…',
            'redirect' => route('mayor_concepto', array_merge(
                MayorConceptoListadoFiltros::paraQueryString($filtros),
                ['consultar' => 1],
            )),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cacheProgressKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'mayor_concepto_progress_'.$userId.'_'.MayorConceptoListadoFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function persistirPackDesdeResultado(array $resultado, array $filtros): array
    {
        $empresaIds = MayorConceptoListadoFiltros::empresaIds(array_merge(
            $filtros,
            ['empresa_ids' => $resultado['parametros']['empresa_ids'] ?? ($filtros['empresa_ids'] ?? [])],
        ));
        $consolidar = (bool) ($resultado['parametros']['consolidar_empresas']
            ?? $filtros['consolidar_empresas']
            ?? true);

        // Misma orden final que aplanar: consolidado = un bloque por concepto/cuenta.
        if ($consolidar && count($empresaIds) > 1) {
            $resultado['secciones'] = $this->reporteService->asegurarSeccionesConsolidadas(
                $resultado['secciones'] ?? []
            );
            $resultado['parametros']['consolidar_empresas'] = true;
        }

        $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
        $resumen = $this->reporteService->resumenAgrupado($resultado);
        $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
        $totales = $this->armarTotalesDesdeResultado($resultado);

        unset($resultado['mayor_plano_analitico'], $resultado['analitico_por_asiento'], $resultado['resultados_por_empresa']);

        if (isset($auditoriaPanel['conciliacion']) && is_array($auditoriaPanel['conciliacion'])) {
            $cuadradas = $auditoriaPanel['conciliacion']['filas_cuadradas'] ?? [];
            if (is_array($cuadradas) && count($cuadradas) > 50) {
                $auditoriaPanel['conciliacion']['filas_cuadradas'] = array_slice($cuadradas, 0, 50);
                $auditoriaPanel['conciliacion']['filas_cuadradas_recortadas'] = true;
            }
        }

        $pack = [
            'resultado' => $resultado,
            'totales' => $totales,
            'resumen' => $resumen,
            'resumen_por_cuenta' => $resumenPorCuenta,
            'auditoria_panel' => $auditoriaPanel,
        ];

        Cache::store('file')->put($this->cachePackKey($filtros), $pack, now()->addHours(4));
        session()->forget(self::SESSION_CACHE_KEY);
        session([
            self::SESSION_CACHE_KEY => [
                'firma' => MayorConceptoListadoFiltros::firma($filtros),
            ],
        ]);

        return $pack;
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-asiento');

        MayorConceptoRuntimeSupport::elevarLimites();

        $filtros = MayorConceptoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas(MayorConceptoListadoFiltros::empresaIds($filtros));

        if (! MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('mayor_concepto');
        }

        $formato = strtoupper($formato);
        if ($formato === 'PDF'
            && empty($filtros['consolidar_empresas'])
            && count(MayorConceptoListadoFiltros::empresaIds($filtros)) > 1
        ) {
            return $this->descargarPdfPorEmpresa($filtros);
        }

        // Reutilizar pack de pantalla (evita regenerar Anita al exportar junio / mes completo).
        $pack = $this->leerCachePack($filtros);
        if ($pack === null) {
            $pack = $this->generarYCachearPack($filtros);
        }

        $resultado = $pack['resultado'];
        $filas = $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtros);
        $resumen = $pack['resumen'] ?? $this->reporteService->resumenSegunAgrupacion($resultado, $filtros);
        $resumenPorCuenta = $pack['resumen_por_cuenta'] ?? $this->reporteService->resumenAgrupadoPorCuenta($resultado);
        if (($filtros['agrupacion_resumen'] ?? 'concepto_cuenta') === 'cuenta_concepto') {
            $resumen = $this->reporteService->resumenSegunAgrupacion($resultado, $filtros);
        }
        $totales = $pack['totales'] ?? $this->armarTotalesDesdeResultado($resultado);
        $auditoriaPanel = $pack['auditoria_panel'] ?? $this->reporteService->armarAuditoriaPanel($resultado);
        $agrupacionResumen = $filtros['agrupacion_resumen'] ?? 'concepto_cuenta';
        $titulo = 'Mayor por concepto';
        $subtitulo = $this->armarSubtituloExport($filtros);
        $multiempresa = MayorConceptoListadoFiltros::esMultiempresa($filtros);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.mayor_concepto.listado', compact(
                    'filas',
                    'resumen',
                    'resumenPorCuenta',
                    'filtros',
                    'totales',
                    'titulo',
                    'subtitulo',
                    'auditoriaPanel',
                    'agrupacionResumen',
                    'multiempresa',
                ))->render();

                return $this->descargarPdf($view, 'mayor_por_concepto', 'legal', 'landscape');

            case 'EXCEL':
                return (new MayorConceptoExport($this->reporteService))
                    ->parametros($filtros, $pack)
                    ->download('mayor_por_concepto.xlsx');

            case 'CSV':
                // CSV no lleva formato: "auto" no puede adaptarse, cae al respaldo (ar|intl).
                $filtrosCsv = $filtros;
                $filtrosCsv['excel_formato_numero'] = ExcelFormatoNumero::paraCsv(
                    $filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal()
                );

                return (new MayorConceptoExport($this->reporteService))
                    ->parametros($filtrosCsv, $pack)
                    ->download('mayor_por_concepto.csv', Excel::CSV);
        }

        return redirect()->route('mayor_concepto', array_merge(
            MayorConceptoListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cachePackKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'mayor_concepto_pack_'.$userId.'_'.MayorConceptoListadoFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   resultado: array<string, mixed>,
     *   totales: array<string, mixed>,
     *   resumen: list<array<string, mixed>>,
     *   resumen_por_cuenta: list<array<string, mixed>>,
     *   auditoria_panel: array<string, mixed>
     * }
     */
    private function generarYCachearPack(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);

        return $this->persistirPackDesdeResultado($resultado, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   resultado: array<string, mixed>,
     *   totales: array<string, mixed>,
     *   resumen: list<array<string, mixed>>,
     *   resumen_por_cuenta: list<array<string, mixed>>,
     *   auditoria_panel: array<string, mixed>
     * }|null
     */
    private function leerCachePack(array $filtros): ?array
    {
        $firma = MayorConceptoListadoFiltros::firma($filtros);
        $pack = Cache::store('file')->get($this->cachePackKey($filtros));

        if (! is_array($pack) || ! isset($pack['resultado'])) {
            // Limpiar sesión legacy hinchada (formato viejo con resultado completo).
            $legacy = session(self::SESSION_CACHE_KEY);
            if (is_array($legacy) && (isset($legacy['resultado']) || isset($legacy['pack']))) {
                session()->forget(self::SESSION_CACHE_KEY);
            }

            return null;
        }

        $marker = session(self::SESSION_CACHE_KEY);
        if (is_array($marker) && ($marker['firma'] ?? '') !== '' && ($marker['firma'] ?? '') !== $firma) {
            return null;
        }

        return $pack;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function paginarFilas(array $filas, int $perPage, Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $coleccion = collect($filas);
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $items = $coleccion->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $coleccion->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function armarTotalesDesdeResultado(array $resultado): array
    {
        return [
            'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
            'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
            'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
            'stats' => $resultado['stats'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function armarSubtituloExport(array $filtros): string
    {
        $partes = [];

        $empresas = $this->reporteService->formatearEmpresasTexto($filtros);
        if ($empresas !== '') {
            $partes[] = 'Empresa'.(MayorConceptoListadoFiltros::esMultiempresa($filtros) ? 's' : '').': '.$empresas;
            if (MayorConceptoListadoFiltros::esMultiempresa($filtros)) {
                $partes[] = ! empty($filtros['consolidar_empresas']) ? 'Modo: consolidado' : 'Modo: por empresa';
            }
        }

        $periodo = $this->reporteService->formatearPeriodoTexto($filtros);
        if ($periodo !== '') {
            $partes[] = 'Período: '.$periodo;
        }

        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));
        if ($moneda) {
            $partes[] = 'Expresado en: '.$moneda->nombre.' ('.$moneda->abreviatura.')';
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $partes[] = 'Solo moneda origen';
        }

        $filtrosDetalle = MayorConceptoListadoFiltros::descripcionFiltrosDetalleActivos($filtros);
        if ($filtrosDetalle !== []) {
            $partes[] = 'Filtro detalle: '.implode(' · ', $filtrosDetalle);
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaultsEmpresas(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        if (! $request->has('excel_formato_numero')) {
            $filtros['excel_formato_numero'] = MayorConceptoExcelFormatoNumero::normalizar(
                ReportePreferenciasUsuario::leerString(
                    self::PREFERENCIAS_CLAVE,
                    'excel_formato_numero',
                    ExcelFormatoNumero::preferenciaGlobal(),
                )
            );
        } else {
            $filtros['excel_formato_numero'] = MayorConceptoExcelFormatoNumero::normalizar(
                $filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal()
            );
        }

        if (MayorConceptoListadoFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            } else {
                $legacy = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($legacy !== null && in_array($legacy, $permitidos, true)) {
                    $filtros['empresa_ids'] = [$legacy];
                }
            }
        }

        if (MayorConceptoListadoFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $filtros['empresa_id'] = (int) (MayorConceptoListadoFiltros::empresaIds($filtros)[0] ?? 0);

        return $filtros;
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function assertAccesoEmpresas(array $empresaIds): void
    {
        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            if (! $this->empresaRepository->empresaIdPermitida((int) $empresaId)) {
                abort(403, 'No tiene acceso a la empresa seleccionada.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function descargarPdfPorEmpresa(array $filtros)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];
        $titulo = 'Mayor por concepto';

        try {
            foreach (MayorConceptoListadoFiltros::empresaIds($filtros) as $empresaId) {
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [(int) $empresaId],
                    'empresa_id' => (int) $empresaId,
                    'consolidar_empresas' => true,
                ]);

                $resultado = $this->reporteService->generarDesdeFiltros($filtrosEmpresa);
                $filas = $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtrosEmpresa);
                $resumen = $this->reporteService->resumenSegunAgrupacion($resultado, $filtrosEmpresa);
                $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
                $totales = $this->armarTotalesDesdeResultado($resultado);
                $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
                $agrupacionResumen = $filtrosEmpresa['agrupacion_resumen'] ?? 'concepto_cuenta';
                $subtitulo = $this->armarSubtituloExport($filtrosEmpresa);
                $multiempresa = false;

                $view = \View::make('contable.mayor_concepto.listado', compact(
                    'filas',
                    'resumen',
                    'resumenPorCuenta',
                    'filtros',
                    'totales',
                    'titulo',
                    'subtitulo',
                    'auditoriaPanel',
                    'agrupacionResumen',
                    'multiempresa',
                ))->render();

                $temp = $dir.'/mayor_concepto_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $nombrePdf = 'mayor_por_concepto_'.date('Ymd_His');
            $destino = $dir.'/'.$nombrePdf.'.pdf';

            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
