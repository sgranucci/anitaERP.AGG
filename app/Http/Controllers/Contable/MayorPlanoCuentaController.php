<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MayorPlanoCuentaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class MayorPlanoCuentaController extends Controller
{
    private const SESSION_CACHE_KEY = 'mayor_plano_cuenta_resultado_cache';

    private const PREFERENCIAS_CLAVE = 'mayor_plano_cuenta';

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
        $resumen = [];
        $totales = null;
        $erroresBridge = [];
        $resultado = null;

        if ($request->boolean('consultar') && MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            MayorPlanoCuentaRuntimeSupport::elevarLimites();
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
            'cuentas_iniciales' => $this->metaCuentasParticulares($filtros['cuentas'] ?? [], $empresaRefId),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_ordencompra' => can('listar-ordencompra', false) || can('editar-ordencompra', false),
            'puede_ver_proveedor' => can('listar-proveedor', false) || can('editar-proveedor', false),
            'puede_ver_comprobante_proveedor' => can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false),
            'puede_ver_factura' => can('listar-factura', false) || can('editar-factura', false),
            'multiempresa' => count($filtros['empresa_ids'] ?? []) > 1
                || empty($filtros['consolidar_empresas']),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-mayor-plano-cuenta');

        MayorPlanoCuentaRuntimeSupport::elevarLimites();

        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if (! MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('mayor_plano_cuenta');
        }

        // Reutilizar resultado de pantalla (evita regenerar Anita al exportar).
        $resultado = $this->obtenerResultado($filtros);
        $filas = $this->reporteService->aplanarFilas($resultado, $filtros, true);
        $resumen = $this->reporteService->resumenPorCuenta($resultado);
        $totales = $this->armarTotalesDesdeResultado($resultado);
        $titulo = 'Mayor analítico por cuenta contable';
        $subtitulo = $this->armarSubtituloExport($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado);
                }

                $view = \View::make('contable.mayor_plano_cuenta.listado', compact(
                    'filas',
                    'resumen',
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
                return (new MayorPlanoCuentaExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'xlsx'));

            case 'CSV':
                return (new MayorPlanoCuentaExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'csv'), Excel::CSV);
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
        $firma = MayorPlanoCuentaListadoFiltros::firma($filtros);
        Cache::store('file')->put($this->cacheKey($filtros), [
            'firma' => $firma,
            'resultado' => $resultado,
        ], now()->addHours(4));

        // Solo marca de firma en sesión (el payload grande va a file cache).
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
        $firma = MayorPlanoCuentaListadoFiltros::firma($filtros);
        $pack = Cache::store('file')->get($this->cacheKey($filtros));

        if (is_array($pack) && isset($pack['resultado']) && is_array($pack['resultado'])) {
            if (($pack['firma'] ?? '') === $firma) {
                return $pack['resultado'];
            }

            return null;
        }

        // Migrar cache legacy en sesión (payload completo) → file cache.
        $legacy = session(self::SESSION_CACHE_KEY);
        if (is_array($legacy)
            && isset($legacy['resultado'])
            && is_array($legacy['resultado'])
            && ($legacy['firma'] ?? '') === $firma
        ) {
            $this->persistirCache($legacy['resultado'], $filtros);

            return $legacy['resultado'];
        }

        if (is_array($legacy) && isset($legacy['resultado'])) {
            session()->forget(self::SESSION_CACHE_KEY);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        // v2: Mon.Referencia con conversión explícita + export desde cache.
        return 'mayor_plano_cuenta_v2_'.$userId.'_'.MayorPlanoCuentaListadoFiltros::firma($filtros);
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

    /**
     * @param  list<int|string>  $codigos
     * @return list<array{codigo: int, codigo_fmt: string, nombre: string}>
     */
    private function metaCuentasParticulares(array $codigos, int $empresaId): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos), fn (int $c) => $c > 0)));
        sort($codigos);
        if ($codigos === []) {
            return [];
        }

        $nombres = [];
        if ($empresaId > 0) {
            $nombres = DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->whereIn('codigo', $codigos)
                ->pluck('nombre', 'codigo')
                ->all();
        }

        $out = [];
        foreach ($codigos as $codigo) {
            $out[] = [
                'codigo' => $codigo,
                'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                'nombre' => isset($nombres[$codigo]) ? (string) $nombres[$codigo] : '',
            ];
        }

        return $out;
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
     * @param  array<string, mixed>|null  $resultadoCompleto  Cache multiempresa desconsolidado
     */
    private function descargarPdfPorEmpresa(array $filtros, ?array $resultadoCompleto = null)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];
        $titulo = 'Mayor analítico por cuenta contable';
        $resultadoCompleto ??= $this->obtenerResultado($filtros);

        try {
            foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
                $empresaId = (int) $empresaId;
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'consolidar_empresas' => true,
                ]);

                // Partir secciones del resultado en cache (sin volver a Anita).
                $seccionesEmpresa = array_values(array_filter(
                    $resultadoCompleto['secciones'] ?? [],
                    fn (array $s) => (int) ($s['empresa_id'] ?? 0) === $empresaId,
                ));
                if ($seccionesEmpresa === []) {
                    $resultado = $this->reporteService->generarDesdeFiltros($filtrosEmpresa);
                } else {
                    $totalDebe = 0.0;
                    $totalHaber = 0.0;
                    $totalLineas = 0;
                    foreach ($seccionesEmpresa as $sec) {
                        $totalDebe += (float) ($sec['total_debe'] ?? 0);
                        $totalHaber += (float) ($sec['total_haber'] ?? 0);
                        $totalLineas += (int) ($sec['cantidad_lineas'] ?? 0);
                    }
                    $resultado = [
                        'parametros' => array_merge($resultadoCompleto['parametros'] ?? [], [
                            'empresa_ids' => [$empresaId],
                            'consolidar_empresas' => true,
                        ]),
                        'secciones' => $seccionesEmpresa,
                        'totales' => [
                            'debe' => round($totalDebe, 2),
                            'haber' => round($totalHaber, 2),
                            'lineas' => $totalLineas,
                            'cuentas' => count($seccionesEmpresa),
                        ],
                        'errores_bridge' => $resultadoCompleto['errores_bridge'] ?? [],
                        'stats' => $resultadoCompleto['stats'] ?? [],
                    ];
                }

                $filas = $this->reporteService->aplanarFilas($resultado, $filtrosEmpresa, true);
                $resumen = $this->reporteService->resumenPorCuenta($resultado);
                $totales = $this->armarTotalesDesdeResultado($resultado);
                $subtitulo = $this->armarSubtituloExport($filtrosEmpresa);

                $view = \View::make('contable.mayor_plano_cuenta.listado', [
                    'filas' => $filas,
                    'resumen' => $resumen,
                    'filtros' => $filtrosEmpresa,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();

                $temp = $dir.'/mayor_plano_tmp_'.uniqid('', true).'.pdf';
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

            // Descarga al PC del usuario y borra el PDF del servidor (no acumula disco).
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

        $nombrePdf = $nombreBase !== ''
            ? $nombreBase
            : 'mayor_analitico_cuenta_'.date('Ymd_His');
        $ruta = $path.'/'.$nombrePdf.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($ruta);

        return response()->download($ruta, $nombrePdf.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * Nombre de descarga: período del reporte + empresas + HHMMSS
     * (el sufijo horario evita pisar exports anteriores el mismo día).
     * Excel/CSV se streaman; PDF se borra tras enviar.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function armarNombreArchivoExport(array $filtros, string $extension): string
    {
        $periodo = '';
        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            $anio = (int) ($filtros['anio'] ?? 0);
            $mes = (int) ($filtros['mes'] ?? 0);
            if ($anio > 0 && $mes > 0) {
                $periodo = sprintf('%04d-%02d', $anio, $mes);
            }
        } else {
            $desde = preg_replace('/\D/', '', (string) ($filtros['fecha_desde'] ?? '')) ?? '';
            $hasta = preg_replace('/\D/', '', (string) ($filtros['fecha_hasta'] ?? '')) ?? '';
            $desde = strlen($desde) >= 8 ? substr($desde, 0, 8) : '';
            $hasta = strlen($hasta) >= 8 ? substr($hasta, 0, 8) : '';
            if ($desde !== '' && $hasta !== '') {
                // Un solo día → una sola fecha (no 20260714_20260714).
                $periodo = $desde === $hasta ? $desde : $desde.'_'.$hasta;
            } elseif ($desde !== '') {
                $periodo = $desde;
            }
        }

        $empresas = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? [])));
        $empPart = $empresas === [] ? '' : 'emp'.implode('-', array_slice($empresas, 0, 4));
        if (count($empresas) > 4) {
            $empPart .= 'y'.(count($empresas) - 4);
        }

        $partes = array_filter([
            'mayor_analitico_cuenta',
            $periodo,
            $empPart,
            date('His'),
        ], fn ($p) => $p !== '' && $p !== null);

        $base = implode('_', $partes);
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '', $base) ?? $base;

        return $extension !== '' ? $base.'.'.$extension : $base;
    }
}
