<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/**
 * Escenas mockup — Gastronomía.
 *
 * @return array<string, array<string, mixed>>
 */
final class GastronomiaEscenas
{
    public static function todas(): array
    {
        $sidebar = ['Jornada', 'Habilitación turno', 'Facturación POS', 'Facturas del día', 'Cierres de turno', 'Informes', 'Configuración PV'];

        return [
            'jornada' => [
                'archivo' => 'jornada.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Jornada',
                'card_titulo' => 'Apertura y cierre de jornada',
                'card_color' => 'info',
                'breadcrumb' => 'Ventas › Gastronomía › Jornada',
                'sidebar' => $sidebar,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Fecha jornada', 'valor' => '15/08/2026'],
                    ['label' => 'Estado', 'valor' => 'Abierta'],
                    ['label' => 'Usuario apertura', 'valor' => 'mlopez'],
                ],
                'botones' => [
                    ['texto' => 'Cerrar jornada', 'estilo' => 'warning'],
                    ['texto' => 'Abrir jornada', 'estilo' => 'success'],
                ],
                'nota' => 'La jornada habilita turnos y facturación del día operativo.',
            ],
            'habilitacion_turno' => [
                'archivo' => 'habilitacion-turno.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Habilitación',
                'card_titulo' => 'Habilitación de turno gastronomía',
                'breadcrumb' => 'Ventas › Gastronomía › Habilitación de turno',
                'sidebar' => $sidebar,
                'campos' => [
                    ['label' => 'Punto de venta', 'valor' => 'PV 03 — Salón'],
                    ['label' => 'Turno', 'valor' => 'Mañana'],
                    ['label' => 'Mozo / cajero', 'valor' => 'jperez'],
                    ['label' => 'Fondo inicial', 'valor' => '$ 15.000,00'],
                ],
                'columnas' => ['PV', 'Turno', 'Usuario', 'Estado', 'Desde'],
                'filas' => [
                    ['03', 'Mañana', 'jperez', 'Habilitado', '08:05'],
                    ['03', 'Tarde', '—', 'Pendiente', '—'],
                    ['05', 'Mañana', 'agomez', 'Habilitado', '08:12'],
                ],
                'botones' => [['texto' => 'Habilitar', 'estilo' => 'success']],
            ],
            'huecos_arca_recuperables' => [
                'archivo' => 'huecos-arca-recuperables.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Cierres',
                'card_titulo' => 'Cierre de turno — PV 03',
                'breadcrumb' => 'Ventas › Gastronomía › Cierre de turno',
                'sidebar' => $sidebar,
                'campos' => [
                    ['label' => 'Turno', 'valor' => 'Mañana — jperez'],
                    ['label' => 'Total ventas', 'valor' => '$ 482.350,00'],
                ],
                'modal' => [
                    'titulo' => 'Saneamiento fiscal ARCA',
                    'texto' => 'Se detectaron números FAC faltantes. ARCA confirmó estos comprobantes:',
                    'columnas' => ['Tipo', 'Número', 'CAE', 'Importe'],
                    'filas' => [
                        ['FAC A', '0003-00001245', '74125896301245', '$ 18.500,00'],
                        ['FAC A', '0003-00001246', '74125896301246', '$ 9.200,00'],
                        ['FAC B', '0003-00000881', '85236974102588', '$ 4.150,00'],
                    ],
                    'botones' => [
                        ['texto' => 'Corregir lote (NC consolidada)', 'estilo' => 'primary'],
                        ['texto' => 'Cerrar turno sin sanear', 'estilo' => 'outline'],
                    ],
                ],
            ],
            'huecos_arca_sin_conexion' => [
                'archivo' => 'huecos-arca-sin-conexion.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Cierres',
                'card_titulo' => 'Cierre de turno — PV 03',
                'breadcrumb' => 'Ventas › Gastronomía › Cierre de turno',
                'sidebar' => $sidebar,
                'alertas' => [
                    ['texto' => 'ARCA no responde. El cierre no se bloquea: puede dejar pendientes para auditoría matutina.', 'tipo' => 'warning'],
                ],
                'modal' => [
                    'titulo' => 'Huecos ARCA — sin conexión',
                    'texto' => 'No se pudo consultar ARCA. Números FAC sospechosos quedarán como pendientes.',
                    'columnas' => ['PV', 'Número faltante', 'Acción sugerida'],
                    'filas' => [
                        ['03', '0003-00001247', 'Reintentar mañana'],
                        ['03', '0003-00001248', 'Reintentar mañana'],
                    ],
                    'botones' => [
                        ['texto' => 'Cerrar turno sin sanear', 'estilo' => 'warning'],
                        ['texto' => 'Cancelar', 'estilo' => 'outline'],
                    ],
                ],
            ],
            'proceso_facturacion' => [
                'archivo' => 'proceso-facturacion.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Facturación',
                'card_titulo' => 'Proceso de facturación (POS)',
                'breadcrumb' => 'Ventas › Gastronomía › Facturación',
                'sidebar' => $sidebar,
                'campos' => [
                    ['label' => 'Mesa / ticket', 'valor' => 'Mesa 12'],
                    ['label' => 'Cliente', 'valor' => 'Consumidor final'],
                    ['label' => 'Medio de pago', 'valor' => 'Tarjeta'],
                    ['label' => 'Total', 'valor' => '$ 24.850,00'],
                ],
                'columnas' => ['Artículo', 'Cant.', 'P. unit.', 'Importe'],
                'filas' => [
                    ['Menú ejecutivo', '2', '8.500,00', '17.000,00'],
                    ['Agua mineral', '2', '1.200,00', '2.400,00'],
                    ['Café', '2', '2.725,00', '5.450,00'],
                ],
                'botones' => [
                    ['texto' => 'Facturar', 'estilo' => 'success'],
                    ['texto' => 'Anular ítem', 'estilo' => 'danger'],
                ],
            ],
            'facturas_dia' => [
                'archivo' => 'facturas-dia.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Facturas',
                'card_titulo' => 'Facturas del día',
                'breadcrumb' => 'Ventas › Gastronomía › Facturas del día',
                'sidebar' => $sidebar,
                'filtros' => ['Empresa: Biyemas', 'Fecha: 15/08/2026', 'PV: Todos'],
                'tools' => ['PDF', 'Excel', 'CSV'],
                'columnas' => ['Comprobante', 'PV', 'Cliente', 'CAE', 'Importe', 'Estado'],
                'filas' => [
                    ['FAC A 0003-1240', '03', 'Consumidor final', '7412…', '12.400,00', 'OK'],
                    ['FAC B 0003-0875', '03', 'Cliente VIP 102', '8523…', '8.900,00', 'OK'],
                    ['NC A 0003-0211', '03', 'Ajuste fiscal', '9632…', '18.500,00', 'OK'],
                    ['FAC A 0005-3310', '05', 'Consumidor final', '7412…', '5.200,00', 'OK'],
                ],
            ],
            'cierres_turno' => [
                'archivo' => 'cierres-turno.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Cierres',
                'card_titulo' => 'Consulta de cierres de turno',
                'breadcrumb' => 'Ventas › Gastronomía › Cierres de turno',
                'sidebar' => $sidebar,
                'filtros' => ['Desde: 01/08/2026', 'Hasta: 15/08/2026', 'PV: 03'],
                'columnas' => ['Fecha', 'PV', 'Turno', 'Usuario', 'Ventas', 'Z'],
                'filas' => [
                    ['15/08/2026', '03', 'Mañana', 'jperez', '482.350,00', 'Sí'],
                    ['15/08/2026', '03', 'Tarde', 'agomez', '391.200,00', 'Sí'],
                    ['14/08/2026', '03', 'Mañana', 'jperez', '455.100,00', 'Sí'],
                ],
                'tools' => ['Ver detalle', 'PDF'],
            ],
            'informe_gerente' => [
                'archivo' => 'informe-gerente.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Informes',
                'card_titulo' => 'Informe gerente',
                'breadcrumb' => 'Ventas › Gastronomía › Informe gerente',
                'sidebar' => $sidebar,
                'filtros' => ['Empresa: Biyemas', 'Período: 01–15/08/2026'],
                'columnas' => ['Concepto', 'Efectivo', 'Tarjeta', 'Otros', 'Total'],
                'filas' => [
                    ['Ventas netas', '1.250.000', '2.840.000', '120.000', '4.210.000'],
                    ['NC / ajustes', '0', '45.000', '0', '45.000'],
                    ['Canjes marketing', '0', '0', '38.500', '38.500'],
                ],
                'tools' => ['PDF', 'Excel'],
            ],
            'articulos_vendidos' => [
                'archivo' => 'articulos-vendidos.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Informes',
                'card_titulo' => 'Artículos vendidos',
                'breadcrumb' => 'Ventas › Gastronomía › Artículos vendidos',
                'sidebar' => $sidebar,
                'filtros' => ['Fecha: 15/08/2026', 'PV: Todos'],
                'columnas' => ['Código', 'Artículo', 'Cantidad', 'Importe'],
                'filas' => [
                    ['G-100', 'Menú ejecutivo', '186', '1.581.000'],
                    ['G-210', 'Café espresso', '420', '573.300'],
                    ['G-050', 'Agua mineral', '310', '372.000'],
                    ['G-300', 'Postre del día', '95', '285.000'],
                ],
            ],
            'saneamiento_turno' => [
                'archivo' => 'saneamiento-turno.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Cierres',
                'card_titulo' => 'Saneamiento de turnos',
                'breadcrumb' => 'Ventas › Gastronomía › Saneamiento',
                'sidebar' => $sidebar,
                'alertas' => [
                    ['texto' => 'Herramienta administrativa: reabre / corrige turnos con inconsistencias documentadas.', 'tipo' => 'info'],
                ],
                'columnas' => ['Fecha', 'PV', 'Turno', 'Problema', 'Acción'],
                'filas' => [
                    ['15/08/2026', '03', 'Mañana', 'Huecos ARCA pendientes', 'Auditar'],
                    ['12/08/2026', '05', 'Tarde', 'Z sin asiento', 'Regenerar'],
                ],
                'botones' => [['texto' => 'Ejecutar saneamiento', 'estilo' => 'warning']],
            ],
            'configuracion_pv' => [
                'archivo' => 'configuracion-pv.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Configuración',
                'card_titulo' => 'Configuración punto de venta gastronomía',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Configuración PV gastronomía',
                'sidebar' => $sidebar,
                'campos' => [
                    ['label' => 'Código PV', 'valor' => '03'],
                    ['label' => 'Nombre', 'valor' => 'Salón principal'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Depósito', 'valor' => 'DEP-GAS-01'],
                    ['label' => 'Cuenta caja efectivo', 'valor' => '111001 — Caja salón'],
                    ['label' => 'Facturación ARCA', 'valor' => 'Sí — CAE online'],
                ],
                'botones' => [
                    ['texto' => 'Guardar', 'estilo' => 'primary'],
                    ['texto' => 'Volver', 'estilo' => 'outline'],
                ],
            ],
            'totem_waitry' => [
                'archivo' => 'totem-waitry.png',
                'modulo' => 'Gastronomía',
                'pantalla' => 'Configuración',
                'card_titulo' => 'Tótems Waitry — configuración (AGG)',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Gastronomía › Tótems Waitry',
                'sidebar' => $sidebar,
                'columnas' => ['Código', 'Ubicación', 'PV', 'Estado', 'Última sync'],
                'filas' => [
                    ['WT-01', 'Hall entrada', '03', 'Activo', '15/08 10:22'],
                    ['WT-02', 'Terraza', '03', 'Activo', '15/08 10:21'],
                    ['WT-03', 'Salón VIP', '05', 'Suspendido', '10/08 18:00'],
                ],
                'botones' => [['texto' => 'Nuevo tótem', 'estilo' => 'primary']],
            ],
        ];
    }
}
