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
        if ($cantidad > 0.000001 || $rechazada > 0.000001) {
            return self::RECIBIR;
        }

        $explicito = strtoupper(trim((string) ($item['accion_linea_oc'] ?? '')));
        if (in_array($explicito, [self::PENDIENTE, self::CERRAR], true)) {
            return $explicito;
        }

        if (! empty($item['fl_cerrar_linea_oc'])) {
            return self::CERRAR;
        }

        return self::PENDIENTE;
    }

    /** Línea OC sin cantidad y sin pendiente/cierre ya elegido (modal al guardar borrador). */
    public static function requiereDefinicionEnGuardado(array $item): bool
    {
        $tipo = (string) ($item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);
        if ($tipo === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return false;
        }

        $cantidad = (float) ($item['cantidad'] ?? 0);
        $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);
        if ($cantidad > 0.000001 || $rechazada > 0.000001) {
            return false;
        }

        $explicito = strtoupper(trim((string) ($item['accion_linea_oc'] ?? '')));

        return ! in_array($explicito, [self::PENDIENTE, self::CERRAR], true)
            && empty($item['fl_cerrar_linea_oc']);
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
