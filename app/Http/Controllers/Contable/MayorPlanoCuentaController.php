<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\MayorPlanoCuentaExport;
use App\Exports\Contable\MayorPlanoCuentaMultiExport;
use App\Http\Controllers\Controller;
use App\Jobs\Contable\GenerarMayorPlanoCuentaJob;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCacheSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaConsultaAsyncSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCsvExportSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ReportePreferenciasUsuario::persistirBool(
                self::PREFERENCIAS_CLAVE,
                'mostrar_columna_centrocosto',
                MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros),
            );
        }

        $consultado = false;
        $filas = null;
        $resumen = [];
        $resumenCc = [];
        $totales = null;
        $erroresBridge = [];
        $resultado = null;
        $cuadreCobroVentas = null;

        if ($request->boolean('consultar') && MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            // Período largo + cuentas amplias: cola + mail. Mes / pocas cuentas: pantalla.
            if (MayorPlanoCuentaConsultaAsyncSupport::debeEncolar($filtros)) {
                return $this->encolarConsultaLarga($filtros);
            }

            MayorPlanoCuentaRuntimeSupport::elevarLimites();
            // Si el browser cortó a mitad de un run anterior, el cache puede estar listo.
            if ($this->leerCache($filtros) === null) {
                ignore_user_abort(true);
                $this->generarYCachear($filtros);
            }

            // PRG: no armar HTML en la misma request.
            return redirect()
                ->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
                ->with('mensaje', 'Mayor listo. Mostrando resultado desde cache.');
        }

        // Tras encolar período largo: no pintar cache viejo (engañaba: flash verde 3s + grilla enorme).
        $omitirCachePantalla = (bool) session('mayor_plano_async_pendiente');

        if (! $omitirCachePantalla && MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            if ($resultado !== null) {
                $consultado = true;
            }
        }

        if ($consultado && $resultado !== null) {
            MayorPlanoCuentaRuntimeSupport::elevarLimites();
            $t0 = microtime(true);
            $totales = $this->armarTotalesDesdeResultado($resultado);
            $resumen = $this->reporteService->resumenPorCuenta($resultado);
            $resumenCc = $this->reporteService->resumenPorCentrocosto($resultado);
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $soloTotalesVentas = ! empty($filtros['solo_movimientos_ventas']);
            $cuadreCobroVentas = $soloTotalesVentas
                ? $this->reporteService->cuadreCobroVentasDesdeResumen($resumen)
                : null;
            $filas = $soloTotalesVentas
                ? $this->reporteService->paginarFilas([], $perPage)
                : $this->reporteService->aplanarYPaginarParaPantalla($resultado, $filtros, $perPage);
            \Illuminate\Support\Facades\Log::info('mayor_plano_cuenta.render_pantalla', [
                'lineas' => (int) ($resultado['totales']['lineas'] ?? 0),
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'mem_mb' => round(memory_get_usage(true) / 1048576, 1),
            ]);
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
            'resumen_cc' => $resumenCc,
            'totales' => $totales,
            'errores_bridge' => $erroresBridge,
            'moneda' => $moneda,
            'cuenta_desde_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_desde'] ?? 0), $empresaRefId),
            'cuenta_hasta_meta' => $this->metaCuentaFiltro((int) ($filtros['cuenta_hasta'] ?? 0), $empresaRefId),
            'cuentas_iniciales' => $this->metaCuentasParticulares($filtros['cuentas'] ?? [], $empresaRefId),
            'cc_desde_meta' => $this->metaCentrocostoFiltro((string) ($filtros['cc_desde'] ?? '')),
            'cc_hasta_meta' => $this->metaCentrocostoFiltro((string) ($filtros['cc_hasta'] ?? '')),
            'centrocostos_iniciales' => $this->metaCentrocostosParticulares(
                (string) ($filtros['centrocostos_codigo'] ?? ''),
            ),
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'empresas_texto' => $this->reporteService->formatearEmpresasTexto($filtros),
            'inclusion_asientos_texto' => $this->reporteService->formatearInclusionAsientosTexto($filtros),
            'centrocostos_texto' => $this->reporteService->formatearCentrocostosTexto($filtros),
            'origen_movimientos_texto' => $this->reporteService->formatearOrigenMovimientosTexto($filtros),
            'solo_totales_ventas' => ! empty($filtros['solo_movimientos_ventas']),
            'cuadre_cobro_ventas' => $cuadreCobroVentas ?? null,
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_ordencompra' => can('listar-ordencompra', false) || can('editar-ordencompra', false),
            'puede_ver_proveedor' => can('listar-proveedor', false) || can('editar-proveedor', false),
            'puede_ver_cliente' => can('listar-clientes', false) || can('editar-clientes', false),
            'puede_ver_cuentacaja' => can('listar-cuentas-de-caja', false) || can('editar-cuentas-de-caja', false),
            'puede_ver_comprobante_proveedor' => can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false),
            'puede_ver_factura' => can('listar-factura', false) || can('editar-factura', false),
            'puede_ver_remesa' => can('listar-remesa', false) || can('editar-remesa', false),
            'puede_ver_jornada_gastronomia' => can('listar-waitry-cierre-jornada-caja', false),
            'puede_ver_rendicion_estacionamiento' => can('listar-rendicion-estacionamiento-caja', false) || can('editar-rendicion-estacionamiento-caja', false),
            'puede_ver_transferencia_mercaderia' => can('crear-transferencia-mercaderia', false) || can('listar-transferencias-pendientes', false),
            'puede_ver_cobranza' => can('listar-cobranza', false) || can('editar-cobranza', false),
            'puede_ver_pagoproveedor' => can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false),
            'puede_ver_recepcion_proveedor' => can('listar-recepcion-proveedor', false) || can('editar-recepcion-proveedor', false),
            'puede_ver_movimientostock' => can('listar-movimientos-de-stock', false) || can('editar-movimientos-de-stock', false),
            'puede_ver_caja_movimiento' => can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false),
            'puede_ver_solicitudpago' => can('listar-solicitud-pago', false) || can('editar-solicitud-pago', false),
            'multiempresa' => count($filtros['empresa_ids'] ?? []) > 1
                || empty($filtros['consolidar_empresas']),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-mayor-plano-cuenta');

        MayorPlanoCuentaRuntimeSupport::elevarLimites();

        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest($request);
        if (! $request->has('mostrar_columna_centrocosto')) {
            $filtros['mostrar_columna_centrocosto'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'mostrar_columna_centrocosto',
                true,
            );
        }
        $this->assertAccesoEmpresas($filtros['empresa_ids'] ?? []);

        if (! MayorPlanoCuentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('mayor_plano_cuenta');
        }

        // Reutilizar resultado de pantalla (evita regenerar Anita al exportar).
        $resultado = $this->obtenerResultado($filtros);
        $formatoNorm = strtoupper($formato);
        $lineas = (int) ($resultado['totales']['lineas'] ?? 0);

        // Excel plano: siempre CSV streameado (mismas columnas enriquecidas).
        // PhpSpreadsheet FromView arma HTML de decenas de MB y traba el download (~17k filas).
        if ($formatoNorm === 'EXCEL_PLANO') {
            ignore_user_abort(true);
            Log::info('mayor_plano_cuenta.export_excel_plano_csv', [
                'lineas' => $lineas,
                'usuario_id' => (int) (auth()->id() ?? 0),
            ]);

            return $this->descargarCsvPlanoStream($filtros, $resultado, true);
        }

        // PhpSpreadsheet / DomPDF no bancan volúmenes grandes: CSV streameado.
        if ($lineas > 15000 && in_array($formatoNorm, ['EXCEL', 'PDF', 'CSV'], true)) {
            if ($formatoNorm === 'PDF') {
                return redirect()->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
                    ->with('error', 'El PDF no admite este volumen ('.$lineas.' movimientos). Use Excel plano o CSV.');
            }

            ignore_user_abort(true);

            return $this->descargarCsvPlanoStream($filtros, $resultado, $formatoNorm === 'CSV');
        }

        $filas = $this->reporteService->aplanarFilas($resultado, $filtros, true);
        $resumen = $this->reporteService->resumenPorCuenta($resultado);
        $cuadreCobroVentas = ! empty($filtros['solo_movimientos_ventas'])
            ? $this->reporteService->cuadreCobroVentasDesdeResumen($resumen)
            : null;
        $totales = $this->armarTotalesDesdeResultado($resultado);
        $titulo = ! empty($filtros['solo_movimientos_ventas'])
            ? 'Mayor analítico por cuenta — solo movimientos de ventas'
            : 'Mayor analítico por cuenta contable';
        $subtitulo = $this->armarSubtituloExport($filtros);

        switch ($formatoNorm) {
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
                    'cuadreCobroVentas',
                ))->render();

                return $this->descargarPdf(
                    $view,
                    $this->armarNombreArchivoExport($filtros, ''),
                    'legal',
                    'landscape',
                );

            case 'EXCEL':
                $solapasSeparadas = ! empty($filtros['excel_solapas_separadas'])
                    && MayorPlanoCuentaListadoFiltros::puedeExcelSolapasSeparadas($filtros);

                if ($solapasSeparadas) {
                    return (new MayorPlanoCuentaMultiExport($this->reporteService))
                        ->parametros($filtros, $resultado)
                        ->download($this->armarNombreArchivoExport($filtros, 'xlsx'));
                }

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
     * Excel plano → CSV en disco y descarga (sin PhpSpreadsheet ni IA).
     * Escribir el archivo completo evita buffers de Apache/proxy que tragan el stream.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    private function descargarCsvPlanoStream(array $filtros, array $resultado, bool $estiloAnita)
    {
        $nombre = $this->armarNombreArchivoExport(
            $filtros,
            'csv',
            $estiloAnita ? 'mayor_plano_anita' : 'mayor_analitico_cuenta',
        );

        $stamp = now()->format('Ymd_His');
        $usuarioId = (int) (auth()->id() ?? 0);
        $rutaRelativa = 'exports/mayor_plano_sync/'.$stamp.'_u'.$usuarioId.'_'.$nombre;
        $rutaAbsoluta = storage_path('app/public/'.$rutaRelativa);

        $t0 = microtime(true);
        $export = MayorPlanoCuentaCsvExportSupport::escribirCsvExcelPlano(
            $this->reporteService,
            $resultado,
            $filtros,
            $rutaAbsoluta,
        );
        Log::info('mayor_plano_cuenta.export_excel_plano_csv_ok', [
            'lineas' => $export['filas'],
            'bytes' => $export['bytes'],
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'archivo' => $rutaRelativa,
        ]);

        if ($export['bytes'] <= 0 || ! is_file($rutaAbsoluta)) {
            return redirect()
                ->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
                ->with('mensaje-error', 'No se pudo generar el CSV del Excel plano.');
        }

        return response()->download($rutaAbsoluta, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function encolarConsultaLarga(array $filtros)
    {
        $usuario = auth()->user();
        $usuarioId = (int) (auth()->id() ?? 0);
        $email = trim((string) ($usuario->email ?? ''));

        if ($usuarioId <= 0) {
            abort(403);
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
                ->with('mensaje-error', 'Tu usuario no tiene un email válido; no se puede enviar el mayor de período largo por correo. Pedí un mes por pantalla o cargá el email en tu usuario.');
        }

        try {
            Bus::dispatch(new GenerarMayorPlanoCuentaJob($filtros, $usuarioId));
        } catch (\Throwable $e) {
            Log::error('mayor_plano_cuenta.async.dispatch_fallo', [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
                ->with('mensaje-error', 'No se pudo encolar el mayor: '.$e->getMessage());
        }

        $periodo = $this->reporteService->formatearPeriodoTexto($filtros);
        $dias = MayorPlanoCuentaConsultaAsyncSupport::diasPeriodo($filtros);
        Log::info('mayor_plano_cuenta.async.encolado', [
            'usuario_id' => $usuarioId,
            'email' => $email,
            'dias' => $dias,
            'periodo' => $periodo,
            'firma' => MayorPlanoCuentaListadoFiltros::firma($filtros),
        ]);

        return redirect()
            ->route('mayor_plano_cuenta', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros))
            ->with(
                'mensaje-aviso',
                'Período largo ('.$dias.' días'.($periodo !== '' ? ', '.$periodo : '').') con todas las cuentas o un rango/lista grande: '
                .'el mayor se genera en segundo plano. Cuando termine te llega un mail a '.$email.' con el Excel plano (CSV). '
                .'Con pocas cuentas (aunque el período sea largo) sigue saliendo en pantalla para analizar y exportar. Este aviso no se cierra solo.'
            )
            ->with('mayor_plano_async_pendiente', 1);
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
        $lineas = (int) ($resultado['totales']['lineas'] ?? 0);
        \Illuminate\Support\Facades\Log::info('mayor_plano_cuenta.post_generar', [
            'lineas' => $lineas,
            'secciones' => count($resultado['secciones'] ?? []),
            'mem_mb' => round(memory_get_usage(true) / 1048576, 1),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);
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
        // Secciones gzip por archivo: evita serialize del pack completo (OOM ene–ago).
        MayorPlanoCuentaCacheSupport::guardar($resultado, $filtros);

        // Solo marca de firma en sesión (el payload grande va a disco).
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
        $resultado = MayorPlanoCuentaCacheSupport::recuperar($filtros);
        if ($resultado !== null) {
            return $resultado;
        }

        // Limpiar sesión legacy hinchada (formato viejo con resultado completo).
        $legacy = session(self::SESSION_CACHE_KEY);
        if (is_array($legacy) && isset($legacy['resultado'])) {
            session()->forget(self::SESSION_CACHE_KEY);
        }

        if (is_array($legacy) && ($legacy['firma'] ?? '') !== '' && ($legacy['firma'] ?? '') !== $firma) {
            return null;
        }

        return null;
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
        $partes[] = $this->reporteService->formatearCentrocostosTexto($filtros);

        $origen = $this->reporteService->formatearOrigenMovimientosTexto($filtros);
        if ($origen !== '') {
            $partes[] = $origen;
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

    /** @return array{codigo: string, nombre: string} */
    private function metaCentrocostoFiltro(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['codigo' => '', 'nombre' => ''];
        }

        $nombre = DB::table('centrocosto')->where('codigo', $codigo)->value('nombre');

        return ['codigo' => $codigo, 'nombre' => $nombre ? (string) $nombre : ''];
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private function metaCentrocostosParticulares(string $codigos): array
    {
        $lista = MayorPlanoCuentaCentrocostoFiltroSupport::parsearCodigos($codigos);
        if ($lista === []) {
            return [];
        }

        $nombres = DB::table('centrocosto')->whereIn('codigo', $lista)->pluck('nombre', 'codigo')->all();

        return array_map(static fn (string $codigo): array => [
            'codigo' => $codigo,
            'nombre' => isset($nombres[$codigo]) ? (string) $nombres[$codigo] : '',
        ], $lista);
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

        if (! $request->has('mostrar_columna_centrocosto')) {
            $filtros['mostrar_columna_centrocosto'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'mostrar_columna_centrocosto',
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
                $cuadreCobroVentas = ! empty($filtros['solo_movimientos_ventas'])
                    ? $this->reporteService->cuadreCobroVentasDesdeResumen($resumen)
                    : null;
                $totales = $this->armarTotalesDesdeResultado($resultado);
                $subtitulo = $this->armarSubtituloExport($filtrosEmpresa);

                $view = \View::make('contable.mayor_plano_cuenta.listado', [
                    'filas' => $filas,
                    'resumen' => $resumen,
                    'filtros' => $filtrosEmpresa,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'cuadreCobroVentas' => $cuadreCobroVentas,
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
    private function armarNombreArchivoExport(array $filtros, string $extension, string $prefijo = 'mayor_analitico_cuenta'): string
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
            $prefijo !== '' ? $prefijo : 'mayor_analitico_cuenta',
            $periodo,
            $empPart,
            date('His'),
        ], fn ($p) => $p !== '' && $p !== null);

        $base = implode('_', $partes);
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '', $base) ?? $base;

        return $extension !== '' ? $base.'.'.$extension : $base;
    }
}
