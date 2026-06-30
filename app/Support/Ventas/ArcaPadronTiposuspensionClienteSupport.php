<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tiposuspensioncliente;

final class ArcaPadronTiposuspensionClienteSupport
{
    public const NOMBRE_BAJA_IMPUESTOS = 'Baja de impuestos';

    public static function idBajaImpuestos(): ?int
    {
        $configId = (int) config('arca.padron_validacion_cliente.tiposuspension_baja_impuestos_id', 0);
        if ($configId > 0) {
            return $configId;
        }

        $id = Tiposuspensioncliente::query()
            ->where('nombre', self::NOMBRE_BAJA_IMPUESTOS)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
