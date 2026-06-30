<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Exports\Ventas\GastronomiaConciliacionDiariaReporteExport;
use App\Mail\Ventas\GastronomiaConciliacionDiariaReporte;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstacionamientoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionGastroTotalDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionRendgAsientosDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
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
            $indiceAnitaBulk = null;
            $cacheManifest = null;

            if ($usarCache) {
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
                $dias[] = $this->armarDia($empresaId, $fechaJornada, $tolerancia, $indiceAnitaBulk);
            }

            $empresas[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'dias' => $dias,
                'anita_cache' => $cacheManifest,
            ];
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'usar_cache_anita' => $usarCache,
            'empresas' => $empresas,
            'hay_diferencias' => $this->hayDiferencias(['empresas' => $empresas]),
        ];
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
            }
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

        return [
            'fecha_jornada' => $fechaJornada,
            'jornada_abierta' => $jornadaAbierta,
            'filas' => $filas,
            'totales' => $totales,
            'post_cierre_caea' => $postCierre,
            'agregados_caea' => $agregados,
            'estacionamiento' => $estacionamiento,
            'vending' => $vending,
            'control_gastro_total' => $controlGastro,
            'control_rendg_asientos' => $controlRendgAsientos,
        ];
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
        } elseif ($tipo === 'vending_rendg') {
            $tipo = 'vending_pv';
        }

        $asientoFacturaDia = '';
        $asientoPostCierre = '';
        $asientosTotal = '';
        $diffRendgAsientos = '';
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
            $fila['ventas_anita_cae'] ?? 0,
            $fila['ventas_anita_caea'] ?? 0,
            $fila['ventas_anita'] ?? 0,
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
        ];
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
