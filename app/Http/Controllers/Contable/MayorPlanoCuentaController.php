<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MayorPlanoCuentaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

class MayorPlanoCuentaController extends Controller
{
    private const SESSION_CACHE_KEY = 'mayor_plano_cuenta_resultado_cache';

    public function __construct(
        private readonly MayorPlanoCuentaReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-mayor-plano-cuenta');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest($request);

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        $consultado = false;
        $filas = null;
        $resumen = [];
        $totales = null;
        $erroresBridge = [];
        $resultado = null;

        if ($request->boolean('consultar') && MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->generarYCachear($filtros);
            $consultado = true;
        } elseif (MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            if ($resultado !== null) {
                $consultado = true;
            }
        }

        if ($consultado && $resultado !== null) {
            $totales = $this->armarTotalesDesdeResultado($resultado);
            $resumen = $this->reporteService->resumenPorCuenta($resultado);
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filasAplanadas = $this->reporteService->aplanarFilas($resultado, $filtros, false);
            $filas = $this->reporteService->paginarFilas($filasAplanadas, $perPage);
        }

        $filtrosQuery = MayorPlanoCuentaListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));
        $empresaRefId = (int) (($filtros['empresa_ids'] ?? [])[0] ?? 0);

        return view('contable.mayor_plano_cuenta.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'resumen' => $resumen,
            'totales' => $totales,
            'errores_bridge' => $erroresBridge,
            'moneda' => $moneda,
            'cuenta_desde_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_desde'] ?? 0), $empresaRefId),
            'cuenta_hasta_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_hasta'] ?? 0), $empresaRefId),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_ordencompra' => can('listar-ordencompra', false) || can('editar-ordencompra', false),
            'multiempresa' => count($filtros['empresa_ids'] ?? []) > 1,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-mayor-plano-cuenta');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '600');

        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if (! MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('mayor_plano_cuenta');
        }

        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $filas = $this->reporteService->aplanarFilas($resultado, $filtros, true);
        $resumen = $this->reporteService->resumenPorCuenta($resultado);
        $totales = $this->armarTotalesDesdeResultado($resultado);
        $titulo = 'Mayor analítico por cuenta contable';
        $subtitulo = $this->armarSubtituloExport($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('contable.mayor_plano_cuenta.listado', compact(
                    'filas',
                    'resumen',
                    'filtros',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();

                return $this->descargarPdf($view, 'mayor_plano_cuenta', 'legal', 'landscape');

            case 'EXCEL':
                return (new MayorPlanoCuentaExport($this->reporteService))
                    ->parametros($filtros)
                    ->download('mayor_plano_cuenta.xlsx');

            case 'CSV':
                return (new MayorPlanoCuentaExport($this->reporteService))
                    ->parametros($filtros)
                    ->download('mayor_plano_cuenta.csv', Excel::CSV);
        }

        return redirect()->route('mayor_plano_cuenta', array_merge(
            MayorPlanoCuentaListadoFiltros::paraQueryString($filtros),
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
        session([
            self::SESSION_CACHE_KEY => [
                'firma' => MayorPlanoCuentaListadoFiltros::firma($filtros),
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

        if (($cache['firma'] ?? '') !== MayorPlanoCuentaListadoFiltros::firma($filtros)) {
            return null;
        }

        return is_array($cache['resultado'] ?? null) ? $cache['resultado'] : null;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function armarTotalesDesdeResultado(array $resultado): array
    {
        return [
            'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
            'cantidad_cuentas' => (int) ($resultado['totales']['cuentas'] ?? 0),
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
            $partes[] = 'Empresas: '.$empresas;
        }

        $periodo = $this->reporteService->formatearPeriodoTexto($filtros);
        if ($periodo !== '') {
            $partes[] = 'Período: '.$periodo;
        }

        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));
        if ($moneda) {
            $partes[] = 'Expresado en: '.$moneda->nombre.' ('.$moneda->abreviatura.')';
        }

        $partes[] = $this->reporteService->formatearInclusionAsientosTexto($filtros);

        if (! empty($filtros['solo_moneda_origen'])) {
            $partes[] = 'Solo moneda origen';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function assertAccesoEmpresas(array $empresaIds): void
    {
        foreach ($empresaIds as $empresaId) {
            if (! $this->empresaRepository->empresaIdPermitida((int) $empresaId)) {
                abort(403, 'No tiene acceso a la empresa seleccionada.');
            }
        }
    }

    /**
     * @return array{codigo: string, nombre: string}
     */
    private function metaCuentaFiltro(int $codigoCuenta, int $empresaId): array
    {
        if ($codigoCuenta <= 0) {
            return ['codigo' => '', 'nombre' => ''];
        }

        $codigoFmt = MayorPlanoCuentaSupport::formatearCodigoCuenta($codigoCuenta);
        if ($empresaId <= 0) {
            return ['codigo' => $codigoFmt, 'nombre' => ''];
        }

        $nombre = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoCuenta)
            ->value('nombre');

        return [
            'codigo' => $codigoFmt,
            'nombre' => $nombre ? (string) $nombre : '',
        ];
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
