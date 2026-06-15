<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionGastronomiaSecuenciaEmpresa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaCabeceraAnitaMapper;
use App\Support\Caja\RendicionGastronomiaNroOperPisoSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Elimina rendgastro/rendvalor legacy, renumerar desde piso por empresa y re-sincroniza rendiciones de turno.
 */
final class RendicionGastronomiaRenumerarResincronizarAnitaService
{
    private const LOG_EVENTO = 'rendicion_gastronomia.renumerar_resincronizar';

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        int $empresaId,
        string $fechaJornadaDesde,
        bool $dryRun = false,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('empresa_id inválida.');
        }

        $fechaJornadaDesde = Carbon::parse($fechaJornadaDesde)->toDateString();
        $piso = RendicionGastronomiaNroOperPisoSupport::pisoParaEmpresa($empresaId);

        $rendiciones = $this->listarRendicionesTurnoDesdeJornada($empresaId, $fechaJornadaDesde);
        $jornadaIds = $rendiciones
            ->map(fn (RendicionGastronomiaCaja $r) => (int) ($r->turnoOperativo?->jornada_gastronomia_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $detalle = [];
        $errores = [];
        $nroOperAnteriores = $rendiciones->mapWithKeys(function (RendicionGastronomiaCaja $r) {
            $nro = (int) ($r->nro_oper_anita
                ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($r->codigo));

            return [$r->id => $nro];
        });

        if ($dryRun) {
            foreach ($rendiciones as $rendicion) {
                $detalle[] = $this->filaDetalleSimulacion($rendicion);
            }

            return [
                'empresa_id' => $empresaId,
                'fecha_jornada_desde' => $fechaJornadaDesde,
                'piso' => $piso,
                'dry_run' => true,
                'rendiciones' => $rendiciones->count(),
                'jornadas' => count($jornadaIds),
                'detalle' => $detalle,
                'errores' => [],
            ];
        }

        DB::transaction(function () use ($rendiciones, $empresaId, $piso, &$detalle, &$errores): void {
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
                        Log::warning(self::LOG_EVENTO.'.delete_anita_fallo', [
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

            $this->resetearSecuenciaEmpresa($empresaId, $piso);
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
                $rendicion = $rendicion->fresh(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

                $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);

                $detalle[] = [
                    'rendicion_id' => $rendicion->id,
                    'turno_operativo_id' => $rendicion->turno_operativo_gastronomia_id,
                    'pv' => $rendicion->puntoventaCae?->codigo,
                    'fecharendicion' => $rendicion->fecharendicion?->format('Y-m-d H:i:s'),
                    'nro_oper_anterior' => $nroAnterior,
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

        foreach ($jornadaIds as $jornadaId) {
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
            'empresa_id' => $empresaId,
            'fecha_jornada_desde' => $fechaJornadaDesde,
            'piso' => $piso,
            'dry_run' => false,
            'rendiciones' => $rendiciones->count(),
            'jornadas' => count($jornadaIds),
            'detalle' => $detalle,
            'errores' => $errores,
        ];
    }

    /**
     * @return Collection<int, RendicionGastronomiaCaja>
     */
    private function listarRendicionesTurnoDesdeJornada(int $empresaId, string $fechaJornadaDesde): Collection
    {
        return RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('empresa_id', $empresaId)
            ->whereHas('turnoOperativo.jornada', function ($q) use ($fechaJornadaDesde) {
                $q->whereDate('fecha_jornada', '>=', $fechaJornadaDesde);
            })
            ->with(['puntoventaCae', 'turnoOperativo.jornada'])
            ->orderBy('fecharendicion')
            ->orderBy('id')
            ->get();
    }

    private function resetearSecuenciaEmpresa(int $empresaId, int $piso): void
    {
        $ultimo = max(0, $piso - 1);
        $proximo = $piso > 0 ? $piso : 1;

        RendicionGastronomiaSecuenciaEmpresa::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'ultimo_nro_anita' => $ultimo,
                'ultimo_nro_erp' => $ultimo,
                'proximo_nro' => $proximo,
                'consultado_anita_en' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDetalleSimulacion(RendicionGastronomiaCaja $rendicion): array
    {
        $nroAnterior = (int) ($rendicion->nro_oper_anita
            ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        return [
            'rendicion_id' => $rendicion->id,
            'turno_operativo_id' => $rendicion->turno_operativo_gastronomia_id,
            'pv' => $rendicion->puntoventaCae?->codigo,
            'jornada' => $rendicion->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d'),
            'fecharendicion' => $rendicion->fecharendicion?->format('Y-m-d H:i:s'),
            'nro_oper_anterior' => $nroAnterior > 0 ? $nroAnterior : null,
            'estado' => 'simulado',
        ];
    }
}
