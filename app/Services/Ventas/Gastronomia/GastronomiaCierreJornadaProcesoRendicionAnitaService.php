<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Services\Caja\RendicionGastronomiaAnitaSyncService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\RendicionGastronomiaNroOperPisoSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
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
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
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

        $rendSnap = is_array($payload['rendicion_proceso_anita'] ?? null)
            ? $payload['rendicion_proceso_anita']
            : null;

        if ($rendSnap !== null && ! empty($rendSnap['nro_oper'])) {
            if ($this->rendicionPostCierreValidaEnAnita((int) $jornada->empresa_id, (int) $jornada->id, $rendSnap)) {
                return [
                    'ok' => true,
                    'mensaje' => 'La rendición Anita del proceso ya fue registrada.',
                    'ya_existia' => true,
                    'rendicion' => $rendSnap,
                ];
            }

            $payload = $this->quitarRendicionSnapshot($snapshot, $payload);
        }

        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];
        $emisionOmitida = CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emision);
        $ventaIds = CierreJornadaProcesoFacturaRecuperacionSupport::ventaIdsDesdeRecuperacion($emision);
        if ($ventaIds === [] && ! $emisionOmitida) {
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

        $this->assertNroOperEnRangoEmpresa($empresaId, $nroOper);

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
        if ($ventaIds !== []) {
            $totalCobrado = CierreJornadaProcesoRendicionAnitaSupport::totalCobradoProceso($ventaIds);
            if (abs($totalFacturado - $totalCobrado) > 0.05) {
                throw new InvalidArgumentException(
                    'Los medios de cobro de las facturas CF ('.number_format($totalCobrado, 2, ',', '.')
                    .') no coinciden con el total facturado ('.number_format($totalFacturado, 2, ',', '.').').',
                );
            }
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

        $this->assertCabeceraPostCierreInsertada($empresaId, (int) $jornada->id, $nroOper, $totalFacturado);

        // Z en CIERRE-WAITRY = post-cierre únicamente (mismo importe que total_x / tot_fc_caea).
        try {
            $this->anitaSyncService->actualizarTotalesReparacionPorNroOper(
                $nroOper,
                $totalFacturado,
                0.0,
                $totalFacturado,
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Rendición insertada pero no se pudo normalizar cabecera post-cierre #'.$nroOper.': '.$e->getMessage(),
                0,
                $e,
            );
        }

        $this->anitaSyncService->reaplicarTotalZPorPcEnJornada($jornadaId);

        $registro = [
            'nro_oper' => $nroOper,
            'tipo_oper' => (string) ($ctx['tipo_oper'] ?? config('rendicion_gastronomia_anita.tipo_oper', 'F')),
            'puntoventa_id' => $puntoventaId,
            'puntoventa_codigo' => (string) ($ctx['puntoventa_caea_codigo'] ?? ''),
            'total_x' => $totalFacturado,
            'total_z' => $totalFacturado,
            'tot_nc' => 0.0,
            'portadora_nro_oper' => $nroOper,
            'turno' => CierreJornadaProcesoRendicionAnitaSupport::TURNO_LETRA,
            'movimientos' => $ctx['movimientos_filas'] ?? [],
            'fuente_nro_oper' => (string) ($numeracion['fuente'] ?? ''),
            'grabado_en' => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($snapshot, $registro) {
            $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
            $payload['rendicion_proceso_anita'] = $registro;
            $snapshot->payload = $payload;
            $snapshot->save();
        });

        return [
            'ok' => true,
            'mensaje' => 'Rendición Anita registrada (rendgastro #'.$nroOper.', CIERRE-WAITRY $ '
                .number_format($totalFacturado, 2, ',', '.').').',
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

        $cab = $this->rendgastroSupport->listarCabeceraPorNroOper(
            (int) ($jornada?->empresa_id ?? 0),
            $nroOper,
        );
        if ($cab !== null && $this->rendgastroSupport->esCabeceraPostCierreWaitry($cab)) {
            try {
                $this->anitaSyncService->eliminarEnAnita($nroOper, $tipoOper);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'No se pudo borrar rendgastro/rendvalor #'.$nroOper.' en Anita: '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        if ($jornada !== null) {
            try {
                $this->anitaSyncService->reaplicarTotalZPorPcEnJornada((int) $jornada->id);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Rendición eliminada pero falló el recálculo de Z por PC: '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        return [
            'eliminada' => true,
            'nro_oper' => $nroOper,
            'tipo_oper' => $tipoOper,
        ];
    }

    /**
     * @param  array<string, mixed>  $rendSnap
     */
    public function rendicionPostCierreValidaEnAnita(int $empresaId, int $jornadaId, array $rendSnap): bool
    {
        $cabeceras = $this->rendgastroSupport->listarCabecerasPostCierrePorJornada($empresaId, $jornadaId);
        if ($cabeceras !== []) {
            return true;
        }

        $nroOper = (int) ($rendSnap['nro_oper'] ?? 0);
        if ($nroOper <= 0) {
            return false;
        }

        $cab = $this->rendgastroSupport->listarCabeceraPorNroOper($empresaId, $nroOper);
        if ($cab === null || ! $this->rendgastroSupport->esCabeceraPostCierreWaitry($cab)) {
            return false;
        }

        return (int) ($cab->rendg_nro_rend_vta ?? 0) === $jornadaId;
    }

    private function assertNroOperEnRangoEmpresa(int $empresaId, int $nroOper): void
    {
        $piso = RendicionGastronomiaNroOperPisoSupport::pisoParaEmpresa($empresaId);
        if ($piso <= 0) {
            return;
        }

        if (! RendicionGastronomiaNroOperPisoSupport::enRangoEmpresa($empresaId, $nroOper)) {
            throw new RuntimeException(
                'nro_oper '.$nroOper.' fuera del rango dedicado de la empresa '.$empresaId
                .' (piso '.$piso.'). Revise numeración Anita/ERP.',
            );
        }
    }

    private function assertCabeceraPostCierreInsertada(
        int $empresaId,
        int $jornadaId,
        int $nroOper,
        float $totalEsperado,
    ): void {
        $cab = $this->rendgastroSupport->listarCabeceraPorNroOper($empresaId, $nroOper);
        if ($cab === null || ! $this->rendgastroSupport->esCabeceraPostCierreWaitry($cab)) {
            throw new RuntimeException(
                'Tras insertar rendición #'.$nroOper.' no se encontró cabecera CIERRE-WAITRY en Anita.',
            );
        }

        if ((int) ($cab->rendg_nro_rend_vta ?? 0) !== $jornadaId) {
            throw new RuntimeException(
                'Cabecera CIERRE-WAITRY #'.$nroOper.' no corresponde a la jornada #'.$jornadaId.'.',
            );
        }

        $totalX = round((float) ($cab->rendg_total_x ?? 0), 2);
        if ($totalEsperado > 0 && abs($totalX - $totalEsperado) > 0.05) {
            throw new RuntimeException(
                'Cabecera CIERRE-WAITRY #'.$nroOper.' total_x '.$totalX.' ≠ esperado '.$totalEsperado.'.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function quitarRendicionSnapshot(
        GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        array $payload,
    ): array {
        unset($payload['rendicion_proceso_anita']);
        DB::transaction(function () use ($snapshot, $payload) {
            $snapshot->payload = $payload;
            $snapshot->save();
        });

        return $payload;
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
