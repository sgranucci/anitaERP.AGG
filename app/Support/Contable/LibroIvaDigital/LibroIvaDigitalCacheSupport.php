<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Deja el Libro IVA Digital en el tamaño que usan la pantalla y el ZIP.
 */
final class LibroIvaDigitalCacheSupport
{
    /**
     * Quita arreglos internos (registros / detalle) que no van a pantalla ni al ZIP.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public static function compactar(array $resultado): array
    {
        unset(
            $resultado['ventas']['registros'],
            $resultado['compras']['registros'],
            $resultado['importaciones']['registros'],
            $resultado['iva_simple']['detalle_debito'],
            $resultado['iva_simple']['detalle_restitucion_debito'],
            $resultado['iva_simple']['detalle_credito'],
            $resultado['iva_simple']['detalle_restitucion_credito'],
        );

        return $resultado;
    }
}
