<?php

namespace App\Support\Compras;

use App\Models\Compras\Configuracion_ComprobanteProveedor;

/**
 * Flags de controles de legajo / match por línea en configuración de comprobante proveedor.
 *
 * @phpstan-type ConfigControles array{
 *     activo: bool,
 *     controla_sku_vs_com: bool,
 *     controla_precio_unitario: bool,
 *     tolerancia_precio_pct: float,
 *     match_lineas_activo: bool
 * }
 */
final class ComprobanteProveedorControlesConfigSupport
{
    /**
     * @return ConfigControles
     */
    public static function paraEmpresa(int $empresaId): array
    {
        $defaults = [
            'activo' => true,
            'controla_sku_vs_com' => false,
            'controla_precio_unitario' => false,
            'tolerancia_precio_pct' => 0.0,
            'match_lineas_activo' => false,
        ];

        if ($empresaId <= 0) {
            return $defaults;
        }

        $row = Configuracion_ComprobanteProveedor::query()
            ->where('empresa_id', $empresaId)
            ->first([
                'activo',
                'controla_sku_vs_com',
                'controla_precio_unitario',
                'tolerancia_precio_pct',
            ]);

        if (! $row) {
            return $defaults;
        }

        $controlaSku = (bool) $row->controla_sku_vs_com;
        $controlaPrecio = (bool) $row->controla_precio_unitario;

        return [
            'activo' => (bool) $row->activo,
            'controla_sku_vs_com' => $controlaSku,
            'controla_precio_unitario' => $controlaPrecio,
            'tolerancia_precio_pct' => (float) ($row->tolerancia_precio_pct ?? 0),
            'match_lineas_activo' => $controlaSku || $controlaPrecio,
        ];
    }

    public static function controlesLegajoActivos(int $empresaId): bool
    {
        return self::paraEmpresa($empresaId)['activo'];
    }
}
