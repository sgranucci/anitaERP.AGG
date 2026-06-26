<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use Illuminate\Support\Facades\Log;

/**
 * Re-sincroniza en Anita (rendgastro + rendvalor) todas las rendiciones turno del ERP
 * y marca rendg_estado='F' en cabeceras post-cierre Waitry (CIERRE-WAITRY).
 */
final class RendicionGastronomiaActualizarTodasDesdeErpAnitaService
{
    private const LOG_EVENTO = 'rendicion_gastronomia.actualizar_todas_desde_erp';

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function ejecutar(
        array $empresaIds,
        bool $dryRun = false,
        bool $incluirPostCierre = true,
        ?callable $progreso = null,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), static fn (int $id): bool => $id > 0));
        if ($empresaIds === []) {
            throw new \InvalidArgumentException('Indique al menos una empresa.');
        }

        $resultado = [
            'dry_run' => $dryRun,
            'empresas' => $empresaIds,
            'rendiciones_turno' => [
                'total' => 0,
                'actualizadas' => 0,
                'insertadas' => 0,
                'omitidas_jornada' => 0,
                'errores' => [],
            ],
            'post_cierre' => [
                'total' => 0,
                'estado_f' => 0,
                'errores' => [],
            ],
        ];

        $query = RendicionGastronomiaCaja::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where('nro_oper_anita', '>', 0)
            ->orderBy('id');

        $resultado['rendiciones_turno']['total'] = (clone $query)->count();

        $query->chunkById(25, function ($rendiciones) use ($dryRun, $progreso, &$resultado): void {
            /** @var RendicionGastronomiaCaja $rendicion */
            foreach ($rendiciones as $rendicion) {
                if ($rendicion->esRendicionJornada()) {
                    $resultado['rendiciones_turno']['omitidas_jornada']++;

                    continue;
                }

                $progreso && $progreso(
                    'Rendición #'.$rendicion->id.' nro_oper '.$rendicion->nro_oper_anita.' (emp '.$rendicion->empresa_id.')',
                );

                if ($dryRun) {
                    $resultado['rendiciones_turno']['actualizadas']++;

                    continue;
                }

                try {
                    $rendicion->load([
                        'movimientos.cuentacaja',
                        'puntoventaCae',
                        'puntoventaCaea',
                        'turnoOperativo.turno',
                        'turnoOperativo.jornada',
                    ]);

                    if ($this->anitaSyncService->existsCabeceraEnAnita($rendicion)) {
                        $this->anitaSyncService->actualizarEnAnita($rendicion);
                        $resultado['rendiciones_turno']['actualizadas']++;
                    } else {
                        $this->anitaSyncService->insertarEnAnita($rendicion);
                        $resultado['rendiciones_turno']['insertadas']++;
                    }

                    $rendicion->update(['anita_sincronizado_en' => now()]);
                } catch (\Throwable $e) {
                    Log::warning(self::LOG_EVENTO.'.rendicion_fallo', [
                        'rendicion_id' => (int) $rendicion->id,
                        'nro_oper' => (int) $rendicion->nro_oper_anita,
                        'error' => $e->getMessage(),
                    ]);
                    $resultado['rendiciones_turno']['errores'][] = [
                        'rendicion_id' => (int) $rendicion->id,
                        'nro_oper' => (int) $rendicion->nro_oper_anita,
                        'mensaje' => $e->getMessage(),
                    ];
                }
            }
        });

        if ($incluirPostCierre) {
            $this->actualizarPostCierreWaitry($empresaIds, $dryRun, $progreso, $resultado);
        }

        Log::info(self::LOG_EVENTO.'.ok', $resultado);

        return $resultado;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $resultado
     */
    private function actualizarPostCierreWaitry(
        array $empresaIds,
        bool $dryRun,
        ?callable $progreso,
        array &$resultado,
    ): void {
        $nroOperVistos = [];
        $jornadaIds = JornadaGastronomia::query()
            ->whereIn('empresa_id', $empresaIds)
            ->pluck('id')
            ->all();

        foreach ($jornadaIds as $jornadaId) {
            $empresaId = (int) JornadaGastronomia::query()->whereKey($jornadaId)->value('empresa_id');
            foreach ($this->rendgastroSupport->listarCabecerasPostCierrePorJornada($empresaId, (int) $jornadaId) as $cab) {
                $nroOper = (int) ($cab->rendg_nro_oper ?? 0);
                if ($nroOper > 0) {
                    $nroOperVistos[$nroOper] = true;
                }
            }
        }

        foreach (GastronomiaCierreJornadaProcesoSnapshot::query()->get() as $snapshot) {
            $jornadaId = (int) ($snapshot->jornada_gastronomia_id ?? 0);
            if ($jornadaId <= 0) {
                continue;
            }
            $empresaId = (int) JornadaGastronomia::query()->whereKey($jornadaId)->value('empresa_id');
            if (! in_array($empresaId, $empresaIds, true)) {
                continue;
            }
            $rend = is_array($snapshot->payload['rendicion_proceso_anita'] ?? null)
                ? $snapshot->payload['rendicion_proceso_anita']
                : null;
            $nroOper = (int) ($rend['nro_oper'] ?? 0);
            if ($nroOper > 0) {
                $nroOperVistos[$nroOper] = true;
            }
        }

        $nroOperList = array_keys($nroOperVistos);
        sort($nroOperList);
        $resultado['post_cierre']['total'] = count($nroOperList);

        foreach ($nroOperList as $nroOper) {
            $progreso && $progreso('Post-cierre CIERRE-WAITRY nro_oper '.$nroOper);

            if ($dryRun) {
                $resultado['post_cierre']['estado_f']++;

                continue;
            }

            try {
                $this->anitaSyncService->marcarEstadoFinalPorNroOper($nroOper);
                $resultado['post_cierre']['estado_f']++;
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.post_cierre_fallo', [
                    'nro_oper' => $nroOper,
                    'error' => $e->getMessage(),
                ]);
                $resultado['post_cierre']['errores'][] = [
                    'nro_oper' => $nroOper,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }
    }
}
