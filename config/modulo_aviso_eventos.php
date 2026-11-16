<?php

/*
| Catálogo de eventos de dominio que pueden suscribirse a tipos ModuloAviso
| (tabla modulo_aviso_evento_suscripcion). Emitir con:
|   app(ModuloAvisoEventBus::class)->emit('stock.recepcion.confirmada', ['entity_id' => $id, 'empresa_id' => $emp]);
*/

return [
    'catalogo' => [
        'stock.recepcion.confirmada' => 'Recepción de proveedor confirmada',
        'stock.prestamo.solicitud' => 'Solicitud de préstamo',
        'compras.oc.alerta_abierta' => 'Digest OC abiertas (también vía cron)',
        'caja.cheque.aging' => 'Aging cheques (también vía cron)',
        'seguridad.ingreso_proveedor.creado' => 'Alta ticket ingreso proveedor',
        'compras.entrega.dia_sin_recepcion' => 'Entrega del día sin recepción (también vía cron)',
        'compras.comprobante.borrador_pendiente' => 'Facturas proveedor en borrador (también vía cron)',
        'sueldos.libreta_sanitaria.pendiente' => 'Libreta sanitaria pendiente (también vía cron)',
        'arca.certificado.vencimiento' => 'Certificado ARCA por vencer (también vía cron)',
        'stock.prestamo.recordatorio' => 'Recordatorio de préstamo (también vía cron)',
        'stock.transferencia.confirmada' => 'Transferencia de mercadería confirmada',
        'stock.transferencia.rechazada' => 'Transferencia de mercadería rechazada',
        'stock.transferencia.pendiente' => 'Transferencia de mercadería pendiente de aprobación',
        'ticket.alta' => 'Alta de ticket',
        'ticket.asignacion' => 'Asignación de ticket a técnico',
        'compras.oc.estado_cambiado' => 'Cambio de estado de orden de compra',
        'compras.comprobante.contabilizado' => 'Comprobante de proveedor contabilizado',
        'configuracion.arbol.aprobado' => 'Aprobación en árbol de aprobación',
        'configuracion.arbol.rechazado' => 'Rechazo en árbol de aprobación',
    ],
];
