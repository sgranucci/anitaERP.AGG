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
                'modulo' => 'Stock — Gastronomía, fórmulas e insumos',
                'bajada' => 'Fórmulas, referencias de compra, depósitos venta/insumos, descuento al facturar y tipos de movimiento.',
                'url' => route('manual_stock_gastronomia'),
                'icono' => 'fa-flask',
                'disponible' => true,
            ],
            [
                'modulo' => 'Caja — Posición financiera, Flash, máquinas y bingo',
                'bajada' => 'Flujo de datos diario, origen de totales Flash, rendiciones de máquinas, pozo bingo y posición financiera.',
                'url' => route('manual_caja'),
                'icono' => 'fa-cash-register',
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
                'modulo' => 'Cuentas a pagar / Propuesta de pagos',
                'bajada' => 'Proyección de pagos, armar y autorizar lote (árbol PP), ejecutar OP, lote bancario, clearing, cockpit y cash forecast.',
                'url' => route('manual_propuesta_pago'),
                'icono' => 'fa-university',
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
                'modulo' => 'Contaduría — Cierres de rendiciones',
                'bajada' => 'Asientos de máquinas, bingo, estacionamiento y vending: preview, cuentas, pozo acumulado y conciliación Flash.',
                'url' => route('manual_cierres_rendiciones'),
                'icono' => 'fa-balance-scale',
                'disponible' => true,
            ],
            [
                'modulo' => 'Contable — Reportes definibles',
                'bajada' => 'Catálogo FSV, layouts, consolidación IC, ejecución, drill-down, publicación, distribución, notas y paridad Anita.',
                'url' => route('manual_reporte_definible'),
                'icono' => 'fa-chart-bar',
                'disponible' => true,
            ],
            [
                'modulo' => 'Sueldos — Sanciones disciplinarias',
                'bajada' => 'Tipos y motivos, carga en el empleado, importe no cobrado, novedad de liquidación y reporte.',
                'url' => route('manual_sueldos'),
                'icono' => 'fa-gavel',
                'disponible' => true,
            ],
            [
                'modulo' => 'UIF — Clientes, premios e informes',
                'bajada' => 'Clientes y premios, informe mensual Excel/PDF/XML, congelados, conciliación Wigos y tablas maestras.',
                'url' => route('manual_uif'),
                'icono' => 'fa-id-card',
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
