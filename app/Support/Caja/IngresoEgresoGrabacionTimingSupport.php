<?php

namespace App\Support\Caja;

use Illuminate\Support\Facades\Log;

/**
 * Diagnóstico de demora al grabar IE (alta). Activar con CAJA_IE_GRABACION_TIMING=true.
 * Emite un único Log::info('caja.ie.grabacion.timing', …) por request instrumentado.
 */
final class IngresoEgresoGrabacionTimingSupport
{
    private const MAX_ANITA_CALLS = 100;

    /** @var array<string, mixed>|null */
    private static ?array $sesion = null;

    public static function habilitado(): bool
    {
        return (bool) config('caja.ingresoegreso_grabacion_timing', false);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function comenzar(array $meta = []): void
    {
        if (! self::habilitado()) {
            return;
        }

        self::$sesion = [
            't0' => microtime(true),
            'meta' => $meta,
            'etapas' => [],
            'anita_calls' => [],
            'anita_calls_count' => 0,
            'anita_ms' => 0,
            'anita_calls_truncated' => false,
        ];
    }

    public static function activa(): bool
    {
        return self::$sesion !== null;
    }

    public static function ahora(): ?float
    {
        return self::$sesion === null ? null : microtime(true);
    }

    public static function marcarEtapa(string $clave, ?float $desde): void
    {
        if (self::$sesion === null || $desde === null) {
            return;
        }

        self::$sesion['etapas'][$clave] = (int) round((microtime(true) - $desde) * 1000);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function registrarAnitaCall(array $payload, float $ms, ?string $contexto = null): void
    {
        if (self::$sesion === null) {
            return;
        }

        $msInt = (int) round($ms);
        self::$sesion['anita_ms'] += $msInt;
        self::$sesion['anita_calls_count']++;

        if (count(self::$sesion['anita_calls']) >= self::MAX_ANITA_CALLS) {
            self::$sesion['anita_calls_truncated'] = true;

            return;
        }

        $entry = [
            'tabla' => $payload['tabla'] ?? null,
            'acc' => $payload['acc'] ?? null,
            'ms' => $msInt,
        ];
        if ($contexto !== null && $contexto !== '') {
            $entry['contexto'] = $contexto;
        }
        self::$sesion['anita_calls'][] = $entry;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function finalizar(array $extra = []): void
    {
        if (self::$sesion === null) {
            return;
        }

        $s = self::$sesion;
        self::$sesion = null;

        $payload = array_merge(
            $s['meta'],
            $s['etapas'],
            $extra,
            [
                'anita_calls_count' => $s['anita_calls_count'],
                'anita_ms' => $s['anita_ms'],
                'anita_calls_truncated' => $s['anita_calls_truncated'],
                'anita_calls' => $s['anita_calls'],
                'total_ms' => (int) round((microtime(true) - $s['t0']) * 1000),
            ]
        );

        Log::info('caja.ie.grabacion.timing', $payload);
    }
}
