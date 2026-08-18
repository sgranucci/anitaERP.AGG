<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class StockGastronomiaEscenas
{
    public static function todas(): array
    {
        $sb = ['Config. PV', 'Fórmulas', 'Mov. stock', 'Tipos mov.', 'Reportes insumos'];

        return [
            'flujo_stock_gastro' => [
                'archivo' => 'flujo-stock-gastro.png',
                'modulo' => 'Stock',
                'pantalla' => 'Config. PV',
                'card_titulo' => 'Flujo operativo — stock gastronómico',
                'breadcrumb' => 'Stock › Manual › Flujo',
                'sidebar' => $sb,
                'nota' => 'Configuración PV → fórmula → factura POS → movimientos automáticos en depósitos venta e insumos.',
                'columnas' => ['Paso', 'Acción', 'Pantalla / proceso', 'Efecto en stock'],
                'filas' => [
                    ['1', 'Configurar depósitos', 'Config. PV gastronomía', 'Dep. venta + dep. insumos por PV'],
                    ['2', 'Armar fórmula', 'stock/formula-articulo', 'Hijos insumo por ítem vendible'],
                    ['3', 'Facturar en POS', 'Ventas › Gastronomía', 'Salida ítem en dep. venta'],
                    ['4', 'Consumo insumos', 'Motor facturación', 'Salida insumos según fórmula'],
                    ['5', 'Consultar / ajustar', 'Mov. stock / reportes', 'Kardex y reporte insumos'],
                ],
            ],
            'config_depositos' => [
                'archivo' => 'config-depositos.png',
                'modulo' => 'Stock',
                'pantalla' => 'Config. PV',
                'card_titulo' => 'Configuración PV gastronomía — depósitos',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Config. PV gastronomía',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Punto de venta', 'valor' => 'PV 03 — Salón'],
                    ['label' => 'Depósito venta', 'valor' => 'GAST-VEN-03 (productos terminados)'],
                    ['label' => 'Depósito insumos', 'valor' => 'GAST-INS-03 (materias primas)'],
                ],
                'alertas' => [
                    ['texto' => 'Ambos depósitos deben pertenecer a la empresa del PV y estar autorizados para el usuario.', 'tipo' => 'info'],
                ],
                'botones' => [['texto' => 'Guardar configuración', 'estilo' => 'primary']],
            ],
            'formula_listado' => [
                'archivo' => 'formula-listado.png',
                'modulo' => 'Stock',
                'pantalla' => 'Fórmulas',
                'card_titulo' => 'Listado de fórmulas de artículos',
                'breadcrumb' => 'Stock › Fórmula artículo',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Artículo: Milanesa', 'Solo activas: Sí'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['Código', 'Artículo padre', 'Hijos', 'Costo ref.', 'Actualizado'],
                'filas' => [
                    ['FOR-120', 'Milanesa napolitana', '6 insumos', '$ 1.850,00', '10/08/2026'],
                    ['FOR-118', 'Pizza muzzarella G', '8 insumos', '$ 2.120,00', '08/08/2026'],
                    ['FOR-105', 'Café con leche', '3 insumos', '$ 420,00', '01/08/2026'],
                ],
            ],
            'formula_edicion' => [
                'archivo' => 'formula-edicion.png',
                'modulo' => 'Stock',
                'pantalla' => 'Fórmulas',
                'card_titulo' => 'Edición FOR-120 — Milanesa napolitana',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Fórmula artículo › Editar',
                'sidebar' => $sb,
                'tabs' => ['Cabecera', 'Insumos (hijos)', 'Costos', 'Refs. compra'],
                'tab_activa' => 1,
                'campos' => [
                    ['label' => 'Artículo padre', 'valor' => 'MIL-NAP-01 — Milanesa napolitana'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Rendimiento', 'valor' => '1 porción'],
                ],
                'columnas' => ['Insumo', 'Cantidad', 'Unidad', 'Costo unit.', 'Subtotal'],
                'filas' => [
                    ['CAR-001 Carne vacuna', '0,180', 'kg', '$ 4.200', '$ 756,00'],
                    ['PAN-010 Pan mignon', '1', 'un', '$ 85', '$ 85,00'],
                    ['SAL-020 Salsa tomate', '0,050', 'lt', '$ 1.200', '$ 60,00'],
                    ['QUE-030 Mozzarella', '0,080', 'kg', '$ 5.800', '$ 464,00'],
                ],
                'botones' => [['texto' => 'Guardar fórmula', 'estilo' => 'primary']],
            ],
            'articulo_proveedor' => [
                'archivo' => 'articulo-proveedor.png',
                'modulo' => 'Stock',
                'pantalla' => 'Artículos',
                'card_titulo' => 'Artículo I000045 — Proveedores',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Artículos › Editar › Proveedores',
                'sidebar' => $sb,
                'tabs' => ['General', 'Stock', 'Fórmula', 'Proveedores'],
                'tab_activa' => 3,
                'alertas' => [
                    ['texto' => 'Coef. conv. = unidades de stock por 1 unidad de compra. Si ambas UM coinciden, debe ser 1.', 'tipo' => 'info'],
                ],
                'columnas' => ['Proveedor', 'Nombre proveedor', 'Código prov.', 'UM compra', 'Coef.', 'Activo', 'Pref.'],
                'filas' => [
                    ['PROV 018', 'Mozzarella horma 4 kg', 'MZ-H4', 'horma', '4', 'Sí', 'Sí'],
                    ['PROV 027', 'Muzzarella caja 8 kg', 'MUZ-08', 'caja', '8', 'Sí', 'No'],
                    ['PROV 041', 'Mozzarella fraccionada', 'MZ-KG', 'kg', '1', 'Sí', 'No'],
                ],
                'botones' => [
                    ['texto' => '+ Agregar proveedor', 'estilo' => 'outline'],
                    ['texto' => 'Actualizar', 'estilo' => 'primary'],
                ],
                'nota' => 'Ejemplo: 3 hormas × coef. 4 = 12 kg de stock; $ 18.000 por horma ÷ 4 = $ 4.500 por kg.',
            ],
            'consumo_factura' => [
                'archivo' => 'consumo-factura.png',
                'modulo' => 'Stock',
                'pantalla' => 'Mov. stock',
                'card_titulo' => 'Factura FAC 0003-00004521 — movimientos generados',
                'breadcrumb' => 'Ventas › Gastronomía › Factura del día',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'PV', 'valor' => '03 — Salón'],
                    ['label' => 'Total factura', 'valor' => '$ 18.500,00'],
                    ['label' => 'Dep. venta', 'valor' => 'GAST-VEN-03'],
                    ['label' => 'Dep. insumos', 'valor' => 'GAST-INS-03'],
                ],
                'modal' => [
                    'titulo' => 'Descuento de stock al facturar',
                    'texto' => 'Salida del ítem vendible y consumo de insumos según fórmula FOR-120.',
                    'columnas' => ['Tipo', 'Artículo', 'Depósito', 'Cantidad', 'Movimiento'],
                    'filas' => [
                        ['Venta', 'MIL-NAP-01 Milanesa nap.', 'GAST-VEN-03', '-1', 'VEN-GAST'],
                        ['Insumo', 'CAR-001 Carne vacuna', 'GAST-INS-03', '-0,180', 'CON-GAST'],
                        ['Insumo', 'PAN-010 Pan mignon', 'GAST-INS-03', '-1', 'CON-GAST'],
                        ['Insumo', 'QUE-030 Mozzarella', 'GAST-INS-03', '-0,080', 'CON-GAST'],
                    ],
                    'botones' => [['texto' => 'Ver kardex', 'estilo' => 'primary'], ['texto' => 'Cerrar', 'estilo' => 'outline']],
                ],
            ],
            'tipos_movimiento' => [
                'archivo' => 'tipos-movimiento.png',
                'modulo' => 'Stock',
                'pantalla' => 'Tipos mov.',
                'card_titulo' => 'Tipos de movimiento de stock — gastronomía y operativos',
                'breadcrumb' => 'Stock › Tipos transacción stock',
                'sidebar' => $sb,
                'filtros' => ['Módulo: Todos', 'Signo: Entrada/Salida/Transferencia'],
                'columnas' => ['Código', 'Nombre', 'Signo', 'Origen', 'Uso típico'],
                'filas' => [
                    ['VEN-GAST', 'Venta gastronomía', 'S', 'Factura POS', 'Salida ítem en dep. venta'],
                    ['CON-GAST', 'Consumo insumo', 'S', 'Factura POS', 'Salida insumos por fórmula'],
                    ['COM', 'Recepción proveedor', 'E', 'Compras', 'Entrada por COM confirmada'],
                    ['TRA', 'Transferencia', 'T', 'Stock manual', 'Entre depósitos autorizados'],
                    ['RCAJ*', 'Recuento caja insumos', 'E/S', 'Recuento', 'Ajuste por conteo físico'],
                    ['AJ-GAST', 'Ajuste gastronomía', 'E/S', 'Mov. manual', 'Merma / corrección depósito'],
                ],
            ],
            'insumos_reporte' => [
                'archivo' => 'insumos-reporte.png',
                'modulo' => 'Stock',
                'pantalla' => 'Reportes insumos',
                'card_titulo' => 'Reporte insumos por tipo de artículo',
                'breadcrumb' => 'Stock › Reportes › Insumos vendidos',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Desde: 01/08/2026', 'Hasta: 15/08/2026', 'PV: 03'],
                'tools' => ['Consultar', 'PDF', 'Excel'],
                'columnas' => ['Tipo artículo', 'Insumo', 'Cant. consumida', 'Unidad', 'Costo ref.'],
                'filas' => [
                    ['Milanesas', 'CAR-001 Carne vacuna', '42,500', 'kg', '$ 178.500,00'],
                    ['Milanesas', 'QUE-030 Mozzarella', '18,200', 'kg', '$ 105.560,00'],
                    ['Pizzas', 'HAR-100 Harina 000', '28,000', 'kg', '$ 33.600,00'],
                    ['Bebidas', 'CAF-010 Café molido', '6,400', 'kg', '$ 89.600,00'],
                ],
            ],
        ];
    }
}
