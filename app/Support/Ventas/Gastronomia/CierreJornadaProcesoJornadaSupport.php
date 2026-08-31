<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;

/**
 * Reglas de jornada gastronomía vs proceso Waitry en Caja.
 *
 * - Cierre de jornada en Ventas → Gastronomía congela el día (no más facturación POS).
 * - Este proceso en Caja puede auditar con jornada abierta (vista previa del día).
 * - La emisión de la factura del proceso exige jornada cerrada y snapshot definitivo.
 */
final class CierreJornadaProcesoJornadaSupport
{
    /** Incrementar cuando cambie el formato de líneas congeladas (p. ej. metadatos descuento Waitry). */
    public const LINEAS_SCHEMA_VERSION = 2;
    /**
     * @return array{
     *   estado: string,
     *   abierta: bool,
     *   cerrada: bool,
     *   puede_auditar: bool,
     *   puede_facturar_proceso: bool,
     *   factura_bloqueada: bool,
     *   motivo_factura_bloqueada: string|null,
     *   modo: 'auditoria_en_curso'|'auditoria_definitiva'
     * }
     */
    public static function contexto(JornadaGastronomia $jornada, ?GastronomiaCierreJornadaProcesoSnapshot $snapshot = null): array
    {
        $estado = (string) ($jornada->estado ?? '');
        $abierta = $estado === JornadaGastronomia::ESTADO_ABIERTA;
        $cerrada = $estado === JornadaGastronomia::ESTADO_CERRADA && $jornada->cierre_en !== null;

        $snapshotProvisional = $snapshot !== null && self::snapshotEsProvisional($snapshot);
        $snapshotDefinitivo = $snapshot !== null && ! $snapshotProvisional;

        $motivoBloqueo = null;
        $puedeFacturar = false;

        if ($abierta) {
            $motivoBloqueo = 'La jornada gastronomía sigue abierta. Puede auditar y recalcular el %; el último ticket Waitry '
                .'del tramo es el último leído en cada análisis (puede cambiar si entran órdenes nuevas). La factura del proceso '
                .'solo se podrá emitir después del cierre de jornada en Ventas → Gastronomía → Jornada '
                .'(ahí se fija el último ticket del día y se congela la facturación POS).';
        } elseif (! $cerrada) {
            $motivoBloqueo = 'La jornada no está cerrada correctamente (sin fecha/hora de cierre).';
        } elseif ($snapshot === null) {
            $motivoBloqueo = 'Debe ejecutar «Analizar tramo» con la jornada ya cerrada para congelar el tramo Waitry definitivo.';
        } elseif ($snapshotProvisional) {
            $motivoBloqueo = 'El análisis se hizo con la jornada abierta (snapshot provisional). Vuelva a analizar tras el cierre '
                .'de jornada (o use refrescar Waitry) para fijar el tramo definitivo.';
        } else {
            $puedeFacturar = true;
        }

        $modo = ($abierta || $snapshotProvisional) ? 'auditoria_en_curso' : 'auditoria_definitiva';

        $payloadSnap = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $emisionSnap = is_array($payloadSnap['factura_proceso_emision'] ?? null)
            ? $payloadSnap['factura_proceso_emision']
            : [];
        $requiereEmisionProceso = (bool) ($payloadSnap['requiere_emision_proceso'] ?? true);
        $facturaOmitida = self::emisionProcesoOmitida($emisionSnap);
        $facturaEmitida = self::facturaProcesoConsideradaEmitida($emisionSnap)
            || CierreJornadaProcesoEmisionExistenteSupport::existeParaJornada($jornada);
        $asientosGrabados = ! empty($payloadSnap['asientos_proceso_grabacion']['asientos']);
        $rendicionAnitaGrabada = ! empty($payloadSnap['rendicion_proceso_anita']['nro_oper']);
        // Rendgastro cuelga de las ventas emitidas (no de asientos).
        $rendicionAnitaPendiente = $facturaEmitida && ! $rendicionAnitaGrabada;

        if ($puedeFacturar && ! $requiereEmisionProceso && ! $facturaEmitida) {
            $motivoBloqueo = 'No hay comandas Waitry sin facturar ni ajuste de insumos. '
                .'No corresponde emitir facturas del proceso; puede grabar los asientos contables directamente.';
        }

        $motivoBloqueoAsientos = null;
        $puedeGrabarAsientos = false;
        if (! $puedeFacturar) {
            $motivoBloqueoAsientos = $motivoBloqueo;
        } elseif (! $facturaEmitida && $requiereEmisionProceso) {
            $motivoBloqueoAsientos = 'Debe emitir las facturas del proceso (o el ajuste de insumos) antes de grabar los asientos contables.';
        } elseif (! $facturaEmitida && ! $requiereEmisionProceso) {
            $motivoBloqueoAsientos = 'Pulse «Analizar tramo» con la jornada cerrada para confirmar que no hay comandas Waitry a facturar.';
        } elseif ($asientosGrabados && $rendicionAnitaGrabada) {
            $motivoBloqueoAsientos = 'Ya se grabaron los asientos del proceso para esta jornada.';
        } elseif ($asientosGrabados && ! $rendicionAnitaGrabada) {
            // Red de seguridad: reintentar rendgastro vía botón asientos si falló al emitir.
            $puedeGrabarAsientos = true;
        } else {
            $puedeGrabarAsientos = true;
        }

        if ($facturaEmitida && $motivoBloqueo === null && ! $facturaOmitida) {
            $motivoBloqueo = 'Ya se emitió la facturación del proceso para esta jornada.';
        } elseif ($facturaOmitida && $motivoBloqueo === null) {
            $motivoBloqueo = 'No hay comandas Waitry sin facturar; la facturación del proceso no aplica. '
                .'Puede grabar los asientos contables.';
        }

        $resultadoGrabado = self::resultadoGrabadoDesdePayload($payloadSnap);
        $procesoCierreCompletado = $facturaEmitida && $asientosGrabados && $rendicionAnitaGrabada;
        $recuperacionArchivada = self::recuperacionEmisionDesdePayload($payloadSnap);

        return [
            'estado' => $estado,
            'abierta' => $abierta,
            'cerrada' => $cerrada,
            'puede_auditar' => true,
            'puede_facturar_proceso' => $puedeFacturar && ! $facturaEmitida && $requiereEmisionProceso,
            'factura_bloqueada' => ! $puedeFacturar || $facturaEmitida || ! $requiereEmisionProceso,
            'motivo_factura_bloqueada' => $motivoBloqueo,
            'factura_proceso_emitida' => $facturaEmitida && ! $facturaOmitida,
            'factura_proceso_omitida' => $facturaOmitida,
            'requiere_emision_proceso' => $requiereEmisionProceso,
            'recuperacion_emision_archivada' => $recuperacionArchivada !== null,
            'recuperacion_emision_resumen' => $recuperacionArchivada !== null
                ? [
                    'cantidad_lotes' => count($recuperacionArchivada['facturas'] ?? []),
                    'facturas' => array_values(array_map(
                        static fn (array $f) => (string) ($f['factura'] ?? ''),
                        array_filter(
                            $recuperacionArchivada['facturas'] ?? [],
                            static fn ($f) => is_array($f),
                        ),
                    )),
                ]
                : null,
            'puede_grabar_asientos_proceso' => $puedeGrabarAsientos,
            'asientos_grabados' => $asientosGrabados,
            'rendicion_anita_grabada' => $rendicionAnitaGrabada,
            'rendicion_anita_pendiente' => $rendicionAnitaPendiente,
            'motivo_asientos_bloqueados' => $motivoBloqueoAsientos,
            'proceso_cierre_completado' => $procesoCierreCompletado,
            'puede_revertir_proceso' => $facturaEmitida || $asientosGrabados,
            'resultado_grabado' => $resultadoGrabado,
            'modo' => $modo,
            'snapshot_provisional' => $snapshotProvisional,
            'snapshot_definitivo' => $snapshotDefinitivo,
        ];
    }

    /**
     * Facturas / asientos ya grabados en el snapshot (para revisión al reingresar al día).
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{
     *   facturas: list<array<string, mixed>>,
     *   asientos: list<array<string, mixed>>,
     *   ajuste_insumos: array<string, mixed>|null,
     *   total_factura: float,
     *   total_ajuste: float,
     *   emitido_en: string|null,
     *   grabado_en: string|null,
     *   porcentaje: float|null
     * }
     */
    public static function resultadoGrabadoDesdePayload(?array $payload): array
    {
        if (! is_array($payload)) {
            return self::resultadoGrabadoVacio();
        }

        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];
        $grabacion = is_array($payload['asientos_proceso_grabacion'] ?? null)
            ? $payload['asientos_proceso_grabacion']
            : [];

        $facturas = [];
        foreach ($emision['facturas'] ?? [] as $fac) {
            if (! is_array($fac)) {
                continue;
            }
            $facturas[] = [
                'lote' => (int) ($fac['lote'] ?? 0),
                'venta_id' => (int) ($fac['venta_id'] ?? 0),
                'factura' => (string) ($fac['factura'] ?? ''),
                'total' => round((float) ($fac['total'] ?? 0), 2),
                'cobranza_id' => (int) ($fac['cobranza_id'] ?? 0),
                'cantidad_comandas' => (int) ($fac['cantidad_comandas'] ?? 0),
            ];
        }

        if ($facturas === [] && ! empty($emision['venta_id'])) {
            $facturas[] = [
                'lote' => 1,
                'venta_id' => (int) $emision['venta_id'],
                'factura' => (string) ($emision['factura'] ?? ''),
                'total' => 0.,
                'cobranza_id' => (int) ($emision['cobranza_id'] ?? 0),
                'cantidad_comandas' => (int) ($emision['cantidad_comandas'] ?? 0),
            ];
        }

        $asientos = [];
        foreach ($grabacion['asientos'] ?? [] as $asi) {
            if (! is_array($asi)) {
                continue;
            }
            $asientos[] = [
                'codigo' => (string) ($asi['codigo'] ?? ''),
                'titulo' => (string) ($asi['titulo'] ?? ''),
                'asiento_id' => (int) ($asi['asiento_id'] ?? 0),
                'numeroasiento' => (string) ($asi['numeroasiento'] ?? ''),
                'resumen_debe' => round((float) ($asi['resumen_debe'] ?? 0), 2),
                'resumen_haber' => round((float) ($asi['resumen_haber'] ?? 0), 2),
            ];
        }

        $porcentaje = null;
        if (isset($emision['porcentaje'])) {
            $porcentaje = round((float) $emision['porcentaje'], 4);
        } elseif (isset($grabacion['porcentaje'])) {
            $porcentaje = round((float) $grabacion['porcentaje'], 4);
        }

        return [
            'facturas' => $facturas,
            'asientos' => $asientos,
            'ajuste_insumos' => is_array($emision['ajuste_insumos'] ?? null) ? $emision['ajuste_insumos'] : null,
            'total_factura' => round((float) ($emision['total_factura'] ?? 0), 2),
            'total_ajuste' => round((float) ($emision['total_ajuste'] ?? 0), 2),
            'emitido_en' => isset($emision['emitido_en']) ? (string) $emision['emitido_en'] : null,
            'grabado_en' => isset($grabacion['grabado_en']) ? (string) $grabacion['grabado_en'] : null,
            'porcentaje' => $porcentaje,
        ];
    }

    /**
     * @return array{
     *   facturas: list<array<string, mixed>>,
     *   asientos: list<array<string, mixed>>,
     *   ajuste_insumos: null,
     *   total_factura: float,
     *   total_ajuste: float,
     *   emitido_en: null,
     *   grabado_en: null,
     *   porcentaje: null
     * }
     */
    private static function resultadoGrabadoVacio(): array
    {
        return [
            'facturas' => [],
            'asientos' => [],
            'ajuste_insumos' => null,
            'total_factura' => 0.,
            'total_ajuste' => 0.,
            'emitido_en' => null,
            'grabado_en' => null,
            'porcentaje' => null,
        ];
    }

    public static function snapshotEsProvisional(GastronomiaCierreJornadaProcesoSnapshot $snapshot): bool
    {
        $payload = $snapshot->payload;
        if (! is_array($payload)) {
            return true;
        }

        return ! empty($payload['snapshot_provisional']);
    }

    /**
     * Si el snapshot ya no coincide con el estado actual de la jornada, debe descartarse.
     */
    public static function debeInvalidarSnapshot(JornadaGastronomia $jornada, GastronomiaCierreJornadaProcesoSnapshot $snapshot): bool
    {
        $payload = $snapshot->payload;
        if (! is_array($payload)) {
            return true;
        }

        $estadoSnapshot = (string) ($payload['jornada_estado'] ?? '');
        $estadoActual = (string) ($jornada->estado ?? '');

        if ($estadoSnapshot === JornadaGastronomia::ESTADO_ABIERTA
            && $estadoActual === JornadaGastronomia::ESTADO_CERRADA) {
            return true;
        }

        if ($estadoSnapshot === JornadaGastronomia::ESTADO_CERRADA
            && $estadoActual === JornadaGastronomia::ESTADO_ABIERTA) {
            return true;
        }

        $cierreSnapshot = (string) ($payload['jornada_cierre_en'] ?? '');
        $cierreActual = $jornada->cierre_en?->toIso8601String() ?? '';
        if ($estadoActual === JornadaGastronomia::ESTADO_CERRADA
            && $cierreSnapshot !== ''
            && $cierreActual !== ''
            && $cierreSnapshot !== $cierreActual) {
            return true;
        }

        if ((int) ($payload['lineas_schema_version'] ?? 1) < self::LINEAS_SCHEMA_VERSION) {
            return true;
        }

        if ($estadoActual === JornadaGastronomia::ESTADO_CERRADA) {
            $jornada->loadMissing('cierreTotem');
            $cierreTotem = $jornada->cierreTotem;
            if ($cierreTotem !== null
                && (int) $cierreTotem->cantidad_lineas > 0
                && count($snapshot->lineas()) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function assertPuedeEmitirFacturaProceso(JornadaGastronomia $jornada, ?GastronomiaCierreJornadaProcesoSnapshot $snapshot = null): void
    {
        $ctx = self::contexto($jornada, $snapshot);
        if ($ctx['puede_facturar_proceso']) {
            return;
        }

        throw new \InvalidArgumentException($ctx['motivo_factura_bloqueada'] ?? 'No puede emitir la factura del proceso en este momento.');
    }

    public static function assertPuedeGrabarAsientosProceso(JornadaGastronomia $jornada, ?GastronomiaCierreJornadaProcesoSnapshot $snapshot = null): void
    {
        $ctx = self::contexto($jornada, $snapshot);
        if ($ctx['puede_grabar_asientos_proceso']) {
            return;
        }

        throw new \InvalidArgumentException($ctx['motivo_asientos_bloqueados'] ?? 'No puede grabar los asientos del proceso en este momento.');
    }

    /**
     * Porcentaje efectivo: request explícito, columna snapshot o emisión archivada.
     * Con $exigirRecalculado=true falla si no hay % tras analizar/recalcular.
     */
    public static function resolverPorcentajeOperacion(
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        float $porcentajeRequest,
        bool $exigirRecalculado = false,
    ): float {
        if ($porcentajeRequest > 0.0001) {
            return round($porcentajeRequest, 4);
        }

        $pctSnapshot = (float) ($snapshot?->porcentaje ?? 0);
        if ($pctSnapshot > 0.0001) {
            return round($pctSnapshot, 4);
        }

        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        if (self::recalculoAplicadoEnSnapshot($payload)) {
            return round((float) ($payload['porcentaje'] ?? $pctSnapshot), 4);
        }

        foreach (['factura_proceso_emision_recuperacion', 'factura_proceso_emision'] as $clave) {
            $bloque = $payload[$clave] ?? null;
            if (is_array($bloque) && isset($bloque['porcentaje'])) {
                $pct = (float) $bloque['porcentaje'];
                if ($pct > 0.0001) {
                    return round($pct, 4);
                }
            }
        }

        if ($exigirRecalculado) {
            $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
            if (! (bool) ($payload['requiere_emision_proceso'] ?? true)) {
                return 0.;
            }

            throw new \InvalidArgumentException(
                'Debe indicar el porcentaje y pulsar «Recalcular medios» antes de emitir las facturas del proceso.',
            );
        }

        return 0.;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function recalculoAplicadoEnSnapshot(?array $payload): bool
    {
        return is_array($payload) && ! empty($payload['recalculo_aplicado_en']);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function requiereEmisionProcesoDesdeMovimientos(array $movimientos): bool
    {
        $plan = CierreJornadaProcesoFacturaLotesSupport::armarPlanDesdeMovimientos($movimientos);

        return ($plan['lotes'] ?? []) !== [] || ($plan['comandas_ajuste'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>|null  $emision
     */
    public static function emisionProcesoOmitida(?array $emision): bool
    {
        return is_array($emision) && ! empty($emision['omitida']);
    }

    /**
     * Emisión sin facturas CF: omitida (cero comandas) o solo ajuste de insumos (Waitry cash).
     * El rendgastro CIERRE-WAITRY puede ir en $0; no mezclar con omitida en el resto del proceso
     * (un reanálisis no debe borrar el ajuste de insumos).
     *
     * @param  array<string, mixed>|null  $emision
     */
    public static function emisionSinFacturasParaRendgastro(?array $emision): bool
    {
        if (! is_array($emision)) {
            return false;
        }

        if (self::emisionProcesoOmitida($emision)) {
            return true;
        }

        return ! empty($emision['solo_ajuste'])
            && empty($emision['facturas'])
            && empty($emision['venta_id']);
    }

    /**
     * Emisión real o marcada como omitida (sin comandas Waitry sin facturar).
     *
     * @param  array<string, mixed>|null  $emision
     */
    public static function facturaProcesoConsideradaEmitida(?array $emision): bool
    {
        if (! is_array($emision)) {
            return false;
        }

        if (! empty($emision['omitida'])) {
            return true;
        }

        return ! empty($emision['emitido_en'])
            || ! empty($emision['facturas'])
            || ! empty($emision['venta_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function emisionOmitidaPayload(float $porcentaje = 0.): array
    {
        return [
            'omitida' => true,
            'sin_comandas_waitry' => true,
            'emitido_en' => now()->toIso8601String(),
            'porcentaje' => round($porcentaje, 4),
            'facturas' => [],
            'total_factura' => 0.,
            'total_ajuste' => 0.,
        ];
    }

    /**
     * Datos archivados para re-emisión con mismas comandas y numeración CF.
     *
     * @return array<string, mixed>|null
     */
    public static function recuperacionEmisionDesdePayload(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $recuperacion = $payload['factura_proceso_emision_recuperacion'] ?? null;
        if (is_array($recuperacion) && ! empty($recuperacion['facturas'])) {
            return $recuperacion;
        }

        return null;
    }

    /**
     * Bloques del snapshot que no deben perderse al reanalizar / recrear el tramo.
     *
     * @return array<string, mixed>
     */
    public static function payloadProcesoPersistente(?GastronomiaCierreJornadaProcesoSnapshot $snapshot): array
    {
        if ($snapshot === null) {
            return [];
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $out = [];
        foreach ([
            'factura_proceso_emision',
            'factura_proceso_emision_recuperacion',
            'asientos_proceso_grabacion',
            'rendicion_proceso_anita',
        ] as $clave) {
            if (isset($payload[$clave]) && is_array($payload[$clave])) {
                $out[$clave] = $payload[$clave];
            }
        }

        return $out;
    }
}
