<?php

namespace App\Support\Contable;

/**
 * Cambiar la empresa de un asiento ya grabado lo mueve de contabilidad: hay que
 * borrar el ctamov de la empresa origen y renumerarlo en la destino. Queda
 * reservado a contaduría (Sup / Enc) con el permiso `cambiar-empresa-asiento`.
 */
class AsientoEmpresaCambioSupport
{
    public const PERMISO = 'cambiar-empresa-asiento';

    public const MENSAJE_SIN_PERMISO = 'No se puede cambiar la empresa de un asiento ya cargado.';

    public static function permitido(): bool
    {
        return (bool) can(self::PERMISO, false);
    }
}
