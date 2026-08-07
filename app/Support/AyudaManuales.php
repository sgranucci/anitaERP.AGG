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
                'bajada' => 'Proveedores, tablas, requisiciones, listas de precio, presupuestos, órdenes de compra y contratos / OC abiertas.',
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
                'modulo' => 'Stock — Recepción y movimientos',
                'bajada' => 'Recepción de proveedores, movimientos de stock, transferencias entre depósitos, comprobantes PDF y aprobaciones.',
                'url' => route('manual_recepcion_movstock'),
                'icono' => 'fa-truck',
                'disponible' => true,
            ],
            [
                'modulo' => 'Canjes Marketing',
                'bajada' => 'Clientes VIP, facturador de canjes en sala y listado de entregas para Marketing.',
                'url' => route('manual_canjes_marketing'),
                'icono' => 'fa-gift',
                'disponible' => true,
            ],
            [
                'modulo' => 'Vending (Gastronomía y Caja)',
                'bajada' => 'Máquinas expendedoras, rendiciones en Ventas, presentación en tesorería e integración Anita.',
                'url' => route('manual_vending'),
                'icono' => 'fa-cube',
                'disponible' => true,
            ],
            [
                'modulo' => 'Gastronomía',
                'bajada' => 'Jornada, turnos, facturación en salón, cierres, restricciones de emisión y consultas gerenciales.',
                'url' => route('manual_gastronomia'),
                'icono' => 'fa-utensils',
                'disponible' => true,
            ],
            [
                'modulo' => 'Ventas — Pedidos y facturación',
                'bajada' => 'Carga de pedidos, pesada con QR, facturación con remito, cierres y reportes de ventas.',
                'url' => route('manual_ventas'),
                'icono' => 'fa-file-invoice',
                'disponible' => true,
            ],
            [
                'modulo' => 'Solicitudes de pago',
                'bajada' => 'Listado, carga masiva CSV, filtros, planes madre/hijas, cuotas, árbol, pago e informe analítico.',
                'url' => route('manual_solicitudpago'),
                'icono' => 'fa-money-check-alt',
                'disponible' => true,
            ],
            [
                'modulo' => 'Contable — Cierres y aperturas',
                'bajada' => 'Agenda mensual por módulo, cierre general, hora de ejecución, histórico y aperturas temporales.',
                'url' => route('manual_contable'),
                'icono' => 'fa-lock',
                'disponible' => true,
            ],
            [
                'modulo' => 'Plataforma IA (SAP-aligned)',
                'bajada' => 'Skills, Document AI, panel, gobernanza, HITL, RAG de manuales, MCP, permisos y runbook operativo.',
                'url' => route('manual_ia'),
                'icono' => 'fa-magic',
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
