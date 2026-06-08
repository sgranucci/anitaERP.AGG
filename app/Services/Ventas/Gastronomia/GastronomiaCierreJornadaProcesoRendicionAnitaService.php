<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Services\Caja\RendicionGastronomiaAnitaSyncService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaTotalZPorPuntoventaService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Genera rendgastro / rendvalor en Anita al cerrar el proceso Waitry (post-asientos).
 */
final class GastronomiaCierreJornadaProcesoRendicionAnitaService
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaTotalZPorPuntoventaService $totalZPorPuntoventaService,
        private readonly Caja_AsignacionQueryInterface $cajaAsignacionQuery,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function grabar(int $jornadaId): array
    {
        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($snapshot === null) {
            throw new InvalidArgumentException('No hay snapshot de proceso para esta jornada.');
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];

        if (empty($payload['asientos_proceso_grabacion']['asientos'])) {
            throw new InvalidArgumentException('Debe grabar los asientos del proceso antes de la rendición Anita.');
        }

        if (! empty($payload['rendicion_proceso_anita']['nro_oper'])) {
            return [
                'ok' => true,
                'mensaje' => 'La rendición Anita del proceso ya fue registrada.',
                'ya_existia' => true,
                'rendicion' => $payload['rendicion_proceso_anita'],
            ];
        }

        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];
        $ventaIds = CierreJornadaProcesoFacturaRecuperacionSupport::ventaIdsDesdeRecuperacion($emision);
        if ($ventaIds === []) {
            throw new InvalidArgumentException('No hay facturas del proceso para rendir en Anita.');
        }

        $puntoventaId = (int) ($emision['puntoventa_id'] ?? 0);
        if ($puntoventaId <= 0) {
            $pv = CierreJornadaProcesoPuntoventaSupport::resolverOError((int) $jornada->empresa_id);
            $puntoventaId = (int) $pv['id'];
        }

        $empresaId = (int) $jornada->empresa_id;
        $numeracion = $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
        $nroOper = (int) ($numeracion['nro_oper'] ?? 0);
        if ($nroOper <= 0) {
            throw new RuntimeException('No se pudo obtener numeración rendgastro para Anita.');
        }

        [$cajaId] = $this->resolverCajaId();

        $ctx = CierreJornadaProcesoRendicionAnitaSupport::armarContextoAnita(
            $jornada,
            $puntoventaId,
            $nroOper,
            $ventaIds,
            $cajaId,
            (int) (Auth::id() ?? 0),
        );

        $totalFacturado = round((float) ($ctx['total_x'] ?? 0), 2);
        $totalCobrado = CierreJornadaProcesoRendicionAnitaSupport::totalCobradoProceso($ventaIds);
        if (abs($totalFacturado - $totalCobrado) > 0.05) {
            throw new InvalidArgumentException(
                'Los medios de cobro de las facturas CF ('.number_format($totalCobrado, 2, ',', '.')
                .') no coinciden con el total facturado ('.number_format($totalFacturado, 2, ',', '.').').',
            );
        }

        try {
            $this->anitaSyncService->insertarDesdeContexto($ctx);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error al grabar rendgastro/rendvalor en Anita: '.$e->getMessage(),
                0,
                $e,
            );
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $recalcZ = $this->totalZPorPuntoventaService->aplicar($empresaId, $fechaJornada, $puntoventaId);

        $registro = [
            'nro_oper' => $nroOper,
            'tipo_oper' => (string) ($ctx['tipo_oper'] ?? config('rendicion_gastronomia_anita.tipo_oper', 'F')),
            'puntoventa_id' => $puntoventaId,
            'puntoventa_codigo' => (string) ($ctx['puntoventa_caea_codigo'] ?? ''),
            'total_x' => round((float) ($ctx['total_x'] ?? 0), 2),
            'total_z' => round((float) ($recalcZ['total_z'] ?? 0), 2),
            'tot_nc' => round((float) ($recalcZ['tot_nc'] ?? 0), 2),
            'portadora_nro_oper' => $recalcZ['portadora_nro_oper'] ?? null,
            'turno' => CierreJornadaProcesoRendicionAnitaSupport::TURNO_LETRA,
            'movimientos' => $ctx['movimientos_filas'] ?? [],
            'fuente_nro_oper' => (string) ($numeracion['fuente'] ?? ''),
            'grabado_en' => now()->toIso8601String(),
            'recalc_z' => $recalcZ,
        ];

        DB::transaction(function () use ($snapshot, $registro) {
            $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
            $payload['rendicion_proceso_anita'] = $registro;
            $snapshot->payload = $payload;
            $snapshot->save();
        });

        return [
            'ok' => true,
            'mensaje' => 'Rendición Anita registrada (rendgastro #'.$nroOper.', X '
                .number_format((float) $registro['total_x'], 2, ',', '.').', Z PV '
                .number_format((float) $registro['total_z'], 2, ',', '.').').',
            'rendicion' => $registro,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revertirDesdePayload(array $payload, ?JornadaGastronomia $jornada = null): array
    {
        $rendicion = is_array($payload['rendicion_proceso_anita'] ?? null)
            ? $payload['rendicion_proceso_anita']
            : null;

        if ($rendicion === null || empty($rendicion['nro_oper'])) {
            return ['eliminada' => false, 'motivo' => 'sin_rendicion'];
        }

        $nroOper = (int) $rendicion['nro_oper'];
        $tipoOper = (string) ($rendicion['tipo_oper'] ?? config('rendicion_gastronomia_anita.tipo_oper', 'F'));
        $puntoventaId = (int) ($rendicion['puntoventa_id'] ?? 0);

        try {
            $this->anitaSyncService->eliminarEnAnita($nroOper, $tipoOper);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo borrar rendgastro/rendvalor #'.$nroOper.' en Anita: '.$e->getMessage(),
                0,
                $e,
            );
        }

        $recalcZ = null;
        if ($jornada !== null && $puntoventaId > 0) {
            $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
                ?? $jornada->cierre_en?->format('Y-m-d');
            if ($fechaJornada !== null && $fechaJornada !== '') {
                try {
                    $recalcZ = $this->totalZPorPuntoventaService->aplicar(
                        (int) $jornada->empresa_id,
                        $fechaJornada,
                        $puntoventaId,
                    );
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        'Rendición eliminada pero falló el recálculo de Z del PV: '.$e->getMessage(),
                        0,
                        $e,
                    );
                }
            }
        }

        return [
            'eliminada' => true,
            'nro_oper' => $nroOper,
            'tipo_oper' => $tipoOper,
            'recalc_z' => $recalcZ,
        ];
    }

    /**
     * @return array{0:int}
     */
    private function resolverCajaId(): array
    {
        $asignacion = $this->cajaAsignacionQuery->leeAsignacionPorUsuario((int) Auth::id(), Carbon::now());
        if ($asignacion !== null && (int) ($asignacion->caja_id ?? 0) > 0) {
            return [(int) $asignacion->caja_id];
        }

        return [0];
    }
}
