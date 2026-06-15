<?php

namespace App\Support\Compras;

/**
 * Equivalente ERP de dft_carga en a-compprov.c (Anita).
 */
final class ComprobanteProveedorModoCarga
{
    /** Asigna COM comparando precios (ASIGNA_RECEPCION). */
    public const ASIGNA_RECEPCION = 'ASIGNA_RECEPCION';

    /** Asigna OC comparando precios (ASIGNA_OC). */
    public const ASIGNA_OC = 'ASIGNA_OC';

    /** Gastos / servicios sin recepción (NOCARGA_RECEPCION). */
    public const SIN_RECEPCION = 'SIN_RECEPCION';

    /** Genera recepción de mercadería al facturar (CARGA_RECEPCION). */
    public const CARGA_RECEPCION = 'CARGA_RECEPCION';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::ASIGNA_RECEPCION,
            self::ASIGNA_OC,
            self::SIN_RECEPCION,
            self::CARGA_RECEPCION,
        ];
    }

    public static function etiqueta(string $modo): string
    {
        return match ($modo) {
            self::ASIGNA_RECEPCION => 'Factura contra recepción (COM)',
            self::ASIGNA_OC => 'Factura contra orden de compra',
            self::SIN_RECEPCION => 'Gasto sin recepción',
            self::CARGA_RECEPCION => 'Factura con alta de recepción',
            default => $modo,
        };
    }
}
