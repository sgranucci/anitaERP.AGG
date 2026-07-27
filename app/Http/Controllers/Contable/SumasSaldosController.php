<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\SumasSaldosExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\SumasSaldosReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;
use App\Support\Contable\SumasSaldosListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class SumasSaldosController extends Controller
{
    private const SESSION_CACHE_KEY = 'sumas_saldos_resultado_cache';

    private const PREFERENCIAS_CLAVE = 'sumas_saldos';

    public function __construct(
        private readonly SumasSaldosReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-sumas-saldos');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();
        $filtros = SumasSaldosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaultsEmpresas($request, $filtros, $empresaQuery);

        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'] ?? [],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
        }

        $consultado = false;
        $filas = null;
        $totales = null;
        $resultado = null;
        $advertencias = [];

        if ($request->boolean('consultar') && SumasSaldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            SumasSaldosRuntimeSupport::elevarLimites();
            $resultado = $this->generarYCachear($filtros);
            $consultado = true;
        } elseif (SumasSaldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            if ($resultado !== null) {
                $consultado = true;
            }
        }

        if ($consultado && $resultado !== null) {
            $totales = $resultado['totales'] ?? [];
            $advertencias = $resultado['advertencias'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filasAplanadas = $this->reporteService->aplanarFilas($resultado, $filtros, false);
            $filas = $this->reporteService->paginarFilas($filasAplanadas, $perPage);
        }

        $filtrosQuery = SumasSaldosListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $moneda = $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1));
        $empresaRefId = (int) (($filtros['empresa_ids'] ?? [])[0] ?? 0);

        return view('contable.sumas_saldos.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'advertencias' => $advertencias,
            'moneda' => $moneda,
            'cuenta_desde_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_desde'] ?? 0), $empresaRefId),
            'cuenta_hasta_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_hasta'] ?? 0), $empresaRefId),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            // Columna empresa solo si hay varias y NO se consolidan (Anita consolida sin esa columna).
            'multiempresa' => count($filtros['empresa_ids'] ?? []) > 1
                && empty($filtros['consolidar_empresas']),
            'fuente' => $resultado['fuente'] ?? null,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-sumas-saldos');

        SumasSaldosRuntimeSupport::elevarLimites();

        $filtros = SumasSaldosListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if (! SumasSaldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('sumas_saldos');
        }

        $resultado = $this->obtenerResultado($filtros);
        $filas = $this->reporteService->aplanarFilas($resultado, $filtros, true);
        $totales = $resultado['totales'] ?? [];
        $titulo = 'Balance de sumas y saldos';
        $subtitulo = $this->armarSubtituloExport($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado);
                }

                $view = \View::make('contable.sumas_saldos.listado', compact(
                    'filas',
                    'filtros',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();

                return $this->descargarPdf(
                    $view,
                    $this->armarNombreArchivoExport($filtros, ''),
                    'legal',
                    'landscape',
                );

            case 'EXCEL':
                return (new SumasSaldosExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'xlsx'));

            case 'CSV':
                return (new SumasSaldosExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'csv'), Excel::CSV);
        }

        return redirect()->route('sumas_saldos', array_merge(
            SumasSaldosListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function obtenerResultado(array $filtros): array
    {
        $resultado = $this->leerCache($filtros);
        if ($resultado !== null) {
            return $resultado;
        }

        return $this->generarYCachear($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachear(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $this->persistirCache($resultado, $filtros);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    private function persistirCache(array $resultado, array $filtros): void
    {
        $firma = SumasSaldosListadoFiltros::firma($filtros);
        Cache::store('file')->put($this->cacheKey($filtros), [
            'firma' => $firma,
            'resultado' => $resultado,
        ], now()->addHours(4));

        session()->forget(self::SESSION_CACHE_KEY);
        session([
            self::SESSION_CACHE_KEY => [
                'firma' => $firma,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function leerCache(array $filtros): ?array
    {
        $firma = SumasSaldosListadoFiltros::firma($filtros);
        $pack = Cache::store('file')->get($this->cacheKey($filtros));

        if (is_array($pack) && isset($pack['resultado']) && is_array($pack['resultado'])) {
            if (($pack['firma'] ?? '') === $firma) {
                return $pack['resultado'];
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'sumas_saldos_v2_'.$userId.'_'.SumasSaldosListadoFiltros::firma($filtros);
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

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoCompleto
     */
    private function descargarPdfPorEmpresa(array $filtros, array $resultadoCompleto)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];
        $titulo = 'Balance de sumas y saldos';

        try {
            foreach ($resultadoCompleto['secciones'] ?? [] as $seccion) {
                $empresaId = (int) ($seccion['empresa_id'] ?? 0);
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'consolidar_empresas' => true,
                ]);
                $filas = $seccion['filas'] ?? [];
                $totales = $seccion['totales'] ?? [];
                $subtitulo = ((string) ($seccion['empresa_nombre'] ?? ''))
                    .' · '.$this->armarSubtituloExport($filtrosEmpresa);

                $view = \View::make('contable.sumas_saldos.listado', compact(
                    'filas',
                    'filtros',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();

                $temp = $dir.'/sumas_saldos_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $nombreBase = $this->armarNombreArchivoExport($filtros, '');
            $destino = $dir.'/'.$nombreBase.'.pdf';

            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
                $temporales = [];
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino, $nombreBase.'.pdf')->deleteFileAfterSend(true);
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

        $nombrePdf = $nombreBase !== '' ? $nombreBase : 'sumas_saldos_'.date('Ymd_His');
        $ruta = $path.'/'.$nombrePdf.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($ruta);

        return response()->download($ruta, $nombrePdf.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function armarNombreArchivoExport(array $filtros, string $extension): string
    {
        $periodo = '';
        if (($filtros['modo_periodo'] ?? '') === SumasSaldosListadoFiltros::MODO_PERIODOS) {
            $pd = (int) ($filtros['periodo_desde'] ?? 0);
            $ph = (int) ($filtros['periodo_hasta'] ?? 0);
            if ($pd > 0) {
                $periodo = (string) $pd;
                if ($ph > 0 && $ph !== $pd) {
                    $periodo .= '_'.$ph;
                }
            }
        } else {
            $desde = preg_replace('/\D/', '', (string) ($filtros['fecha_desde'] ?? '')) ?? '';
            $hasta = preg_replace('/\D/', '', (string) ($filtros['fecha_hasta'] ?? '')) ?? '';
            $desde = strlen($desde) >= 8 ? substr($desde, 0, 8) : '';
            $hasta = strlen($hasta) >= 8 ? substr($hasta, 0, 8) : '';
            if ($desde !== '' && $hasta !== '') {
                $periodo = $desde === $hasta ? $desde : $desde.'_'.$hasta;
            }
        }

        $empresas = implode('-', array_map('intval', $filtros['empresa_ids'] ?? []));
        $base = 'sumas_saldos';
        if ($periodo !== '') {
            $base .= '_'.$periodo;
        }
        if ($empresas !== '') {
            $base .= '_e'.$empresas;
        }
        $base .= '_'.date('His');

        return $extension === '' ? $base : $base.'.'.$extension;
    }
}
