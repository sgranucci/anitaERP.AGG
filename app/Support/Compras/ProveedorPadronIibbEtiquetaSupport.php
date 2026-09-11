<?php

namespace App\Support\Compras;

/**
 * Texto de alícuota IIBB en el ABM de proveedor.
 *
 * En proveedores manda la retención (lo que se retiene al pagar),
 * no la percepción del mismo padrón.
 */
final class ProveedorPadronIibbEtiquetaSupport
{
    public static function retencion(?array $padron): string
    {
        if ($padron === null || ($padron['tasa'] ?? null) === null) {
            return 'No esta en padron';
        }

        return round((float) $padron['tasa'], 2).'%';
    }
}
