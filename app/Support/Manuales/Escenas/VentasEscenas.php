<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class VentasEscenas
{
    public static function todas(): array
    {
        $sb = ['Pedidos', 'Cierre masivo', 'Clientes', 'Reportes'];

        return [
            'pedido_listado' => [
                'archivo' => 'pedido-listado.png',
                'modulo' => 'Ventas',
                'pantalla' => 'Pedidos',
                'card_titulo' => 'Listado de pedidos de clientes',
                'breadcrumb' => 'Ventas › Pedidos',
                'sidebar' => $sb,
                'filtros' => ['Estado: Abierto', 'Cliente: Distribuidora', 'Desde: 01/08'],
                'tools' => ['Nuevo', 'PDF', 'Excel', 'Cierre masivo'],
                'columnas' => ['Pedido', 'Fecha', 'Cliente', 'Estado', 'Kilos', 'Importe'],
                'filas' => [
                    ['PD-78401', '15/08/2026', 'Distribuidora Sur', 'Abierto', '1.250', '4.820.000'],
                    ['PD-78390', '14/08/2026', 'Mayorista Norte', 'Pesado', '980', '3.410.000'],
                    ['PD-78350', '10/08/2026', 'Distribuidora Sur', 'Facturado', '1.100', '4.050.000'],
                ],
            ],
            'pedido_crear' => [
                'archivo' => 'pedido-crear.png',
                'modulo' => 'Ventas',
                'pantalla' => 'Pedidos',
                'card_titulo' => 'Alta de pedido — cabecera e ítems',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Pedidos › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Cliente', 'valor' => 'Distribuidora Sur S.A.'],
                    ['label' => 'Fecha entrega', 'valor' => '18/08/2026'],
                    ['label' => 'Depósito', 'valor' => 'DEP-VEN-01'],
                    ['label' => 'Condición venta', 'valor' => '30 días'],
                ],
                'columnas' => ['SKU', 'Descripción', 'Cant. pedida', 'Precio', 'Kilos est.'],
                'filas' => [
                    ['CAR-100', 'Corte A', '40', '12.500', '480'],
                    ['CAR-210', 'Corte B', '25', '9.800', '300'],
                    ['CAR-300', 'Corte C', '30', '7.200', '470'],
                ],
                'botones' => [['texto' => 'Guardar pedido', 'estilo' => 'primary']],
            ],
            'pedido_editar' => [
                'archivo' => 'pedido-editar.png',
                'modulo' => 'Ventas',
                'pantalla' => 'Pedidos',
                'card_titulo' => 'Edición PD-78401 — pesada y facturación',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Pedidos › Editar',
                'sidebar' => $sb,
                'tabs' => ['Cabecera', 'Ítems / pesada', 'Facturación', 'Archivos'],
                'tab_activa' => 1,
                'columnas' => ['SKU', 'Pedida', 'Pesada', 'Kilos reales', 'Importe'],
                'filas' => [
                    ['CAR-100', '40', '39', '468', '1.872.000'],
                    ['CAR-210', '25', '25', '302', '1.209.000'],
                    ['CAR-300', '30', '28', '451', '1.353.000'],
                ],
                'botones' => [
                    ['texto' => 'Guardar pesada', 'estilo' => 'primary'],
                    ['texto' => 'Facturar', 'estilo' => 'success'],
                ],
            ],
            'pedido_cerrar' => [
                'archivo' => 'pedido-cerrar.png',
                'modulo' => 'Ventas',
                'pantalla' => 'Cierre',
                'card_titulo' => 'Cierre masivo de pedidos',
                'card_color' => 'warning',
                'breadcrumb' => 'Ventas › Pedidos › Cierre masivo',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Solo se cierran pedidos en estado Facturado sin pendientes de entrega.', 'tipo' => 'info'],
                ],
                'filtros' => ['Hasta fecha: 15/08/2026', 'Cliente: Todos'],
                'columnas' => ['Pedido', 'Cliente', 'Estado', 'Seleccionar'],
                'filas' => [
                    ['PD-78350', 'Distribuidora Sur', 'Facturado', 'Sí'],
                    ['PD-78320', 'Mayorista Norte', 'Facturado', 'Sí'],
                    ['PD-78310', 'Retail Este', 'Facturado', 'No'],
                ],
                'botones' => [['texto' => 'Cerrar seleccionados', 'estilo' => 'warning']],
            ],
        ];
    }
}
