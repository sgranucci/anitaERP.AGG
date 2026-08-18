<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Exports\Ventas\GastronomiaConciliacionDiariaReporteExport;
use App\Mail\Ventas\GastronomiaAuditoriaMediosMensual;
use App\Mail\Ventas\GastronomiaConciliacionDiariaReporte;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstacionamientoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionFlashSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionMedioPagoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionGastroTotalDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionRendgAsientosDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlFlashSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use App\Support\Ventas\Gastronomia\GastronomiaVentasSoloErpSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Reporte día a día: ventas ERP vs cabeceras Anita vs rendgastro Z, por PC (CAE + CAEA).
 */
final class GastronomiaConciliacionDiariaReporteService
{
    public function __construct(
        private readonly GastronomiaConciliacionPorPcSupport $conciliacionPorPcSupport,
        private readonly GastronomiaConciliacionPostCierreCaeaSupport $postCierreCaeaSupport,
        private readonly GastronomiaConciliacionGastroTotalDiaSupport $gastroTotalDiaSupport,
        private readonly GastronomiaConciliacionRendgAsientosDiaSupport $rendgAsientosDiaSupport,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaAnitaMesCacheSupport $anitaMesCacheSupport,
        private readonly GastronomiaConciliacionEstacionamientoSupport $estacionamientoSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
        private readonly GastronomiaAuditoriaHuecosNumeracionService $huecosNumeracionService,
        private readonly GastronomiaConciliacionFlashSupport $flashSupport,
        private readonly GastronomiaConciliacionMedioPagoSupport $medioPagoSupport,
    ) {
    }

    /**
     * @param  list<int>  $empresasIds
     * @return array{
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   tolerancia: float,
     *   empresas: list<array{
     *     empresa_id: int,
     *     empresa_nombre: string,
     *     dias: list<array{
     *       fecha_jornada: string,
     *       filas: list<array<string, mixed>>,
     *       totales: array<string, float|null>
     *     }>
     *   }>
     * }
     */
    /**
     * Empresas cuya jornada está cerrada en todas las fechas del rango (para schedule diario).
     *
     * @param  list<int>  $empresasIds
     * @return list<int>
     */
    public function filtrarEmpresasJornadaCerrada(array $empresasIds, string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $filtradas = [];

        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $incluir = true;
            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                if ($this->esJornadaPreMigracion($empresaId, $dia->toDateString())) {
                    continue;
                }
                if (! $this->jornadaEstaCerrada($empresaId, $dia->toDateString())) {
                    $incluir = false;
                    break;
                }
            }

            if ($incluir) {
                $filtradas[] = $empresaId;
            }
        }

        return $filtradas;
    }

    public function generar(
        string $fechaDesde,
        string $fechaHasta,
        array $empresasIds,
        float $tolerancia = 0.02,
        bool $forzarCacheAnita = true,
        ?bool $usarCacheAnita = null,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $usarCache = $usarCacheAnita ?? (bool) config('gastronomia.conciliacion_diaria_reporte.usar_cache_anita', true);

        $empresas = [];
        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $empresa = Empresa::query()->find($empresaId);
            $empresaCodigo = (int) ($empresa->codigo ?? $empresaId);
            $flashOffset = $this->controlFlashJornadaOffsetDias();
            $flashDesde = Carbon::parse($desde)->subDays($flashOffset)->toDateString();
            $flashHasta = Carbon::parse($hasta)->subDays($flashOffset)->toDateString();
            $flashDesglosePorJornada = $this->cargarFlashDesgloseEmpresa($empresaCodigo, $flashDesde, $flashHasta);
            $indiceAnitaBulk = null;
            $cacheManifest = null;

            $rangoSoloErp = GastronomiaVentasSoloErpSupport::esJornada($empresaId, $desde);
            if ($usarCache && ! $rangoSoloErp) {
                $cacheManifest = $this->resolverIndiceAnitaBulk(
                    $empresaId,
                    $desde,
                    $hasta,
                    $forzarCacheAnita,
                    $indiceAnitaBulk,
                );
            }

            $dias = [];

            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $fechaJornada = $dia->toDateString();
                if ($this->esJornadaPreMigracion($empresaId, $fechaJornada)) {
                    continue;
                }
                $dias[] = $this->armarDia($empresaId, $fechaJornada, $tolerancia, $indiceAnitaBulk, $flashDesglosePorJornada);
            }

            $empresas[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'dias' => $dias,
                'anita_cache' => $cacheManifest,
                'huecos_rango' => $this->huecosNumeracionService->auditarRango(
                    $desde,
                    $hasta,
                    [$empresaId],
                    null,
                    $usarCache,
                    false,
                ),
            ];
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'usar_cache_anita' => $usarCache,
            'empresas' => $empresas,
            'hay_diferencias' => $this->hayDiferencias(['empresas' => $empresas]),
            'hay_huecos_numeracion' => $this->huecosNumeracionService->hayHuecos(['empresas' => $empresas]),
        ];
    }

    /**
     * Control mensual por medio SIN el cache pesado de Anita (venta/huecos): computa directo por jornada
     * venta ERP, contabilidad global, flash (rendgastro) y conciliación por medio (Z vs contabilizado).
     *
     * @param  list<int>  $empresasIds
     * @return list<array<string, mixed>>
     */
    public function resumenMensualMediosDirecto(
        string $fechaDesde,
        string $fechaHasta,
        array $empresasIds,
        float $tolerancia = 0.02,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $flashOffset = $this->controlFlashJornadaOffsetDias();

        $out = [];
        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }
            $empresa = Empresa::query()->find($empresaId);
            $empresaCodigo = (int) ($empresa->codigo ?? $empresaId);

            $flashDesde = Carbon::parse($desde)->subDays($flashOffset)->toDateString();
            $flashHasta = Carbon::parse($hasta)->subDays($flashOffset)->toDateString();
            $flashDesglose = $this->cargarFlashDesgloseEmpresa($empresaCodigo, $flashDesde, $flashHasta);

            $dias = [];
            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $fechaJornada = $dia->toDateString();
                if ($this->esJornadaPreMigracion($empresaId, $fechaJornada)) {
                    continue;
                }
                if ($this->jornadaAbierta($empresaId, $fechaJornada)) {
                    continue;
                }

                $ventaErpNeto = round((float) ($this->gastroTotalDiaSupport->totalesDiaEmpresa($empresaId, $fechaJornada)['neto'] ?? 0), 2);
                $contabilidad = round((float) ($this->rendgAsientosDiaSupport->auditarAsientosFacturacionJornada($empresaId, $fechaJornada)['total'] ?? 0), 2);
                $fechaFlash = $flashOffset > 0 ? Carbon::parse($fechaJornada)->subDays($flashOffset)->toDateString() : $fechaJornada;
                $flashDia = round((float) ($flashDesglose[$fechaFlash]['total_flash'] ?? 0), 2);
                $conc = $this->medioPagoSupport->conciliarJornada($empresaId, $fechaJornada, $tolerancia);

                $dias[] = [
                    'fecha_jornada' => $fechaJornada,
                    'jornada_abierta' => false,
                    'control_gastro_total' => ['ventas_erp' => $ventaErpNeto],
                    'control_rendg_asientos' => ['asientos_total' => $contabilidad],
                    'control_flash' => [['total_flash' => $flashDia, 'es_control_flash' => true]],
                    'conciliacion_medios' => $conc,
                ];
            }

            $out[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'dias' => $dias,
            ];
        }

        return $this->resumenMensualMedios(['empresas' => $out], $tolerancia);
    }

    /**
     * Control mensual por empresa: venta ERP, contabilidad global, flash, y por medio de pago
     * (Z vs contabilizado), agregando las jornadas del rango.
     *
     * @param  array<string, mixed>  $informe  salida de {@see generar()}
     * @return list<array<string, mixed>>
     */
    public function resumenMensualMedios(array $informe, float $tolerancia = 0.02): array
    {
        $out = [];
        foreach ($informe['empresas'] ?? [] as $empresa) {
            $ventaErp = 0.0;
            $contabilidadGlobal = 0.0;
            $flash = 0.0;
            $zPorMedio = [];
            $contabPorMedio = [];
            $jornadas = 0;
            $jornadasDif = [];
            $porDia = [];

            foreach ($empresa['dias'] ?? [] as $dia) {
                if (($dia['jornada_abierta'] ?? false) === true) {
                    continue;
                }
                $jornadas++;

                $ctrlGastro = $dia['control_gastro_total'] ?? null;
                if (is_array($ctrlGastro)) {
                    $ventaErp = round($ventaErp + (float) ($ctrlGastro['ventas_erp'] ?? 0), 2);
                }
                $ctrlAs = $dia['control_rendg_asientos'] ?? null;
                if (is_array($ctrlAs)) {
                    $contabilidadGlobal = round($contabilidadGlobal + (float) ($ctrlAs['asientos_total'] ?? 0), 2);
                }
                foreach ($this->filasControlFlash($dia) as $filaFlash) {
                    $flash = round($flash + (float) ($filaFlash['total_flash'] ?? 0), 2);
                }

                $conc = $dia['conciliacion_medios'] ?? null;
                $mediosDia = [];
                $totalZDia = 0.0;
                $totalContabDia = 0.0;
                if (is_array($conc)) {
                    foreach ($conc['medios'] ?? [] as $m) {
                        $cod = (string) ($m['cuenta_codigo'] ?? '');
                        if ($cod === '') {
                            continue;
                        }
                        $nombre = (string) ($m['cuenta_nombre'] ?? '');
                        $zMonto = (float) ($m['z'] ?? 0);
                        $cMonto = (float) ($m['contabilizado'] ?? 0);
                        $diff = $m['diff'] ?? round($zMonto - $cMonto, 2);
                        $estadoMedio = (string) ($m['estado'] ?? 'OK');
                        $fuente = (string) ($m['fuente_z'] ?? '');

                        if (! isset($zPorMedio[$cod])) {
                            $zPorMedio[$cod] = ['cuenta_codigo' => $cod, 'cuenta_nombre' => $nombre, 'total' => 0.0, 'fuente' => $fuente];
                        }
                        if (! isset($contabPorMedio[$cod])) {
                            $contabPorMedio[$cod] = ['cuenta_codigo' => $cod, 'cuenta_nombre' => $nombre, 'total' => 0.0];
                        }
                        $zPorMedio[$cod]['total'] = round($zPorMedio[$cod]['total'] + $zMonto, 2);
                        if ($fuente !== '' && ($zPorMedio[$cod]['fuente'] ?? '') === '') {
                            $zPorMedio[$cod]['fuente'] = $fuente;
                        }
                        $contabPorMedio[$cod]['total'] = round($contabPorMedio[$cod]['total'] + $cMonto, 2);

                        $totalZDia = round($totalZDia + $zMonto, 2);
                        $totalContabDia = round($totalContabDia + $cMonto, 2);
                        $mediosDia[] = [
                            'cuenta_codigo' => $cod,
                            'cuenta_nombre' => $nombre,
                            'fuente_z' => $fuente,
                            'z' => $zMonto,
                            'contabilizado' => $cMonto,
                            'diff' => (float) $diff,
                            'estado' => $estadoMedio,
                        ];
                    }
                    if (($conc['estado'] ?? '') === 'DIF') {
                        $mediosDif = [];
                        foreach ($conc['medios'] ?? [] as $m) {
                            if (($m['estado'] ?? '') === 'DIF') {
                                $mediosDif[] = [
                                    'cuenta_codigo' => $m['cuenta_codigo'] ?? '',
                                    'cuenta_nombre' => $m['cuenta_nombre'] ?? '',
                                    'fuente_z' => $m['fuente_z'] ?? '',
                                    'z' => (float) ($m['z'] ?? 0),
                                    'contabilizado' => (float) ($m['contabilizado'] ?? 0),
                                    'diff' => (float) ($m['diff'] ?? 0),
                                ];
                            }
                        }
                        $jornadasDif[] = [
                            'fecha_jornada' => (string) ($dia['fecha_jornada'] ?? ''),
                            'diff_total' => (float) ($conc['diff_total'] ?? 0),
                            'medios' => $mediosDif,
                        ];
                    }
                }

                $porDia[] = [
                    'fecha_jornada' => (string) ($dia['fecha_jornada'] ?? ''),
                    'medios' => $mediosDia,
                    'total_z' => $totalZDia,
                    'total_contabilizado' => $totalContabDia,
                    'diff_total' => round($totalZDia - $totalContabDia, 2),
                    'estado' => (string) ($conc['estado'] ?? 'OK'),
                ];
            }

            ksort($zPorMedio);
            ksort($contabPorMedio);

            $out[] = [
                'empresa_id' => (int) ($empresa['empresa_id'] ?? 0),
                'empresa_nombre' => (string) ($empresa['empresa_nombre'] ?? ''),
                'jornadas' => $jornadas,
                'venta_erp' => $ventaErp,
                'contabilidad_global' => $contabilidadGlobal,
                'diff_erp_contabilidad' => round($ventaErp - $contabilidadGlobal, 2),
                'flash' => $flash,
                'z_por_medio' => array_values($zPorMedio),
                'contabilizado_por_medio' => array_values($contabPorMedio),
                'total_z' => round(array_sum(array_column($zPorMedio, 'total')), 2),
                'total_contabilizado' => round(array_sum(array_column($contabPorMedio, 'total')), 2),
                'por_dia' => $porDia,
                'jornadas_dif_medio' => $jornadasDif,
                'estado' => $jornadasDif === [] ? 'OK' : 'DIF',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return list<array<int|string|float|null>>
     */
    public function construirFilasCsv(array $informe): array
    {
        $filas = [];
        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['dias'] ?? [] as $dia) {
                foreach ($dia['filas'] ?? [] as $fila) {
                    $filas[] = $this->filaCsvDesdeReporte($empresa, $dia, $fila);
                }
                if (! empty($dia['control_gastro_total'])) {
                    $filas[] = $this->filaCsvDesdeReporte($empresa, $dia, $dia['control_gastro_total']);
                }
                if (! empty($dia['control_rendg_asientos'])) {
                    $filas[] = $this->filaCsvDesdeReporte($empresa, $dia, $dia['control_rendg_asientos']);
                }
                foreach ($this->filasControlFlash($dia) as $filaFlash) {
                    $filas[] = $this->filaCsvDesdeReporte($empresa, $dia, $filaFlash);
                }
                foreach ($this->filasMedioPago($dia) as $filaMedio) {
                    $filas[] = $this->filaCsvDesdeReporte($empresa, $dia, $filaMedio);
                }
            }
        }

        return $filas;
    }

    /**
     * Filas «medio de pago» (conciliación Z ↔ contabilizado por cuenta) para CSV/consola.
     *
     * @param  array<string, mixed>  $dia
     * @return list<array<string, mixed>>
     */
    public function filasMedioPago(array $dia): array
    {
        $conc = $dia['conciliacion_medios'] ?? null;
        if (! is_array($conc) || ($conc['medios'] ?? []) === []) {
            return [];
        }

        $filas = [];
        foreach ($conc['medios'] as $m) {
            $filas[] = [
                'tipo_fila' => 'medio_pago',
                'circuito' => 'GASTRO',
                'identificador_pc' => 'MEDIO',
                'tipo_pv' => 'MEDIO',
                'pv_codigo' => (string) ($m['cuenta_codigo'] ?? ''),
                'descripcion_pc' => (string) ($m['cuenta_nombre'] ?? ''),
                'ventas_erp' => (float) ($m['contabilizado'] ?? 0),
                'ventas_anita' => (float) ($m['contabilizado'] ?? 0),
                'rendgastro_z' => (float) ($m['z'] ?? 0),
                'diff_erp_rendg' => $m['diff'] ?? null,
                'estado' => (string) ($m['estado'] ?? ''),
                'es_medio_pago' => true,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<int|string|float|null>>  $filasCsv
     */
    public function construirContenidoCsv(array $filasCsv): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear buffer CSV.');
        }

        fputcsv($handle, [
            'empresa_id', 'empresa_nombre', 'fecha_jornada', 'circuito', 'tipo_fila', 'tipo_pv',
            'identificador_pc', 'pv_codigo', 'pv_cae', 'pv_caea',
            'ventas_erp_cae', 'ventas_erp_caea', 'ventas_erp_total',
            'ventas_anita_cae', 'ventas_anita_caea', 'ventas_anita_total',
            'rendgastro_z_portadora', 'rendgastro_caea_campo', 'rendgastro_total',
            'diff_erp_anita', 'diff_erp_rendg', 'estado', 'cant_facturas',
            'nc_erp', 'nc_rendg', 'rendg_neto', 'rendg_legacy_z', 'fc_caea_duplicado',
            'asiento_factura_dia', 'asiento_post_cierre', 'asientos_total', 'diff_rendg_asientos',
            'flash_ayb', 'flash_estac', 'total_flash', 'diff_erp_flash', 'diff_anita_flash', 'diff_rendg_flash',
        ], ';');
        foreach ($filasCsv as $fila) {
            fputcsv($handle, $fila, ';');
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return $contenido !== false ? $contenido : '';
    }

    public function nombreArchivoCsv(array $informe): string
    {
        $desde = (string) ($informe['fecha_desde'] ?? 'fecha');
        $hasta = (string) ($informe['fecha_hasta'] ?? $desde);

        return 'conciliacion_gastro_'.$desde.($hasta !== $desde ? '_'.$hasta : '').'.csv';
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function hayDiferencias(array $informe): bool
    {
        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['dias'] ?? [] as $dia) {
                foreach ($dia['filas'] ?? [] as $fila) {
                    if (in_array($fila['tipo_fila'] ?? '', ['pv_cae', 'pv_caea'], true)) {
                        continue;
                    }
                    if (in_array($fila['tipo_fila'] ?? '', ['vending_pv', 'vending_rendg', 'total_vending'], true)) {
                        continue;
                    }
                    if (in_array($fila['tipo_fila'] ?? '', ['estacionamiento_pv', 'total_estacionamiento'], true)) {
                        continue;
                    }
                    if (($fila['estado'] ?? '') === 'RENDG') {
                        continue;
                    }
                    if (in_array($fila['estado'] ?? '', ['DIF', 'DIF venta', 'DIF rendg', 'DIF ambos', 'SIN RENDG'], true)) {
                        return true;
                    }
                }
                $ctrl = $dia['control_gastro_total'] ?? null;
                if (is_array($ctrl) && ($ctrl['estado'] ?? '') === 'DIF') {
                    return true;
                }
                $ctrlAsientos = $dia['control_rendg_asientos'] ?? null;
                if (is_array($ctrlAsientos) && ($ctrlAsientos['estado'] ?? '') === 'DIF') {
                    return true;
                }
                $concMedios = $dia['conciliacion_medios'] ?? null;
                if (is_array($concMedios) && ($concMedios['estado'] ?? '') === 'DIF') {
                    return true;
                }
                foreach ($this->filasControlFlash($dia) as $ctrlFlash) {
                    if (($ctrlFlash['estado'] ?? '') === 'DIF') {
                        return true;
                    }
                }
                $huecos = $dia['huecos_numeracion'] ?? null;
                if (is_array($huecos) && (int) ($huecos['huecos_corr_erp'] ?? 0) > 0) {
                    return true;
                }
                $pendArca = $dia['huecos_arca_pendientes'] ?? null;
                if (is_array($pendArca) && (int) ($pendArca['cantidad'] ?? 0) > 0) {
                    return true;
                }
            }
            $huecosRango = $empresa['huecos_rango'] ?? null;
            if (is_array($huecosRango) && ($huecosRango['hay_huecos'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return array{enviado: bool, destino?: string, error?: string}
     */
    public function construirContenidoExcel(array $informe): string
    {
        return GastronomiaConciliacionDiariaReporteExport::contenidoBinario($informe);
    }

    public function nombreArchivoExcel(array $informe): string
    {
        return GastronomiaConciliacionDiariaReporteExport::nombreArchivo($informe);
    }

    public function enviarCorreo(array $informe, bool $adjuntarExcel = true): array
    {
        $config = config('gastronomia.conciliacion_diaria_reporte', []);
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return ['enviado' => false, 'error' => 'Sin destino de correo configurado'];
        }

        $filasCsv = $this->construirFilasCsv($informe);
        if ($filasCsv === []) {
            return ['enviado' => false, 'error' => 'Sin filas para adjuntar'];
        }

        $informe['hay_diferencias'] = $this->hayDiferencias($informe);
        $csv = $this->construirContenidoCsv($filasCsv);
        $nombreCsv = $this->nombreArchivoCsv($informe);
        $excel = $adjuntarExcel ? $this->construirContenidoExcel($informe) : null;
        $nombreExcel = $adjuntarExcel ? $this->nombreArchivoExcel($informe) : null;

        try {
            Mail::to($destino)->send(new GastronomiaConciliacionDiariaReporte(
                $informe,
                $csv,
                $nombreCsv,
                $excel,
                $nombreExcel,
            ));
            Log::info('gastronomia.conciliacion_diaria_reporte.mail_ok', [
                'destino' => $destino,
                'fecha_desde' => $informe['fecha_desde'] ?? null,
                'fecha_hasta' => $informe['fecha_hasta'] ?? null,
                'hay_diferencias' => $informe['hay_diferencias'],
            ]);

            return ['enviado' => true, 'destino' => $destino];
        } catch (\Throwable $e) {
            Log::error('gastronomia.conciliacion_diaria_reporte.mail_fallo', [
                'destino' => $destino,
                'msg' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'destino' => $destino, 'error' => $e->getMessage()];
        }
    }

    public function guardarCsv(string $ruta, array $informe): void
    {
        $contenido = $this->construirContenidoCsv($this->construirFilasCsv($informe));
        if (file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$ruta);
        }
    }

    /**
     * Envía por correo la auditoría mensual por medio de cobro (Z ↔ contabilizado, ERP sin ctamov).
     *
     * @param  list<array<string, mixed>>  $resumen  salida de {@see resumenMensualMediosDirecto()}
     * @return array{enviado: bool, destino?: string, error?: string, hay_diferencias?: bool}
     */
    public function enviarCorreoAuditoriaMediosMensual(
        array $resumen,
        string $fechaDesde,
        string $fechaHasta,
        float $tolerancia,
    ): array {
        $configMensual = config('gastronomia.auditoria_medios_mensual', []);
        $destino = trim((string) ($configMensual['email']
            ?? config('gastronomia.conciliacion_diaria_reporte.email', '')));
        if ($destino === '') {
            return ['enviado' => false, 'error' => 'Sin destino de correo configurado'];
        }

        $hayDiferencias = false;
        foreach ($resumen as $emp) {
            if ((string) ($emp['estado'] ?? 'OK') === 'DIF') {
                $hayDiferencias = true;
                break;
            }
        }

        $csv = $this->construirCsvMediosMensual($resumen, $fechaDesde, $fechaHasta);
        $nombreCsv = 'auditoria_medios_'.$fechaDesde.'_'.$fechaHasta.'.csv';
        $destinatarios = array_values(array_filter(array_map('trim', explode(',', $destino))));

        try {
            Mail::to($destinatarios)->send(new GastronomiaAuditoriaMediosMensual(
                $resumen,
                $fechaDesde,
                $fechaHasta,
                $tolerancia,
                $hayDiferencias,
                $csv,
                $nombreCsv,
            ));
            Log::info('gastronomia.auditoria_medios_mensual.mail_ok', [
                'destino' => $destino,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'hay_diferencias' => $hayDiferencias,
            ]);

            return ['enviado' => true, 'destino' => $destino, 'hay_diferencias' => $hayDiferencias];
        } catch (\Throwable $e) {
            Log::error('gastronomia.auditoria_medios_mensual.mail_fallo', [
                'destino' => $destino,
                'msg' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'destino' => $destino, 'error' => $e->getMessage()];
        }
    }

    /**
     * CSV: totales del mes + detalle día × medio por empresa.
     *
     * @param  list<array<string, mixed>>  $resumen
     */
    public function construirCsvMediosMensual(array $resumen, string $fechaDesde, string $fechaHasta): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'tipo', 'empresa_id', 'empresa_nombre', 'fecha_desde', 'fecha_hasta', 'fecha_jornada',
            'cuenta_codigo', 'cuenta_nombre', 'fuente_z', 'z', 'contabilizado', 'diff', 'estado',
        ], ';');

        foreach ($resumen as $emp) {
            $empresaId = (int) ($emp['empresa_id'] ?? 0);
            $empresaNombre = (string) ($emp['empresa_nombre'] ?? '');

            $porCuenta = [];
            foreach ($emp['z_por_medio'] ?? [] as $m) {
                $cod = (string) ($m['cuenta_codigo'] ?? '');
                if ($cod === '') {
                    continue;
                }
                $porCuenta[$cod] = [
                    'nombre' => (string) ($m['cuenta_nombre'] ?? ''),
                    'fuente' => (string) ($m['fuente'] ?? ''),
                    'z' => (float) ($m['total'] ?? 0),
                    'contab' => 0.0,
                ];
            }
            foreach ($emp['contabilizado_por_medio'] ?? [] as $m) {
                $cod = (string) ($m['cuenta_codigo'] ?? '');
                if ($cod === '') {
                    continue;
                }
                if (! isset($porCuenta[$cod])) {
                    $porCuenta[$cod] = ['nombre' => (string) ($m['cuenta_nombre'] ?? ''), 'fuente' => '', 'z' => 0.0, 'contab' => 0.0];
                }
                $porCuenta[$cod]['contab'] = (float) ($m['total'] ?? 0);
            }
            ksort($porCuenta);
            foreach ($porCuenta as $cod => $c) {
                $diff = round($c['z'] - $c['contab'], 2);
                fputcsv($handle, [
                    'mes',
                    $empresaId,
                    $empresaNombre,
                    $fechaDesde,
                    $fechaHasta,
                    '',
                    $cod,
                    $c['nombre'],
                    $c['fuente'],
                    number_format($c['z'], 2, '.', ''),
                    number_format($c['contab'], 2, '.', ''),
                    number_format($diff, 2, '.', ''),
                    abs($diff) > 0.02 ? 'DIF' : 'OK',
                ], ';');
            }

            foreach ($emp['por_dia'] ?? [] as $dia) {
                $fechaJornada = (string) ($dia['fecha_jornada'] ?? '');
                foreach ($dia['medios'] ?? [] as $m) {
                    fputcsv($handle, [
                        'dia',
                        $empresaId,
                        $empresaNombre,
                        $fechaDesde,
                        $fechaHasta,
                        $fechaJornada,
                        (string) ($m['cuenta_codigo'] ?? ''),
                        (string) ($m['cuenta_nombre'] ?? ''),
                        (string) ($m['fuente_z'] ?? ''),
                        number_format((float) ($m['z'] ?? 0), 2, '.', ''),
                        number_format((float) ($m['contabilizado'] ?? 0), 2, '.', ''),
                        number_format((float) ($m['diff'] ?? 0), 2, '.', ''),
                        (string) ($m['estado'] ?? ''),
                    ], ';');
                }
            }
        }

        rewind($handle);
        $contenido = (string) stream_get_contents($handle);
        fclose($handle);

        return $contenido;
    }

    /**
     * @return array{
     *   fecha_jornada: string,
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, float|null>
     * }
     */
    private function armarDia(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        ?array $indiceAnitaBulk = null,
        array $flashDesglosePorJornada = [],
    ): array {
        $jornadaAbierta = $this->jornadaAbierta($empresaId, $fechaJornada);
        $conciliacion = $this->conciliacionPorPcSupport->conciliacionDiaCompleta(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $jornadaAbierta,
            $indiceAnitaBulk,
        );
        $filasPc = $conciliacion['filas_pc'];
        $filas = $this->conciliacionPorPcSupport->expandirFilasAuditoria($filasPc, $tolerancia);
        $this->marcarCircuito($filas, 'GASTRO');

        $totales = $conciliacion['totales_salon'];

        if ($filasPc !== []) {
            $totalSalon = null;
            foreach ($conciliacion['filas_totales'] as $filaTotal) {
                if (($filaTotal['tipo_fila'] ?? '') === 'total_salon') {
                    $totalSalon = $filaTotal;
                    break;
                }
            }
            if ($totalSalon !== null) {
                $totalSalon['circuito'] = 'GASTRO';
                $filas[] = $totalSalon;
            }
        }

        $postCierre = $conciliacion['post_cierre'];
        $agregados = $conciliacion['agregados_caea'] ?? [];
        $tienePostCierre = (int) ($postCierre['cantidad_facturas_erp'] ?? 0) > 0
            || (float) ($postCierre['ventas_erp'] ?? 0) > $tolerancia;
        $tieneAgregados = (int) ($agregados['cantidad_facturas_erp'] ?? 0) > 0
            || (float) ($agregados['ventas_erp'] ?? 0) > $tolerancia;

        if ($tienePostCierre) {
            $postCierre['circuito'] = 'GASTRO';
            $filas[] = $postCierre;
        }
        if ($tieneAgregados) {
            $agregados['circuito'] = 'GASTRO';
            $filas[] = $agregados;
        }

        foreach ($conciliacion['filas_totales'] as $filaTotal) {
            if (($filaTotal['tipo_fila'] ?? '') === 'total_gastro') {
                $filaTotal['circuito'] = 'GASTRO';
                $filas[] = $filaTotal;
                break;
            }
        }

        $estacionamiento = $this->estacionamientoSupport->filasReporte(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $jornadaAbierta,
        );
        foreach ($estacionamiento['filas'] as $filaEst) {
            $filas[] = $filaEst;
        }
        if ((float) ($estacionamiento['totales']['ventas_erp'] ?? 0) > $tolerancia
            || (float) ($estacionamiento['totales']['rendgastro_z'] ?? 0) > $tolerancia) {
            $filas[] = $this->estacionamientoSupport->filaTotalEstacionamiento(
                $estacionamiento['totales'],
                $jornadaAbierta,
                $tolerancia,
            );
        }

        $vending = $this->vendingRendgSupport->filasReporte($empresaId, $fechaJornada, $tolerancia, $jornadaAbierta);
        foreach ($vending['filas'] as $filaVending) {
            $filas[] = $filaVending;
        }
        if ((float) ($vending['totales']['ventas_erp'] ?? 0) > $tolerancia
            || (float) ($vending['totales']['rendgastro_z'] ?? 0) > $tolerancia) {
            $filas[] = $this->vendingRendgSupport->filaTotalVending(
                $vending['totales'],
                $jornadaAbierta,
                $tolerancia,
            );
        }

        $controlGastro = $this->armarControlGastroTotal(
            $empresaId,
            $fechaJornada,
            $totales,
            $postCierre,
            $agregados,
            $jornadaAbierta,
            $tolerancia,
        );

        $controlRendgAsientos = $this->rendgAsientosDiaSupport->armarControl(
            $empresaId,
            $fechaJornada,
            $totales,
            $postCierre,
            $agregados,
            $jornadaAbierta,
            $tolerancia,
            (float) ($controlGastro['notas_credito_rendg'] ?? 0),
        );

        $controlFlash = $this->armarControlFlashJornada(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $flashDesglosePorJornada,
        );

        $conciliacionMedios = ! $jornadaAbierta
            ? $this->medioPagoSupport->conciliarJornada($empresaId, $fechaJornada, $tolerancia)
            : null;

        return [
            'fecha_jornada' => $fechaJornada,
            'jornada_abierta' => $jornadaAbierta,
            'ventas_solo_erp' => GastronomiaVentasSoloErpSupport::esJornada($empresaId, $fechaJornada),
            'filas' => $filas,
            'totales' => $totales,
            'post_cierre_caea' => $postCierre,
            'agregados_caea' => $agregados,
            'estacionamiento' => $estacionamiento,
            'vending' => $vending,
            'control_gastro_total' => $controlGastro,
            'control_rendg_asientos' => $controlRendgAsientos,
            'control_flash' => $controlFlash,
            'conciliacion_medios' => $conciliacionMedios,
            'huecos_numeracion' => $this->huecosNumeracionService->resumenJornadaEmpresa($empresaId, $fechaJornada),
            'huecos_arca_pendientes' => $this->resumenHuecosArcaPendientes($empresaId, $fechaJornada),
        ];
    }

    /**
     * @return array{cantidad:int,filas:list<array<string,mixed>>}
     */
    private function resumenHuecosArcaPendientes(int $empresaId, string $fechaJornada): array
    {
        $filas = \App\Models\Ventas\GastronomiaHuecoArcaPendiente::query()
            ->with('puntoventa:id,codigo')
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->whereIn('estado', [
                \App\Models\Ventas\GastronomiaHuecoArcaPendiente::ESTADO_PENDIENTE,
                \App\Models\Ventas\GastronomiaHuecoArcaPendiente::ESTADO_ARCA_INDISPONIBLE,
                \App\Models\Ventas\GastronomiaHuecoArcaPendiente::ESTADO_RECUPERABLE,
            ])
            ->orderBy('numero_comprobante')
            ->get()
            ->map(static fn ($p) => [
                'id' => (int) $p->id,
                'pv' => (string) ($p->puntoventa?->codigo ?? ''),
                'numero' => (int) $p->numero_comprobante,
                'estado' => (string) $p->estado,
                'ultimo_error' => (string) ($p->ultimo_error ?? ''),
            ])
            ->all();

        return [
            'cantidad' => count($filas),
            'filas' => $filas,
        ];
    }

    /**
     * Cuadro FLASH: Informix flash_ayb/estac vs rendg/ERP de la jornada offset (default: día anterior).
     * Con control_flash_ayb_incluye_vending, flash_ayb se compara a gastro+vending (Anita no discrimina).
     *
     * @param  array<string, array{flash_ayb: float, flash_estac: float, total_flash: float}>  $flashDesglosePorJornada
     * @return list<array<string, mixed>>
     */
    private function armarControlFlashJornada(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        array $flashDesglosePorJornada,
    ): array {
        $offset = $this->controlFlashJornadaOffsetDias();
        $fechaFlash = $offset > 0
            ? Carbon::parse($fechaJornada)->subDays($offset)->toDateString()
            : $fechaJornada;

        $filasErpFlash = $this->filasErpParaControlFlash($empresaId, $fechaFlash, $tolerancia);

        return $this->flashSupport->armarControl(
            $empresaId,
            $fechaFlash,
            $filasErpFlash,
            $this->jornadaAbierta($empresaId, $fechaFlash),
            $tolerancia,
            $flashDesglosePorJornada[$fechaFlash] ?? null,
            $fechaFlash !== $fechaJornada ? $fechaFlash : null,
        );
    }

    /**
     * Totales ERP mínimos (gastro + estac) para armar el cuadro FLASH de una jornada.
     *
     * @return list<array<string, mixed>>
     */
    private function filasErpParaControlFlash(int $empresaId, string $fechaFlash, float $tolerancia): array
    {
        $gastroNeto = round(
            (float) ($this->gastroTotalDiaSupport->totalesDiaEmpresa($empresaId, $fechaFlash)['neto'] ?? 0),
            2,
        );

        $estac = $this->estacionamientoSupport->filasReporte(
            $empresaId,
            $fechaFlash,
            $tolerancia,
            $this->jornadaAbierta($empresaId, $fechaFlash),
        );
        $estacNeto = round((float) ($estac['totales']['ventas_erp'] ?? 0), 2);

        return [
            [
                'tipo_fila' => 'total_gastro',
                'ventas_erp' => $gastroNeto,
            ],
            [
                'tipo_fila' => 'total_estacionamiento',
                'ventas_erp' => $estacNeto,
            ],
        ];
    }

    private function controlFlashJornadaOffsetDias(): int
    {
        return max(0, (int) config(
            'gastronomia.conciliacion_diaria_reporte.control_flash_jornada_offset_dias',
            1,
        ));
    }

    /**
     * @return array<string, array{flash_ayb: float, flash_estac: float, total_flash: float}>
     */
    private function cargarFlashDesgloseEmpresa(int $empresaCodigo, string $desde, string $hasta): array
    {
        if (! (bool) config('gastronomia.conciliacion_diaria_reporte.control_flash_habilitado', true)) {
            return [];
        }

        try {
            $desglose = app(GastronomiaControlFlashSupport::class)->desglosePorEmpresaJornada(
                $desde,
                $hasta,
                [$empresaCodigo],
            )[$empresaCodigo] ?? [];
        } catch (\Throwable $e) {
            Log::warning('gastronomia.conciliacion_diaria_reporte.flash_fallo', [
                'empresa_codigo' => $empresaCodigo,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'msg' => $e->getMessage(),
            ]);

            return [];
        }

        $map = [];
        foreach ($desglose as $fecha => $partes) {
            $ayb = round((float) ($partes['flash_ayb'] ?? 0), 2);
            $estac = round((float) ($partes['flash_estac'] ?? 0), 2);
            $map[(string) $fecha] = [
                'flash_ayb' => $ayb,
                'flash_estac' => $estac,
                'total_flash' => round($ayb + $estac, 2),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, float|null>  $totalesDia
     * @param  array<string, mixed>  $postCierre
     * @param  array<string, mixed>  $agregados
     * @return array<string, mixed>
     */
    private function armarControlGastroTotal(
        int $empresaId,
        string $fechaJornada,
        array $totalesDia,
        array $postCierre,
        array $agregados,
        bool $jornadaAbierta,
        float $tolerancia,
    ): array {
        $erpTotales = $this->gastroTotalDiaSupport->totalesDiaEmpresa($empresaId, $fechaJornada);
        $ventasErpBruto = $erpTotales['bruto'];
        $ncErp = $erpTotales['nc'];
        $ventasErpNeto = $erpTotales['neto'];

        $rendgSalonNeto = ! $jornadaAbierta && ($totalesDia['diff_erp_rendg'] ?? null) !== null
            ? (float) ($totalesDia['rendgastro_z'] ?? 0)
            : null;
        $rendgPost = (float) ($postCierre['rendgastro_z'] ?? 0);
        $rendgAgregados = (float) ($agregados['rendgastro_z'] ?? 0);
        $rendgNeto = $rendgSalonNeto !== null
            ? round($rendgSalonNeto + $rendgPost + $rendgAgregados, 2)
            : null;

        $ncRendg = null;
        $rendgBruto = null;
        $rendgLegacy = null;
        $fcCaeaDuplicado = null;
        $pvCaeaZInflado = null;
        if (! $jornadaAbierta && $rendgNeto !== null) {
            $fechaEntera = (int) str_replace('-', '', $fechaJornada);
            $jornadaId = $this->jornadaId($empresaId, $fechaJornada) ?? 0;
            $ncRendg = $this->rendgastroSupport->sumaNcPortadorasPcMasPostCierre($empresaId, $fechaEntera, $jornadaId);
            $rendgBruto = round($rendgNeto + $ncRendg, 2);

            $hostsConfig = ConfiguracionPuntoventaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->pluck('identificador_pc')
                ->map(static fn ($host): string => trim((string) $host))
                ->filter()
                ->values()
                ->all();
            $auditLegacy = $this->rendgastroSupport->auditarCabecerasHuérfanasLegacy(
                $empresaId,
                $fechaEntera,
                $hostsConfig,
                $tolerancia,
            );
            $rendgLegacy = $auditLegacy['rendg_legacy_z'];
            $fcCaeaDuplicado = $auditLegacy['fc_caea_duplicado_portadora'];
            $pvCaeaZInflado = $auditLegacy['rendg_pv_caea_z_inflado'];
        }

        $diff = $rendgNeto !== null ? round($ventasErpNeto - $rendgNeto, 2) : null;
        $hayLegacy = $rendgLegacy !== null && $rendgLegacy > $tolerancia;
        $hayFcCaeaDup = $fcCaeaDuplicado !== null && $fcCaeaDuplicado > $tolerancia;
        $hayPvCaeaZInflado = ($pvCaeaZInflado ?? null) !== null && $pvCaeaZInflado > $tolerancia;
        $estado = $jornadaAbierta || $rendgNeto === null
            ? '—'
            : ((abs((float) $diff) <= $tolerancia && ! $hayLegacy && ! $hayFcCaeaDup && ! $hayPvCaeaZInflado) ? 'OK' : 'DIF');

        return [
            'tipo_fila' => 'control_gastro_total',
            'circuito' => 'GASTRO',
            'identificador_pc' => 'TOTAL-GASTRONOMIA',
            'tipo_pv' => 'EMPRESA',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Control día: neto ERP vs neto rendgastro (bruto − NC)',
            'pv_cae' => '—',
            'pv_caea' => '—',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp_bruto' => $ventasErpBruto,
            'notas_credito_erp' => $ncErp,
            'ventas_erp' => $ventasErpNeto,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => $ventasErpNeto,
            'rendgastro_z' => $rendgBruto,
            'notas_credito_rendg' => $ncRendg,
            'rendgastro_neto' => $rendgNeto,
            'rendg_legacy_z' => $rendgLegacy,
            'fc_caea_duplicado' => $fcCaeaDuplicado,
            'rendg_pv_caea_z_inflado' => $pvCaeaZInflado ?? null,
            'rendgastro_z_cae' => null,
            'rendgastro_caea' => null,
            'diff_erp_anita' => 0.0,
            'diff_erp_rendg' => $diff,
            'estado' => $estado,
            'cantidad_facturas_erp' => null,
            'es_control_gastro_total' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function marcarCircuito(array &$filas, string $circuito): void
    {
        foreach ($filas as &$fila) {
            $fila['circuito'] = $circuito;
        }
        unset($fila);
    }

    private function jornadaId(int $empresaId, string $fechaJornada): ?int
    {
        $id = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $empresa
     * @param  array<string, mixed>  $dia
     * @param  array<string, mixed>  $fila
     * @return list<int|string|float|null>
     */
    private function filaCsvDesdeReporte(array $empresa, array $dia, array $fila): array
    {
        $tipo = (string) ($fila['tipo_fila'] ?? 'pc');
        if ($tipo === 'pc' && ! empty($fila['es_total'])) {
            $tipo = 'total_salon';
        } elseif ($tipo === 'pc' && ! empty($fila['es_post_cierre_caea'])) {
            $tipo = 'post_cierre_caea';
        } elseif ($tipo === 'pc' && ! empty($fila['es_control_gastro_total'])) {
            $tipo = 'control_gastro_total';
        } elseif (! empty($fila['es_control_rendg_asientos'])) {
            $tipo = 'control_rendg_asientos';
        } elseif (! empty($fila['es_control_flash'])) {
            $tipo = 'control_flash';
        } elseif ($tipo === 'vending_rendg') {
            $tipo = 'vending_pv';
        }

        $asientoFacturaDia = '';
        $asientoPostCierre = '';
        $asientosTotal = '';
        $diffRendgAsientos = '';
        $flashAyb = $fila['flash_ayb'] ?? '';
        $flashEstac = $fila['flash_estac'] ?? '';
        $totalFlash = $fila['total_flash'] ?? '';
        $diffErpFlash = $fila['diff_erp_flash'] ?? '';
        $diffAnitaFlash = $fila['diff_anita_flash'] ?? '';
        $diffRendgFlash = $fila['diff_rendg_flash'] ?? '';
        $rendgZPortadora = $fila['rendgastro_z_cae'] ?? '';
        $rendgCaea = $fila['rendgastro_caea'] ?? '';
        $rendgTotal = $fila['rendgastro_z'] ?? '';
        $rendgNeto = $fila['rendgastro_neto'] ?? '';
        if (! empty($fila['es_control_rendg_asientos'])) {
            $asientoFacturaDia = $fila['asientos_factura_dia'] ?? '';
            $asientoPostCierre = $fila['asientos_post_cierre'] ?? '';
            $asientosTotal = $fila['asientos_total'] ?? '';
            $diffRendgAsientos = $fila['diff_rendg_asientos'] ?? '';
            $rendgZPortadora = $fila['rendg_salon'] ?? '';
            $rendgCaea = $fila['rendg_post_cierre'] ?? '';
            $rendgTotal = $fila['rendg_total'] ?? '';
            $rendgNeto = $fila['rendg_total'] ?? '';
        }

        return [
            $empresa['empresa_id'],
            $empresa['empresa_nombre'],
            $dia['fecha_jornada'],
            $fila['circuito'] ?? 'GASTRO',
            $tipo,
            $fila['tipo_pv'] ?? '',
            $fila['identificador_pc'] ?? '',
            $fila['pv_codigo'] ?? ($fila['pv_cae'] ?? ''),
            $fila['pv_cae'] ?? '',
            $fila['pv_caea'] ?? '',
            $fila['ventas_erp_cae'] ?? 0,
            $fila['ventas_erp_caea'] ?? 0,
            $fila['ventas_erp'] ?? 0,
            array_key_exists('ventas_anita_cae', $fila) ? $fila['ventas_anita_cae'] : 0,
            array_key_exists('ventas_anita_caea', $fila) ? $fila['ventas_anita_caea'] : 0,
            array_key_exists('ventas_anita', $fila) ? $fila['ventas_anita'] : 0,
            $rendgZPortadora,
            $rendgCaea,
            $rendgTotal,
            $fila['diff_erp_anita'] ?? '',
            $fila['diff_erp_rendg'] ?? '',
            $fila['estado'] ?? '',
            $fila['cantidad_facturas_erp'] ?? ($fila['asientos_cantidad'] ?? ''),
            $fila['notas_credito_erp'] ?? '',
            $fila['notas_credito_rendg'] ?? '',
            $rendgNeto,
            $fila['rendg_legacy_z'] ?? '',
            $fila['fc_caea_duplicado'] ?? '',
            $asientoFacturaDia,
            $asientoPostCierre,
            $asientosTotal,
            $diffRendgAsientos,
            $flashAyb,
            $flashEstac,
            $totalFlash,
            $diffErpFlash,
            $diffAnitaFlash,
            $diffRendgFlash,
        ];
    }

    /**
     * @param  array<string, mixed>  $dia
     * @return list<array<string, mixed>>
     */
    public function filasControlFlashDesdeDia(array $dia): array
    {
        return $this->filasControlFlash($dia);
    }

    /**
     * @param  array<string, mixed>  $dia
     * @return list<array<string, mixed>>
     */
    private function filasControlFlash(array $dia): array
    {
        $control = $dia['control_flash'] ?? null;
        if (! is_array($control) || $control === []) {
            return [];
        }

        if (array_is_list($control)) {
            return array_values(array_filter($control, static fn ($fila): bool => is_array($fila)));
        }

        return [$control];
    }

    private function esJornadaPreMigracion(int $empresaId, string $fechaJornada): bool
    {
        $map = config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $min = trim((string) ($map[$empresaId] ?? ''));
        if ($min === '') {
            return false;
        }

        return $fechaJornada < Carbon::parse($min)->toDateString();
    }

    private function jornadaAbierta(int $empresaId, string $fechaJornada): bool
    {
        return ! $this->jornadaEstaCerrada($empresaId, $fechaJornada);
    }

    private function jornadaEstaCerrada(int $empresaId, string $fechaJornada): bool
    {
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first(['estado']);

        if ($jornada === null) {
            return false;
        }

        return (string) ($jornada->estado ?? '') === JornadaGastronomia::ESTADO_CERRADA;
    }

    /**
     * @param  array<int, array<string, array<string, object>>>|null  $indiceAnitaBulk
     * @return array<string, mixed>|null
     */
    private function resolverIndiceAnitaBulk(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $forzarDescarga,
        ?array &$indiceAnitaBulk,
    ): ?array {
        $cache = $this->anitaMesCacheSupport->resolverVentaCache(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $forzarDescarga,
        );
        $pvPorSucursal = $this->anitaMesCacheSupport->puntoventasPorSucursalEmpresa($empresaId);
        $indiceAnitaBulk = $this->anitaMesCacheSupport->indexarCabecerasPorSucursalJornada(
            $cache['venta'],
            $pvPorSucursal,
        );

        Log::info('gastronomia.conciliacion_diaria_reporte.cache_anita', [
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'cabeceras' => count($cache['venta']),
            'directorio' => $cache['manifest']['directorio'] ?? null,
            'generado_at' => $cache['manifest']['generado_at'] ?? null,
        ]);

        return $cache['manifest'];
    }
}
