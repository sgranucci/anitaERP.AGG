<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use DateTimeInterface;

/**
 * Fecha contable del comprobante de proveedor = fecha de IVA.
 * Se usa en asiento, cierre de período y cuenta corriente.
 * Si falta fechaiva, cae a fechacomprobante.
 */
final class ComprobanteProveedorFechaContableSupport
{
    public static function fechaYmd(Comprobante_Proveedor $comprobante): string
    {
        $iva = self::formatear($comprobante->fechaiva ?? null);
        if ($iva !== null) {
            return $iva;
        }

        $comprobanteFecha = self::formatear($comprobante->fechacomprobante ?? null);
        if ($comprobanteFecha !== null) {
            return $comprobanteFecha;
        }

        return now()->format('Y-m-d');
    }

    private static function formatear(mixed $fecha): ?string
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $fecha);

        return $texto !== '' ? substr($texto, 0, 10) : null;
    }
}
