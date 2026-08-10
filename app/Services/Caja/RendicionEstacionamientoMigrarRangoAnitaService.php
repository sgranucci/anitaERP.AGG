<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Support\Database\SqlDialectSupport;
use App\ApiAnita;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoValorAnitaMapper;
use App\Support\Caja\RendicionEstacionamientoNroOperPisoSupport;
use App\Support\Caja\RendicionEstacionamientoSecuenciaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mueve rendiciones de estacionamiento al rango dedicado (piso 850000+).
 * Al borrar/mover rendvalor SIEMPRE filtra por fecha de jornada.
 */
final class RendicionEstacionamientoMigrarRangoAnitaService
{
    private const LOG_EVENTO = 'rendicion_estacionamiento.migrar_rango_anita';

    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @param  list<int>|null  $empresaIds  null = todas
     * @return array<string, mixed>
     */
    public function ejecutar(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = true,
        ?array $empresaIds = null,
    ): array {
        $piso = RendicionEstacionamientoNroOperPisoSupport::piso();
        if ($piso <= 0) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_NRO_OPER_PISO no configurado.');
        }

        $query = RendicionEstacionamientoCaja::query()
            ->with(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada'])
            ->where('tipo', 'turno')
            ->where(function ($q) use ($fechaDesde, $fechaHasta) {
                $q->whereHas('turnoOperativo.jornada', function ($jq) use ($fechaDesde, $fechaHasta) {
                    $jq->whereBetween('fecha_jornada', [$fechaDesde, $fechaHasta]);
                })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereDoesntHave('turnoOperativo.jornada')
                        ->whereBetween(DB::raw(SqlDialectSupport::fecha('fecharendicion')), [$fechaDesde, $fechaHasta]);
                });
            })
            ->orderBy('id');

        if ($empresaIds !== null && $empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        /** @var \Illuminate\Support\Collection<int, RendicionEstacionamientoCaja> $rendiciones */
        $rendiciones = $query->get()->sortBy(function (RendicionEstacionamientoCaja $r) {
            $fecha = optional($r->turnoOperativo?->jornada)->fecha_jornada
                ?? substr((string) $r->fecharendicion, 0, 10);

            return sprintf('%s-%010d', $fecha, (int) $r->id);
        })->values();

        $api = new ApiAnita;
        $sistema = (string) config('rendicion_estacionamiento_anita.sistema', 'caja');
        $tipoOper = (string) config('rendicion_estacionamiento_anita.tipo_oper', 'F');

        $ultimoAnita = $this->anitaSyncService->ultimoNroOperEnAnita(0);
        $ultimoErp = $this->anitaSyncService->ultimoNroOperEnErp(0);
        $calculo = RendicionEstacionamientoSecuenciaSupport::calcularSiguiente(
            $ultimoAnita,
            $ultimoErp,
            $piso,
            RendicionEstacionamientoNroOperPisoSupport::techo(),
        );
        $siguiente = (int) $calculo['siguiente'];

        $detalle = [];
        $ok = 0;
        $omitidas = 0;
        $errores = [];

        foreach ($rendiciones as $rendicion) {
            $nroAnterior = (int) ($rendicion->nro_oper_anita
                ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

            $fechaJornada = optional($rendicion->turnoOperativo?->jornada)->fecha_jornada
                ?? substr((string) $rendicion->fecharendicion, 0, 10);
            $fechaEntera = (int) str_replace('-', '', (string) $fechaJornada);

            if ($nroAnterior > 0 && RendicionEstacionamientoNroOperPisoSupport::enRango($nroAnterior)) {
                $omitidas++;
                $detalle[] = [
                    'rendicion_id' => (int) $rendicion->id,
                    'empresa_id' => (int) $rendicion->empresa_id,
                    'fecha_jornada' => (string) $fechaJornada,
                    'nro_oper_anterior' => $nroAnterior,
                    'nro_oper_nuevo' => $nroAnterior,
                    'estado' => 'ya_en_rango',
                ];
                continue;
            }

            if ($fechaEntera <= 0) {
                $errores[] = 'Rendición #'.$rendicion->id.': sin fecha de jornada para filtrar rendvalor.';
                $detalle[] = [
                    'rendicion_id' => (int) $rendicion->id,
                    'empresa_id' => (int) $rendicion->empresa_id,
                    'fecha_jornada' => (string) $fechaJornada,
                    'nro_oper_anterior' => $nroAnterior,
                    'nro_oper_nuevo' => null,
                    'estado' => 'error_sin_fecha',
                ];
                continue;
            }

            $nroNuevo = $siguiente;
            $siguiente++;

            $fila = [
                'rendicion_id' => (int) $rendicion->id,
                'empresa_id' => (int) $rendicion->empresa_id,
                'fecha_jornada' => (string) $fechaJornada,
                'fecha_entera' => $fechaEntera,
                'nro_oper_anterior' => $nroAnterior > 0 ? $nroAnterior : null,
                'nro_oper_nuevo' => $nroNuevo,
                'estado' => $dryRun ? 'simulado' : 'pendiente',
            ];

            if ($dryRun) {
                $ok++;
                $detalle[] = $fila;
                continue;
            }

            try {
                $this->moverUna($rendicion, $nroAnterior, $nroNuevo, $fechaEntera, $api, $sistema, $tipoOper);
                $fila['estado'] = 'ok';
                $ok++;
            } catch (\Throwable $e) {
                $fila['estado'] = 'error';
                $fila['error'] = $e->getMessage();
                $errores[] = 'Rendición #'.$rendicion->id.': '.$e->getMessage();
                Log::error(self::LOG_EVENTO, $fila);
            }

            $detalle[] = $fila;
        }

        if (! $dryRun && $ok > 0) {
            // Persiste secuencia ERP alineada al MAX ya grabado en Anita/ERP del rango.
            foreach ([1, 2, 3] as $empresaId) {
                try {
                    $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
                } catch (\Throwable $e) {
                    Log::warning(self::LOG_EVENTO.'.secuencia', [
                        'empresa_id' => $empresaId,
                        'mensaje' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'piso' => $piso,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'dry_run' => $dryRun,
            'total' => $rendiciones->count(),
            'ok' => $ok,
            'omitidas' => $omitidas,
            'errores' => $errores,
            'primer_nro' => $calculo['siguiente'],
            'detalle' => $detalle,
        ];
    }

    private function moverUna(
        RendicionEstacionamientoCaja $rendicion,
        int $nroAnterior,
        int $nroNuevo,
        int $fechaEntera,
        ApiAnita $api,
        string $sistema,
        string $tipoOper,
    ): void {
        DB::transaction(function () use ($rendicion, $nroNuevo) {
            $rendicion->update([
                'codigo' => (string) $nroNuevo,
                'nro_oper_anita' => $nroNuevo,
                'fuente_nro_oper' => 'rango_850000',
                'anita_sincronizado_en' => null,
            ]);
        });

        $rendicion->refresh();
        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        $this->anitaSyncService->insertarEnAnita($rendicion);

        if ($nroAnterior > 0 && $nroAnterior !== $nroNuevo) {
            // CRÍTICO: solo líneas de la fecha de jornada de esta rendición.
            $api->apiCallEscritura([
                'acc' => 'delete',
                'tabla' => 'rendvalor',
                'sistema' => $sistema,
                'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacion($nroAnterior, $tipoOper)
                    ." AND rendv_fecha = '".$fechaEntera."' ",
            ], 'rendvalor delete fecha migrar rango', self::LOG_EVENTO);

            $api->apiCallEscritura([
                'acc' => 'delete',
                'tabla' => 'rendgastro',
                'sistema' => $sistema,
                'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroAnterior, $tipoOper),
            ], 'rendgastro delete migrar rango', self::LOG_EVENTO);
        }

        $rendicion->update(['anita_sincronizado_en' => now()]);
    }
}
