<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Mail\Ventas\GastronomiaConciliacionDiariaReporte;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionGastroTotalDiaSupport;
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
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
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
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $empresas = [];
        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $empresa = Empresa::query()->find($empresaId);
            $dias = [];

            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $fechaJornada = $dia->toDateString();
                if ($this->esJornadaPreMigracion($empresaId, $fechaJornada)) {
                    continue;
                }
                $dias[] = $this->armarDia($empresaId, $fechaJornada, $tolerancia);
            }

            $empresas[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'dias' => $dias,
            ];
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
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
            'empresa_id', 'empresa_nombre', 'fecha_jornada', 'tipo_fila', 'tipo_pv',
            'identificador_pc', 'pv_codigo', 'pv_cae', 'pv_caea',
            'ventas_erp_cae', 'ventas_erp_caea', 'ventas_erp_total',
            'ventas_anita_cae', 'ventas_anita_caea', 'ventas_anita_total',
            'rendgastro_z_portadora', 'rendgastro_caea_campo', 'rendgastro_total',
            'diff_erp_anita', 'diff_erp_rendg', 'estado', 'cant_facturas',
            'nc_erp', 'nc_rendg', 'rendg_neto', 'rendg_legacy_z', 'fc_caea_duplicado',
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
                    if (in_array($fila['estado'] ?? '', ['DIF', 'SIN RENDG'], true)) {
                        return true;
                    }
                }
                $ctrl = $dia['control_gastro_total'] ?? null;
                if (is_array($ctrl) && ($ctrl['estado'] ?? '') === 'DIF') {
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
    public function enviarCorreo(array $informe): array
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
        $nombre = $this->nombreArchivoCsv($informe);

        try {
            Mail::to($destino)->send(new GastronomiaConciliacionDiariaReporte($informe, $csv, $nombre));
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
    private function armarDia(int $empresaId, string $fechaJornada, float $tolerancia): array
    {
        $jornadaAbierta = $this->jornadaAbierta($empresaId, $fechaJornada);
        $filasPc = $this->conciliacionPorPcSupport->filasDiaPorPc(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $jornadaAbierta,
        );
        $filas = $this->conciliacionPorPcSupport->expandirFilasAuditoria($filasPc, $tolerancia);

        $totales = [
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => 0.0,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => 0.0,
            'rendgastro_z' => 0.0,
        ];
        $algunaPcSinRendg = false;

        foreach ($filasPc as $fila) {
            $totales['ventas_erp_cae'] += (float) ($fila['ventas_erp_cae'] ?? 0);
            $totales['ventas_erp_caea'] += (float) ($fila['ventas_erp_caea'] ?? 0);
            $totales['ventas_erp'] += (float) ($fila['ventas_erp'] ?? 0);
            $totales['ventas_anita_cae'] += (float) ($fila['ventas_anita_cae'] ?? 0);
            $totales['ventas_anita_caea'] += (float) ($fila['ventas_anita_caea'] ?? 0);
            $totales['ventas_anita'] += (float) ($fila['ventas_anita'] ?? 0);
            if (($fila['rendgastro_z'] ?? null) !== null) {
                $totales['rendgastro_z'] += (float) $fila['rendgastro_z'];
            } elseif (! $jornadaAbierta && (float) ($fila['ventas_erp'] ?? 0) > $tolerancia) {
                $algunaPcSinRendg = true;
            }
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round((float) $v, 2);
        }

        $totales['diff_erp_anita'] = round($totales['ventas_erp'] - $totales['ventas_anita'], 2);
        $totales['diff_erp_rendg'] = ($jornadaAbierta || $algunaPcSinRendg)
            ? null
            : round($totales['ventas_erp'] - $totales['rendgastro_z'], 2);

        if ($filasPc !== []) {
            $filas[] = [
                'tipo_fila' => 'total_salon',
                'identificador_pc' => 'TOTAL-SALON',
                'tipo_pv' => 'TOTAL',
                'pv_codigo' => '—',
                'descripcion_pc' => 'Total salón (suma PCs, sin post-cierre)',
                'pv_cae' => '',
                'pv_caea' => '',
                'ventas_erp_cae' => $totales['ventas_erp_cae'],
                'ventas_erp_caea' => $totales['ventas_erp_caea'],
                'ventas_erp' => $totales['ventas_erp'],
                'ventas_anita_cae' => $totales['ventas_anita_cae'],
                'ventas_anita_caea' => $totales['ventas_anita_caea'],
                'ventas_anita' => $totales['ventas_anita'],
                'rendgastro_z' => ($jornadaAbierta || $algunaPcSinRendg) ? null : $totales['rendgastro_z'],
                'diff_erp_anita' => $totales['diff_erp_anita'],
                'diff_erp_rendg' => $totales['diff_erp_rendg'],
                'estado' => $this->resolverEstado(
                    (float) $totales['diff_erp_anita'],
                    $totales['diff_erp_rendg'],
                    $tolerancia,
                ),
                'jornada_abierta' => $jornadaAbierta,
                'es_total' => true,
            ];
        }

        $postCierre = $this->postCierreCaeaSupport->filaReporte($empresaId, $fechaJornada, $tolerancia);
        $tienePostCierre = (int) ($postCierre['cantidad_facturas_erp'] ?? 0) > 0
            || (float) ($postCierre['ventas_erp'] ?? 0) > $tolerancia;

        if ($tienePostCierre) {
            $filas[] = $postCierre;
            $filas[] = $this->armarFilaTotalDia($totales, $postCierre, $jornadaAbierta, $tolerancia, $algunaPcSinRendg);
        }

        $controlGastro = $this->armarControlGastroTotal(
            $empresaId,
            $fechaJornada,
            $totales,
            $postCierre,
            $jornadaAbierta,
            $tolerancia,
        );

        return [
            'fecha_jornada' => $fechaJornada,
            'jornada_abierta' => $jornadaAbierta,
            'filas' => $filas,
            'totales' => $totales,
            'post_cierre_caea' => $postCierre,
            'control_gastro_total' => $controlGastro,
        ];
    }

    /**
     * Total día = salón (PCs) + post-cierre CAEA (PV compartido, ej. Kandiko 00031).
     *
     * @param  array<string, float|null>  $totalesSalon
     * @param  array<string, mixed>  $postCierre
     * @return array<string, mixed>
     */
    private function armarFilaTotalDia(
        array $totalesSalon,
        array $postCierre,
        bool $jornadaAbierta,
        float $tolerancia,
        bool $algunaPcSinRendg,
    ): array {
        $erpTotal = round((float) ($totalesSalon['ventas_erp'] ?? 0) + (float) ($postCierre['ventas_erp'] ?? 0), 2);
        $anitaTotal = round((float) ($totalesSalon['ventas_anita'] ?? 0) + (float) ($postCierre['ventas_anita'] ?? 0), 2);
        $rendgPost = $postCierre['rendgastro_z'] ?? null;
        $rendgTotal = ($jornadaAbierta || $algunaPcSinRendg || $rendgPost === null)
            ? null
            : round((float) ($totalesSalon['rendgastro_z'] ?? 0) + (float) $rendgPost, 2);
        $diffErpAnita = round($erpTotal - $anitaTotal, 2);
        $diffErpRendg = $rendgTotal !== null ? round($erpTotal - $rendgTotal, 2) : null;

        return [
            'tipo_fila' => 'total_dia',
            'identificador_pc' => 'TOTAL-DIA',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total día (salón + post-cierre CAEA)',
            'pv_cae' => '',
            'pv_caea' => (string) ($postCierre['pv_caea'] ?? '—'),
            'ventas_erp_cae' => (float) ($totalesSalon['ventas_erp_cae'] ?? 0),
            'ventas_erp_caea' => round(
                (float) ($totalesSalon['ventas_erp_caea'] ?? 0) + (float) ($postCierre['ventas_erp'] ?? 0),
                2,
            ),
            'ventas_erp' => $erpTotal,
            'ventas_anita_cae' => (float) ($totalesSalon['ventas_anita_cae'] ?? 0),
            'ventas_anita_caea' => round(
                (float) ($totalesSalon['ventas_anita_caea'] ?? 0) + (float) ($postCierre['ventas_anita'] ?? 0),
                2,
            ),
            'ventas_anita' => $anitaTotal,
            'rendgastro_z' => $rendgTotal,
            'diff_erp_anita' => $diffErpAnita,
            'diff_erp_rendg' => $diffErpRendg,
            'estado' => $this->resolverEstado($diffErpAnita, $diffErpRendg, $tolerancia),
            'jornada_abierta' => $jornadaAbierta,
            'es_total' => true,
        ];
    }

    /**
     * @param  array<string, float|null>  $totalesDia
     * @param  array<string, mixed>  $postCierre
     * @return array<string, mixed>
     */
    private function armarControlGastroTotal(
        int $empresaId,
        string $fechaJornada,
        array $totalesDia,
        array $postCierre,
        bool $jornadaAbierta,
        float $tolerancia,
    ): array {
        $erpTotales = $this->gastroTotalDiaSupport->totalesDiaEmpresa($empresaId, $fechaJornada);
        $ventasErpBruto = $erpTotales['bruto'];
        $ncErp = $erpTotales['nc'];
        $ventasErpNeto = $erpTotales['neto'];

        $rendgPc = ! $jornadaAbierta && ($totalesDia['diff_erp_rendg'] ?? null) !== null
            ? (float) ($totalesDia['rendgastro_z'] ?? 0)
            : null;
        $rendgPost = $postCierre['rendgastro_z'] ?? null;
        $rendgBruto = $jornadaAbierta || $rendgPc === null
            ? null
            : round($rendgPc + (float) ($rendgPost ?? 0), 2);

        $ncRendg = null;
        $rendgNeto = null;
        $rendgLegacy = null;
        $fcCaeaDuplicado = null;
        $pvCaeaZInflado = null;
        if (! $jornadaAbierta && $rendgBruto !== null) {
            $fechaEntera = (int) str_replace('-', '', $fechaJornada);
            $jornadaId = $this->jornadaId($empresaId, $fechaJornada) ?? 0;
            $ncRendg = $this->rendgastroSupport->sumaNcPortadorasPcMasPostCierre($empresaId, $fechaEntera, $jornadaId);
            $rendgNeto = round($rendgBruto - $ncRendg, 2);

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
        }

        return [
            $empresa['empresa_id'],
            $empresa['empresa_nombre'],
            $dia['fecha_jornada'],
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
            $fila['rendgastro_z_cae'] ?? '',
            $fila['rendgastro_caea'] ?? '',
            $fila['rendgastro_z'] ?? '',
            $fila['diff_erp_anita'] ?? '',
            $fila['diff_erp_rendg'] ?? '',
            $fila['estado'] ?? '',
            $fila['cantidad_facturas_erp'] ?? '',
            $fila['notas_credito_erp'] ?? '',
            $fila['notas_credito_rendg'] ?? '',
            $fila['rendgastro_neto'] ?? '',
            $fila['rendg_legacy_z'] ?? '',
            $fila['fc_caea_duplicado'] ?? '',
        ];
    }

    private function resolverEstado(float $diffErpAnita, ?float $diffErpRendg, float $tolerancia): string
    {
        $okAnita = abs($diffErpAnita) <= $tolerancia;
        $okRendg = $diffErpRendg === null || abs($diffErpRendg) <= $tolerancia;

        return ($okAnita && $okRendg) ? 'OK' : 'DIF';
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
}
