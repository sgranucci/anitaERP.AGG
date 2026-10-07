<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Límites de runtime para el mayor por concepto (cierre mensual, volúmenes altos).
 */
class MayorConceptoRuntimeSupport
{
    public static function elevarLimites(): void
    {
        // Sin ignore_user_abort: si recargan la página, este PHP se corta y no queda
        // otro proceso leyendo Anita en paralelo (eso dejaba el banner quieto).
        // El tope real es el Timeout de Apache (1200 s). 900 s de PHP mataba el
        // request a mitad de un mes y el banner seguía en "Procesando…".
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }
}
