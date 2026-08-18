<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class RecepcionMovstockEscenas
{
    public static function todas(): array
    {
        $sb = ['Recepciones', 'Movimientos', 'Transferencias', 'Pendientes', 'Consultas'];

        return [
            'recepcion_listado' => [
                'archivo' => 'recepcion-listado.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recepciones',
                'card_titulo' => 'Listado de recepciones de proveedores',
                'breadcrumb' => 'Stock › Recepción proveedor',
                'sidebar' => $sb,
                'filtros' => ['Estado: Confirmada', 'Desde: 01/08/2026', 'Proveedor: Aceros'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['COM', 'Fecha', 'Proveedor', 'OC', 'Estado', 'Importe'],
                'filas' => [
                    ['COM-5521', '14/08/2026', 'Aceros del Sur', 'OC-9921', 'Confirmada', '1.180.000'],
                    ['COM-5510', '12/08/2026', 'Empaques Norte', 'OC-9910', 'Borrador', '—'],
                    ['COM-5498', '08/08/2026', 'Metales Andinos', 'OC-9899', 'Confirmada', '760.000'],
                ],
            ],
            'recepcion_form' => [
                'archivo' => 'recepcion-form.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recepciones',
                'card_titulo' => 'Recepción COM-5521 — cabecera e ítems',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Recepción proveedor › Editar',
                'sidebar' => $sb,
                'tabs' => ['Datos', 'Ítems', 'Lotes', 'Archivos', 'Asiento'],
                'campos' => [
                    ['label' => 'Proveedor', 'valor' => 'Aceros del Sur S.A.'],
                    ['label' => 'Depósito', 'valor' => 'DEP-01 Principal'],
                    ['label' => 'Remito proveedor', 'valor' => 'R-77821'],
                    ['label' => 'Moneda / cotiz.', 'valor' => 'ARS / 1'],
                ],
                'columnas' => ['SKU', 'Pedida', 'Recibida', 'Pendiente', 'Precio'],
                'filas' => [
                    ['ART-8801', '20', '20', '0', '12.500'],
                    ['ART-8810', '8', '8', '0', '18.200'],
                ],
                'botones' => [['texto' => 'Confirmar', 'estilo' => 'success'], ['texto' => 'Guardar borrador', 'estilo' => 'primary']],
            ],
            'recepcion_modal_oc' => [
                'archivo' => 'recepcion-modal-oc.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recepciones',
                'card_titulo' => 'Nueva recepción — elegir OC',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Recepción proveedor › Crear',
                'sidebar' => $sb,
                'modal' => [
                    'titulo' => 'Órdenes de compra pendientes de recibir',
                    'columnas' => ['OC', 'Proveedor', 'Fecha', 'Pendiente', 'Moneda'],
                    'filas' => [
                        ['OC-9921', 'Aceros del Sur', '14/08/2026', '28 UN', 'ARS'],
                        ['OC-9910', 'Empaques Norte', '10/08/2026', '120 UN', 'USD'],
                        ['OC-9902', 'Servicios Norte', '09/08/2026', '1 servicio', 'ARS'],
                    ],
                    'botones' => [['texto' => 'Elegir', 'estilo' => 'primary'], ['texto' => 'Cerrar', 'estilo' => 'outline']],
                ],
            ],
            'recepcion_devolucion' => [
                'archivo' => 'recepcion-devolucion.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recepciones',
                'card_titulo' => 'Devolución a proveedor contra COM-5521',
                'card_color' => 'warning',
                'breadcrumb' => 'Stock › Recepción › Devolución',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Solo se puede devolver sobre recepciones confirmadas. Genera movimiento de salida y asiento.', 'tipo' => 'info'],
                ],
                'campos' => [
                    ['label' => 'Recepción origen', 'valor' => 'COM-5521'],
                    ['label' => 'Motivo', 'valor' => 'Mercadería defectuosa'],
                ],
                'columnas' => ['SKU', 'Recibida', 'A devolver', 'Depósito'],
                'filas' => [
                    ['ART-8801', '20', '2', 'DEP-01'],
                ],
                'botones' => [['texto' => 'Confirmar devolución', 'estilo' => 'danger']],
            ],
            'movimientos_listado' => [
                'archivo' => 'movimientos-listado.png',
                'modulo' => 'Stock',
                'pantalla' => 'Movimientos',
                'card_titulo' => 'Movimientos y transferencias',
                'breadcrumb' => 'Stock › Movimientos de stock',
                'sidebar' => $sb,
                'filtros' => ['Tipo: Todos', 'Desde: 01/08/2026', 'Depósito: DEP-01'],
                'tools' => ['Nuevo', 'PDF', 'Excel'],
                'columnas' => ['Nº', 'Fecha', 'Tipo', 'Depósito', 'Estado', 'Usuario'],
                'filas' => [
                    ['MS-4410', '15/08/2026', 'Ajuste', 'DEP-01', 'Confirmado', 'stock01'],
                    ['TRA-2201', '14/08/2026', 'Transferencia', 'DEP-01→02', 'Pendiente apr.', 'stock01'],
                    ['MS-4402', '13/08/2026', 'Entrada', 'DEP-01', 'Confirmado', 'stock02'],
                ],
            ],
            'movimientos_form' => [
                'archivo' => 'movimientos-form.png',
                'modulo' => 'Stock',
                'pantalla' => 'Movimientos',
                'card_titulo' => 'Movimiento MS-4410 — alta/edición',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Movimientos › Editar',
                'sidebar' => $sb,
                'tabs' => ['Cabecera', 'Ítems', 'Asiento contable'],
                'campos' => [
                    ['label' => 'Tipo transacción', 'valor' => 'Ajuste inventario'],
                    ['label' => 'Depósito', 'valor' => 'DEP-01 Principal'],
                    ['label' => 'Fecha', 'valor' => '15/08/2026'],
                    ['label' => 'Observación', 'valor' => 'Ajuste por rotura'],
                ],
                'columnas' => ['SKU', 'Descripción', 'Cantidad', 'Signo'],
                'filas' => [
                    ['ART-1200', 'Caja cartón', '-5', 'Salida'],
                    ['ART-1210', 'Film stretch', '-2', 'Salida'],
                ],
                'botones' => [['texto' => 'Confirmar', 'estilo' => 'success']],
            ],
            'transferencia_pantalla' => [
                'archivo' => 'transferencia-pantalla.png',
                'modulo' => 'Stock',
                'pantalla' => 'Transferencias',
                'card_titulo' => 'Transferencia rápida entre depósitos',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Transferencia mercadería',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Depósito origen', 'valor' => 'DEP-01 Principal'],
                    ['label' => 'Depósito destino', 'valor' => 'DEP-02 Sucursal'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Motivo', 'valor' => 'Reposición sucursal'],
                ],
                'columnas' => ['SKU', 'Disponible', 'A transferir', 'UM'],
                'filas' => [
                    ['ART-8801', '120', '15', 'UN'],
                    ['ART-8810', '40', '5', 'UN'],
                ],
                'botones' => [['texto' => 'Enviar a aprobación', 'estilo' => 'success']],
            ],
            'transferencia_pendientes' => [
                'archivo' => 'transferencia-pendientes.png',
                'modulo' => 'Stock',
                'pantalla' => 'Pendientes',
                'card_titulo' => 'Transferencias pendientes de aprobación',
                'breadcrumb' => 'Stock › Transferencias pendientes',
                'sidebar' => $sb,
                'columnas' => ['TRA', 'Fecha', 'Origen → Destino', 'Solicitante', 'Ítems'],
                'filas' => [
                    ['TRA-2201', '14/08/2026', 'DEP-01 → DEP-02', 'stock01', '2'],
                    ['TRA-2198', '13/08/2026', 'DEP-02 → DEP-01', 'stock02', '5'],
                ],
                'botones' => [['texto' => 'Aprobar', 'estilo' => 'success'], ['texto' => 'Rechazar', 'estilo' => 'danger']],
            ],
        ];
    }
}
