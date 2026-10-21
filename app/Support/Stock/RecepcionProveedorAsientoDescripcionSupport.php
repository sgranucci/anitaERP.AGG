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

    public static function esDevolucion(Recepcion_Proveedor $recepcion): bool
    {
        return strtoupper(trim((string) ($recepcion->tipo ?? ''))) === Recepcion_Proveedor::TIPO_DEVOLUCION;
    }

    /**
     * Clave Anita del comprobante, igual que el mayor: COM X0001-00168839.
     */
    public static function comprobanteAnita(Recepcion_Proveedor $recepcion): string
    {
        $tipo = trim((string) ($recepcion->anita_tipo ?? 'COM'));
        $tipo = $tipo !== '' ? $tipo : 'COM';
        $letra = trim((string) ($recepcion->anita_letra ?? ''));
        $letra = $letra !== '' ? $letra : 'X';
        $sucursal = (int) ($recepcion->anita_sucursal ?? 0);
        $nro = (int) ($recepcion->anita_nro ?? 0);
        if ($nro <= 0) {
            $nro = (int) ($recepcion->numerorecepcion ?? 0);
        }
        if ($nro <= 0) {
            return '';
        }

        return $tipo.' '.$letra.sprintf('%04d', $sucursal).'-'.sprintf('%08d', $nro);
    }

    /**
     * COM de la recepción que esta devolución revierte.
     */
    public static function comprobanteDevuelto(Recepcion_Proveedor $recepcion): string
    {
        if (! self::esDevolucion($recepcion)) {
            return '';
        }
        if (! $recepcion->relationLoaded('recepcion_referencia')) {
            $recepcion->loadMissing('recepcion_referencia');
        }
        $origen = $recepcion->recepcion_referencia;
        if (! $origen instanceof Recepcion_Proveedor) {
            return '';
        }

        return self::comprobanteAnita($origen);
    }

    public static function descripcionAsientoErp(Recepcion_Proveedor $recepcion): string
    {
        $nombreProveedor = self::nombreProveedor($recepcion);
        if (self::esDevolucion($recepcion)) {
            $devuelto = self::comprobanteDevuelto($recepcion);
            $propio = self::comprobanteAnita($recepcion);
            $texto = 'Devolución del '.($devuelto !== '' ? $devuelto : 'COM de origen');
            if ($propio !== '') {
                $texto .= '. Comprobante '.$propio;
            }
            if ($nombreProveedor !== '') {
                $texto .= ' '.$nombreProveedor;
            }

            return trim($texto);
        }

        $com = (int) ($recepcion->numerorecepcion ?? 0);

        return trim('Recepción proveedor #'.$com.' '.$nombreProveedor);
    }

    /**
     * Texto de cabecera para pantalla, PDF y Excel.
     * Una devolución ya grabada como «Recepción proveedor» muestra el COM devuelto
     * sin reescribir el asiento.
     */
    public static function observacionCabeceraVisible(?Recepcion_Proveedor $recepcion, ?string $observacion): string
    {
        if ($recepcion instanceof Recepcion_Proveedor && self::esDevolucion($recepcion)) {
            return self::descripcionAsientoErp($recepcion);
        }
        $texto = trim((string) $observacion);

        return $texto !== '' ? $texto : '—';
    }

    /**
     * El tipo de asiento sigue siendo Compras (COM en Anita). La devolución nombra el COM de origen.
     */
    public static function tipoAsientoVisible(?Recepcion_Proveedor $recepcion, ?string $tipoAsiento): string
    {
        $tipo = trim((string) $tipoAsiento);
        $esDevolucion = $recepcion instanceof Recepcion_Proveedor && self::esDevolucion($recepcion);
        if (! $esDevolucion) {
            return $tipo !== '' ? $tipo : '—';
        }
        $devuelto = self::comprobanteDevuelto($recepcion);
        $extra = $devuelto !== '' ? 'Devolución del '.$devuelto : 'Devolución a proveedor';
        if ($tipo === '') {
            return $extra;
        }
        if (stripos($tipo, 'Devolución') !== false) {
            return $tipo;
        }

        return $tipo.' · '.$extra;
    }

    /**
     * Detalle de cada línea del asiento: nombra el COM devuelto.
     */
    public static function detalleLineaVisible(?Recepcion_Proveedor $recepcion, ?string $detalle): string
    {
        $texto = trim((string) $detalle);
        if (! $recepcion instanceof Recepcion_Proveedor || ! self::esDevolucion($recepcion)) {
            return $texto;
        }

        return self::marcarTextoDevolucion($texto, self::comprobanteDevuelto($recepcion));
    }

    /**
     * Prefija el mayor. Si se conoce el COM devuelto, lo nombra.
     */
    public static function marcarTextoDevolucion(string $texto, string $comprobanteDevuelto = ''): string
    {
        $texto = trim($texto);
        $comprobanteDevuelto = trim($comprobanteDevuelto);
        if ($comprobanteDevuelto !== '') {
            if ($texto !== '' && str_contains($texto, $comprobanteDevuelto)) {
                return $texto;
            }

            return 'Devolución del '.$comprobanteDevuelto.($texto !== '' ? ' · '.$texto : '');
        }
        if ($texto === '' || str_starts_with($texto, 'DEV ') || str_starts_with($texto, 'Devolución')) {
            return $texto;
        }

        return 'Devolución · '.$texto;
    }

    public static function descripcionCtamovAnita(Recepcion_Proveedor $recepcion): string
    {
        $com = (int) ($recepcion->numerorecepcion ?? 0);
        $nombreProveedor = self::nombreProveedor($recepcion);
        if (self::esDevolucion($recepcion)) {
            if (! $recepcion->relationLoaded('recepcion_referencia')) {
                $recepcion->loadMissing('recepcion_referencia');
            }
            $origen = $recepcion->recepcion_referencia;
            $nroOrigen = $origen instanceof Recepcion_Proveedor
                ? (int) ($origen->anita_nro ?: $origen->numerorecepcion)
                : 0;
            if ($nroOrigen > 0) {
                $com = $nroOrigen;
            }
        }
        $base = self::esDevolucion($recepcion) ? 'DEV '.$com.' ' : $com.' ';
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
