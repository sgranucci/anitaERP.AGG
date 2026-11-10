<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Soporte para el cierre automático del proceso Waitry (jornadas pendientes).
 */
final class CierreJornadaProcesoAutomaticoSupport
{
    /**
     * Jornada cerrada pendiente de proceso más antigua dentro del lookback.
     *
     * Si falla un día y al siguiente hay otra jornada, hay que reintentar primero
     * la pendiente vieja del tramo reciente: si se toma solo la más reciente, la
     * fallida queda huérfana (incidente Biyemas 2026-11-07). El lookback evita
     * jornadas históricas anteriores al proceso automático.
     */
    public static function jornadaPendienteMasAntigua(int $empresaId): ?JornadaGastronomia
    {
        if ($empresaId <= 0) {
            return null;
        }

        $jornadas = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaGastronomia::ESTADO_CERRADA)
            ->whereNotNull('cierre_en')
            ->whereDate('fecha_jornada', '>=', self::fechaDesdeLookbackPendientes())
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();

        foreach ($jornadas as $jornada) {
            if (! self::procesoCompletado($jornada)) {
                return $jornada;
            }
        }

        return null;
    }

    /**
     * Alias histórico: ahora resuelve la pendiente más antigua del lookback.
     */
    public static function jornadaPendienteMasReciente(int $empresaId): ?JornadaGastronomia
    {
        return self::jornadaPendienteMasAntigua($empresaId);
    }

    public static function fechaDesdeLookbackPendientes(): string
    {
        $dias = max(1, (int) config('gastronomia.cierre_jornada_automatico.pendiente_dias_lookback', 14));

        return now()->subDays($dias)->toDateString();
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
     * Bloquea el cierre de la jornada abierta si hay una cerrada anterior sin proceso completo.
     * Evita acumular días sin asientos/facturas de proceso (como el 7/11 Biyemas).
     */
    public static function mensajeBloqueoCierrePorProcesoPendiente(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }

        if (! filter_var(config('gastronomia.cierre_jornada_automatico.bloquear_cierre_si_proceso_pendiente', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if (EntornoEmpresaSupport::esFerli() || EntornoEmpresaSupport::esCrown()) {
            return null;
        }

        // El bloqueo aplica cuando el proceso Waitry está en uso (cierre tótem / automático).
        if (! filter_var(config('gastronomia.cierre_totem_jornada_habilitado', true), FILTER_VALIDATE_BOOLEAN)
            && ! filter_var(config('gastronomia.cierre_jornada_automatico.habilitado', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $pendiente = self::jornadaPendienteMasAntigua($empresaId);
        if ($pendiente === null) {
            return null;
        }

        $fecha = $pendiente->fecha_jornada?->format('d/m/Y') ?? (string) $pendiente->id;

        return 'No se puede cerrar esta jornada: el proceso Waitry de la jornada '.$fecha
            .' (id '.$pendiente->id.') quedó incompleto (faltan facturas, asientos o rendición Anita). '
            .'Reintente con: php artisan gastronomia:cierre-jornada-waitry-automatico --empresa='
            .$empresaId.' --fecha-jornada='.($pendiente->fecha_jornada?->format('Y-m-d') ?? '')
            .' — o complete el proceso en Caja → Cierre jornada Waitry.';
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
     * Horario diario por empresa (escalonado). Clave = empresa_id, valor = H:i.
     *
     * @return array<int, string>
     */
    public static function horariosPorEmpresa(): array
    {
        $ids = self::empresasHabilitadas();
        $horasCfg = config('gastronomia.cierre_jornada_automatico.horas', []);
        $horas = [];
        if (is_array($horasCfg)) {
            foreach ($horasCfg as $h) {
                $h = trim((string) $h);
                if ($h !== '') {
                    $horas[] = $h;
                }
            }
        }
        $base = trim((string) config('gastronomia.cierre_jornada_automatico.hora', '09:00'));
        if ($base === '') {
            $base = '09:00';
        }

        $out = [];
        foreach ($ids as $i => $empresaId) {
            $hora = $horas[$i] ?? '';
            if ($hora === '') {
                $ts = strtotime($base.' + '.($i * 5).' minutes');
                $hora = $ts !== false ? date('H:i', $ts) : $base;
            }
            $out[$empresaId] = $hora;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function destinatariosEmail(): array
    {
        return self::parseEmails((string) config('gastronomia.cierre_jornada_automatico.email', ''));
    }

    /**
     * Destinatarios del aviso urgente (falla del proceso). Une email + email_urgente.
     *
     * @return list<string>
     */
    public static function destinatariosEmailUrgente(): array
    {
        $normal = self::destinatariosEmail();
        $urgente = self::parseEmails((string) config('gastronomia.cierre_jornada_automatico.email_urgente', ''));

        return array_values(array_unique(array_merge($normal, $urgente)));
    }

    public static function informeTieneErrores(array $informe): bool
    {
        if ((int) ($informe['resumen']['errores'] ?? 0) > 0) {
            return true;
        }

        foreach ($informe['empresas'] ?? [] as $empresa) {
            if (! is_array($empresa)) {
                continue;
            }
            $estado = (string) ($empresa['estado'] ?? '');
            if (in_array($estado, ['error', 'parcial'], true) || ! empty($empresa['error'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function parseEmails(string $raw): array
    {
        if (trim($raw) === '') {
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

    /**
     * Total de facturas del proceso para el mail/resumen.
     * Si falta {@code total_factura} (emisión no lo devolvía), suma los lotes.
     *
     * @param  array<string, mixed>  $emision
     */
    public static function totalFacturasDesdeEmision(array $emision): float
    {
        $declarado = round((float) ($emision['total_factura'] ?? 0), 2);
        $suma = 0.0;
        foreach ($emision['facturas'] ?? [] as $fac) {
            if (! is_array($fac)) {
                continue;
            }
            $suma = round($suma + (float) ($fac['total'] ?? 0), 2);
        }

        if ($suma > 0.0001) {
            return $suma;
        }

        return $declarado;
    }
}
