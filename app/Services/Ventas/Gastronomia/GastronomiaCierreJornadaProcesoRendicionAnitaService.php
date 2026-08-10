<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Caja\RendicionGastronomiaAnitaSyncService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\RendicionGastronomiaNroOperPisoSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Genera rendgastro / rendvalor en Anita a partir de las facturas post-cierre (ventas).
 * No depende de asientos contables: el total CIERRE-WAITRY sale del total facturado ERP.
 */
final class GastronomiaCierreJornadaProcesoRendicionAnitaService
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
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

        $emisionPrevia = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];
        if (! CierreJornadaProcesoJornadaSupport::facturaProcesoConsideradaEmitida($emisionPrevia)
            && ! CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emisionPrevia)) {
            throw new InvalidArgumentException(
                'Debe emitir las facturas del proceso (ventas) antes de la rendición Anita (rendgastro).',
            );
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
        $jornadaId = (int) $jornada->id;

        [$cajaId] = $this->resolverCajaId();

        $totalFacturado = 0.0;
        if ($ventaIds !== []) {
            $totalFacturado = round(CierreJornadaProcesoRendicionAnitaSupport::totalFacturasProceso($ventaIds), 2);
            $totalCobrado = CierreJornadaProcesoRendicionAnitaSupport::totalCobradoProceso($ventaIds);
            if (abs($totalFacturado - $totalCobrado) > 0.05) {
                throw new InvalidArgumentException(
                    'Los medios de cobro de las facturas CF ('.number_format($totalCobrado, 2, ',', '.')
                    .') no coinciden con el total facturado ('.number_format($totalFacturado, 2, ',', '.').').',
                );
            }
        }

        [$nroOper, $numeracion, $ctx] = $this->insertarPostCierreConNroOperDisponible(
            $jornada,
            $puntoventaId,
            $ventaIds,
            $cajaId,
            (int) (Auth::id() ?? 0),
        );

        $fechaEnteraJornada = (int) ($ctx['fecha_entera'] ?? 0);
        $this->assertCabeceraPostCierreInsertada(
            $empresaId,
            $jornadaId,
            $nroOper,
            $totalFacturado,
            $fechaEnteraJornada,
        );

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
        unset($empresaId);
        $piso = RendicionGastronomiaNroOperPisoSupport::piso();
        if ($piso <= 0) {
            return;
        }

        if (! RendicionGastronomiaNroOperPisoSupport::enRango($nroOper)) {
            throw new RuntimeException(
                'nro_oper '.$nroOper.' fuera del rango compartido rendgastro'
                .' (piso '.$piso.'). Revise numeración Anita/ERP.',
            );
        }
    }

    private function assertCabeceraPostCierreInsertada(
        int $empresaId,
        int $jornadaId,
        int $nroOper,
        float $totalEsperado,
        int $fechaEnteraJornada,
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

        if ($fechaEnteraJornada > 0 && (int) ($cab->rendg_fecha ?? 0) !== $fechaEnteraJornada) {
            throw new RuntimeException(
                'Cabecera CIERRE-WAITRY #'.$nroOper.' rendg_fecha '.((int) ($cab->rendg_fecha ?? 0))
                .' ≠ fecha de jornada '.$fechaEnteraJornada.'.',
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
     * @param  list<int>  $ventaIds
     * @return array{0:int,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function insertarPostCierreConNroOperDisponible(
        JornadaGastronomia $jornada,
        int $puntoventaId,
        array $ventaIds,
        int $cajaId,
        int $usuarioId,
    ): array {
        $empresaId = (int) $jornada->empresa_id;
        $jornadaId = (int) $jornada->id;
        $ultimoError = null;

        for ($intento = 0; $intento < 10; $intento++) {
            $numeracion = $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
            $nroOper = (int) ($numeracion['nro_oper'] ?? 0);
            if ($nroOper <= 0) {
                continue;
            }

            $this->assertNroOperEnRangoEmpresa($empresaId, $nroOper);
            if (! $this->nroOperDisponibleParaPostCierreJornada($empresaId, $nroOper, $jornadaId)) {
                continue;
            }

            $ctx = CierreJornadaProcesoRendicionAnitaSupport::armarContextoAnita(
                $jornada,
                $puntoventaId,
                $nroOper,
                $ventaIds,
                $cajaId,
                $usuarioId,
            );

            try {
                $this->anitaSyncService->insertarDesdeContexto($ctx);

                return [$nroOper, $numeracion, $ctx];
            } catch (Throwable $e) {
                if ($this->esErrorNroOperOcupadoPorOtraJornada($e)) {
                    $ultimoError = $e;
                    continue;
                }

                throw new RuntimeException(
                    'Error al grabar rendgastro/rendvalor en Anita: '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        $mensaje = 'No se pudo obtener nro_oper libre para post-cierre de la jornada #'.$jornadaId.'.';
        if ($ultimoError !== null) {
            $mensaje .= ' Último intento: '.$ultimoError->getMessage();
        }

        throw new RuntimeException($mensaje);
    }

    private function nroOperDisponibleParaPostCierreJornada(int $empresaId, int $nroOper, int $jornadaId): bool
    {
        $cab = $this->rendgastroSupport->listarCabeceraPorNroOper($empresaId, $nroOper);
        if ($cab === null) {
            return true;
        }

        if (! $this->rendgastroSupport->esCabeceraPostCierreWaitry($cab)) {
            return false;
        }

        return (int) ($cab->rendg_nro_rend_vta ?? 0) === $jornadaId;
    }

    private function esErrorNroOperOcupadoPorOtraJornada(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'ya pertenece a la jornada #');
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
        [$cajaId] = \App\Support\Caja\CajaRecepcionPcSupport::resolver(null, request());

        return [$cajaId > 0 ? $cajaId : 0];
    }
}
