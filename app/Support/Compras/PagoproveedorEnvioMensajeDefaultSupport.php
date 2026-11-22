<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Texto fijo del mail de OP al proveedor (Anita p-enviaop).
 * Fuente: config pagoproveedor.envio_proveedor.mensaje_default / .env.
 */
final class PagoproveedorEnvioMensajeDefaultSupport
{
    public static function texto(): string
    {
        return mb_substr(trim((string) config('pagoproveedor.envio_proveedor.mensaje_default', '')), 0, 4000);
    }

    public static function resolver(?string $mensajeOperador): ?string
    {
        $operador = trim((string) $mensajeOperador);
        if ($operador !== '') {
            return mb_substr($operador, 0, 4000);
        }

        $default = self::texto();

        return $default !== '' ? $default : null;
    }
}
