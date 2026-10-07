<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * En Ferli la leyenda del proveedor se imprime siempre en la cuenta corriente.
 */
final class ProveedorLeyendaCuentaCorrienteSupport
{
    public static function activa(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    public static function texto(?string $leyenda): string
    {
        if (! self::activa()) {
            return '';
        }

        return trim((string) $leyenda);
    }
}
