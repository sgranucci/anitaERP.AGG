<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reintentos ante deadlock / lock wait y detección de UNIQUE/FK
 * (MySQL 1062/1451/1452 y PostgreSQL 23505/23503).
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
     * Violación de UNIQUE (MySQL 1062 / PostgreSQL 23505).
     * $pistas opcional: alguna debe aparecer en el mensaje (índice o tabla).
     */
    public static function esViolacionUnicidad(Throwable $e, string ...$pistas): bool
    {
        [$sqlState, $driverCode, $mensaje] = self::partesErrorSql($e);

        $esUnicidad = $sqlState === '23505'
            || $driverCode === '1062'
            || str_contains($mensaje, 'duplicate entry')
            || str_contains($mensaje, 'duplicate key')
            || str_contains($mensaje, 'unique constraint')
            || str_contains($mensaje, 'unique violation');

        if (! $esUnicidad) {
            return false;
        }

        return self::coincidePistas($mensaje, ...$pistas);
    }

    /**
     * Violación de FK (MySQL 1451/1452 / PostgreSQL 23503).
     */
    public static function esViolacionClaveForanea(Throwable $e, string ...$pistas): bool
    {
        [$sqlState, $driverCode, $mensaje] = self::partesErrorSql($e);

        $esFk = $sqlState === '23503'
            || in_array($driverCode, ['1451', '1452'], true)
            || str_contains($mensaje, 'foreign key')
            || str_contains($mensaje, 'violates foreign key constraint');

        if (! $esFk) {
            return false;
        }

        return self::coincidePistas($mensaje, ...$pistas);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    private static function partesErrorSql(Throwable $e): array
    {
        $sqlState = null;
        $driverCode = null;
        if ($e instanceof QueryException) {
            $sqlState = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : null;
            $driverCode = isset($e->errorInfo[1]) ? (string) $e->errorInfo[1] : null;
        }

        $mensaje = strtolower($e instanceof QueryException
            ? (string) ($e->errorInfo[2] ?? $e->getMessage())
            : $e->getMessage());

        return [$sqlState, $driverCode, $mensaje];
    }

    private static function coincidePistas(string $mensaje, string ...$pistas): bool
    {
        if ($pistas === []) {
            return true;
        }

        foreach ($pistas as $pista) {
            $pistaNorm = strtolower(trim($pista));
            if ($pistaNorm !== '' && str_contains($mensaje, $pistaNorm)) {
                return true;
            }
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
