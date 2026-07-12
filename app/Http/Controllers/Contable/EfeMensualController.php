<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\EfeMensualExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\EfeMensualReporteService;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;

class EfeMensualController extends Controller
{
    private const SESSION_CACHE_KEY = 'efe_mensual_resultado_cache';

    private const PREFERENCIAS_CLAVE = 'efe_mensual';

    public function __construct(
        private readonly EfeMensualReporteService $reporteService,
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

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
        }

        $consultado = false;
        $resultado = null;
        $resumenPagos = [];
        $filasPreview = null;
        $totales = null;
        $erroresBridge = [];
        $auditoriaPanel = null;

        if ($request->boolean('consultar') && EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->generarYCachear($filtros);
            $consultado = true;
        } elseif (EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            if ($resultado !== null) {
                $consultado = true;
            }
        }

        if ($consultado && $resultado !== null) {
            $resumenPagos = $resultado['resumen_pagos'] ?? [];
            $totales = $resultado['totales'] ?? null;
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $auditoriaPanel = $resultado['auditoria_panel'] ?? null;
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filasPreview = $this->paginarFilas($resumenPagos, $perPage, $request);
        }

        $filtrosQuery = EfeMensualListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filasPreview instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filasPreview->appends($filtrosQuery);
        }

        $empresa = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? $this->empresaRepository->find((int) $filtros['empresa_id'])
            : null;
        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));

        return view('contable.efe_mensual.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resumen_pagos' => $resumenPagos,
            'filas_preview' => $filasPreview,
            'totales' => $totales,
            'errores_bridge' => $erroresBridge,
            'auditoria_panel' => $auditoriaPanel,
            'empresa' => $empresa,
            'moneda' => $moneda,
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-asiento');

        MayorConceptoRuntimeSupport::elevarLimites();

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('efe_mensual');
        }

        if (strtoupper($formato) !== 'EXCEL') {
            return redirect()->route('efe_mensual', array_merge(
                EfeMensualListadoFiltros::paraQueryString($filtros),
                ['consultar' => 1],
            ));
        }

        $resultado = $this->leerCache($filtros);
        if ($resultado === null) {
            $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        }

        $empresa = $this->empresaRepository->find((int) ($filtros['empresa_id'] ?? 0));
        $slugEmpresa = $empresa ? preg_replace('/[^A-Za-z0-9]+/', '_', (string) $empresa->nombre) : 'empresa';
        $mes = str_pad((string) ($filtros['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
        $anio = (string) ($filtros['anio'] ?? '');
        $nombre = 'efe_'.$slugEmpresa.'_'.$anio.$mes.'.xlsx';

        return (new EfeMensualExport($this->reporteService, $this->empresaRepository))
            ->parametros($filtros, $resultado)
            ->download($nombre);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachear(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        unset($resultado['mayor_concepto']['mayor_plano_analitico']);
        unset($resultado['mayor_concepto']['analitico_por_asiento']);
        session([
            self::SESSION_CACHE_KEY => [
                'firma' => EfeMensualListadoFiltros::firma($filtros),
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

        if (($cache['firma'] ?? '') !== EfeMensualListadoFiltros::firma($filtros)) {
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
}
