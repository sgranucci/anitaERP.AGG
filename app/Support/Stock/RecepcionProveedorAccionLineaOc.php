<?php

namespace App\Support\Stock;

use App\Support\Stock\RecepcionProveedorDiferenciaSupport;

final class RecepcionProveedorAccionLineaOc
{
    public const PENDIENTE = 'PENDIENTE';

    public const RECIBIR = 'RECIBIR';

    public const CERRAR = 'CERRAR';

    /** @param array<string, mixed> $item */
    public static function resolver(array $item): string
    {
        $tipo = (string) ($item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);
        if ($tipo === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return self::RECIBIR;
        }

        $cantidad = (float) ($item['cantidad'] ?? 0);
        $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);
        $explicito = strtoupper(trim((string) ($item['accion_linea_oc'] ?? '')));
        $cerrarSaldo = ! empty($item['fl_cerrar_linea_oc']) || $explicito === self::CERRAR;

        if ($cantidad > 0.000001 || $rechazada > 0.000001) {
            return $cerrarSaldo ? self::CERRAR : self::RECIBIR;
        }

        if (in_array($explicito, [self::PENDIENTE, self::CERRAR], true)) {
            return $explicito;
        }

        if ($cerrarSaldo) {
            return self::CERRAR;
        }

        return self::PENDIENTE;
    }

    /** Recepción parcial: cantidad en remito menor a la pedida en OC (saldo queda abierto salvo cierre). */
    public static function esRecepcionParcialConSaldoPendiente(array $item): bool
    {
        $tipo = (string) ($item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);
        if ($tipo === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return false;
        }

        if (self::resolver($item) === self::CERRAR) {
            return false;
        }

        $cantOc = (float) ($item['cantidad_oc'] ?? 0);
        $yaRecibida = (float) ($item['cantidad_recibida'] ?? 0);
        $cantRec = $yaRecibida + (float) ($item['cantidad'] ?? 0) + (float) ($item['cantidad_rechazada'] ?? 0);

        return $cantOc > 0.000001
            && $cantRec > 0.000001
            && $cantRec + 0.000001 < $cantOc;
    }

    /**
     * Sin cantidad no se exige elegir pendiente/cierre: queda pendiente para otra entrega.
     * El cierre de línea OC es una acción explícita (selector o "Cerrar saldo").
     */
    public static function requiereDefinicionEnGuardado(array $item): bool
    {
        return false;
    }

    public static function esPendiente(array $item): bool
    {
        return self::resolver($item) === self::PENDIENTE;
    }

    public static function esCerrar(array $item): bool
    {
        return self::resolver($item) === self::CERRAR;
    }
}
