<?php

namespace App\Support\Compras;

/**
 * Escritura Anita de la factura de proveedor.
 * Sigue el flag global del bridge. El esquema de cada instalación lo resuelve su mapper.
 */
final class ComprobanteProveedorAnitaBridgeGate
{
    public static function escrituraHabilitada(): bool
    {
        return filter_var(config('anita.escritura_habilitada', true), FILTER_VALIDATE_BOOLEAN);
    }
}
