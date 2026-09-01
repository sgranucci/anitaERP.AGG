<?php

namespace App\Support\Admin;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * En Calzados Ferli el menú debe ser el de L8 (calzado / OT / producción).
 * Oculta ítems de AGG, Bierzo, Interforming, gastronomía y Waitry.
 */
final class MenuFerliSupport
{
    private const PREFIXES = [
        'caja/bingo',
        'caja/estacionamiento',
        'caja/interbanking',
        'caja/remesa',
        'caja/waitry',
        'stock/articulo',
        'stock/configuracion-salida-bienes',
        'stock/etiqueta-surmar',
        'stock/salida-bienes',
        'uif/',
        'ventas/asignacion-remito-factura',
        'ventas/configuracion-puntoventa-gastronomia',
        'ventas/contrato',
        'ventas/gastronomia',
        'ventas/remito',
        'ventas/totem-waitry',
        'ventas/tipoempresa-cliente',
    ];

    public static function filtrar(array $menus): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return $menus;
        }

        $ocultos = [];
        foreach ($menus as $menu) {
            if (self::ocultaPorUrl((string) ($menu['url'] ?? ''))) {
                $ocultos[(int) $menu['id']] = true;
            }
        }

        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach ($menus as $menu) {
                $id = (int) $menu['id'];
                $padre = (int) ($menu['menu_id'] ?? 0);
                if (! isset($ocultos[$id]) && $padre > 0 && isset($ocultos[$padre])) {
                    $ocultos[$id] = true;
                    $cambio = true;
                }
            }
        }

        $visibles = array_values(array_filter($menus, function (array $menu) use ($ocultos) {
            return ! isset($ocultos[(int) $menu['id']]);
        }));

        $hijosPorPadre = [];
        foreach ($visibles as $menu) {
            $padre = (int) ($menu['menu_id'] ?? 0);
            if ($padre > 0) {
                $hijosPorPadre[$padre] = true;
            }
        }

        return array_values(array_filter($visibles, function (array $menu) use ($hijosPorPadre) {
            $url = trim((string) ($menu['url'] ?? ''));
            if ($url !== '' && $url !== '#') {
                return true;
            }

            return isset($hijosPorPadre[(int) $menu['id']]);
        }));
    }

    private static function ocultaPorUrl(string $url): bool
    {
        $url = ltrim($url, '/');
        foreach (self::PREFIXES as $prefix) {
            if ($url === $prefix || strpos($url, $prefix.'/') === 0) {
                return true;
            }
        }

        return false;
    }
}
