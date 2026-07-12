<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MayorConceptoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use App\Support\Contable\MayorConceptoListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
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
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
        }

        $consultado = false;
        $filas = null;
        $resultado = null;
        $resumen = [];
        $resumenPorCuenta = [];
        $auditoriaPanel = null;
        $totales = null;
        $erroresBridge = [];

        if ($request->boolean('consultar') && MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->generarYCachear($filtros);
            $consultado = true;
            $totales = $this->armarTotalesDesdeResultado($resultado);
            $resumen = $this->reporteService->resumenAgrupado($resultado);
            $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
            $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->paginarFilas(
                $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtros),
                $perPage,
                $request,
            );
        } elseif (MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            if ($resultado !== null) {
                $consultado = true;
                $totales = $this->armarTotalesDesdeResultado($resultado);
                $resumen = $this->reporteService->resumenAgrupado($resultado);
                $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
                $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
                $erroresBridge = $resultado['errores_bridge'] ?? [];
                $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
                $filas = $this->paginarFilas(
                    $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtros),
                    $perPage,
                    $request,
                );
            }
        }

        $totalesVisibles = null;
        if ($consultado && $resultado !== null && MayorConceptoListadoFiltros::tieneFiltroDetalle($filtros)) {
            $filasFiltradas = $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtros);
            $totalesVisibles = MayorConceptoListadoFiltros::totalesDesdeFilasVisibles($filasFiltradas);
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

        $empresa = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? $this->empresaRepository->find((int) $filtros['empresa_id'])
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
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_concepto' => can('listar-conceptos-de-gastos', false) || can('editar-conceptos-de-gastos', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-asiento');

        MayorConceptoRuntimeSupport::elevarLimites();

        $filtros = MayorConceptoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! MayorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('mayor_concepto');
        }

        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $filas = $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $filtros);
        $resumen = $this->reporteService->resumenSegunAgrupacion($resultado, $filtros);
        $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
        $totales = $this->armarTotalesDesdeResultado($resultado);
        $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
        $agrupacionResumen = $filtros['agrupacion_resumen'] ?? 'concepto_cuenta';
        $titulo = 'Mayor por concepto';
        $subtitulo = $this->armarSubtituloExport($filtros);

        switch (strtoupper($formato)) {
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
                ))->render();

                return $this->descargarPdf($view, 'mayor_por_concepto', 'legal', 'landscape');

            case 'EXCEL':
                return (new MayorConceptoExport($this->reporteService))
                    ->parametros($filtros)
                    ->download('mayor_por_concepto.xlsx');

            case 'CSV':
                return (new MayorConceptoExport($this->reporteService))
                    ->parametros($filtros)
                    ->download('mayor_por_concepto.csv', Excel::CSV);
        }

        return redirect()->route('mayor_concepto', array_merge(
            MayorConceptoListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachear(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        unset($resultado['mayor_plano_analitico']);
        session([
            self::SESSION_CACHE_KEY => [
                'firma' => MayorConceptoListadoFiltros::firma($filtros),
                'resultado' => $resultado,
            ],
        ]);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function leerCache(array $filtros): ?array
    {
        $cache = session(self::SESSION_CACHE_KEY);
        if (! is_array($cache)) {
            return null;
        }

        if (($cache['firma'] ?? '') !== MayorConceptoListadoFiltros::firma($filtros)) {
            return null;
        }

        return is_array($cache['resultado'] ?? null) ? $cache['resultado'] : null;
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

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $nombre = $this->empresaRepository->find($empresaId)?->nombre;
            if ($nombre) {
                $partes[] = 'Empresa: '.$nombre;
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
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $cached = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && in_array($cached, $permitidos, true)) {
                $filtros['empresa_id'] = $cached;
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

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
