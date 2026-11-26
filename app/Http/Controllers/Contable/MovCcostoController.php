<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MovCcostoConciliacionExport;
use App\Exports\Contable\MovCcostoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MovCcostoConciliacionService;
use App\Services\Contable\MovCcostoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MovCcosto\MovCcostoRuntimeSupport;
use App\Support\Contable\MovCcostoListadoFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

/**
 * Totales por centro de costo (Anita l-movccosto).
 */
class MovCcostoController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'movccosto';

    public function __construct(
        private readonly MovCcostoReporteService $reporteService,
        private readonly MovCcostoConciliacionService $conciliacionService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-movccosto');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();

        $filtros = MovCcostoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaultsEmpresas($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'] ?? [],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
        }

        $consultado = false;
        $resultado = null;
        $filas = null;
        $columnas = [];
        $totales = [];
        $advertencias = [];

        if (MovCcostoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            if ($request->boolean('consultar')) {
                MovCcostoRuntimeSupport::elevarLimites();
                $resultado = $this->generarYCachear($filtros);
                $consultado = true;
            } else {
                $resultado = $this->leerCache($filtros);
                $consultado = $resultado !== null;
            }
        }

        if ($consultado && $resultado !== null) {
            $columnas = $resultado['columnas'] ?? [];
            $totales = $resultado['totales'] ?? [];
            $advertencias = $resultado['advertencias'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas(
                $this->reporteService->aplanarFilas($resultado, $filtros, false),
                $perPage,
            );
        }

        $filtrosQuery = MovCcostoListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $empresaRefId = (int) (($filtros['empresa_ids'] ?? [])[0] ?? 0);
        $multiempresa = count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas']);
        $logosFuente = $consultado
            ? collect($this->reporteService->nombresEmpresaSeleccionadas($filtros))
                ->map(fn ($nombre) => ['nombreempresa' => $nombre])
                ->values()
                ->all()
            : [];

        return view('contable.movccosto.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'columnas' => $columnas,
            'totales' => $totales,
            'advertencias' => $advertencias,
            'moneda' => $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1)),
            'cuenta_desde_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_desde'] ?? 0), $empresaRefId),
            'cuenta_hasta_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_hasta'] ?? 0), $empresaRefId),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'agrupacion_texto' => $this->reporteService->formatearAgrupacionTexto($filtros),
            'centros_texto' => $this->reporteService->formatearCentrosTexto($filtros),
            'centrocostos_iniciales' => $this->centrosIniciales($filtros),
            'grupos_iniciales' => $this->gruposIniciales($filtros),
            'cc_desde_meta' => $this->metaCentrocosto((string) ($filtros['cc_desde'] ?? '')),
            'cc_hasta_meta' => $this->metaCentrocosto((string) ($filtros['cc_hasta'] ?? '')),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_mayor' => can('listar-mayor-plano-cuenta', false),
            'mayor_base' => $this->mayorBase($filtros),
            'multiempresa' => $multiempresa,
            'logos_fuente' => $logosFuente,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-movccosto');
        MovCcostoRuntimeSupport::elevarLimites();

        $filtros = MovCcostoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if (! MovCcostoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('movccosto');
        }

        $resultado = $this->obtenerResultado($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                return $this->descargarPdf($filtros, $resultado);

            case 'EXCEL':
                return (new MovCcostoExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'xlsx'));

            case 'CSV':
                return (new MovCcostoExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download($this->armarNombreArchivoExport($filtros, 'csv'), Excel::CSV);
        }

        return redirect()->route('movccosto', array_merge(
            MovCcostoListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * Conciliación: movimientos sin centro de costo en cuentas que manejan centro de costo.
     */
    public function conciliacion(Request $request)
    {
        can('listar-movccosto');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();

        $filtros = MovCcostoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaultsEmpresas($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);
        $soloResumen = $request->boolean('resumen');

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'] ?? [],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
        }

        $consultado = false;
        $resultado = null;
        $filas = null;

        if (MovCcostoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            if ($request->boolean('consultar')) {
                MovCcostoRuntimeSupport::elevarLimites();
                $resultado = $this->generarYCachearConciliacion($filtros);
                $consultado = true;
            } else {
                $resultado = $this->leerCacheConciliacion($filtros);
                $consultado = $resultado !== null;
            }
        }

        $filtrosQuery = $this->queryConciliacion($filtros, $soloResumen);
        if ($consultado && $resultado !== null) {
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            if ($request->has('per_page')) {
                $filtrosQuery['per_page'] = $perPage;
            }
            $filas = $this->conciliacionService->paginarFilas(
                $this->conciliacionService->aplanarFilas($resultado, $soloResumen),
                $perPage,
            );
            $filas->appends($filtrosQuery);
        }

        $empresaRefId = (int) (($filtros['empresa_ids'] ?? [])[0] ?? 0);
        $logosFuente = $consultado
            ? collect($this->reporteService->nombresEmpresaSeleccionadas($filtros))
                ->map(fn ($nombre) => ['nombreempresa' => $nombre])
                ->values()
                ->all()
            : [];

        return view('contable.movccosto.conciliacion.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'solo_resumen' => $soloResumen,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $resultado['totales'] ?? [],
            'advertencias' => $resultado['advertencias'] ?? [],
            'moneda' => $this->monedaRepository->find((int) ($filtros['moneda_id'] ?? 1)),
            'cuenta_desde_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_desde'] ?? 0), $empresaRefId),
            'cuenta_hasta_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_hasta'] ?? 0), $empresaRefId),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'multiempresa' => $this->columnaEmpresaConciliacion($filtros, $soloResumen),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_mayor' => can('listar-mayor-plano-cuenta', false),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'mayor_base' => $this->mayorBase($filtros) + ['mostrar_columna_centrocosto' => 1],
            'reporte_query' => array_merge(MovCcostoListadoFiltros::paraQueryString($filtros), ['consultar' => 1]),
            'logos_fuente' => $logosFuente,
        ]);
    }

    public function exportarConciliacion(Request $request, string $formato)
    {
        can('listar-movccosto');
        MovCcostoRuntimeSupport::elevarLimites();

        $filtros = MovCcostoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);
        $soloResumen = $request->boolean('resumen');

        if (! MovCcostoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('movccosto_conciliacion');
        }

        $resultado = $this->leerCacheConciliacion($filtros) ?? $this->generarYCachearConciliacion($filtros);
        $filas = $this->conciliacionService->aplanarFilas($resultado, $soloResumen);
        $titulo = 'Conciliación: movimientos sin centro de costo';
        $subtitulo = $this->armarSubtituloConciliacion($filtros, $soloResumen);
        $nombre = $this->armarNombreArchivoExport($filtros, '', 'conciliacion_sin_cc');
        $multiempresa = $this->columnaEmpresaConciliacion($filtros, $soloResumen);

        switch (strtoupper($formato)) {
            case 'PDF':
                $html = view('contable.movccosto.conciliacion.listado', [
                    'filas' => $filas,
                    'solo_resumen' => $soloResumen,
                    'multiempresa' => $multiempresa,
                    'totales' => $resultado['totales'] ?? [],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($this->fuenteLogos($filtros)),
                    'totalFilas' => count($filas),
                ])->render();
                $ruta = storage_path('pdf/listados/'.$nombre.'.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => $titulo,
                ]);

                return response()->download($ruta, $nombre.'.pdf')->deleteFileAfterSend(true);

            case 'EXCEL':
            case 'CSV':
                $export = (new MovCcostoConciliacionExport)->parametros(
                    $filas,
                    $resultado['totales'] ?? [],
                    $soloResumen,
                    $multiempresa,
                    $titulo,
                    $subtitulo,
                    $this->fuenteLogos($filtros),
                );

                return strtoupper($formato) === 'CSV'
                    ? $export->download($nombre.'.csv', Excel::CSV)
                    : $export->download($nombre.'.xlsx');
        }

        return redirect()->route('movccosto_conciliacion', array_merge(
            $this->queryConciliacion($filtros, $soloResumen),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function queryConciliacion(array $filtros, bool $soloResumen): array
    {
        $query = MovCcostoListadoFiltros::paraQueryString($filtros);
        // Agrupación, formato y centros no aplican: la conciliación mira los movimientos sin centro.
        unset($query['agrupar'], $query['formato'], $query['con_totales'], $query['incluir_sin_cc'],
            $query['centrocostos_codigo'], $query['cc_desde'], $query['cc_hasta'], $query['grupos_codigo']);
        if ($soloResumen) {
            $query['resumen'] = 1;
        }

        return $query;
    }

    /**
     * Columna Empresa: en el detalle siempre que haya varias; en el resumen solo si no se consolida.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function columnaEmpresaConciliacion(array $filtros, bool $soloResumen): bool
    {
        if (count($filtros['empresa_ids'] ?? []) <= 1) {
            return false;
        }

        return ! $soloResumen || empty($filtros['consolidar_empresas']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{nombreempresa: string}>
     */
    private function fuenteLogos(array $filtros): array
    {
        return collect($this->reporteService->nombresEmpresaSeleccionadas($filtros))
            ->map(fn ($nombre) => ['nombreempresa' => $nombre])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function armarSubtituloConciliacion(array $filtros, bool $soloResumen): string
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
        $cuentas = $this->textoRangoCuentas($filtros);
        if ($cuentas !== '') {
            $partes[] = $cuentas;
        }
        $partes[] = $this->reporteService->formatearInclusionAsientosTexto($filtros);
        $partes[] = $soloResumen ? 'Resumen por cuenta' : 'Detalle de movimientos';

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachearConciliacion(array $filtros): array
    {
        $resultado = $this->conciliacionService->generarDesdeFiltros($filtros);
        Cache::store('file')->put($this->cacheKeyConciliacion($filtros), [
            'firma' => $this->firmaConciliacion($filtros),
            'resultado' => $resultado,
        ], now()->addHours(4));

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function leerCacheConciliacion(array $filtros): ?array
    {
        $pack = Cache::store('file')->get($this->cacheKeyConciliacion($filtros));

        if (is_array($pack)
            && isset($pack['resultado'])
            && is_array($pack['resultado'])
            && ($pack['firma'] ?? '') === $this->firmaConciliacion($filtros)) {
            return $pack['resultado'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function firmaConciliacion(array $filtros): string
    {
        return md5(json_encode($this->queryConciliacion($filtros, false)));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cacheKeyConciliacion(array $filtros): string
    {
        return 'movccosto_conc_v1_'.(int) (auth()->id() ?? 0).'_'.$this->firmaConciliacion($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    private function descargarPdf(array $filtros, array $resultado)
    {
        $filas = $this->reporteService->aplanarFilas($resultado, $filtros, true);
        $columnas = $resultado['columnas'] ?? [];
        $formato = (string) ($filtros['formato'] ?? MovCcostoListadoFiltros::FORMATO_SALDO);

        $porBloque = $formato === MovCcostoListadoFiltros::FORMATO_DEBE_HABER
            ? max(1, (int) config('contable.movccosto.pdf_columnas_por_bloque_debe_haber', 4))
            : max(1, (int) config('contable.movccosto.pdf_columnas_por_bloque_saldo', 9));
        $bloques = $columnas === []
            ? [[]]
            : array_chunk(array_keys($columnas), $porBloque);

        $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);

        $ultimo = count($bloques) - 1;
        $filasPorBloque = [];
        foreach ($bloques as $n => $indices) {
            $filasPorBloque[$n] = $this->reporteService->filasParaBloque(
                $filas,
                $indices,
                $n === $ultimo && count($columnas) > 1,
            );
        }

        $html = view('contable.movccosto.listado', [
            'filas' => $filas,
            'filasPorBloque' => $filasPorBloque,
            'columnas' => $columnas,
            'bloques' => $bloques,
            'filtros' => $filtros,
            'formato' => $formato,
            'totales' => $resultado['totales'] ?? [],
            'titulo' => 'Totales por centro de costo',
            'subtitulo' => $this->armarSubtituloExport($filtros),
            'logosCabecera' => $logosCabecera,
            'totalFilas' => count($filas),
        ])->render();

        $nombre = $this->armarNombreArchivoExport($filtros, '');
        $ruta = storage_path('pdf/listados/'.$nombre.'.pdf');
        DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
            'titulo_corto' => 'Totales por centro de costo',
        ]);

        return response()->download($ruta, $nombre.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * Parámetros base del link al Mayor plano desde una celda (se completa con cuenta y centros).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function mayorBase(array $filtros): array
    {
        [$desde, $hasta] = MovCcostoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $base = [
            'modo_periodo' => 'rango',
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_inclusion_asientos' => (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']) ? 1 : 0,
            'consultar' => 1,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ];
        if (! empty($filtros['solo_moneda_origen'])) {
            $base['solo_moneda_origen'] = 1;
        }
        $base['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));

        return $base;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function obtenerResultado(array $filtros): array
    {
        return $this->leerCache($filtros) ?? $this->generarYCachear($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachear(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        Cache::store('file')->put($this->cacheKey($filtros), [
            'firma' => MovCcostoListadoFiltros::firma($filtros),
            'resultado' => $resultado,
        ], now()->addHours(4));

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function leerCache(array $filtros): ?array
    {
        $pack = Cache::store('file')->get($this->cacheKey($filtros));

        if (is_array($pack)
            && isset($pack['resultado'])
            && is_array($pack['resultado'])
            && ($pack['firma'] ?? '') === MovCcostoListadoFiltros::firma($filtros)) {
            return $pack['resultado'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cacheKey(array $filtros): string
    {
        return 'movccosto_v1_'.(int) (auth()->id() ?? 0).'_'.MovCcostoListadoFiltros::firma($filtros);
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

        $partes[] = $this->reporteService->formatearAgrupacionTexto($filtros);

        $centros = $this->reporteService->formatearCentrosTexto($filtros);
        if ($centros !== '') {
            $partes[] = $centros;
        }

        $cuentas = $this->textoRangoCuentas($filtros);
        if ($cuentas !== '') {
            $partes[] = $cuentas;
        }

        $partes[] = $this->reporteService->formatearInclusionAsientosTexto($filtros);

        if (! empty($filtros['solo_moneda_origen'])) {
            $partes[] = 'Solo moneda origen';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function textoRangoCuentas(array $filtros): string
    {
        $d = (int) ($filtros['cuenta_desde'] ?? 0);
        $h = (int) ($filtros['cuenta_hasta'] ?? 0);
        if ($d <= 0 && $h <= 0) {
            return '';
        }

        return 'Cuentas: '.($d > 0 ? MayorPlanoCuentaSupport::formatearCodigoCuenta($d) : '…')
            .' a '.($h > 0 ? MayorPlanoCuentaSupport::formatearCodigoCuenta($h) : '…');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{codigo: string, nombre: string}>
     */
    private function centrosIniciales(array $filtros): array
    {
        $codigos = MayorPlanoCuentaCentrocostoFiltroSupport::parsearCodigos($filtros['centrocostos_codigo'] ?? '');
        if ($codigos === []) {
            return [];
        }

        $nombres = DB::table('centrocosto')->whereIn('codigo', $codigos)->pluck('nombre', 'codigo');

        return array_map(
            static fn (string $c): array => ['codigo' => $c, 'nombre' => (string) ($nombres[$c] ?? '')],
            $codigos,
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{codigo: string, nombre: string}>
     */
    private function gruposIniciales(array $filtros): array
    {
        $codigos = MovCcostoListadoFiltros::parsearGrupos($filtros['grupos_codigo'] ?? '');
        if ($codigos === []) {
            return [];
        }

        $nombres = [];
        foreach (DB::table('centrocosto')->get(['grupo_codigo', 'grupo_nombre']) as $cc) {
            $codigo = MovCcostoListadoFiltros::normalizarGrupo($cc->grupo_codigo);
            if (! isset($nombres[$codigo]) && trim((string) $cc->grupo_nombre) !== '') {
                $nombres[$codigo] = trim((string) $cc->grupo_nombre);
            }
        }

        return array_map(
            static fn (string $c): array => [
                'codigo' => $c,
                'nombre' => $nombres[$c] ?? ($c === '0' ? 'Sin grupo' : ''),
            ],
            $codigos,
        );
    }

    /**
     * @return array{codigo: string, nombre: string}
     */
    private function metaCentrocosto(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['codigo' => '', 'nombre' => ''];
        }

        $nombre = DB::table('centrocosto')->where('codigo', $codigo)->value('nombre');

        return ['codigo' => $codigo, 'nombre' => $nombre ? (string) $nombre : ''];
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

        return ['codigo' => $codigoFmt, 'nombre' => $nombre ? (string) $nombre : ''];
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
     */
    private function armarNombreArchivoExport(array $filtros, string $extension, string $base = 'totales_centro_costo'): string
    {
        [$desde, $hasta] = MovCcostoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $desde = str_replace('-', '', $desde);
        $hasta = str_replace('-', '', $hasta);

        if ($desde !== '' && $hasta !== '') {
            $base .= '_'.($desde === $hasta ? $desde : $desde.'_'.$hasta);
        }
        $empresas = implode('-', array_map('intval', $filtros['empresa_ids'] ?? []));
        if ($empresas !== '') {
            $base .= '_e'.$empresas;
        }
        $base .= '_'.date('His');

        return $extension === '' ? $base : $base.'.'.$extension;
    }
}
