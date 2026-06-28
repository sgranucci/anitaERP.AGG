<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;

/**
 * Soporte para el cierre automático del proceso Waitry (jornadas pendientes).
 */
final class CierreJornadaProcesoAutomaticoSupport
{
    /**
     * Última jornada cerrada de la empresa cuyo proceso Waitry no está completo.
     */
    public static function jornadaPendienteMasReciente(int $empresaId): ?JornadaGastronomia
    {
        if ($empresaId <= 0) {
            return null;
        }

        $jornadas = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaGastronomia::ESTADO_CERRADA)
            ->whereNotNull('cierre_en')
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->get();

        foreach ($jornadas as $jornada) {
            if (! self::procesoCompletado($jornada)) {
                return $jornada;
            }
        }

        return null;
    }

    public static function procesoCompletado(JornadaGastronomia $jornada): bool
    {
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornada->id)
            ->first();

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        return (bool) ($ctx['proceso_cierre_completado'] ?? false);
    }

    /**
     * @return list<int>
     */
    public static function empresasHabilitadas(): array
    {
        $ids = config('gastronomia.cierre_jornada_automatico.empresas_ids', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
    }

    /**
     * @return list<string>
     */
    public static function destinatariosEmail(): array
    {
        $raw = (string) config('gastronomia.cierre_jornada_automatico.email', '');
        if ($raw === '') {
            return [];
        }

        $emails = array_map('trim', explode(',', $raw));

        return array_values(array_filter($emails, static fn (string $e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    public static function necesitaAnalizarDefinitivo(
        JornadaGastronomia $jornada,
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
    ): bool {
        if ($snapshot === null) {
            return true;
        }

        if (CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot)) {
            return true;
        }

        if (CierreJornadaProcesoJornadaSupport::snapshotEsProvisional($snapshot)) {
            return true;
        }

        return false;
    }

    public static function necesitaRecalcular(
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        float $porcentajeObjetivo,
        bool $facturaYaEmitida,
    ): bool {
        if ($facturaYaEmitida) {
            return false;
        }

        if ($snapshot === null) {
            return true;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        if (! CierreJornadaProcesoJornadaSupport::recalculoAplicadoEnSnapshot($payload)) {
            return true;
        }

        $pctSnapshot = (float) ($snapshot->porcentaje ?? 0);

        return abs($pctSnapshot - $porcentajeObjetivo) > 0.0001;
    }
}
