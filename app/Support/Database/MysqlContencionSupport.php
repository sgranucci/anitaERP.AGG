<?php

namespace App\Support\Database;

use Throwable;

/**
 * @deprecated Usar DbContencionSupport. Se mantiene como alias para callers legacy.
 */
final class MysqlContencionSupport
{
    public static function esErrorReintentable(Throwable $e): bool
    {
        return DbContencionSupport::esErrorReintentable($e);
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
        if (! isset($opciones['contexto'])) {
            $opciones['contexto'] = 'mysql.contencion';
        }

        return DbContencionSupport::ejecutarConReintento($operacion, $opciones);
    }
}
