<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Servicio;

/**
 * Resuelve el tipo de ítem (B/S/L/U) para abreviatura fina FIS/FNS/FIB/…
 *
 * Si el proveedor tiene filas en proveedor_servicio (medidores Anita), fuerza Servicio (S).
 */
final class PrecargaProveedorTipoItemSupport
{
    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     */
    public static function resolver(iterable $itemsOrdenCompra, ?string $cuitProveedor = null, ?int $proveedorId = null): string
    {
        if (self::proveedorTieneServicios($cuitProveedor, $proveedorId)) {
            return 'S';
        }

        return self::resolverDesdeItemsOc($itemsOrdenCompra);
    }

    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     */
    public static function resolverDesdeItemsOc(iterable $itemsOrdenCompra): string
    {
        $tipoItem = 'B';
        foreach ($itemsOrdenCompra as $item) {
            if (($item->stkm_tipo_articulo ?? null) == 'S') {
                $tipoItem = 'S';
            }
            if (($item->stkm_agrupacion ?? null) == '0081') {
                $tipoItem = 'L';
            }
            if (($item->stkm_tipo_articulo ?? null) == 'U') {
                $tipoItem = 'U';
            }
        }

        return $tipoItem;
    }

    public static function proveedorTieneServicios(?string $cuitProveedor = null, ?int $proveedorId = null): bool
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            return Proveedor_Servicio::query()->where('proveedor_id', $proveedorId)->exists();
        }

        $cuit = preg_replace('/\D+/', '', (string) $cuitProveedor) ?? '';
        if ($cuit === '') {
            return false;
        }

        $proveedorIds = Proveedor::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '') = ?",
                [$cuit]
            )
            ->pluck('id');

        if ($proveedorIds->isEmpty()) {
            return false;
        }

        return Proveedor_Servicio::query()->whereIn('proveedor_id', $proveedorIds)->exists();
    }
}
