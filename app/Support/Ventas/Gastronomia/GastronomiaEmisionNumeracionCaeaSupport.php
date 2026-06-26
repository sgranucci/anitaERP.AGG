<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;

/** @deprecated Use CaeaEmisionNumeracionSupport */
final class GastronomiaEmisionNumeracionCaeaSupport
{
    public static function tipoAnitaDesdeTipotransaccion(Tipotransaccion $tipotransaccion): string
    {
        return CaeaEmisionNumeracionSupport::tipoAnitaDesdeTipotransaccion($tipotransaccion);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function aplicarReservaNumeracionAlPayload(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante = 'B',
        bool $lockYaAdquirido = false,
    ): ?string {
        return CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $puntoventa,
            $tipotransaccion,
            $letraComprobante,
            $lockYaAdquirido,
        );
    }
}
