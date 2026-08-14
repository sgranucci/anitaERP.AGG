<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Leyendas de asiento COM recepción proveedor (ERP y ctamov Anita).
 */
final class RecepcionProveedorAsientoDescripcionSupport
{
    /** ctav_desc_mov en Informix (30 caracteres). */
    public const LONGITUD_CTAV_DESC_MOV = 30;

    public static function descripcionAsientoErp(Recepcion_Proveedor $recepcion): string
    {
        $com = (int) ($recepcion->numerorecepcion ?? 0);
        $nombreProveedor = self::nombreProveedor($recepcion);

        return trim('Recepción proveedor #'.$com.' '.$nombreProveedor);
    }

    public static function descripcionCtamovAnita(Recepcion_Proveedor $recepcion): string
    {
        $com = (int) ($recepcion->numerorecepcion ?? 0);
        $nombreProveedor = self::nombreProveedor($recepcion);
        $base = $com.' ';
        $espacioNombre = self::LONGITUD_CTAV_DESC_MOV - strlen($base);
        $nombreRecortado = $espacioNombre > 0
            ? self::truncarTexto($nombreProveedor, $espacioNombre)
            : '';

        return self::sanitizarCtamov(trim($base.$nombreRecortado));
    }

    public static function sanitizarCtamov(string $texto): string
    {
        $sanitizado = preg_replace('/([^A-Za-z0-9 ])/', '', $texto) ?? '';

        return substr($sanitizado, 0, self::LONGITUD_CTAV_DESC_MOV);
    }

    private static function nombreProveedor(Recepcion_Proveedor $recepcion): string
    {
        if (! $recepcion->relationLoaded('proveedores')) {
            $recepcion->loadMissing('proveedores');
        }

        return trim((string) ($recepcion->proveedores?->nombre ?? ''));
    }

    private static function truncarTexto(string $texto, int $max): string
    {
        $texto = trim($texto);
        if ($max <= 0 || $texto === '') {
            return '';
        }

        if (strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(substr($texto, 0, $max));
    }
}
