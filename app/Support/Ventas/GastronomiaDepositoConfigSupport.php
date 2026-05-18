<?php

namespace App\Support\Ventas;

use App\Models\Stock\Depmae;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;

/**
 * Depósitos de stock para gastronomía por terminal (configuración PV).
 */
final class GastronomiaDepositoConfigSupport
{
    public static function depositoVentaId(?ConfiguracionPuntoventaGastronomia $cfg): int
    {
        $id = (int) ($cfg?->deposito_venta_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (config('facturacion.DEPOSITO_VENTA_ID') ?: 0);
    }

    public static function depositoInsumosId(?ConfiguracionPuntoventaGastronomia $cfg): int
    {
        $id = (int) ($cfg?->deposito_insumos_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (config('facturacion.DEPOSITO_VENTA_ID') ?: 0);
    }

    public static function depositoDto(int $depositoId): ?object
    {
        if ($depositoId <= 0) {
            return null;
        }

        $dep = Depmae::query()->find($depositoId);
        if (! $dep) {
            return null;
        }

        return (object) [
            'id' => (int) $dep->id,
            'codigo' => (string) ($dep->codigo ?? ''),
            'nombre' => (string) ($dep->nombre ?? ''),
        ];
    }

    public static function depositoVentaDto(?ConfiguracionPuntoventaGastronomia $cfg): ?object
    {
        return self::depositoDto(self::depositoVentaId($cfg));
    }

    public static function depositoInsumosDto(?ConfiguracionPuntoventaGastronomia $cfg): ?object
    {
        return self::depositoDto(self::depositoInsumosId($cfg));
    }

    /**
     * @return list<string>
     */
    public static function erroresDepositosFaltantes(?ConfiguracionPuntoventaGastronomia $cfg): array
    {
        if (! $cfg) {
            return [];
        }

        $errores = [];
        if ((int) ($cfg->deposito_venta_id ?? 0) <= 0) {
            $errores[] = 'Configure el depósito de artículos facturados en la configuración del punto de venta gastronomía.';
        }
        if ((int) ($cfg->deposito_insumos_id ?? 0) <= 0) {
            $errores[] = 'Configure el depósito de descuento de insumos en la configuración del punto de venta gastronomía.';
        }

        return $errores;
    }
}
