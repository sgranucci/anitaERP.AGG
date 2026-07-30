<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoRendicionReparacionService;
use App\Services\Ventas\Gastronomia\GastronomiaConciliacionDiariaReporteService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaCabeceraAnitaMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Borra rendgastro de hosts gastronomía (salón) en Anita y re-sincroniza rendiciones ERP del rango.
 *
 * Clave temporal: fecha de jornada (rendg_fecha en Anita = jornada_gastronomia.fecha_jornada).
 * No usa fecha calendario de venta.fecha ni fecharendicion para ubicar cabeceras en Informix.
 * No toca estacionamiento legacy ni cabeceras CIERRE-WAITRY (post-cierre aparte).
 */
final class RendicionGastronomiaRegrabarHostsAnitaService
{
    private const LOG_EVENTO = 'rendicion_gastronomia.regrabar_hosts';

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaCierreJornadaProcesoRendicionReparacionService $postCierreService,
        private readonly GastronomiaConciliacionDiariaReporteService $conciliacionService,
    ) {
    }

    public static function fechaInicioEmpresa(int $empresaId): string
    {
        $map = config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $desde = trim((string) ($map[$empresaId] ?? ''));

        return $desde !== '' ? Carbon::parse($desde)->toDateString() : Carbon::parse('2026-06-01')->toDateString();
    }

    /**
     * @param  list<int>  $empresasIds
     * @return array<string, mixed>
     */
    public function ejecutar(
        array $empresasIds,
        ?string $fechaHasta = null,
        bool $dryRun = false,
        bool $regrabarPostCierre = true,
        ?callable $progreso = null,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $fechaHasta = Carbon::parse($fechaHasta ?? '2026-06-30')->toDateString();
        $informe = [
            'fecha_hasta' => $fechaHasta,
            'dry_run' => $dryRun,
            'empresas' => [],
        ];

        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $fechaDesde = self::fechaInicioEmpresa($empresaId);
            if ($fechaDesde > $fechaHasta) {
                throw new \InvalidArgumentException(
                    'fecha_jornada_desde '.$fechaDesde.' posterior a fecha_jornada_hasta '.$fechaHasta.' (empresa '.$empresaId.').'
                );
            }

            $progreso && $progreso('Empresa '.$empresaId.' | jornada '.$fechaDesde.' → '.$fechaHasta);

            $hosts = $this->hostsGastronomiaSalon($empresaId);
            $borrado = $this->borrarRendgastroHostsPorJornadas($empresaId, $fechaDesde, $fechaHasta, $hosts, $dryRun);
            $resync = $this->resincronizarRendicionesTurno($empresaId, $fechaDesde, $fechaHasta, $dryRun);
            $postCierre = $regrabarPostCierre
                ? $this->regrabarPostCierreJornadas($empresaId, $fechaDesde, $fechaHasta, $dryRun)
                : ['jornadas' => 0, 'regrabadas' => 0, 'detalle' => []];
            $auditoria = $this->auditarConciliacion($empresaId, $fechaDesde, $fechaHasta);

            $informe['empresas'][$empresaId] = [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'hosts' => $hosts,
                'borrado' => $borrado,
                'resync' => $resync,
                'post_cierre' => $postCierre,
                'auditoria' => $auditoria,
            ];
        }

        Log::info(self::LOG_EVENTO.'.ok', [
            'empresas' => array_keys($informe['empresas']),
            'dry_run' => $dryRun,
        ]);

        return $informe;
    }

    /**
     * @return list<string>
     */
    public function hostsGastronomiaSalon(int $empresaId): array
    {
        $terminales = ConfiguracionPuntoventaGastronomia::query()
            ->with('puntoventaCae')
            ->where('empresa_id', $empresaId)
            ->orderBy('identificador_pc')
            ->get();

        $hosts = [];
        foreach ($terminales as $cfg) {
            $cae = $cfg->puntoventaCae;
            if ($cae !== null && $this->esTerminalEstacionamiento($cae)) {
                continue;
            }

            $host = trim((string) $cfg->identificador_pc);
            if ($host === '' || ! preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host)) {
                continue;
            }

            $hosts[mb_strtolower($host)] = $host;
        }

        return array_values($hosts);
    }

    /**
     * Elimina cabeceras rendgastro cuyo rendg_fecha = fecha de jornada ERP y rendg_host ∈ hosts salón.
     *
     * @param  list<string>  $hostsGastronomia
     * @return array{jornadas: int, eliminadas: int, omitidas_estacionamiento: int, omitidas_waitry: int, omitidas_host: int, detalle: list<array<string, mixed>>}
     */
    private function borrarRendgastroHostsPorJornadas(
        int $empresaId,
        string $fechaJornadaDesde,
        string $fechaJornadaHasta,
        array $hostsGastronomia,
        bool $dryRun,
    ): array {
        $hostsMap = [];
        foreach ($hostsGastronomia as $host) {
            $hostsMap[mb_strtolower(trim($host))] = true;
        }

        $tipoOper = substr((string) config('rendicion_gastronomia_anita.tipo_oper', 'F'), 0, 1);
        $resultado = [
            'jornadas' => 0,
            'eliminadas' => 0,
            'omitidas_estacionamiento' => 0,
            'omitidas_waitry' => 0,
            'omitidas_host' => 0,
            'detalle' => [],
        ];

        $fechasJornada = $this->listarFechasJornadaEnRango($empresaId, $fechaJornadaDesde, $fechaJornadaHasta);
        $resultado['jornadas'] = count($fechasJornada);

        foreach ($fechasJornada as $fechaJornada) {
            $fechaEntera = (int) str_replace('-', '', $fechaJornada);
            $cabeceras = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);

            foreach ($cabeceras as $cab) {
                if ($this->rendgastroSupport->esCabeceraEstacionamiento($cab, $empresaId)) {
                    $resultado['omitidas_estacionamiento']++;

                    continue;
                }

                if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($cab)) {
                    $resultado['omitidas_waitry']++;

                    continue;
                }

                $host = mb_strtolower(trim((string) ($cab->rendg_host ?? '')));
                if ($host === '' || ! isset($hostsMap[$host])) {
                    $resultado['omitidas_host']++;

                    continue;
                }

                $nroOper = (int) ($cab->rendg_nro_oper ?? 0);
                if ($nroOper <= 0) {
                    continue;
                }

                $fila = [
                    'fecha_jornada' => $fechaJornada,
                    'rendg_fecha' => (int) ($cab->rendg_fecha ?? $fechaEntera),
                    'nro_oper' => $nroOper,
                    'host' => (string) ($cab->rendg_host ?? ''),
                    'sucursal' => (int) ($cab->rendg_sucursal ?? 0),
                    'z' => round((float) ($cab->rendg_total_z ?? 0), 2),
                ];

                if (! $dryRun) {
                    try {
                        $this->anitaSyncService->eliminarEnAnita($nroOper, $tipoOper);
                    } catch (\Throwable $e) {
                        Log::warning(self::LOG_EVENTO.'.delete_fallo', $fila + ['error' => $e->getMessage()]);

                        continue;
                    }
                }

                $resultado['eliminadas']++;
                $resultado['detalle'][] = $fila;
            }
        }

        return $resultado;
    }

    /**
     * Fechas de jornada ERP en el rango (rendg_fecha en Anita debe coincidir con estas fechas).
     *
     * @return list<string>
     */
    private function listarFechasJornadaEnRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $fechaDesde)
            ->whereDate('fecha_jornada', '<=', $fechaHasta)
            ->orderBy('fecha_jornada')
            ->pluck('fecha_jornada')
            ->map(static fn ($fecha) => Carbon::parse((string) $fecha)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{rendiciones: int, replicadas: int, errores: list<array<string, mixed>>, jornadas: list<int>}
     */
    private function resincronizarRendicionesTurno(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun,
    ): array {
        $rendiciones = $this->listarRendicionesTurnoEnRango($empresaId, $fechaDesde, $fechaHasta);
        $jornadaIds = [];
        $detalle = [];
        $errores = [];
        $replicadas = 0;

        if ($dryRun) {
            foreach ($rendiciones as $rendicion) {
                $detalle[] = $this->filaResyncSimulacion($rendicion);
                $jid = (int) ($rendicion->turnoOperativo?->jornada_gastronomia_id ?? 0);
                if ($jid > 0) {
                    $jornadaIds[$jid] = $jid;
                }
            }

            return [
                'rendiciones' => $rendiciones->count(),
                'replicadas' => 0,
                'errores' => [],
                'jornadas' => array_values($jornadaIds),
                'detalle' => $detalle,
            ];
        }

        $nroOperAnteriores = $rendiciones->mapWithKeys(function (RendicionGastronomiaCaja $r) {
            $nro = (int) ($r->nro_oper_anita
                ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($r->codigo));

            return [$r->id => $nro];
        });

        DB::transaction(function () use ($rendiciones): void {
            foreach ($rendiciones as $rendicion) {
                $nroAnterior = (int) ($rendicion->nro_oper_anita
                    ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

                if ($nroAnterior > 0) {
                    try {
                        $this->anitaSyncService->eliminarEnAnita(
                            $nroAnterior,
                            substr((string) config('rendicion_gastronomia_anita.tipo_oper', 'F'), 0, 1),
                        );
                    } catch (\Throwable $e) {
                        Log::warning(self::LOG_EVENTO.'.delete_previo_fallo', [
                            'rendicion_id' => $rendicion->id,
                            'nro_oper' => $nroAnterior,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $rendicion->update([
                    'codigo' => null,
                    'nro_oper_anita' => null,
                    'fuente_nro_oper' => null,
                    'anita_sincronizado_en' => null,
                ]);
            }
        });

        foreach ($rendiciones as $rendicion) {
            $rendicion->refresh();
            $nroAnterior = (int) ($nroOperAnteriores[$rendicion->id] ?? 0);

            try {
                $propuesta = $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
                $rendicion->update([
                    'codigo' => $propuesta['codigo'],
                    'nro_oper_anita' => $propuesta['nro_oper'],
                    'fuente_nro_oper' => $propuesta['fuente'],
                ]);
                $rendicion = $rendicion->fresh([
                    'movimientos.cuentacaja',
                    'puntoventaCae',
                    'puntoventaCaea',
                    'turnoOperativo.turno',
                    'turnoOperativo.jornada',
                ]);

                $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);
                $replicadas++;

                $jid = (int) ($rendicion->turnoOperativo?->jornada_gastronomia_id ?? 0);
                if ($jid > 0) {
                    $jornadaIds[$jid] = $jid;
                }

                $detalle[] = [
                    'rendicion_id' => $rendicion->id,
                    'jornada' => $rendicion->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d'),
                    'host' => $rendicion->turnoOperativo?->identificador_pc,
                    'nro_oper_anterior' => $nroAnterior > 0 ? $nroAnterior : null,
                    'nro_oper_nuevo' => $propuesta['nro_oper'],
                    'estado' => 'ok',
                ];
            } catch (\Throwable $e) {
                $errores[] = [
                    'rendicion_id' => $rendicion->id,
                    'mensaje' => $e->getMessage(),
                ];
                $detalle[] = [
                    'rendicion_id' => $rendicion->id,
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
                Log::error(self::LOG_EVENTO.'.resync_fallo', [
                    'rendicion_id' => $rendicion->id,
                    'error' => $e,
                ]);
            }
        }

        foreach (array_values($jornadaIds) as $jornadaId) {
            try {
                $this->anitaSyncService->reaplicarTotalZPorPcEnJornada((int) $jornadaId);
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.reaplicar_z_fallo', [
                    'jornada_id' => $jornadaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'rendiciones' => $rendiciones->count(),
            'replicadas' => $replicadas,
            'errores' => $errores,
            'jornadas' => array_values($jornadaIds),
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array{jornadas: int, regrabadas: int, detalle: list<array<string, mixed>>}
     */
    private function regrabarPostCierreJornadas(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun,
    ): array {
        $jornadas = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $fechaDesde)
            ->whereDate('fecha_jornada', '<=', $fechaHasta)
            ->orderBy('fecha_jornada')
            ->get();

        $detalle = [];
        $regrabadas = 0;

        foreach ($jornadas as $jornada) {
            $ver = $this->postCierreService->verificarJornada($jornada);
            if (! ($ver['requiere_regrabado'] ?? false) && (float) ($ver['ventas_erp_post_cierre'] ?? 0) <= 0.02) {
                continue;
            }

            if ($dryRun) {
                $detalle[] = [
                    'jornada_id' => $jornada->id,
                    'fecha' => $jornada->fecha_jornada?->format('Y-m-d'),
                    'estado' => 'simulado',
                    'erp_post_cierre' => $ver['ventas_erp_post_cierre'] ?? 0,
                ];

                continue;
            }

            try {
                $r = $this->postCierreService->regrabarJornada($jornada, false);
                $regrabadas++;
                $detalle[] = [
                    'jornada_id' => $jornada->id,
                    'fecha' => $jornada->fecha_jornada?->format('Y-m-d'),
                    'estado' => $r['estado'] ?? 'ok',
                ];
            } catch (\Throwable $e) {
                $detalle[] = [
                    'jornada_id' => $jornada->id,
                    'fecha' => $jornada->fecha_jornada?->format('Y-m-d'),
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning(self::LOG_EVENTO.'.post_cierre_fallo', [
                    'jornada_id' => $jornada->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'jornadas' => $jornadas->count(),
            'regrabadas' => $regrabadas,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array{dias_dif: int, dias_total: int, detalle_dif: list<array<string, mixed>>}
     */
    private function auditarConciliacion(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $informe = $this->conciliacionService->generar(
            $fechaDesde,
            $fechaHasta,
            [$empresaId],
            (float) config('gastronomia.conciliacion_diaria_reporte.tolerancia', 0.02),
        );

        $diasDif = 0;
        $diasTotal = 0;
        $detalleDif = [];

        foreach ($informe['empresas'][0]['dias'] ?? [] as $dia) {
            $diasTotal++;
            $estado = (string) ($dia['totales']['estado'] ?? '');
            if ($estado !== 'OK') {
                $diasDif++;
                $detalleDif[] = [
                    'fecha' => $dia['fecha_jornada'] ?? '',
                    'estado' => $estado,
                    'erp' => $dia['totales']['ventas_erp'] ?? null,
                    'anita' => $dia['totales']['ventas_anita'] ?? null,
                    'rendg' => $dia['totales']['rendgastro_z'] ?? null,
                    'diff_erp_rendg' => $dia['totales']['diff_erp_rendg'] ?? null,
                ];
            }
        }

        return [
            'dias_dif' => $diasDif,
            'dias_total' => $diasTotal,
            'detalle_dif' => $detalleDif,
        ];
    }

    /**
     * @return Collection<int, RendicionGastronomiaCaja>
     */
    private function listarRendicionesTurnoEnRango(int $empresaId, string $fechaDesde, string $fechaHasta): Collection
    {
        return RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('empresa_id', $empresaId)
            ->whereHas('turnoOperativo.jornada', function ($q) use ($fechaDesde, $fechaHasta) {
                $q->whereDate('fecha_jornada', '>=', $fechaDesde)
                    ->whereDate('fecha_jornada', '<=', $fechaHasta);
            })
            ->with(['puntoventaCae', 'turnoOperativo.jornada'])
            ->orderBy('fecharendicion')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function filaResyncSimulacion(RendicionGastronomiaCaja $rendicion): array
    {
        $nroAnterior = (int) ($rendicion->nro_oper_anita
            ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        return [
            'rendicion_id' => $rendicion->id,
            'jornada' => $rendicion->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d'),
            'host' => $rendicion->turnoOperativo?->identificador_pc,
            'nro_oper_anterior' => $nroAnterior > 0 ? $nroAnterior : null,
            'estado' => 'simulado',
        ];
    }

    private function esTerminalEstacionamiento(Puntoventa $pvCae): bool
    {
        $codigos = config('rendicion_gastronomia_anita.auditoria_diaria.puntoventa_codigos_solo_anita', []);
        if (in_array(trim((string) $pvCae->codigo), $codigos, true)) {
            return true;
        }

        $nombre = mb_strtolower(trim((string) $pvCae->nombre));

        return str_contains($nombre, 'estacionamiento')
            || str_contains($nombre, 'estac.');
    }
}
