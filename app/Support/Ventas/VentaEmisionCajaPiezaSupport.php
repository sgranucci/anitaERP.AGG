<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * caja/pieza en venta_emision existen solo en EL BIERZO.
 * AGG (gastronomía, estacionamiento) no tiene esas columnas: no deben ir en el INSERT.
 */
final class VentaEmisionCajaPiezaSupport
{
    private static ?bool $columnasDisponibles = null;

    public static function resetCache(): void
    {
        self::$columnasDisponibles = null;
    }

    public static function columnasDisponibles(): bool
    {
        if (self::$columnasDisponibles !== null) {
            return self::$columnasDisponibles;
        }

        try {
            self::$columnasDisponibles = Schema::hasTable('venta_emision')
                && Schema::hasColumn('venta_emision', 'pieza')
                && Schema::hasColumn('venta_emision', 'caja');
        } catch (Throwable $e) {
            self::$columnasDisponibles = false;
        }

        return self::$columnasDisponibles;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filtrarPayload(array $data, ?bool $columnasDisponibles = null): array
    {
        if ($columnasDisponibles ?? self::columnasDisponibles()) {
            return $data;
        }

        unset($data['pieza'], $data['caja']);

        return $data;
    }
}
