<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Interface Anita del ABM de artículos (stkmae / stcustom / stkmgastro / sync).
 *
 * En Crown el catálogo vive solo en anitaERP: alta, edición, baja y sync no llaman a Anita.
 */
final class ArticuloAnitaAbmSupport
{
    public static function habilitado(): bool
    {
        return ! EntornoEmpresaSupport::esCrown();
    }
}
