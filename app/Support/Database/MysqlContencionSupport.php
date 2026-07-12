<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class MysqlContencionSupport
{
    public static function esErrorReintentable(Throwable $e): bool
    {
        $mensaje = $e instanceof QueryException
            ? (string) ($e->errorInfo[2] ?? $e->getMessage())
            : $e->getMessage();
        $m = strtolower($mensaje);

        return str_contains($m, 'deadlock found')
            || str_contains($m, '1213')
            || str_contains($m, 'lock wait timeout exceeded')
            || str_contains($m, '1205');
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
        $contexto = (string) ($opciones['contexto'] ?? 'mysql.contencion');

        $ultimoError = null;
        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                return $operacion();
            } catch (Throwable $e) {
                $ultimoError = $e;
                if (! self::esErrorReintentable($e) || $intento >= $maxIntentos) {
                    throw $e;
                }

                Log::warning($contexto.': reintento por contención MySQL', [
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
