<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Configuracion_RecepcionProveedorTolerancia;

class RecepcionProveedorLaboratorioSupport
{
    public static function esArticuloLaboratorio(?Articulo $articulo): bool
    {
        if (! $articulo) {
            return false;
        }

        $sku = strtoupper(trim((string) ($articulo->sku ?? '')));
        $prefijo = strtoupper(trim((string) config('recepcion_proveedor.sku_prefijo_laboratorio', 'LAB')));

        if ($prefijo !== '' && str_starts_with($sku, $prefijo)) {
            return true;
        }

        $idsLab = config('recepcion_proveedor.usoarticulo_laboratorio_ids', [3]);

        return in_array((int) ($articulo->usoarticulo_id ?? 0), array_map('intval', (array) $idsLab), true);
    }

    /** @param iterable<int, Articulo|null> $articulos */
    public static function recepcionEsLaboratorio(iterable $articulos): bool
    {
        foreach ($articulos as $articulo) {
            if (self::esArticuloLaboratorio($articulo)) {
                return true;
            }
        }

        return false;
    }
}
