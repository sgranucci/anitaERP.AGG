<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reintentos ante deadlock / lock wait en MySQL/MariaDB y PostgreSQL.
 *
 * Sustituye el uso directo de MysqlContencionSupport (alias de compatibilidad).
 * Ver docs/arquitectura/portabilidad-base-datos.md (Fase 2.2).
 */
final class DbContencionSupport
{
    public static function esErrorReintentable(Throwable $e): bool
    {
        $sqlState = null;
        $driverCode = null;
        if ($e instanceof QueryException) {
            $sqlState = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : null;
            $driverCode = isset($e->errorInfo[1]) ? (string) $e->errorInfo[1] : null;
        }

        // PostgreSQL: deadlock / lock not available
        if (in_array($sqlState, ['40P01', '55P03'], true)) {
            return true;
        }

        $mensaje = $e instanceof QueryException
            ? (string) ($e->errorInfo[2] ?? $e->getMessage())
            : $e->getMessage();
        $m = strtolower($mensaje);

        // MySQL / MariaDB
        if (
            str_contains($m, 'deadlock found')
            || str_contains($m, '1213')
            || $driverCode === '1213'
            || str_contains($m, 'lock wait timeout exceeded')
            || str_contains($m, '1205')
            || $driverCode === '1205'
        ) {
            return true;
        }

        // PostgreSQL (mensaje, por si el driver no expone SQLSTATE)
        if (
            str_contains($m, 'deadlock detected')
            || str_contains($m, 'lock_not_available')
            || str_contains($m, 'canceling statement due to lock timeout')
            || str_contains($m, 'could not obtain lock')
            || str_contains($m, '40p01')
            || str_contains($m, '55p03')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operacion
     * @param  array{
     *     max_intentos?: int,
     *     espera_inicial_ms?: int,
     *     multiplicador_espera?: float,
     *     contexto?: string
     * }  $opciones
     * @return T
     */
    public static function ejecutarConReintento(callable $operacion, array $opciones = []): mixed
    {
        $maxIntentos = max(1, (int) ($opciones['max_intentos'] ?? 5));
        $esperaMs = max(50, (int) ($opciones['espera_inicial_ms'] ?? 150));
        $multiplicador = max(1.0, (float) ($opciones['multiplicador_espera'] ?? 2.0));
        $contexto = (string) ($opciones['contexto'] ?? 'db.contencion');

        $ultimoError = null;
        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                return $operacion();
            } catch (Throwable $e) {
                $ultimoError = $e;
                if (! self::esErrorReintentable($e) || $intento >= $maxIntentos) {
                    throw $e;
                }

                Log::warning($contexto.': reintento por contención de BD', [
                    'intento' => $intento,
                    'max_intentos' => $maxIntentos,
                    'espera_ms' => $esperaMs,
                    'error' => $e->getMessage(),
                ]);

                usleep($esperaMs * 1000);
                $esperaMs = (int) min(5000, round($esperaMs * $multiplicador));
            }
        }

        throw $ultimoError ?? new RuntimeException($contexto.': reintento agotado');
    }
}
