<?php

namespace App\Support\Compras\AnitaSync\Pagoproveedor;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Clave Anita de la OP en Crown (/usr2/casino, che_ban.pago).
 * Mono: pag_empresa 0, pag_sucursal 0, pag_letra A (OPP 219620 y OPA 6777).
 * No aplica a AGG ni a Ferli.
 */
final class PagoproveedorAnitaCrownClaveSupport
{
    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    public static function empresaAnita(): int
    {
        return 0;
    }

    public static function sucursal(): int
    {
        return 0;
    }

    public static function letra(): string
    {
        return 'A';
    }

    public static function letraDocumento(): string
    {
        if (self::aplica()) {
            return self::letra();
        }

        $letra = (string) config('pagoproveedor.letra_default', ' ');

        return $letra !== '' ? $letra : ' ';
    }
}
