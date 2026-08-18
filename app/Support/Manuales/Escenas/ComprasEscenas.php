<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class ComprasEscenas
{
    public static function todas(): array
    {
        $sb = ['Proveedores', 'Requisiciones', 'Órdenes de compra', 'Listas de precio', 'Reportes', 'Tablas'];

        return [
            'login' => [
                'archivo' => 'login.png',
                'tipo' => 'login',
                'card_titulo' => 'Inicio de sesión — Compras',
            ],
            'proveedor' => [
                'archivo' => 'proveedor-listado.png',
                'modulo' => 'Compras',
                'pantalla' => 'Proveedores',
                'card_titulo' => 'Listado de proveedores',
                'breadcrumb' => 'Compras › Proveedores',
                'sidebar' => $sb,
                'filtros' => ['Texto: metal', 'Estado: Activos', 'Empresa: Biyemas'],
                'tools' => ['Nuevo', 'PDF', 'Excel', 'CSV'],
                'columnas' => ['Código', 'Razón social', 'CUIT', 'Condición', 'Estado'],
                'filas' => [
                    ['P-1020', 'Aceros del Sur S.A.', '30-71234567-8', '30 días', 'Activo'],
                    ['P-1044', 'Metales Andinos SRL', '30-69876543-1', 'Contado', 'Activo'],
                    ['P-1102', 'Empaques Norte SA', '30-55443322-9', '60 días', 'Activo'],
                ],
            ],
            'proveedor_edicion' => [
                'archivo' => 'proveedor-edicion.png',
                'modulo' => 'Compras',
                'pantalla' => 'Proveedores',
                'card_titulo' => 'Ficha de proveedor',
                'card_color' => 'primary',
                'breadcrumb' => 'Compras › Proveedores › Editar',
                'sidebar' => $sb,
                'tabs' => ['Datos', 'Cuentas contables', 'Contactos', 'Archivos'],
                'tab_activa' => 0,
                'campos' => [
                    ['label' => 'Código', 'valor' => 'P-1020'],
                    ['label' => 'Razón social', 'valor' => 'Aceros del Sur S.A.'],
                    ['label' => 'CUIT', 'valor' => '30-71234567-8'],
                    ['label' => 'Condición IVA', 'valor' => 'Responsable Inscripto'],
                    ['label' => 'Condición pago', 'valor' => '30 días fecha factura'],
                    ['label' => 'Email compras', 'valor' => 'compras@aceros.ejemplo'],
                ],
                'botones' => [['texto' => 'Actualizar', 'estilo' => 'primary'], ['texto' => 'Volver', 'estilo' => 'outline']],
            ],
            'requisicion' => [
                'archivo' => 'requisicion-listado.png',
                'modulo' => 'Compras',
                'pantalla' => 'Requisiciones',
                'card_titulo' => 'Listado de requisiciones',
                'breadcrumb' => 'Compras › Requisiciones',
                'sidebar' => $sb,
                'filtros' => ['Estado: Pendiente', 'Desde: 01/08/2026', 'Hasta: 15/08/2026'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['Nº', 'Fecha', 'Solicitante', 'Sector', 'Estado', 'Monto'],
                'filas' => [
                    ['RQ-4581', '12/08/2026', 'mgomez', 'Mantenimiento', 'Pendiente', '1.250.000'],
                    ['RQ-4578', '11/08/2026', 'lruiz', 'Producción', 'Aprobada', '890.400'],
                    ['RQ-4560', '08/08/2026', 'mgomez', 'Depósito', 'En OC', '320.000'],
                ],
            ],
            'requisicion_edicion' => [
                'archivo' => 'requisicion-edicion.png',
                'modulo' => 'Compras',
                'pantalla' => 'Requisiciones',
                'card_titulo' => 'Formulario de requisición RQ-4581',
                'card_color' => 'primary',
                'breadcrumb' => 'Compras › Requisiciones › Editar',
                'sidebar' => $sb,
                'tabs' => ['Cabecera', 'Ítems', 'Presupuestos', 'Archivos'],
                'campos' => [
                    ['label' => 'Fecha', 'valor' => '12/08/2026'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Centro de costo', 'valor' => 'CC-450 Mantenimiento'],
                    ['label' => 'Moneda', 'valor' => 'ARS'],
                ],
                'columnas' => ['SKU', 'Descripción', 'Cant.', 'Precio ref.', 'Partida'],
                'filas' => [
                    ['ART-8801', 'Rodamiento 6205', '20', '12.500', 'PG-120'],
                    ['ART-8810', 'Grasa industrial 5kg', '8', '18.200', 'PG-120'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary'], ['texto' => 'Enviar a aprobación', 'estilo' => 'success']],
            ],
            'presupuesto' => [
                'archivo' => 'presupuestos-tab.png',
                'modulo' => 'Compras',
                'pantalla' => 'Requisiciones',
                'card_titulo' => 'Solapa Presupuestos — RQ-4581',
                'card_color' => 'primary',
                'breadcrumb' => 'Compras › Requisiciones › Presupuestos',
                'sidebar' => $sb,
                'tabs' => ['Cabecera', 'Ítems', 'Presupuestos', 'Archivos'],
                'tab_activa' => 2,
                'columnas' => ['Proveedor', 'Fecha', 'Moneda', 'Total', 'Elegido'],
                'filas' => [
                    ['Aceros del Sur', '13/08/2026', 'ARS', '1.180.000', 'Sí'],
                    ['Metales Andinos', '13/08/2026', 'ARS', '1.245.000', 'No'],
                    ['Empaques Norte', '14/08/2026', 'USD', '980', 'No'],
                ],
                'nota' => 'El presupuesto elegido alimenta la orden de compra.',
            ],
            'listaprecio' => [
                'archivo' => 'listaprecio-proveedor.png',
                'modulo' => 'Compras',
                'pantalla' => 'Listas',
                'card_titulo' => 'Lista de precio proveedor',
                'breadcrumb' => 'Compras › Listas de precio',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Proveedor', 'valor' => 'P-1020 Aceros del Sur'],
                    ['label' => 'Vigencia', 'valor' => '01/08/2026 — 31/12/2026'],
                ],
                'columnas' => ['SKU', 'Descripción', 'UM', 'Precio', 'Moneda'],
                'filas' => [
                    ['ART-8801', 'Rodamiento 6205', 'UN', '12.500', 'ARS'],
                    ['ART-8802', 'Rodamiento 6206', 'UN', '14.800', 'ARS'],
                    ['ART-9001', 'Chapa 2mm', 'KG', '1.850', 'ARS'],
                ],
            ],
            'ordencompra' => [
                'archivo' => 'ordencompra-listado.png',
                'modulo' => 'Compras',
                'pantalla' => 'Órdenes',
                'card_titulo' => 'Listado de órdenes de compra',
                'breadcrumb' => 'Compras › Órdenes de compra',
                'sidebar' => $sb,
                'filtros' => ['Estado: Aprobada', 'Proveedor: Aceros', 'Empresa: Biyemas'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['OC', 'Fecha', 'Proveedor', 'Estado', 'Moneda', 'Monto'],
                'filas' => [
                    ['OC-9921', '14/08/2026', 'Aceros del Sur', 'Aprobada', 'ARS', '1.180.000'],
                    ['OC-9910', '10/08/2026', 'Empaques Norte', 'Parcial', 'USD', '4.500'],
                    ['OC-9899', '05/08/2026', 'Metales Andinos', 'Cerrada', 'ARS', '760.000'],
                ],
            ],
            'ordencompra_edicion' => [
                'archivo' => 'ordencompra-edicion.png',
                'modulo' => 'Compras',
                'pantalla' => 'Órdenes',
                'card_titulo' => 'Orden de compra OC-9921',
                'card_color' => 'primary',
                'breadcrumb' => 'Compras › Órdenes de compra › Editar',
                'sidebar' => $sb,
                'tabs' => ['Datos', 'Ítems', 'Cuotas', 'Archivos', 'Aprobaciones'],
                'campos' => [
                    ['label' => 'Proveedor', 'valor' => 'Aceros del Sur S.A.'],
                    ['label' => 'Condición compra', 'valor' => '30 días'],
                    ['label' => 'Moneda / cotización', 'valor' => 'ARS / 1'],
                    ['label' => 'Entrega estimada', 'valor' => '25/08/2026'],
                ],
                'columnas' => ['SKU', 'Cant. pedida', 'Precio', 'CC destino', 'Entrega'],
                'filas' => [
                    ['ART-8801', '20', '12.500', 'CC-450', '25/08'],
                    ['ART-8810', '8', '18.200', 'CC-450', '25/08'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary'], ['texto' => 'Enviar aprobación', 'estilo' => 'success']],
            ],
            'tablas' => [
                'archivo' => 'tablas-maestras.png',
                'modulo' => 'Compras',
                'pantalla' => 'Tablas',
                'card_titulo' => 'Tablas maestras de Compras',
                'breadcrumb' => 'Compras › Configuración › Tablas',
                'sidebar' => $sb,
                'columnas' => ['Tabla', 'Registros', 'Última actualización', 'Acción'],
                'filas' => [
                    ['Condiciones de compra', '12', '01/08/2026', 'Administrar'],
                    ['Tipos de requisición', '5', '15/07/2026', 'Administrar'],
                    ['Motivos de rechazo OC', '8', '20/06/2026', 'Administrar'],
                ],
            ],
            'contrato_reporte' => [
                'archivo' => 'contrato-vencimiento.png',
                'modulo' => 'Compras',
                'pantalla' => 'Reportes',
                'card_titulo' => 'Contratos y OC abiertas por vencer',
                'breadcrumb' => 'Compras › Reportes › Vencimientos',
                'sidebar' => $sb,
                'filtros' => ['Horizonte: 30 días', 'Empresa: Todas'],
                'tools' => ['PDF', 'Excel'],
                'columnas' => ['Documento', 'Proveedor', 'Vence', 'Saldo', 'Alerta'],
                'filas' => [
                    ['Contrato CT-88', 'Aceros del Sur', '28/08/2026', '2.400.000', '15 días'],
                    ['OC-9910', 'Empaques Norte', '20/08/2026', '1.200 USD', '5 días'],
                    ['Contrato CT-71', 'Servicios Norte', '02/09/2026', '890.000', '18 días'],
                ],
            ],
        ];
    }
}
