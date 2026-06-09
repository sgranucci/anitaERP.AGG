<?php

namespace App\Support;

class AyudaManuales
{
    /**
     * Catálogo de manuales por módulo (índice / bajadas).
     *
     * @return array<int, array{modulo: string, bajada: string, url: string, icono: string, disponible: bool}>
     */
    public static function catalogo(): array
    {
        return [
            [
                'modulo' => 'Compras',
                'bajada' => 'Proveedores, tablas, requisiciones, listas de precio, presupuestos y órdenes de compra.',
                'url' => route('manual_compras'),
                'icono' => 'fa-shopping-cart',
                'disponible' => true,
            ],
            [
                'modulo' => 'Stock — Recuento de inventario',
                'bajada' => 'Conteos físicos, ajustes de stock, modos de cierre, importación Excel y consulta de movimientos.',
                'url' => route('manual_stock'),
                'icono' => 'fa-clipboard',
                'disponible' => true,
            ],
            [
                'modulo' => 'Gastronomía',
                'bajada' => 'Jornada, turnos, facturación en salón, cierres, restricciones de emisión y consultas gerenciales.',
                'url' => route('manual_gastronomia'),
                'icono' => 'fa-utensils',
                'disponible' => true,
            ],
        ];
    }

    /**
     * Destino del enlace global «Centro de ayuda»: manual completo si hay uno solo; índice si hay varios.
     */
    public static function urlCentroAyuda(): string
    {
        $disponibles = array_values(array_filter(
            static::catalogo(),
            static fn (array $m): bool => (bool) ($m['disponible'] ?? false)
        ));

        if (count($disponibles) === 1) {
            return $disponibles[0]['url'];
        }

        return route('ayuda');
    }
}
