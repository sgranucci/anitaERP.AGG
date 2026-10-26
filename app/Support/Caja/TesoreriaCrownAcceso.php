<?php

namespace App\Support\Caja;

use App\Support\Configuracion\EntornoEmpresaSupport;

class TesoreriaCrownAcceso
{
    public static function abortSiNoEsCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    public static function marcado(mixed $valor): bool
    {
        return (int) $valor === 1;
    }
}
