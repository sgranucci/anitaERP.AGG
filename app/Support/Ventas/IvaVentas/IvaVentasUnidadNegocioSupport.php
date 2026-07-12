<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\Venta;

/**
 * Clasifica cada comprobante del reporte IVA ventas en una unidad de negocio
 * (Gastronomía / Vending / Estacionamiento / Administración) según la PC / punto
 * de venta de la factura.
 */
final class IvaVentasUnidadNegocioSupport
{
    public const GASTRONOMIA = 'gastronomia';

    public const VENDING = 'vending';

    public const ESTACIONAMIENTO = 'estacionamiento';

    public const OTROS = 'otros';

    /** @var array<int, array<int, bool>> */
    private static array $vendingPvCache = [];

    /**
     * Orden de presentación de las unidades.
     *
     * @return list<string>
     */
    public static function orden(): array
    {
        return [self::GASTRONOMIA, self::VENDING, self::ESTACIONAMIENTO, self::OTROS];
    }

    public static function label(string $key): string
    {
        $labels = (array) config('iva_ventas.unidades_negocio.labels', []);

        return (string) ($labels[$key] ?? self::labelDefault($key));
    }

    /**
     * Punto de venta (id) de las máquinas vending de la empresa (+ overrides de config).
     *
     * @return array<int, bool>
     */
    public static function vendingPuntoventaIds(int $empresaId): array
    {
        if (isset(self::$vendingPvCache[$empresaId])) {
            return self::$vendingPvCache[$empresaId];
        }

        $ids = [];
        $consulta = Maquinavending::query()->whereNotNull('puntoventa_id');
        if ($empresaId > 0) {
            $consulta->where('empresa_id', $empresaId);
        }
        foreach ($consulta->pluck('puntoventa_id') as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        foreach ((array) config('iva_ventas.unidades_negocio.vending_puntoventa_ids', []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return self::$vendingPvCache[$empresaId] = $ids;
    }

    /**
     * @param  array<int, bool>  $vendingPvIds
     */
    public static function clasificar(Venta $venta, array $vendingPvIds): string
    {
        if ($venta->estacionamientoEmision !== null) {
            return self::ESTACIONAMIENTO;
        }

        $pvId = (int) ($venta->puntoventa_id ?? 0);
        if ($pvId > 0 && isset($vendingPvIds[$pvId])) {
            return self::VENDING;
        }

        if ($venta->gastronomiaEmision !== null) {
            return self::GASTRONOMIA;
        }

        return self::OTROS;
    }

    private static function labelDefault(string $key): string
    {
        return match ($key) {
            self::GASTRONOMIA => 'Gastronomía',
            self::VENDING => 'Vending',
            self::ESTACIONAMIENTO => 'Estacionamiento',
            default => 'Administración / Otros',
        };
    }
}
