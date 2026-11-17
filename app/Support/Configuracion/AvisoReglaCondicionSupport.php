<?php

namespace App\Support\Configuracion;

use App\Support\ReglaNegocio\ReglaNegocioCondicionSupport;

/**
 * Wrapper de compatibilidad: el evaluador vive en ReglaNegocioCondicionSupport.
 *
 * @deprecated Preferir ReglaNegocioCondicionSupport.
 */
final class AvisoReglaCondicionSupport
{
    /**
     * @param  array<string, mixed>|null  $condiciones
     * @param  array<string, mixed>  $contexto
     */
    public static function cumple(?array $condiciones, array $contexto): bool
    {
        return ReglaNegocioCondicionSupport::cumple($condiciones, $contexto);
    }
}
